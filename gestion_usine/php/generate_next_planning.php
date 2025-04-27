<?php
require_once __DIR__ . '/connexion.php'; // uses $pdo (PDO)

// 1. Get all machines that are in function
$machines = $pdo->query("
    SELECT f.id_mach 
    FROM fabrication f
    JOIN machine m ON f.id_mach = m.id_mach
    WHERE m.en_fonction = 1
    GROUP BY f.id_mach
")->fetchAll(PDO::FETCH_ASSOC);

// 2. Get all active operators
$operators = $pdo->query("SELECT * FROM operateures WHERE actif = 1")->fetchAll(PDO::FETCH_ASSOC);

// 3. Get the last planning date
$lastDate = $pdo->query("SELECT MAX(date_action) FROM historique_planing")->fetchColumn();
if (!$lastDate) {
    $lastDate = date('Y-m-d'); // fallback if no planning exists yet
}

// 4. Fetch the last week's planning
$latestPlanning = $pdo->prepare("SELECT * FROM historique_planing WHERE date_action = ? ORDER BY id_ordre, heure_debut");
$latestPlanning->execute([$lastDate]);
$latestPlanning = $latestPlanning->fetchAll(PDO::FETCH_ASSOC);

// 5. Determine shift types from machine table
$shiftTypes = [];
foreach ($machines as $m) {
    $stmt = $pdo->prepare("SELECT type_horaire FROM machine WHERE id_mach = ?");
    $stmt->execute([$m['id_mach']]);
    $type = $stmt->fetchColumn();
    $shiftTypes[$m['id_mach']] = ($type === '12h') ? '12h' : '8h';
}

$shifts8 = [
    ['06:00:00', '14:00:00'],
    ['14:00:00', '22:00:00'],
    ['22:00:00', '06:00:00'],
];
$shifts12 = [
    ['06:00:00', '18:00:00'],
    ['18:00:00', '06:00:00'],
];

// 6. Build all posts (machine + shift combinations)
// Initialiser la date de planification AVANT la génération des postes
$date_action = date('Y-m-d', strtotime($lastDate . ' +7 days'));
$posts = [];
foreach ($machines as $mach) {
    $machId = $mach['id_mach'];
    $shifts = ($shiftTypes[$machId] === '12h') ? $shifts12 : $shifts8;
    foreach ($shifts as $shift) {
        // Récupérer l'id_article pour la machine et la semaine planifiée
        $stmtArt = $pdo->prepare("SELECT id_article FROM fabrication WHERE id_mach = ? AND ? BETWEEN periode_du AND periode_au LIMIT 1");
        $stmtArt->execute([$machId, $date_action]);
        $id_article = $stmtArt->fetchColumn();
        $posts[] = [
            'machine_id' => $machId,
            'heure_debut' => $shift[0],
            'heure_fin' => $shift[1],
            'shift_label' => $shift[0] . ' - ' . $shift[1],
            'id_article' => $id_article // peut être null si pas trouvé
        ];
    }
}

// 7. Build map of operator last positions and last shift type
$opLastPosition = []; // id_op => post_key
$opLastShiftType = []; // id_op => '12h' or '8h'
foreach ($latestPlanning as $plan) {
    $key = $plan['id_ordre'] . '_' . $plan['heure_debut'] . '_' . $plan['heure_fin'];
    $opLastPosition[$plan['id_op']] = $key;
    // Déduire le type de shift selon la durée
    $start = strtotime($plan['heure_debut']);
    $end = strtotime($plan['heure_fin']);
    $duree = ($end > $start) ? ($end - $start) : ($end + 24*3600 - $start);
    // 12h = 43200s, 8h = 28800s
    $opLastShiftType[$plan['id_op']] = ($duree >= 11*3600) ? '12h' : '8h';
}

// 8. Rotate Operators to new Posts
$date_action = date('Y-m-d', strtotime($lastDate . ' +7 days'));
$nextPlanning = [];
$usedPosts = [];
$postCount = count($posts);
$opCount = count($operators);
$startIndex = 0; // rotation start

foreach ($operators as $op) {
    $opId = $op['id_op'];
    $lastPostKey = $opLastPosition[$opId] ?? null;
    $requiredShiftType = $opLastShiftType[$opId] ?? null;

    // 1. Lister tous les postes du bon type de shift, non utilisés
    $possiblePosts = [];
    foreach ($posts as $post) {
        $postKey = $post['machine_id'] . '_' . $post['heure_debut'] . '_' . $post['heure_fin'];
        // Calculer le type de shift du poste
        $start = strtotime($post['heure_debut']);
        $end = strtotime($post['heure_fin']);
        $duree = ($end > $start) ? ($end - $start) : ($end + 24*3600 - $start);
        $postShiftType = ($duree >= 11*3600) ? '12h' : '8h';
        if ($postShiftType === $requiredShiftType && !isset($usedPosts[$postKey])) {
            $possiblePosts[] = $post;
        }
    }
    // 2. Sélectionner un poste du même type mais HORAIRE différent
    $chosenPost = null;
    if ($requiredShiftType === '8h') {
        // Priorité 1 : horaire (début/fin) strictement différent, peu importe la machine
        $chosenPost = null;
        if ($lastPostKey) {
            list($lastMach, $lastDebut, $lastFin) = explode('_', $lastPostKey);
            foreach ($possiblePosts as $post) {
                if ($post['heure_debut'] !== $lastDebut || $post['heure_fin'] !== $lastFin) {
                    $chosenPost = $post;
                    break;
                }
            }
        }
        // Si aucun horaire différent, alors on prend le même horaire (dernier recours)
        if (!$chosenPost && !empty($possiblePosts)) {
            $chosenPost = $possiblePosts[0];
        }
    } else {
        // Pour les 12h : garder la logique actuelle (horaire différent si possible)
        foreach ($possiblePosts as $post) {
            $postKey = $post['machine_id'] . '_' . $post['heure_debut'] . '_' . $post['heure_fin'];
            if ($postKey !== $lastPostKey) {
                $chosenPost = $post;
                break;
            }
        }
        if (!$chosenPost && !empty($possiblePosts)) {
            $chosenPost = $possiblePosts[0];
        }
    }
    // 4. Si aucun poste dispo, on skippe
    if (!$chosenPost) continue;
    $postKey = $chosenPost['machine_id'] . '_' . $chosenPost['heure_debut'] . '_' . $chosenPost['heure_fin'];
    $usedPosts[$postKey] = true;
    $nextPlanning[] = [
        'shift_travaille' => $chosenPost['shift_label'],
        'id_op' => $op['id_op'],
        'date_action' => $date_action,
        'id_ordre' => $chosenPost['machine_id'],
        'id_article' => $chosenPost['id_article'] ?? null, // à remplir si dispo
        'nom_op' => $op['nom_complet'],
        'cin' => $op['CIN'],
        'heure_debut' => $chosenPost['heure_debut'],
        'heure_fin' => $chosenPost['heure_fin']
    ];
}

// 9. Insert the new planning
try {
    $pdo->query("SELECT action FROM historique_planing LIMIT 1");
    $hasActionCol = true;
} catch (PDOException $e) {
    // Colonne manquante
}
$stmt = $pdo->prepare("INSERT INTO historique_planing (shift_travaille, id_op, date_action, id_ordre, id_article, nom_op, cin, heure_debut, heure_fin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
foreach ($nextPlanning as $row) {
    $stmt->execute([
        $row['shift_travaille'],
        $row['id_op'],
        $row['date_action'],
        $row['id_ordre'],
        $row['id_article'],
        $row['nom_op'],
        $row['cin'],
        $row['heure_debut'],
        $row['heure_fin']
    ]);
}


echo "✅ Next week's planning has been generated and saved successfully.";
?>
