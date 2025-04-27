<?php
// edit_planning_form.php
// Affiche le formulaire d'édition du planning avec des <select> actifs pour chaque poste déjà assigné
require_once __DIR__ . '/connexion.php';

$date_action = $_GET['date_action'] ?? date('Y-m-d');

// Charger le planning original
$stmt = $pdo->prepare("SELECT * FROM historique_planing WHERE date_action = ?");
$stmt->execute([$date_action]);
$planning = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer tous les opérateurs assignés cette semaine
$assigned_ops = [];
foreach ($planning as $row) {
    if ($row['id_op']) {
        $assigned_ops[$row['id_op']] = $row['nom_op'];
    }
}

// Construction du tableau pour affichage
$posts = [];
foreach ($planning as $row) {
    $key = $row['id_ordre'] . '|' . $row['shift_travaille'];
    $posts[$key] = [
        'id_ordre' => $row['id_ordre'],
        'shift_travaille' => $row['shift_travaille'],
        'id_op' => $row['id_op'],
        'nom_op' => $row['nom_op'],
        'is_assigned' => ($row['id_op'] !== null)
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Éditer le planning</title>
    <style>
        table { border-collapse: collapse; }
        td, th { border: 1px solid #ccc; padding: 6px; }
        select { min-width: 120px; }
    </style>
</head>
<body>
<h2>Édition du planning du <?php echo htmlspecialchars($date_action); ?></h2>
<form method="post" action="edit_planning.php">
    <input type="hidden" name="date_action" value="<?php echo htmlspecialchars($date_action); ?>">
    <table>
        <tr><th>Machine|Shift</th><th>Opérateur</th></tr>
        <?php foreach ($posts as $key => $post): ?>
        <tr>
            <td><?php echo htmlspecialchars($post['id_ordre'] . ' | ' . $post['shift_travaille']); ?></td>
            <td>
                <?php if ($post['is_assigned']): ?>
                    <select name="assignments[<?php echo htmlspecialchars($key); ?>]">
                        <?php foreach ($assigned_ops as $op_id => $op_name): ?>
                            <option value="<?php echo $op_id; ?>" <?php if ($op_id == $post['id_op']) echo 'selected'; ?>><?php echo htmlspecialchars($op_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <select disabled><option>Vide</option></select>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <button type="submit">Enregistrer les modifications</button>
</form>
</body>
</html>
