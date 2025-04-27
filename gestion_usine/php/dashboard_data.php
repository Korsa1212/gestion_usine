<?php
require_once __DIR__ . '/connexion.php';
header('Content-Type: application/json');

$type = $_GET['type'] ?? '';

if ($type === 'operators_over_time') {
    // Count number of unique operators present in planning (historique_planing) per date
    $stmt = $pdo->query("SELECT DATE(date_action) as date, COUNT(DISTINCT id_op) as count FROM historique_planing GROUP BY DATE(date_action) ORDER BY date");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['labels' => array_column($data, 'date'), 'data' => array_column($data, 'count')]);
    exit;
} elseif ($type === 'machine_usage') {
    // Count number of times each machine is used in fabrication
    $stmt = $pdo->query("SELECT f.id_mach, m.nom_mach, COUNT(*) as usage_count FROM fabrication f JOIN machine m ON f.id_mach = m.id_mach GROUP BY f.id_mach, m.nom_mach ORDER BY usage_count DESC");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode([
        'labels' => array_column($data, 'nom_mach'),
        'data' => array_column($data, 'usage_count'),
    ]);
    exit;
}
// Invalid type
http_response_code(400);
echo json_encode(['error' => 'Invalid data type']);
