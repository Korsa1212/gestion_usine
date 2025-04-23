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
    $id_article = trim($_POST['id_article'] ?? '');
    $nom_article = trim($_POST['nom_article'] ?? '');
    $edit_mode = isset($_POST['edit_mode']) && $_POST['edit_mode'] === '1';
    $old_id_article = $_POST['old_id_article'] ?? '';

    if ($id_article === '' || $nom_article === '') {
        $error = 'Tous les champs sont obligatoires.';
    } else {
        try {
            if ($edit_mode) {
                // UPDATE
                $stmt = $pdo->prepare("UPDATE article SET id_article=?, nom_article=? WHERE id_article=?");
                $stmt->execute([$id_article, $nom_article, $old_id_article]);
                $success = 'Article modifié avec succès!';
            } else {
                // INSERT
                $stmt = $pdo->prepare("INSERT INTO article (id_article, nom_article) VALUES (?, ?)");
                $stmt->execute([$id_article, $nom_article]);
                $success = 'Nouvel article ajouté!';
            }
            echo '<meta http-equiv="refresh" content="1;url=?section=articles">';
            echo '<div class="alert alert-success">' . htmlspecialchars($success) . ' Redirection en cours...</div>';
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == '23000' && strpos($e->getMessage(), '1062 Duplicate entry') !== false) {
                $error = "Erreur: Cet ID article existe déjà.";
            } else {
                $error = 'Erreur: ' . $e->getMessage();
            }
        }
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id_article = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM article WHERE id_article = ?");
        $stmt->execute([$id_article]);
        $success = 'Article supprimé!';
    } catch (PDOException $e) {
        $error = 'Erreur: ' . $e->getMessage();
    }
}

// For edit form population
$edit_data = null;
$show_form = false;
if (isset($_GET['edit'])) {
    $id_article = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM article WHERE id_article = ?");
    $stmt->execute([$id_article]);
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $show_form = true;
}

$result = $pdo->query("SELECT * FROM article");
?>
<h1 class="mb-4">Gestion des Articles</h1>

<?php if ($success): ?>
    <div class="alert alert-success"> <?= htmlspecialchars($success) ?> </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"> <?= htmlspecialchars($error) ?> </div>
<?php endif; ?>

<?php if (empty($edit_data) && !isset($_GET['add']) && !$show_form): ?>
    <a href="?section=articles&add=1" class="btn btn-primary mb-3">Ajouter un nouvel article</a>
<?php endif; ?>

<div class="card mb-4" id="articleFormContainer" style="display:<?= ($show_form || isset($_GET['add'])) ? 'block' : 'none' ?>">
    <div class="card-header">
        <?= $edit_data ? 'Modifier Article' : 'Ajouter un nouvel article' ?>
    </div>
    <div class="card-body">
        <form method="post" action="?section=articles<?php if (isset($_GET['edit'])) echo '&edit=' . urlencode($_GET['edit']); if (isset($_GET['add'])) echo '&add=1'; ?>">
            <input type="hidden" name="edit_mode" value="<?= $edit_data ? '1' : '0' ?>">
            <input type="hidden" name="old_id_article" value="<?= $edit_data['id_article'] ?? '' ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">ID Article</label>
                    <input type="text" class="form-control" name="id_article" value="<?= htmlspecialchars($edit_data['id_article'] ?? '') ?>" required>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Nom Article</label>
                    <input type="text" class="form-control" name="nom_article" value="<?= htmlspecialchars($edit_data['nom_article'] ?? '') ?>" required>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-success">Enregistrer</button>
                <a href="?section=articles" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<div id="articleTableContainer" style="display:<?= ($show_form || isset($_GET['add'])) ? 'none' : 'block' ?>">
<table class="table table-bordered table-striped align-middle text-center">
    <thead class="table-dark">
        <tr>
            <th>ID Article</th>
            <th>Nom Article</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
<?php
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>
        <td>".htmlspecialchars($row['id_article'])."</td>
        <td>".htmlspecialchars($row['nom_article'])."</td>
        <td>
            <a href='?section=articles&edit=".urlencode($row['id_article'])."' class='btn btn-sm btn-warning'>Modifier</a>
            <a href='?section=articles&delete=".urlencode($row['id_article'])."' class='btn btn-sm btn-danger' onclick=\"return confirm('Supprimer cet article ?')\" >Supprimer</a>
        </td>
    </tr>";
}
?>
    </tbody>
</table>
</div>
