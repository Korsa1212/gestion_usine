<?php
// edit_historique.php
require_once __DIR__ . '/connexion.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo '<div class="alert alert-danger">Entrée invalide.</div>';
    exit;
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['shift_travaille','id_op','date_action','id_ordre','id_article','nom_op','cin','periode_du','periode_au'];
    $params = [];
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
        $id
    ]);
    header('Location: admin_dashboard.php?section=historique&edit=success');
    exit;
}

// Fetch row for editing
$stmt = $pdo->prepare("SELECT * FROM historique_planing WHERE id_hist_info = ?"); // contient id_article et action si la colonne existe
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo '<div class="alert alert-danger">Entrée non trouvée.</div>';
    exit;
}
?>
<div class="container mt-4">
    <h2>Éditer l'entrée historique</h2>
<p>Champs : id_hist_info, id_ordre, id_article, id_op, nom_op, action</p>
    <form method="POST">
        <div class="row mb-2">
            <div class="col"><label>Shift</label><input class="form-control" name="shift_travaille" value="<?php echo htmlspecialchars($row['shift_travaille']); ?>" required></div>
            <div class="col"><label>ID Opérateur</label><input class="form-control" name="id_op" value="<?php echo htmlspecialchars($row['id_op']); ?>" required></div>
            <div class="col"><label>Date</label><input class="form-control" name="date_action" type="date" value="<?php echo htmlspecialchars($row['date_action']); ?>" required></div>
        </div>
        <div class="row mb-2">
            <div class="col"><label>ID Machine</label><input class="form-control" name="id_ordre" value="<?php echo htmlspecialchars($row['id_ordre']); ?>" required></div>
            <div class="col"><label>ID Article</label><input class="form-control" name="id_article" value="<?php echo htmlspecialchars($row['id_article'] ?? ''); ?>" required></div>
            <div class="col"><label>Nom Opérateur</label><input class="form-control" name="nom_op" value="<?php echo htmlspecialchars($row['nom_op']); ?>" required></div>
            <div class="col"><label>CIN</label><input class="form-control" name="cin" value="<?php echo htmlspecialchars($row['cin']); ?>" required></div>
        </div>
        <div class="row mb-2">
            <div class="col"><label>Période du</label><input class="form-control" name="periode_du" type="date" value="<?php echo htmlspecialchars($row['periode_du']); ?>" required></div>
            <div class="col"><label>Période au</label><input class="form-control" name="periode_au" type="date" value="<?php echo htmlspecialchars($row['periode_au']); ?>" required></div>
        </div>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
        <a href="admin_dashboard.php?section=historique" class="btn btn-secondary">Annuler</a>
    </form>
</div>
