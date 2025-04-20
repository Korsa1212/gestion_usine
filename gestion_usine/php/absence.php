<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin'])) {
    header("Location: ../index.html");
    exit;
}
require_once __DIR__ . '/connexion.php';

// Messages
$success = $error = '';

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_abs = trim($_POST['id_abs'] ?? '');
    $date_absence = trim($_POST['date_absence'] ?? '');
    $dure_absence = trim($_POST['dure_absence'] ?? '');
    $type_absence = trim($_POST['type_absence'] ?? '');
    $id_op = trim($_POST['id_op'] ?? '');
    $nom_op = trim($_POST['nom_op'] ?? '');
    $cin = trim($_POST['cin'] ?? '');
    $edit_mode = isset($_POST['edit_mode']) && $_POST['edit_mode'] === '1';
    $old_id_abs = $_POST['old_id_abs'] ?? '';

    // Validation
    if ($date_absence === '' || $type_absence === '' || $id_op === '' || $nom_op === '' || $cin === '') {
        $error = 'Tous les champs obligatoires doivent être remplis.';
    } else {
        try {
            if ($edit_mode) {
                // UPDATE
                $stmt = $pdo->prepare("UPDATE ABSENCE SET date_absence=?, dure_absence=?, type_absence=?, id_op=?, nom_op=?, cin=? WHERE id_abs=?");
                $stmt->execute([$date_absence, $dure_absence, $type_absence, $id_op, $nom_op, $cin, $old_id_abs]);
                $success = 'Absence modifiée avec succès!';
            } else {
                // INSERT
                $stmt = $pdo->prepare("INSERT INTO ABSENCE (date_absence, dure_absence, type_absence, id_op, nom_op, cin) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$date_absence, $dure_absence, $type_absence, $id_op, $nom_op, $cin]);
                $success = 'Nouvelle absence ajoutée!';
            }
            header('Location: ?section=absences&success=1');
            exit;
        } catch (PDOException $e) {
            $error = 'Erreur: ' . $e->getMessage();
        }
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id_abs = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM ABSENCE WHERE id_abs = ?");
        $stmt->execute([$id_abs]);
        header('Location: ?section=absences&success=1');
        exit;
    } catch (PDOException $e) {
        $error = 'Erreur: ' . $e->getMessage();
    }
}

// For edit form population
$edit_data = null;
$show_form = false;
if (isset($_GET['edit'])) {
    $id_abs = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM ABSENCE WHERE id_abs = ?");
    $stmt->execute([$id_abs]);
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $show_form = true;
}

// Filters
$where = [];
$params = [];
if (!empty($_GET['filter_date'])) {
    $where[] = 'date_absence = ?';
    $params[] = $_GET['filter_date'];
}
if (!empty($_GET['filter_nom_op'])) {
    $where[] = 'nom_op LIKE ?';
    $params[] = '%' . $_GET['filter_nom_op'] . '%';
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = $pdo->prepare("SELECT * FROM ABSENCE $where_sql ORDER BY date_absence DESC, id_abs DESC");
$stmt->execute($params);
$result = $stmt;

// Success message after redirect
if (isset($_GET['success'])) {
    $success = 'Opération réalisée avec succès!';
}
?>
<h1 class="mb-4">Gestion des Absences</h1>

<!-- Affichage des messages -->
<?php if ($success): ?>
    <div class="alert alert-success"> <?= htmlspecialchars($success) ?> </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"> <?= htmlspecialchars($error) ?> </div>
<?php endif; ?>

<!-- Zone de filtrage -->
<div class="card mb-3">
    <div class="card-body">
        <form class="row g-3" method="get" action="">
            <input type="hidden" name="section" value="absences">
            <div class="col-md-3">
                <label class="form-label">Filtrer par date</label>
                <input type="date" class="form-control" name="filter_date" value="<?= htmlspecialchars($_GET['filter_date'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Filtrer par nom complet</label>
                <input type="text" class="form-control" name="filter_nom_op" placeholder="Nom complet..." value="<?= htmlspecialchars($_GET['filter_nom_op'] ?? '') ?>">
            </div>
            <div class="col-md-3 align-self-end">
                <button type="submit" class="btn btn-primary">Filtrer</button>
                <a href="?section=absences" class="btn btn-secondary">Réinitialiser</a>
            </div>
        </form>
    </div>
</div>

<!-- Bouton pour afficher le formulaire d'ajout -->
<?php if (empty($edit_data) && !isset($_GET['add']) && !$show_form): ?>
    <a href="?section=absences&add=1" class="btn btn-primary mb-3">Ajouter une absence</a>
<?php endif; ?>

<!-- Formulaire d'ajout/modification -->
<div class="card mb-4" id="absenceFormContainer" style="display:<?= ($show_form || isset($_GET['add'])) ? 'block' : 'none' ?>">
    <div class="card-header">
        <?= $edit_data ? 'Modifier Absence' : 'Ajouter une nouvelle absence' ?>
    </div>
    <div class="card-body">
        <form method="post" action="?section=absences<?php if (isset($_GET['edit'])) echo '&edit=' . urlencode($_GET['edit']); if (isset($_GET['add'])) echo '&add=1'; ?>">
            <input type="hidden" name="edit_mode" value="<?= $edit_data ? '1' : '0' ?>">
            <input type="hidden" name="old_id_abs" value="<?= $edit_data['id_abs'] ?? '' ?>">
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Date d'absence</label>
                    <input type="date" class="form-control" name="date_absence" value="<?= htmlspecialchars($edit_data['date_absence'] ?? '') ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Durée</label>
                    <input type="text" class="form-control" name="dure_absence" value="<?= htmlspecialchars($edit_data['dure_absence'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <input type="text" class="form-control" name="type_absence" value="<?= htmlspecialchars($edit_data['type_absence'] ?? '') ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">ID Opérateur</label>
                    <input type="text" class="form-control" name="id_op" value="<?= htmlspecialchars($edit_data['id_op'] ?? '') ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Nom complet</label>
                    <input type="text" class="form-control" name="nom_op" value="<?= htmlspecialchars($edit_data['nom_op'] ?? '') ?>" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label">CIN</label>
                    <input type="text" class="form-control" name="cin" value="<?= htmlspecialchars($edit_data['cin'] ?? '') ?>" required>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-success">Enregistrer</button>
                <a href="?section=absences" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<!-- Tableau des absences -->
<div id="absenceTableContainer" style="display:<?= ($show_form || isset($_GET['add'])) ? 'none' : 'block' ?>">
<table class="table table-bordered table-striped align-middle text-center">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Date</th>
            <th>Durée</th>
            <th>Type</th>
            <th>ID Opérateur</th>
            <th>Nom Complet</th>
            <th>CIN</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
<?php
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>
        <td>".htmlspecialchars($row['id_abs'])."</td>
        <td>".htmlspecialchars($row['date_absence'])."</td>
        <td>".htmlspecialchars($row['dure_absence'])."</td>
        <td>".htmlspecialchars($row['type_absence'])."</td>
        <td>".htmlspecialchars($row['id_op'])."</td>
        <td>".htmlspecialchars($row['nom_op'])."</td>
        <td>".htmlspecialchars($row['cin'])."</td>
        <td>
            <a href='?section=absences&edit=".urlencode($row['id_abs'])."' class='btn btn-sm btn-warning'>Modifier</a>
            <a href='?section=absences&delete=".urlencode($row['id_abs'])."' class='btn btn-sm btn-danger' onclick=\"return confirm('Supprimer cette absence ?')\">Supprimer</a>
        </td>
    </tr>";
}
?>
    </tbody>
</table>
</div>

<?php
