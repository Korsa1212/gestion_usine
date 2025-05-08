<?php
/**
 * AJAX Request Handler
 * 
 * This file handles various AJAX requests from the application
 * Currently handles:
 * - Getting available machines based on time period
 */

// Start session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only allow Ajax requests from authenticated admins
if (!isset($_SESSION['admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

require_once __DIR__ . '/connexion.php';

// Handle different actions
$action = $_POST['action'] ?? '';

if ($action === 'get_available_machines') {
    getAvailableMachines($pdo);
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid action']);
    exit;
}

/**
 * Get machines available during a specific time period
 * 
 * @param PDO $pdo Database connection
 * @return void
 */
function getAvailableMachines($pdo) {
    $periode_du = $_POST['periode_du'] ?? '';
    $periode_au = $_POST['periode_au'] ?? '';
    $current_ordre_id = $_POST['current_ordre_id'] ?? '';
    
    header('Content-Type: application/json');
    
    // Validate required parameters
    if (empty($periode_du) || empty($periode_au)) {
        echo json_encode([
            'error' => 'Missing required parameters',
            'machines' => []
        ]);
        return;
    }
    
    try {
        // Build the query to get available machines
        // Exclude machines that are already assigned to orders during this period
        $sql = "
            SELECT DISTINCT m.id_mach, m.nom_mach
            FROM machine m
            WHERE m.en_fonction = 1
            AND m.id_mach NOT IN (
                SELECT f.id_mach
                FROM fabrication f
                WHERE f.periode_du <= :periode_au 
                AND f.periode_au >= :periode_du
                " . (!empty($current_ordre_id) ? "AND f.id_ordre != :current_ordre_id" : "") . "
            )
            ORDER BY m.nom_mach
        ";
        
        $stmt = $pdo->prepare($sql);
        
        $params = [
            ':periode_du' => $periode_du,
            ':periode_au' => $periode_au
        ];
        
        if (!empty($current_ordre_id)) {
            $params[':current_ordre_id'] = $current_ordre_id;
        }
        
        $stmt->execute($params);
        $machines = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'machines' => $machines
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'error' => 'Database error: ' . $e->getMessage(),
            'machines' => []
        ]);
    }
}
