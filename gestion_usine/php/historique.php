<?php
require_once __DIR__ . '/connexion.php';

// Edit functionality
$editMode = false;
$rowToEdit = null;

// If edit_id is present, fetch the record for editing
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $editMode = true;
    $editId = intval($_GET['edit_id']);
    $editStmt = $pdo->prepare("SELECT * FROM historique_planing WHERE id_hist_info = ?");
    $editStmt->execute([$editId]);
    $rowToEdit = $editStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$rowToEdit) {
        $editMode = false;
        echo "<div class='alert alert-danger'>Record not found.</div>";
    }
}

// Handle form submission for edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_historique'])) {
    $fields = ['shift_travaille','id_op','date_action','id_ordre','id_article','nom_op','cin','periode_du','periode_au'];
    $params = [];
    $updateId = intval($_POST['id_hist_info']);
    
    foreach ($fields as $f) {
        $params[$f] = $_POST[$f] ?? '';
    }
    
    $stmt = $pdo->prepare("UPDATE historique_planing SET shift_travaille=?, id_op=?, date_action=?, id_ordre=?, id_article=?, nom_op=?, cin=?, periode_du=?, periode_au=? WHERE id_hist_info=?");
    $stmt->execute([
        $params['shift_travaille'],
        $params['id_op'],
        $params['date_action'],
        $params['id_ordre'],
        $params['id_article'],
        $params['nom_op'],
        $params['cin'],
        $params['periode_du'],
        $params['periode_au'],
        $updateId
    ]);
    
    // Redirect to remove the edit_id parameter
    header('Location: admin_dashboard.php?section=historique&edit=success');
    exit;
}

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

$query = "SELECT h.id_hist_info, h.shift_travaille, h.id_op, h.date_action, h.id_ordre, h.nom_op, h.cin, h.periode_du, h.periode_au, h.id_article, a.nom_article
FROM historique_planing h
LEFT JOIN article a ON h.id_article = a.id_article
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
    // Affichage de la période de planification (semaines)
    $period_start = isset($_SESSION['planning_start']) ? $_SESSION['planning_start'] : null;
    $period_end = isset($_SESSION['planning_end']) ? $_SESSION['planning_end'] : null;
    if ($period_start && $period_end) {
        $week_start = date('W', strtotime($period_start));
        $week_end = date('W', strtotime($period_end));
        $year_start = date('Y', strtotime($period_start));
        $year_end = date('Y', strtotime($period_end));
        echo "<div class='alert alert-info text-center mb-3'>Période historique utilisée : semaine <b>$week_start</b> ($period_start) au <b>$week_end</b> ($period_end)</div>";
    }
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

<?php if ($editMode && $rowToEdit): ?>
<!-- Show only Edit Form when in edit mode -->
<div class="card mb-4">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0">Éditer l'entrée historique</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="id_hist_info" value="<?php echo htmlspecialchars($rowToEdit['id_hist_info']); ?>">
            <div class="row mb-2">
                <div class="col"><label>Shift</label><input class="form-control" name="shift_travaille" value="<?php echo htmlspecialchars($rowToEdit['shift_travaille']); ?>" required></div>
                <div class="col"><label>ID Opérateur</label><input class="form-control" name="id_op" value="<?php echo htmlspecialchars($rowToEdit['id_op']); ?>" required></div>
                <div class="col"><label>Date</label><input class="form-control" name="date_action" type="date" value="<?php echo htmlspecialchars(substr($rowToEdit['date_action'], 0, 10)); ?>" required></div>
            </div>
            <div class="row mb-2">
                <div class="col"><label>ID Ordre</label><input class="form-control" name="id_ordre" value="<?php echo htmlspecialchars($rowToEdit['id_ordre']); ?>" required></div>
                <div class="col"><label>ID Article</label><input class="form-control" name="id_article" value="<?php echo htmlspecialchars($rowToEdit['id_article'] ?? ''); ?>" required></div>
                <div class="col"><label>Nom Opérateur</label><input class="form-control" name="nom_op" value="<?php echo htmlspecialchars($rowToEdit['nom_op']); ?>" required></div>
                <div class="col"><label>CIN</label><input class="form-control" name="cin" value="<?php echo htmlspecialchars($rowToEdit['cin']); ?>" required></div>
            </div>
            <div class="row mb-2">
                <div class="col"><label>Période du</label><input class="form-control" name="periode_du" type="date" value="<?php echo htmlspecialchars(substr($rowToEdit['periode_du'], 0, 10)); ?>" required></div>
                <div class="col"><label>Période au</label><input class="form-control" name="periode_au" type="date" value="<?php echo htmlspecialchars(substr($rowToEdit['periode_au'], 0, 10)); ?>" required></div>
            </div>
            <button type="submit" name="update_historique" class="btn btn-primary">Enregistrer</button>
            <a href="admin_dashboard.php?section=historique" class="btn btn-secondary">Annuler</a>
        </form>
    </div>
</div>
<?php else: ?>
<!-- Only show the table when not in edit mode -->
    <?php if (empty($rows)): ?>
        <div class="alert alert-info text-center">Aucun historique trouvé.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle text-center">
            <thead class="table-dark">
                <tr>
                    
<th>Shift</th>

<th>Date</th>
<th>ID Ordre</th>
<th>Nom Opérateur</th>
<th>CIN</th>
<th>Période au</th>
<th>Période du</th>
<th>ID Article</th>
<th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                       
<td><?php echo htmlspecialchars($row['shift_travaille']); ?></td>
<td><?php echo htmlspecialchars($row['date_action']); ?></td>
<td><?php echo htmlspecialchars($row['id_ordre']); ?></td>
<td><?php echo htmlspecialchars($row['nom_op']); ?></td>
<td><?php echo htmlspecialchars($row['cin']); ?></td>
<td><?php echo htmlspecialchars($row['periode_au']); ?></td>
<td><?php echo htmlspecialchars($row['periode_du']); ?></td>
<td><?php echo htmlspecialchars($row['nom_article'] ?? 'N/A'); ?></td>
<td>
    <a href="admin_dashboard.php?section=historique&edit_id=<?php echo urlencode($row['id_hist_info']); ?>" class="btn btn-sm btn-warning">Éditer</a>
    <a href="delete_historique.php?id=<?php echo urlencode($row['id_hist_info']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer cette entrée ?');">Supprimer</a>
</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
<?php endif; ?>
</div>
</body>
</html>
