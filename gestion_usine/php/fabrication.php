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
    $id_ordre = trim($_POST['id_ordre'] ?? '');
    $periode_du = trim($_POST['periode_du'] ?? '');
    $periode_au = trim($_POST['periode_au'] ?? '');
    $id_mach = trim($_POST['id_mach'] ?? '');
    $id_article = trim($_POST['id_article'] ?? '');
    $edit_mode = isset($_POST['edit_mode']) && $_POST['edit_mode'] === '1';
    $old_id_ordre = $_POST['old_id_ordre'] ?? '';

    if ($id_ordre === '' || $periode_du === '' || $periode_au === '' || $id_mach === '' || $id_article === '') {
        $error = 'Tous les champs sont obligatoires.';
    } else {
        try {
            if ($edit_mode) {
                $stmt = $pdo->prepare("UPDATE fabrication SET id_ordre=?, periode_du=?, periode_au=?, id_mach=?, id_article=? WHERE id_ordre=?");
                $stmt->execute([$id_ordre, $periode_du, $periode_au, $id_mach, $id_article, $old_id_ordre]);
                $success = 'Ordre de fabrication modifié avec succès!';
            } else {
                $stmt = $pdo->prepare("INSERT INTO fabrication (id_ordre, periode_du, periode_au, id_mach, id_article) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$id_ordre, $periode_du, $periode_au, $id_mach, $id_article]);
                $success = 'Nouvel ordre de fabrication ajouté!';
            }
            header('Location: ?section=fabrication');
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == '23000' && strpos($e->getMessage(), '1062 Duplicate entry') !== false) {
                $error = "Erreur: Cet ID ordre existe déjà.";
            } else {
                $error = 'Erreur: ' . $e->getMessage();
            }
        }
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id_ordre = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM fabrication WHERE id_ordre = ?");
        $stmt->execute([$id_ordre]);
        $success = 'Ordre de fabrication supprimé!';
    } catch (PDOException $e) {
        $error = 'Erreur: ' . $e->getMessage();
    }
}

// For edit form population
$edit_data = null;
$show_form = false;
if (isset($_GET['edit'])) {
    $id_ordre = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM fabrication WHERE id_ordre = ?");
    $stmt->execute([$id_ordre]);
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $show_form = true;
}

// Fetch machines and articles for dropdowns
$baseMachineSql = "SELECT id_mach, nom_mach FROM machine WHERE en_fonction = 1";

$machines = [];

// 1) Filter when editing (we know the period)
if ($edit_data) {
    $periode_du_tmp = $edit_data['periode_du'];
    $periode_au_tmp = $edit_data['periode_au'];
    $current_ordre_id = $edit_data['id_ordre'];

    $stmtMach = $pdo->prepare("{$baseMachineSql} AND id_mach NOT IN (
            SELECT id_mach FROM fabrication
            WHERE periode_du <= :periode_au
              AND periode_au >= :periode_du
              AND id_ordre <> :curr_id)
            ORDER BY nom_mach");
    $stmtMach->execute([
        ':periode_du' => $periode_du_tmp,
        ':periode_au' => $periode_au_tmp,
        ':curr_id'    => $current_ordre_id
    ]);
    $machines = $stmtMach->fetchAll(PDO::FETCH_ASSOC);
}
// 2) Filter after a failed POST (fields re-populate from $_POST)
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !$edit_mode) {
    // If user is adding a new order, period fields are in the POST payload
    $periode_du_tmp = $periode_du;
    $periode_au_tmp = $periode_au;
    if ($periode_du_tmp && $periode_au_tmp) {
        $stmtMach = $pdo->prepare("{$baseMachineSql} AND id_mach NOT IN (
                SELECT id_mach FROM fabrication
                WHERE periode_du <= :periode_au
                  AND periode_au >= :periode_du)
                ORDER BY nom_mach");
        $stmtMach->execute([
            ':periode_du' => $periode_du_tmp,
            ':periode_au' => $periode_au_tmp
        ]);
        $machines = $stmtMach->fetchAll(PDO::FETCH_ASSOC);
    }
}

// 3) Default (no period yet) – show all active machines
if (!$machines) {
    $machines = $pdo->query("{$baseMachineSql} ORDER BY nom_mach")->fetchAll(PDO::FETCH_ASSOC);
}

$articles = $pdo->query("SELECT id_article, nom_article FROM article ORDER BY nom_article")->fetchAll(PDO::FETCH_ASSOC);

// If form is submitted, validate if the selected machine is already in use during the specified period
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    // Only check for conflicts if all required fields are provided
    if ($id_ordre !== '' && $periode_du !== '' && $periode_au !== '' && $id_mach !== '') {
        // Query to check for conflicts - machines already assigned during this period
        $conflictStmt = $pdo->prepare("SELECT COUNT(*) FROM fabrication 
            WHERE id_mach = ? 
            AND periode_du <= ? 
            AND periode_au >= ? 
            AND id_ordre != ?");
        $conflictStmt->execute([$id_mach, $periode_au, $periode_du, $edit_mode ? $old_id_ordre : '']);
        $hasConflict = $conflictStmt->fetchColumn() > 0;
        
        if ($hasConflict) {
            $error = 'Cette machine est déjà assignée à un autre ordre pendant cette période.';
        }
    }
}

$result = $pdo->query("SELECT f.*, m.nom_mach, a.nom_article FROM fabrication f JOIN machine m ON f.id_mach = m.id_mach JOIN article a ON f.id_article = a.id_article");
?>
<h1 class="mb-4">Gestion des Ordres de Fabrication</h1>

<?php if ($success): ?>
    <div class="alert alert-success"> <?= htmlspecialchars($success) ?> </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"> <?= htmlspecialchars($error) ?> </div>
<?php endif; ?>

<?php if (empty($edit_data) && !isset($_GET['add']) && !$show_form): ?>
    <a href="?section=fabrication&add=1" class="btn btn-primary mb-3">Ajouter un nouvel ordre</a>
<?php endif; ?>

<div class="card mb-4" id="fabricationFormContainer" style="display:<?= ($show_form || isset($_GET['add'])) ? 'block' : 'none' ?>">
    <div class="card-header">
        <?= $edit_data ? 'Modifier Ordre' : 'Ajouter un nouvel ordre' ?>
    </div>
    <div class="card-body">
        <form method="post" action="?section=fabrication<?php if (isset($_GET['edit'])) echo '&edit=' . urlencode($_GET['edit']); if (isset($_GET['add'])) echo '&add=1'; ?>">
            <input type="hidden" name="edit_mode" value="<?= $edit_data ? '1' : '0' ?>">
            <input type="hidden" name="old_id_ordre" value="<?= $edit_data['id_ordre'] ?? '' ?>">
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">ID Ordre</label>
                    <input type="text" class="form-control" name="id_ordre" value="<?= htmlspecialchars($edit_data['id_ordre'] ?? '') ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Période Du</label>
                    <input type="date" class="form-control" name="periode_du" value="<?= htmlspecialchars($edit_data['periode_du'] ?? '') ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Période Au</label>
                    <input type="date" class="form-control" name="periode_au" value="<?= htmlspecialchars($edit_data['periode_au'] ?? '') ?>" required>
                </div>
                <div class="col-md-3">
    <label class="form-label">Machine</label>
    <select class="form-select" name="id_mach" required>
        <option value="">Sélectionner une machine</option>
        <?php foreach ($machines as $machine): ?>
            <option value="<?= htmlspecialchars($machine['id_mach']) ?>" <?= (isset($edit_data['id_mach']) && $edit_data['id_mach'] == $machine['id_mach']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($machine['nom_mach']) ?> (<?= htmlspecialchars($machine['id_mach']) ?>)
            </option>
        <?php endforeach; ?>
    </select>
</div>
<div class="col-md-3">
    <label class="form-label">Article</label>
    <select class="form-select" name="id_article" required>
        <option value="">Sélectionner un article</option>
        <?php foreach ($articles as $article): ?>
            <option value="<?= htmlspecialchars($article['id_article']) ?>" <?= (isset($edit_data['id_article']) && $edit_data['id_article'] == $article['id_article']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($article['nom_article']) ?> (<?= htmlspecialchars($article['id_article']) ?>)
            </option>
        <?php endforeach; ?>
    </select>
</div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-success">Enregistrer</button>
                <a href="?section=fabrication" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<div id="fabricationTableContainer" style="display:<?= ($show_form || isset($_GET['add'])) ? 'none' : 'block' ?>">
<table class="table table-bordered table-striped align-middle text-center">
    <thead class="table-dark">
        <tr>
            <th>ID Ordre</th>
            <th>Période Du</th>
            <th>Période Au</th>
            <th>Machine</th>
            <th>Article</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
<?php
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>
        <td>".htmlspecialchars($row['id_ordre'])."</td>
        <td>".htmlspecialchars($row['periode_du'])."</td>
        <td>".htmlspecialchars($row['periode_au'])."</td>
        <td>".htmlspecialchars($row['nom_mach'])."</td>
        <td>".htmlspecialchars($row['nom_article'])."</td>
        <td>
            <a href='?section=fabrication&edit=".urlencode($row['id_ordre'])."' class='btn btn-sm btn-warning'>Modifier</a>
            <a href='?section=fabrication&delete=".urlencode($row['id_ordre'])."' class='btn btn-sm btn-danger' onclick=\"return confirm('Supprimer cet ordre ?')\" >Supprimer</a>
        </td>
    </tr>";
}
?>
    </tbody>
</table>
</div>
