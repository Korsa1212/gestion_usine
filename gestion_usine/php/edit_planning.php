<?php
// edit_planning.php
// Traitement de l'édition du planning avec validation stricte des règles métier
require_once __DIR__ . '/connexion.php';

// Récupérer le planning original de la semaine (avant édition)
// Supposons qu'on reçoit la semaine à éditer via $_POST['date_action']
$date_action = $_POST['date_action'] ?? null;
if (!$date_action) {
    die('Date de planning manquante.');
}

// Charger le planning original
$stmt = $pdo->prepare("SELECT * FROM historique_planing WHERE date_action = ?");
$stmt->execute([$date_action]);
$original = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Construction des maps pour validation
$original_posts = [];
$original_op_ids = [];
foreach ($original as $row) {
    $key = $row['id_ordre'] . '|' . $row['shift_travaille'];
    if ($row['id_op']) {
        $original_posts[$key] = $row['id_op'];
        $original_op_ids[] = $row['id_op'];
    } else {
        $original_posts[$key] = null;
    }
}

// Récupérer la nouvelle répartition envoyée par le formulaire (tableau associatif: [post_key => op_id])
$new_assignments = $_POST['assignments'] ?? [];
// Format attendu: [ 'id_ordre|shift_travaille' => id_op, ... ]

// 1. Vérifier que le nombre de postes assignés reste le même
$original_assigned = array_filter($original_posts, fn($v) => $v !== null);
$new_assigned = array_filter($new_assignments, fn($v) => $v !== null && $v !== '');
if (count($original_assigned) !== count($new_assigned)) {
    die('Erreur: Le nombre de postes assignés doit rester identique.');
}

// 2. Vérifier qu'aucun poste vide à l'origine n'est rempli
foreach ($original_posts as $key => $op_id) {
    if ($op_id === null && !empty($new_assignments[$key])) {
        die("Erreur: Impossible d'assigner un opérateur à un poste qui était vide à l'origine.");
    }
}

// 3. Vérifier qu'aucun poste assigné n'est laissé vide
foreach ($original_posts as $key => $op_id) {
    if ($op_id !== null && (empty($new_assignments[$key]) || $new_assignments[$key] === '')) {
        die("Erreur: Impossible de laisser vide un poste qui était assigné à l'origine.");
    }
}

// 4. Vérifier qu'aucun opérateur n'est supprimé (on doit juste pouvoir échanger)
// (optionnel: si tu veux forcer que ce sont les mêmes opérateurs, pas d'ajout/suppression)
$new_op_ids = array_values($new_assigned);
foreach ($new_op_ids as $op_id) {
    if (!in_array($op_id, $original_op_ids)) {
        die("Erreur: Impossible d'ajouter un nouvel opérateur qui n'était pas déjà dans le planning.");
    }
}

// 5. Appliquer les changements (update uniquement les changements)
foreach ($new_assignments as $key => $new_op_id) {
    list($id_ordre, $shift_travaille) = explode('|', $key);
    $old_op_id = $original_posts[$key];
    if ($old_op_id !== $new_op_id) {
        // Mettre à jour l'opérateur affecté à ce poste
        $stmt = $pdo->prepare("UPDATE historique_planing SET id_op = ? WHERE date_action = ? AND id_ordre = ? AND shift_travaille = ?");
        $stmt->execute([$new_op_id, $date_action, $id_ordre, $shift_travaille]);
    }
}
echo '✅ Planning modifié avec succès.';
