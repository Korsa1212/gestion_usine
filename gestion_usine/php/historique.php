<?php
require_once __DIR__ . '/connexion.php';

// Handle messages
$successMessage = '';
if (isset($_GET['delete_all']) && $_GET['delete_all'] === 'success') {
    $successMessage = "<div class='alert alert-success text-center'>Tout l'historique a été supprimé avec succès.</div>";
}
if (isset($_GET['delete']) && $_GET['delete'] === 'success') {
    $successMessage = "<div class='alert alert-success text-center'>Suppression effectuée avec succès.</div>";
}
if (isset($_GET['edit']) && $_GET['edit'] === 'success') {
    $successMessage = "<div class='alert alert-success text-center'>Modification enregistrée avec succès.</div>";
}


// Filtering logic
$cin = array_key_exists('cin', $_GET) ? trim($_GET['cin']) : null;
$nom_op = array_key_exists('nom_op', $_GET) ? trim($_GET['nom_op']) : null;
$date_action = array_key_exists('date_action', $_GET) ? trim($_GET['date_action']) : null;

$query = "SELECT h.id_hist_info, h.shift_travaille, h.id_op, h.date_action, h.id_ordre, h.nom_op, h.cin, h.heure_debut, h.heure_fin, f.id_article
FROM historique_planing h
LEFT JOIN fabrication f ON h.id_ordre = f.id_ordre
WHERE 1=1";
$params = [];
if ($cin !== '') {
    $query .= " AND h.cin LIKE :cin";
    $params[':cin'] = "%$cin%";
}
if ($nom_op !== '') {
    $query .= " AND h.nom_op LIKE :nom_op";
    $params[':nom_op'] = "%$nom_op%";
}
if ($date_action !== '') {
    $query .= " AND h.date_action = :date_action";
    $params[':date_action'] = $date_action;
}
$query .= " ORDER BY h.date_action DESC, h.id_hist_info DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Historique des Plannings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <h2 class="mb-3">Historique des Plannings</h2>
    <?php if ($successMessage) echo $successMessage; ?>
    <?php
// Determine form action: always point to historique.php
$form_action = 'historique.php';
?>
<!-- Filter Form: not nested in any other form -->
<form method="get" action="<?php echo htmlspecialchars($form_action); ?>" class="row g-3 mb-3">
    <div class="col-md-3">
        <input type="text" name="cin" class="form-control" placeholder="CIN" value="<?php echo htmlspecialchars($cin ?? ''); ?>">
    </div>
    <div class="col-md-3">
        <input type="text" name="nom_op" class="form-control" placeholder="Nom opérateur" value="<?php echo htmlspecialchars($nom_op ?? ''); ?>">
    </div>
    <div class="col-md-3">
        <input type="date" name="date_action" class="form-control" value="<?php echo htmlspecialchars($date_action ?? ''); ?>">
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100">Filtrer</button>
    </div>
    <div class="col-md-1">
        <a href="<?php echo htmlspecialchars($form_action); ?>" class="btn btn-secondary w-100">Reset</a>
    </div>
</form>
<!-- End Filter Form -->

<!-- Delete All Form: separate, not nested -->
<div class="mb-3 text-end">
    <form method="POST" action="delete_all_historique.php" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer tout l\'historique ? Cette action est irréversible.');">
        <button type="submit" class="btn btn-danger">Supprimer tout l'historique</button>
    </form>
</div>

    <?php if (empty($rows)): ?>
        <div class="alert alert-info text-center">Aucun historique trouvé.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle text-center">
            <thead class="table-dark">
                <tr>
                    <th>ID Hist.</th>
<th>Shift</th>
<th>ID Op</th>
<th>Date</th>
<th>ID Ordre</th>
<th>Nom Opérateur</th>
<th>CIN</th>
<th>Heure Début</th>
<th>Heure Fin</th>
<th>ID Article</th>
<th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id_hist_info']); ?></td>
<td><?php echo htmlspecialchars($row['shift_travaille']); ?></td>
<td><?php echo htmlspecialchars($row['id_op']); ?></td>
<td><?php echo htmlspecialchars($row['date_action']); ?></td>
<td><?php echo htmlspecialchars($row['id_ordre']); ?></td>
<td><?php echo htmlspecialchars($row['nom_op']); ?></td>
<td><?php echo htmlspecialchars($row['cin']); ?></td>
<td><?php echo htmlspecialchars($row['heure_debut']); ?></td>
<td><?php echo htmlspecialchars($row['heure_fin']); ?></td>
<td><?php echo htmlspecialchars($row['id_article']); ?></td>
<td>
    <a href="edit_historique.php?id=<?php echo urlencode($row['id_hist_info']); ?>" class="btn btn-sm btn-warning">Éditer</a>
    <a href="delete_historique.php?id=<?php echo urlencode($row['id_hist_info']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer cette entrée ?');">Supprimer</a>
</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
