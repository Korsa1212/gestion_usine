<?php
// delete_historique.php
require_once __DIR__ . '/connexion.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id > 0) {
    $stmt = $pdo->prepare("DELETE FROM historique_planing WHERE id_hist_info = ?");
    $stmt->execute([$id]);
}
header('Location: admin_dashboard.php?section=historique&delete=success');
exit;
