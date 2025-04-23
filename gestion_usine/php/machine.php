<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin'])) {
    header("Location: ../index.html");
    exit;
}
require_once __DIR__ . '/connexion.php';

$success = $error = '';

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_mach = trim($_POST['id_mach'] ?? '');
    $nom_mach = trim($_POST['nom_mach'] ?? '');
    $type_horaire = trim($_POST['type_horaire'] ?? '');
    $en_fonction = isset($_POST['en_fonction']) ? 1 : 0;
    $edit_mode = isset($_POST['edit_mode']) && $_POST['edit_mode'] === '1';
    $old_id_mach = $_POST['old_id_mach'] ?? '';

    if ($id_mach === '' || $nom_mach === '' || $type_horaire === '') {
        $error = 'Tous les champs sont obligatoires.';
    } else {
        try {
            if ($edit_mode) {
                // UPDATE
                $stmt = $pdo->prepare("UPDATE machine SET id_mach=?, nom_mach=?, type_horaire=?, en_fonction=? WHERE id_mach=?");
                $stmt->execute([$id_mach, $nom_mach, $type_horaire, $en_fonction, $old_id_mach]);
                $success = 'Machine modifiée avec succès!';
            } else {
                // INSERT
                $stmt = $pdo->prepare("INSERT INTO machine (id_mach, nom_mach, type_horaire, en_fonction) VALUES (?, ?, ?, ?)");
                $stmt->execute([$id_mach, $nom_mach, $type_horaire, $en_fonction]);
                $success = 'Nouvelle machine ajoutée!';
            }
            echo '<meta http-equiv="refresh" content="1;url=?section=machines">';
            echo '<div class="alert alert-success">' . htmlspecialchars($success) . ' Redirection en cours...</div>';
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == '23000' && strpos($e->getMessage(), '1062 Duplicate entry') !== false) {
                $error = "Erreur: Cet ID machine existe déjà.";
            } else {
                $error = 'Erreur: ' . $e->getMessage();
            }
        }
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id_mach = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM machine WHERE id_mach = ?");
        $stmt->execute([$id_mach]);
        $success = 'Machine supprimée!';
    } catch (PDOException $e) {
        $error = 'Erreur: ' . $e->getMessage();
    }
}

// For edit form population
$edit_data = null;
$show_form = false;
if (isset($_GET['edit'])) {
    $id_mach = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM machine WHERE id_mach = ?");
    $stmt->execute([$id_mach]);
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $show_form = true;
}

$result = $pdo->query("SELECT * FROM machine");
?>
<h1 class="mb-4">Gestion des Machines</h1>

<?php if ($success): ?>
    <div class="alert alert-success"> <?= htmlspecialchars($success) ?> </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"> <?= htmlspecialchars($error) ?> </div>
<?php endif; ?>

<?php if (empty($edit_data) && !isset($_GET['add']) && !$show_form): ?>
    <a href="?section=machines&add=1" class="btn btn-primary mb-3">Ajouter une nouvelle machine</a>
<?php endif; ?>

<div class="card mb-4" id="machineFormContainer" style="display:<?= ($show_form || isset($_GET['add'])) ? 'block' : 'none' ?>">
    <div class="card-header">
        <?= $edit_data ? 'Modifier Machine' : 'Ajouter une nouvelle machine' ?>
    </div>
    <div class="card-body">
        <form method="post" action="?section=machines<?php if (isset($_GET['edit'])) echo '&edit=' . urlencode($_GET['edit']); if (isset($_GET['add'])) echo '&add=1'; ?>">
            <input type="hidden" name="edit_mode" value="<?= $edit_data ? '1' : '0' ?>">
            <input type="hidden" name="old_id_mach" value="<?= $edit_data['id_mach'] ?? '' ?>">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">ID Machine</label>
                    <input type="text" class="form-control" name="id_mach" value="<?= htmlspecialchars($edit_data['id_mach'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nom Machine</label>
                    <input type="text" class="form-control" name="nom_mach" value="<?= htmlspecialchars($edit_data['nom_mach'] ?? '') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Type Horaire</label>
                    <input type="text" class="form-control" name="type_horaire" value="<?= htmlspecialchars($edit_data['type_horaire'] ?? '') ?>" required>
                </div>
                <div class="col-md-2 d-flex align-items-center">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="en_fonction" value="1" id="enFonctionCheck" <?= isset($edit_data['en_fonction']) ? ($edit_data['en_fonction'] ? 'checked' : '') : '' ?>>
                        <label class="form-check-label" for="enFonctionCheck">En Fonction</label>
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-success">Enregistrer</button>
                <a href="?section=machines" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<div id="machineTableContainer" style="display:<?= ($show_form || isset($_GET['add'])) ? 'none' : 'block' ?>">
<table class="table table-bordered table-striped align-middle text-center">
    <thead class="table-dark">
        <tr>
            <th>ID Machine</th>
            <th>Nom Machine</th>
            <th>Type Horaire</th>
            <th>En Fonction</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
<?php
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>
        <td>".htmlspecialchars($row['id_mach'])."</td>
        <td>".htmlspecialchars($row['nom_mach'])."</td>
        <td>".htmlspecialchars($row['type_horaire'])."</td>
        <td>".($row['en_fonction'] ? 'Oui' : 'Non')."</td>
        <td>
            <a href='?section=machines&edit=".urlencode($row['id_mach'])."' class='btn btn-sm btn-warning'>Modifier</a>
            <a href='?section=machines&delete=".urlencode($row['id_mach'])."' class='btn btn-sm btn-danger' onclick=\"return confirm('Supprimer cette machine ?')\" >Supprimer</a>
        </td>
    </tr>";
}
?>
    </tbody>
</table>
</div>
