<?php
// planning.php
require_once __DIR__ . '/connexion.php';

date_default_timezone_set('Europe/Paris');

// Handle period selection and planning generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_next_planning'])) {
    // If first planning (no planning exists yet), just store the period and show the assignment form
    if (!$pdo->query("SELECT MAX(date_action) FROM historique_planing")->fetchColumn()) {
        $_SESSION['planning_start'] = $_POST['planning_start'] ?? null;
        $_SESSION['planning_end'] = $_POST['planning_end'] ?? null;
        header('Location: admin_dashboard.php?section=planning');
        exit;
    } else {
        // For next week, increment session dates by 7 days from last planning
        $lastDate = $pdo->query("SELECT MAX(date_action) FROM historique_planing")->fetchColumn();
        $nextStart = date('Y-m-d', strtotime($lastDate . ' +7 days'));
        $nextEnd = date('Y-m-d', strtotime($lastDate . ' +13 days'));
        $_SESSION['planning_start'] = $nextStart;
        $_SESSION['planning_end'] = $nextEnd;
        require __DIR__ . '/generate_next_planning.php';
        header('Location: admin_dashboard.php?section=planning&success=1');
        exit;
    }
}

// Handle cancel period button
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_period'])) {
    // Clear the period selection
    unset($_SESSION['planning_start']);
    unset($_SESSION['planning_end']);
    
    header('Location: admin_dashboard.php?section=planning');
    exit;
}

// Handle first planning manual assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_first_planning'])) {
    $assignments = $_POST['assignment'] ?? [];
    $today = date('Y-m-d');
    
    foreach ($assignments as $key => $op_id) {
        if (empty($op_id)) continue;
        
        list($id_mach, $shift_label) = explode('_', $key, 2);
        
        // Use the global planning period for all rows
        $periode_du = $_SESSION['planning_start'] ?? $today;
        $periode_au = $_SESSION['planning_end'] ?? $today;
        
        // Get operator info
        $op = $pdo->prepare("SELECT * FROM operateures WHERE id_op = ?");
        $op->execute([$op_id]);
        $opData = $op->fetch(PDO::FETCH_ASSOC);
        
        // Get order details for id_article using id_mach and current period
        $orderInfo = $pdo->prepare("SELECT id_article FROM fabrication WHERE id_mach = ? AND ? BETWEEN periode_du AND periode_au LIMIT 1");
        $orderInfo->execute([$id_mach, $today]);
        $order = $orderInfo->fetch(PDO::FETCH_ASSOC);
        
        // Only insert if the order exists in fabrication
        if ($opData && $order && !empty($order['id_article'])) {
            $stmt = $pdo->prepare("INSERT INTO historique_planing 
                (shift_travaille, id_op, date_action, id_ordre, id_article, nom_op, cin, periode_du, periode_au) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $shift_label,
                $opData['id_op'],
                $today,
                $id_mach, // Use machine ID from the form
                $order['id_article'],
                $opData['nom_complet'],
                $opData['CIN'],
                $periode_du,
                $periode_au
            ]);
        }
    }
    
    // Clear planning period selection
    unset($_SESSION['planning_start']);
    unset($_SESSION['planning_end']);
    
    // Redirect to the planning page and select the week just saved
    header('Location: admin_dashboard.php?section=planning&planning_date=' . $today . '&success=1');
    exit;
}

// Handle next week planning generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_next_planning'])) {
    require __DIR__ . '/generate_next_planning.php';
    header('Location: admin_dashboard.php?section=planning&success=1');
    exit;
}

// Handle saving edited planning
// Handle saving edited planning
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_edit_planning'])) {
    $updates = $_POST['assignment'] ?? [];

    foreach ($updates as $id_hist_info => $new_id_op) {
        if (!empty($new_id_op)) {
            // Fetch current planning line
            $stmt = $pdo->prepare("SELECT * FROM historique_planing WHERE id_hist_info = ?");
            $stmt->execute([$id_hist_info]);
            $currentRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($currentRow && $currentRow['id_op'] != $new_id_op) {
                // Fetch new operator data
                $opStmt = $pdo->prepare("SELECT nom_complet, CIN FROM operateures WHERE id_op = ?");
                $opStmt->execute([$new_id_op]);
                $newOperator = $opStmt->fetch(PDO::FETCH_ASSOC);

                if ($newOperator) {
                    // Update historique_planing with the new operator
                    $updateStmt = $pdo->prepare("
                        UPDATE historique_planing
                        SET id_op = :id_op,
                            nom_op = :nom_op,
                            cin = :cin
                        WHERE id_hist_info = :id_hist_info
                    ");
                    $updateStmt->execute([
                        ':id_op' => $new_id_op,
                        ':nom_op' => $newOperator['nom_complet'],
                        ':cin' => $newOperator['CIN'],
                        ':id_hist_info' => $id_hist_info
                    ]);
                }
            }
        }
    }

    // After update, redirect to clear POST data and show success message
    header('Location: admin_dashboard.php?section=planning&edit_success=1');
    exit;
}


// Get all unique planning dates
// Add page title at the top


echo '<div class="mb-4">';
$dates = $pdo->query("SELECT DISTINCT date_action FROM historique_planing ORDER BY date_action DESC")->fetchAll(PDO::FETCH_COLUMN);

// If just generated next week, or after success, auto-select most recent week
if ((isset($_GET['success']) && $_GET['success'] == '1') || isset($_GET['edit_success'])) {
    $selectedDate = $dates[0] ?? null;
} else {
    $selectedDate = $_GET['planning_date'] ?? $dates[0] ?? null;
}

echo '<form method="get" class="mb-3">';
echo '<input type="hidden" name="section" value="planning">';
echo '<label for="planning_date" class="form-label">Semaine à afficher:</label>';
echo '<select name="planning_date" id="planning_date" class="form-select" onchange="this.form.submit()">';
foreach ($dates as $date) {
    $sel = ($date == $selectedDate) ? 'selected' : '';
    echo "<option value=\"$date\" $sel>" . htmlspecialchars($date) . "</option>";
}
echo '</select>';
echo '</form>';

// Check if we're in the period selection phase - only if POST values were explicitly submitted
$periodSelected = isset($_SESSION['planning_start']) && isset($_SESSION['planning_end']) && 
                  !empty($_SESSION['planning_start']) && !empty($_SESSION['planning_end']) && 
                  !$selectedDate;

if (!$selectedDate && !$periodSelected) {
    // Step 1: Show period selection form
    echo '<form method="post" class="row g-2 align-items-end">
        <div class="col-auto">
            <label for="planning_start" class="form-label mb-0">Période du</label>
            <input type="date" id="planning_start" name="planning_start" class="form-control" required>
        </div>
        <div class="col-auto">
            <label for="planning_end" class="form-label mb-0">au</label>
            <input type="date" id="planning_end" name="planning_end" class="form-control" required>
        </div>
        <div class="col-auto">
            <button type="submit" name="generate_next_planning" class="btn btn-primary">Générer Planning</button>
        </div>
    </form>';
} else if ($periodSelected) {
    // Show the period that was selected
    echo '<div class="alert alert-info">Période sélectionnée: ' . date('d/m/Y', strtotime($_SESSION['planning_start'])) . 
         ' au ' . date('d/m/Y', strtotime($_SESSION['planning_end'])) . '</div>';
} else {
    // After first planning: show only 'Générer Next Week' and 'Edit All' buttons
    echo '<form method="post" class="d-inline">
        <button type="submit" name="generate_next_planning" class="btn btn-success">Générer la semaine suivante</button>
    </form> ';
    echo '<a href="admin_dashboard.php?section=planning&edit_all=1" class="btn btn-warning ms-2">Editer tout</a>';
}
echo '</div>';

// Define machines variable for normal display mode
$machines = [];
if (!$periodSelected) {
    $machines = $pdo->query("
        SELECT m.id_mach, m.nom_mach, m.type_horaire 
        FROM machine m 
        JOIN fabrication f ON m.id_mach = f.id_mach 
        WHERE m.en_fonction = 1 
        GROUP BY m.id_mach, m.nom_mach, m.type_horaire
    ")->fetchAll(PDO::FETCH_ASSOC);
}

$shifts8 = [
    ['06:00:00', '14:00:00'],
    ['14:00:00', '22:00:00'],
    ['22:00:00', '06:00:00'],
];
$shifts12 = [
    ['06:00:00', '18:00:00'],
    ['18:00:00', '06:00:00'],
];

// Check if we need to show the manual assignment form (period selected but no planning yet)
if ($periodSelected) {
    // Ensure we only get machines that have AT LEAST ONE order in the specific period
    $activeOrdersStmt = $pdo->prepare("
        SELECT f.*, m.nom_mach, m.type_horaire, a.nom_article 
        FROM fabrication f
        JOIN machine m ON f.id_mach = m.id_mach
        JOIN article a ON f.id_article = a.id_article
        WHERE f.periode_du <= :planning_end 
          AND f.periode_au >= :planning_start
          AND m.en_fonction = 1
    ");
    $activeOrdersStmt->execute([
        'planning_start' => $_SESSION['planning_start'],
        'planning_end' => $_SESSION['planning_end']
    ]);
    $activeOrders = $activeOrdersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get operators
    $operators = $pdo->query("SELECT * FROM operateures WHERE actif = 1")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($activeOrders)) {
        echo '<div class="alert alert-warning">Aucun ordre de fabrication actif pour la période sélectionnée.</div>';
    } else {
        // Build all shifts based on machine types
        $all_shifts = [];
        foreach ($activeOrders as $order) {
            $shifts = ($order['type_horaire'] === '12h') ? $shifts12 : $shifts8;
            foreach ($shifts as $shift) {
                $label = $shift[0] . ' - ' . $shift[1];
                if (!in_array($label, $all_shifts)) $all_shifts[] = $label;
            }
        }
        
        // Show form
        echo '<div class="container mt-4">
            <h4>Assignation des Opérateurs - Première Semaine</h4>
            <p class="text-muted">Affichage uniquement des ordres/machines actifs pendant la période sélectionnée</p>
            <form method="POST">
                <input type="hidden" name="save_first_planning" value="1">
                <table class="table table-bordered text-center">
                    <thead class="table-dark">
                        <tr>
                            <th>Shift</th>';
                            
        // Group by machine ID to display in columns
        $uniqueMachines = [];
        foreach ($activeOrders as $order) {
            $key = $order['id_mach'];
            if (!isset($uniqueMachines[$key])) {
                $uniqueMachines[$key] = [
                    'id_mach' => $order['id_mach'],
                    'nom_mach' => $order['nom_mach'],
                    'type_horaire' => $order['type_horaire'],
                    'orders' => []
                ];
            }
            // Store all orders for this machine
            $uniqueMachines[$key]['orders'][$order['id_ordre']] = $order;
        }
        
        // Output machine headers
        foreach ($uniqueMachines as $machine) {
            echo '<th class="text-center">
                    <strong>' . htmlspecialchars($machine['nom_mach']) . '</strong>
                    <br><small>(' . htmlspecialchars($machine['type_horaire']) . ')</small>
                  </th>';
        }
        
        echo '</tr></thead><tbody>';
        
        // For each shift (row)
        foreach ($all_shifts as $shift_label) {
            echo '<tr><td><strong>' . $shift_label . '</strong></td>';
            
            // For each machine (column)
            foreach ($uniqueMachines as $machine) {
                $shifts = ($machine['type_horaire'] === '12h') ? $shifts12 : $shifts8;
                $show = false;
                
                // Check if this shift applies to this machine type
                foreach ($shifts as $shift) {
                    if ($shift_label === ($shift[0] . ' - ' . $shift[1])) {
                        $show = true;
                        break;
                    }
                }
                
                echo '<td class="text-center align-middle">';
                if ($show) {
                    // Get the first order for this machine (use first order ID for this machine)
                    $firstOrder = reset($machine['orders']);
                    $orderId = $firstOrder['id_ordre'];
                    
                    echo '<select name="assignment[' . $orderId . '_' . $shift_label . ']" class="form-select">
                              <option value="">Sélectionner</option>';
                    foreach ($operators as $op) {
                        echo '<option value="' . $op['id_op'] . '">' . 
                             htmlspecialchars($op['nom_complet']) . ' (' . htmlspecialchars($op['CIN']) . ')</option>';
                    }
                    echo '</select>';
                } else {
                    echo '<span class="text-muted">N/A</span>';
                }
                echo '</td>';
            }
            
            echo '</tr>';
        }
        
        echo '</tbody></table>
              <div class="text-center mt-3">
                <button type="submit" class="btn btn-success">Enregistrer le Planning</button>
                <button type="submit" name="cancel_period" class="btn btn-secondary ms-2">Modifier la période</button>
              </div>
            </form>
          </div>';
    }
    
    // Don't show the regular planning display
    exit;
}

// Normal Mode (Display selected planning week)
$planning = $pdo->prepare("SELECT * FROM historique_planing WHERE date_action = ? ORDER BY id_ordre, periode_du");
$planning->execute([$selectedDate]);
$planningRows = $planning->fetchAll(PDO::FETCH_ASSOC);

// Get shifts
$shifts = [];
foreach ($planningRows as $row) {
    if (!in_array($row['shift_travaille'], $shifts)) {
        $shifts[] = $row['shift_travaille'];
    }
}

// Fetch all operators
$operators = $pdo->query("SELECT * FROM operateures WHERE actif = 1")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <h2>Planning Hebdomadaire des Opérateurs</h2>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">Le planning de la semaine a été généré avec succès !</div>
    <?php elseif (isset($_GET['edit_success'])): ?>
        <div class="alert alert-success">Le planning a été modifié avec succès !</div>
    <?php endif; ?>

    <?php /* These buttons are already shown at the top of the page */ ?>

    <div class="table-responsive mt-4">
        <form method="POST">
            <?php if (isset($_GET['edit_all']) && $_GET['edit_all'] == '1'): ?>
                <input type="hidden" name="save_edit_planning" value="1">
            <?php endif; ?>
            <table class="table table-bordered text-center align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Shift</th>
                        <?php foreach ($machines as $mach): ?>
                            <th><?= htmlspecialchars($mach['nom_mach']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shifts as $shift): ?>
                        <tr>
                            <td><strong><?= $shift ?></strong></td>
                            <?php foreach ($machines as $mach): ?>
                                <td>
                                    <?php
                                    $found = false;
                                    foreach ($planningRows as $row) {
                                        if ($row['id_ordre'] == $mach['id_mach'] && $row['shift_travaille'] == $shift) {
                                            $found = true;
                                            if (isset($_GET['edit_all']) && $_GET['edit_all'] == '1') {
                                                ?>
                                                <select name="assignment[<?= $row['id_hist_info'] ?>]" class="form-select">
                                                    <?php foreach ($operators as $op): ?>
                                                        <option value="<?= $op['id_op'] ?>" <?= ($op['id_op'] == $row['id_op']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($op['nom_complet']) ?> (<?= htmlspecialchars($op['CIN']) ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <?php
                                            } else {
                                                echo htmlspecialchars($row['nom_op']) . "<br><small class='text-muted'>" . htmlspecialchars($row['cin']) . "</small>";
                                            }
                                            break;
                                        }
                                    }
                                    if (!$found) {
                                        echo '<span class="text-muted">Aucun opérateur</span>';
                                    }
                                    ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (isset($_GET['edit_all']) && $_GET['edit_all'] == '1'): ?>
                <button type="submit" class="btn btn-success mt-3">Enregistrer les modifications</button>
            <?php endif; ?>
        </form>
    </div>
</div>
