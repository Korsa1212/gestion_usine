<?php
require_once __DIR__ . '/connexion.php';

// Delete all planning/historique rows where id_ordre does not exist in fabrication

$sql = "DELETE FROM historique_planing WHERE id_ordre NOT IN (SELECT id_ordre FROM fabrication)";
$count = $pdo->exec($sql);

echo "✅ $count orphan planning/historique rows deleted.\n";
?>
