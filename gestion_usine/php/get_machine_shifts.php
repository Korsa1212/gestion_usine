<?php
require_once __DIR__ . '/connexion.php';
header('Content-Type: application/json');

// Get all machines and their active shifts from fabrication table
$sql = "SELECT m.id_mach, m.nom_mach, m.type_horaire, f.periode_du, f.periode_au
        FROM machine m
        JOIN fabrication f ON m.id_mach = f.id_mach
        WHERE m.en_fonction = 1";
$stmt = $pdo->query($sql);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by machine and collect unique shifts (start-end time)
$machine_shifts = [];
foreach ($results as $row) {
    $id = $row['id_mach'];
    if (!isset($machine_shifts[$id])) {
        $machine_shifts[$id] = [
            'nom_mach' => $row['nom_mach'],
            'type_horaire' => $row['type_horaire'],
            'shifts' => []
        ];
    }
    // Use periode_du and periode_au as the shift (if that's how you model it)
    $machine_shifts[$id]['shifts'][] = [
        'start' => $row['periode_du'],
        'end' => $row['periode_au']
    ];
}
// Remove duplicate shifts per machine
foreach ($machine_shifts as &$mach) {
    $mach['shifts'] = array_unique($mach['shifts'], SORT_REGULAR);
}
unset($mach);
echo json_encode($machine_shifts);
