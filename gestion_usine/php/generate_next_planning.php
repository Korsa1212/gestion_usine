<?php
require_once __DIR__ . '/connexion.php'; // uses $pdo

// 1. Get all machines that are in function
$planning_start = isset($_SESSION['planning_start']) ? $_SESSION['planning_start'] : null;
$planning_end = isset($_SESSION['planning_end']) ? $_SESSION['planning_end'] : null;

if (!$planning_start || !$planning_end) {
    // Redirect user to planning selection page instead of dying
    header('Location: admin_dashboard.php?section=planning&error=periode_non_specifiee');
    exit;
}

$machinesStmt = $pdo->prepare("
    SELECT DISTINCT m.id_mach, m.nom_mach, m.type_horaire
    FROM machine m
    JOIN fabrication f ON m.id_mach = f.id_mach
    WHERE f.periode_du <= :planning_end AND f.periode_au >= :planning_start
      AND m.en_fonction = 1
");
$machinesStmt->execute(['planning_start' => $planning_start, 'planning_end' => $planning_end]);
$machines = $machinesStmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Get all active operators
$operators = $pdo->query("SELECT * FROM operateures WHERE actif = 1")->fetchAll(PDO::FETCH_ASSOC);

// 3. Get the last planning date
$lastDate = $pdo->query("SELECT MAX(date_action) FROM historique_planing")->fetchColumn();
if (!$lastDate) {
    $lastDate = date('Y-m-d'); // fallback if no planning exists yet
}

// 4. Fetch the last week's planning
$latestPlanning = $pdo->prepare("SELECT * FROM historique_planing WHERE date_action = ? ORDER BY id_ordre, periode_du");
$latestPlanning->execute([$lastDate]);
$latestPlanning = $latestPlanning->fetchAll(PDO::FETCH_ASSOC);

// 5. We'll query each machine's shift type as needed since we only care about machines with active orders

// Define the shifts
$shifts8 = [
    ['06:00:00', '14:00:00'],
    ['14:00:00', '22:00:00'],
    ['22:00:00', '06:00:00'],
];
$shifts12 = [
    ['06:00:00', '18:00:00'],
    ['18:00:00', '06:00:00'],
];

// 6. Prepare posts for each 7-day period in the selected range
$posts = [];
$period_start = $planning_start;
$period_end = $planning_end;

$current_start = $period_start;
while (strtotime($current_start) <= strtotime($period_end)) {
    $date_action = $current_start;
    // Fetch all fabrication orders active in this week WITH their machine types
    $stmtOrders = $pdo->prepare("SELECT f.*, m.type_horaire 
                             FROM fabrication f 
                             JOIN machine m ON f.id_mach = m.id_mach 
                             WHERE f.periode_du <= ? AND f.periode_au >= ? AND m.en_fonction = 1");
    $stmtOrders->execute([$date_action, $date_action]);
    $orders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($orders as $order) {
        $machId = $order['id_mach'];
        $machType = $order['type_horaire'];
        $shifts = ($machType === '12h') ? $shifts12 : $shifts8;
        foreach ($shifts as $shift) {
            $posts[] = [
                'machine_id' => $machId,
                'periode_du' => $shift[0],
                'periode_au' => $shift[1],
                'shift_label' => $shift[0] . ' - ' . $shift[1],
                'id_ordre' => $order['id_ordre'],
                'id_article' => $order['id_article'],
                'date_action' => $date_action
            ];
        }
    }
    // Move to next 7-day period
    $current_start = date('Y-m-d', strtotime($current_start . ' +7 days'));
}


// 7. Build operator last shift and rotation index
$opLastShiftIndex = [];
$opShiftType = []; // id_op => '8h' or '12h'
foreach ($latestPlanning as $plan) {
    $start = strtotime($plan['periode_du']);
    $end = strtotime($plan['periode_au']);
    $duree = ($end > $start) ? ($end - $start) : ($end + 24*3600 - $start);
    $shiftType = ($duree >= 11*3600) ? '12h' : '8h';

    $opShiftType[$plan['id_op']] = $shiftType;

    // Find the shift index
    $shiftsList = ($shiftType === '12h') ? $shifts12 : $shifts8;
    foreach ($shiftsList as $idx => $shift) {
        if ($plan['periode_du'] === $shift[0] && $plan['periode_au'] === $shift[1]) {
            $opLastShiftIndex[$plan['id_op']] = $idx;
            break;
        }
    }
}

// 8. Assign new shifts (rotation)
$usedPosts = [];
$nextPlanning = [];

// Build list of unique machines
$machineIds = array_unique(array_map(function($post) { return $post['machine_id']; }, $posts));
$machineIds = array_values($machineIds); // reindex
$machineCount = count($machineIds);

// Safety checks
if ($machineCount === 0) {
    echo "<div style='color:red;padding:1em;'>Aucune machine disponible pour la période sélectionnée. Vérifiez les ordres de fabrication et la période de planning.</div>";
    return;
}
if (count($posts) === 0) {
    echo "<div style='color:red;padding:1em;'>Aucun poste (shift/machine) disponible pour la période sélectionnée. Vérifiez les ordres de fabrication et la période de planning.</div>";
    return;
}
if (count($operators) === 0) {
    echo "<div style='color:red;padding:1em;'>Aucun opérateur actif disponible pour la planification.</div>";
    return;
}

// Track last machine index for each operator
$opLastMachineIndex = [];
foreach ($latestPlanning as $plan) {
    $opLastMachineIndex[$plan['id_op']] = array_search($plan['id_ordre'], $machineIds);
}

foreach ($operators as $op) {
    $opId = $op['id_op'];
    $shiftType = $opShiftType[$opId] ?? '8h'; // default to 8h if unknown
    $shiftsList = ($shiftType === '12h') ? $shifts12 : $shifts8;
    $currentShiftIndex = $opLastShiftIndex[$opId] ?? -1;
    $newShiftIndex = ($currentShiftIndex + 1) % count($shiftsList); // rotate to next shift
    $currentMachineIndex = $opLastMachineIndex[$opId] ?? -1;
    $newMachineIndex = ($currentMachineIndex + 1) % $machineCount; // rotate to next machine

    // Try to assign to the next shift and next machine in the cycle
    $targetShift = $shiftsList[$newShiftIndex];
    $targetMachine = $machineIds[$newMachineIndex];
    $chosenPost = null;
    foreach ($posts as $key => $post) {
        if (
            $post['periode_du'] == $targetShift[0] &&
            $post['periode_au'] == $targetShift[1] &&
            $post['machine_id'] == $targetMachine &&
            !isset($usedPosts[$key])
        ) {
            $chosenPost = $post;
            $usedPosts[$key] = true;
            break;
        }

        
    }
    // If can't assign to the rotated shift+machine, assign to any available post (but keep cycle order)
    if (!$chosenPost) {
        foreach ($posts as $key => $post) {
            if (!isset($usedPosts[$key])) {
                $chosenPost = $post;
                $usedPosts[$key] = true;
                break;
            }
        }
    }
    if (!$chosenPost) continue;

    // Only add to planning if the order exists in fabrication
    if (!empty($chosenPost['id_article'])) {
        $nextPlanning[] = [
            'shift_travaille' => $chosenPost['shift_label'],
            'id_op' => $opId,
            'date_action' => $date_action,
            'id_ordre' => $chosenPost['machine_id'],
            'id_article' => $chosenPost['id_article'],
            'nom_op' => $op['nom_complet'],
            'cin' => $op['CIN'],
            // Use the global planning period for all rows
            'periode_du' => $planning_start,
            'periode_au' => $planning_end,
        ];
    }
}

// 9. Insert the new planning
$stmt = $pdo->prepare("INSERT INTO historique_planing (shift_travaille, id_op, date_action, id_ordre, id_article, nom_op, cin, periode_du, periode_au) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$inserted = 0;
foreach ($nextPlanning as $row) {
    $stmt->execute([
        $row['shift_travaille'],
        $row['id_op'],
        $row['date_action'],
        $row['id_ordre'],
        $row['id_article'],
        $row['nom_op'],
        $row['cin'],
        $row['periode_du'],
        $row['periode_au']
    ]);
    $inserted++;
}

echo "<div style='padding:1em;background:#ffe;border:1px solid #cc0;color:#333;'>[DEBUG] Script executed: $inserted rows inserted for date $date_action</div>";
?>
