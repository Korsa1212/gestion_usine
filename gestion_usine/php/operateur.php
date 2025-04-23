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
    // After processing, redirect to avoid form resubmission
    $redirect = false;
    $id_op = trim($_POST['id_op'] ?? '');
$nom_complet = trim($_POST['nom_complet'] ?? '');
$CIN = trim($_POST['CIN'] ?? '');
$Telephone = trim($_POST['Telephone'] ?? '');
$actif = isset($_POST['actif']) ? 1 : 0;
    $edit_mode = isset($_POST['edit_mode']) && $_POST['edit_mode'] === '1';
    $old_id_op = $_POST['old_id_op'] ?? '';

    // Validation
    if ($id_op === '' || $nom_complet === '' || $CIN === '' || $Telephone === '') {
    $error = 'Tous les champs sont obligatoires.';
    } else {
        try {
            if ($edit_mode) {
    // UPDATE
    $stmt = $pdo->prepare("UPDATE operateures SET id_op=?, nom_complet=?, CIN=?, Telephone=?, actif=? WHERE id_op=?");
    $stmt->execute([$id_op, $nom_complet, $CIN, $Telephone, $actif, $old_id_op]);
    $success = 'Opérateur modifié avec succès!';
} else {
    // INSERT
    $stmt = $pdo->prepare("INSERT INTO operateures (id_op, nom_complet, CIN, Telephone, actif) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$id_op, $nom_complet, $CIN, $Telephone, $actif]);
    $success = 'Nouvel opérateur ajouté!';
            }
            $redirect = true;

        } catch (PDOException $e) {
            // Handle duplicate entry error for id_op
            if ($e->getCode() == '23000' && strpos($e->getMessage(), '1062 Duplicate entry') !== false) {
                $error = "Erreur: Cet ID opérateur existe déjà.";
            } else {
                $error = 'Erreur: ' . $e->getMessage();
            }
        }
    }
    if (isset($redirect) && $redirect) {
        // Soft redirect: show message and refresh after 1 second
        echo '<meta http-equiv="refresh" content="1;url=?section=operators">';
        echo '<div class="alert alert-success">' . htmlspecialchars($success) . ' Redirection en cours...</div>';
        // Stop further output
        exit;
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id_op = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM operateures WHERE id_op = ?");
        $stmt->execute([$id_op]);
        $success = 'Opérateur supprimé!';
    } catch (PDOException $e) {
        // Check for foreign key constraint error (MySQL error 1451)
        if ($e->getCode() == '23000' && strpos($e->getMessage(), '1451') !== false) {
            // Check if this id_op is referenced in fabrication
            $referencedInFabrication = false;
            try {
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM fabrication WHERE id_op = ?");
                $checkStmt->execute([$id_op]);
                $count = $checkStmt->fetchColumn();
                if ($count > 0) {
                    $referencedInFabrication = true;
                }
            } catch (PDOException $ex) {
                $referencedInFabrication = null; // Table missing or error
            }
            if ($referencedInFabrication === true) {
                $error = "Impossible de supprimer cet opérateur car il est utilisé dans une fabrication.";
            } else if ($referencedInFabrication === false) {
                $error = "Impossible de supprimer cet opérateur à cause d'une contrainte, mais il n'est pas utilisé dans la table fabrication. Veuillez vérifier les autres tables ou la structure de la base de données.";
            } else {
                $error = "Impossible de supprimer cet opérateur à cause d'une contrainte de clé étrangère.";
            }
        } else {
            $error = 'Erreur: ' . $e->getMessage();
        }
    }
}

// For edit form population
$edit_data = null;
$show_form = false;
if (isset($_GET['edit'])) {
    $id_op = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM operateures WHERE id_op = ?");
    $stmt->execute([$id_op]);
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $show_form = true;
}

$result = $pdo->query("SELECT * FROM operateures");

?>
<h1 class="mb-4">Gestion des Opérateurs</h1>

<!-- Affichage des messages -->
<?php if ($success): ?>
    <div class="alert alert-success"> <?= htmlspecialchars($success) ?> </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"> <?= htmlspecialchars($error) ?> </div>
<?php endif; ?>

<!-- Bouton pour afficher le formulaire d'ajout -->
<?php if (empty($edit_data) && !isset($_GET['add']) && !$show_form): ?>
    <a href="?section=operators&add=1" class="btn btn-primary mb-3">Ajouter un nouvel opérateur</a>
<?php endif; ?>

<!-- Formulaire d'ajout/modification -->
<div class="card mb-4" id="operatorFormContainer" style="display:<?= ($show_form || isset($_GET['add'])) ? 'block' : 'none' ?>">
    <div class="card-header">
        <?= $edit_data ? 'Modifier Opérateur' : 'Ajouter un nouvel opérateur' ?>
    </div>
    <div class="card-body">
        <form method="post" action="?section=operators<?php if (isset($_GET['edit'])) echo '&edit=' . urlencode($_GET['edit']); if (isset($_GET['add'])) echo '&add=1'; ?>">
            <input type="hidden" name="edit_mode" value="<?= $edit_data ? '1' : '0' ?>">
            <input type="hidden" name="old_id_op" value="<?= $edit_data['id_op'] ?? '' ?>">
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">ID Opérateur</label>
                    <input type="text" class="form-control" name="id_op" value="<?= htmlspecialchars($edit_data['id_op'] ?? '') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Nom Complet</label>
                    <input type="text" class="form-control" name="nom_complet" value="<?= htmlspecialchars($edit_data['nom_complet'] ?? '') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">CIN</label>
                    <input type="text" class="form-control" name="CIN" value="<?= htmlspecialchars($edit_data['CIN'] ?? '') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Téléphone</label>
                    <input type="text" class="form-control" name="Telephone" value="<?= htmlspecialchars($edit_data['Telephone'] ?? '') ?>" required>
                </div>
                <div class="col-md-1 d-flex align-items-center">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="actif" value="1" id="actifCheck" <?= isset($edit_data['actif']) ? ($edit_data['actif'] ? 'checked' : '') : 'checked' ?>>
                        <label class="form-check-label" for="actifCheck">Actif</label>
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-success">Enregistrer</button>
                <a href="?section=operators" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<!-- Tableau des opérateurs -->
<div id="operatorTableContainer" style="display:<?= ($show_form || isset($_GET['add'])) ? 'none' : 'block' ?>">
<table class="table table-bordered table-striped align-middle text-center">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Nom Complet</th>
            <th>CIN</th>
            <th>Téléphone</th>
            <th>Actif</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
<?php
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>
        <td>".htmlspecialchars($row['id_op'])."</td>
        <td>".htmlspecialchars($row['nom_complet'])."</td>
        <td>".htmlspecialchars($row['CIN'])."</td>
        <td>".htmlspecialchars($row['Telephone'])."</td>
        <td>".($row['actif'] ? 'Oui' : 'Non')."</td>
        <td>
            <a href='?section=operators&edit=".urlencode($row['id_op'])."' class='btn btn-sm btn-warning'>Modifier</a>
            <a href='?section=operators&delete=".urlencode($row['id_op'])."' class='btn btn-sm btn-danger' onclick=\"return confirm('Supprimer cet opérateur ?')\" >Supprimer</a>
        </td>
    </tr>";
}
?>
    </tbody>
</table>
</div>

<?php
