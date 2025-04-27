<?php
require_once __DIR__ . '/connexion.php';
$machines = $pdo->query("SELECT m.id_mach, m.nom_mach, m.type_horaire FROM machine m JOIN fabrication f ON m.id_mach = f.id_mach WHERE m.en_fonction = 1 GROUP BY m.id_mach, m.nom_mach, m.type_horaire")->fetchAll(PDO::FETCH_ASSOC);
echo '<pre>';
echo "Machines loaded for planning page:\n";
print_r($machines);
echo '</pre>';
?>
