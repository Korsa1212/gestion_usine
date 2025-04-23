<?php
// delete_all_historique.php
require_once __DIR__ . '/connexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->exec("DELETE FROM historique_planing");
    header('Location: admin_dashboard.php?section=historique&delete_all=success');
    exit;
}
// If accessed directly, redirect to dashboard
header('Location: admin_dashboard.php?section=historique');
exit;
