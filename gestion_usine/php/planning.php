<?php
// planning.php
// Section for viewing and generating operator-machine planning (weekly rotation)

require_once __DIR__ . '/connexion.php';

// Handle planning generation button
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_next_planning'])) {
    require __DIR__ . '/generate_next_planning.php';
    // After generation, reload the page to show updated planning
    header('Location: admin_dashboard.php?section=planning&success=1');
    exit;
}

// Get the latest planning week
date_default_timezone_set('Europe/Paris');
$latestDate = $pdo->query("SELECT MAX(date_action) FROM historique_planing")->fetchColumn();

// Get machines for table headers, but only those present in fabrication for the current/latest week
$machines = $pdo->query("SELECT m.id_mach, m.nom_mach, m.type_horaire FROM machine m JOIN fabrication f ON m.id_mach = f.id_mach WHERE m.en_fonction = 1 GROUP BY m.id_mach, m.nom_mach, m.type_horaire")->fetchAll(PDO::FETCH_ASSOC);

// Define shifts per type
$shifts8 = [
    ['06:00:00', '14:00:00'],
    ['14:00:00', '22:00:00'],
    ['22:00:00', '06:00:00'],
];
$shifts12 = [
    ['06:00:00', '18:00:00'],
    ['18:00:00', '06:00:00'],
];

if (!$latestDate) {
    // FIRST WEEK: show manual assignment form
    $today = date('Y-m-d');
    // Get active operators
    $operators = $pdo->query("SELECT * FROM operateures WHERE actif = 1")->fetchAll(PDO::FETCH_ASSOC);

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['first_week_planning'])) {
        $success = $error = '';
        $assignments = $_POST['assignment'] ?? [];
        $valid = true;
        foreach ($machines as $mach) {
            $type_horaire = $mach['type_horaire'];
            $shifts = ($type_horaire === '12h') ? $shifts12 : $shifts8;
            foreach ($shifts as $shift) {
                $shift_label = $shift[0] . ' - ' . $shift[1];
                $key = $mach['id_mach'] . '_' . $shift_label;
                if (empty($assignments[$key])) {
                    $valid = false;
                    break 2;
                }
            }
        }
        if ($valid) {
            foreach ($machines as $mach) {
                $type_horaire = $mach['type_horaire'];
                $shifts = ($type_horaire === '12h') ? $shifts12 : $shifts8;
                foreach ($shifts as $shift) {
                    $shift_label = $shift[0] . ' - ' . $shift[1];
                    $key = $mach['id_mach'] . '_' . $shift_label;
                    $op_id = $assignments[$key];
                    // Get operator info
                    $op = array_values(array_filter($operators, function($o) use ($op_id) { return $o['id_op'] == $op_id; }));
                    if ($op) $op = $op[0];
                    else continue;
                    $stmt = $pdo->prepare("INSERT INTO historique_planing (shift_travaille, id_op, date_action, id_ordre, nom_op, cin, heure_debut, heure_fin) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $shift_label,
                        $op['id_op'],
                        $today,
                        $mach['id_mach'],
                        $op['nom_complet'],
                        $op['CIN'],
                        $shift[0],
                        $shift[1]
                    ]);
                }
            }
            header('Location: admin_dashboard.php?section=planning&success=1');
            exit;
        } else {
            $error = 'Tous les postes doivent être assignés.';
        }
    }
    ?>
    <div class="container mt-4">
        <h2 class="mb-3">Premier Planning Hebdomadaire des Opérateurs</h2>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="first_week_planning" value="1">
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle text-center">
                    <thead class="table-dark">
                        <tr>
                            <th>Shift</th>
                            <?php foreach ($machines as $mach): ?>
                                <th><?= htmlspecialchars($mach['nom_mach']) ?> <br><span class="badge bg-secondary"><?= htmlspecialchars($mach['type_horaire']) ?></span></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Collect all unique shifts for all machines
                        $all_shifts = [];
                        foreach ($machines as $mach) {
                            $type_horaire = $mach['type_horaire'];
                            $shifts = ($type_horaire === '12h') ? $shifts12 : $shifts8;
                            foreach ($shifts as $shift) {
                                $label = $shift[0] . ' - ' . $shift[1];
                                if (!in_array($label, $all_shifts)) $all_shifts[] = $label;
                            }
                        }
                        foreach ($all_shifts as $shift_label): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($shift_label) ?></strong></td>
                                <?php foreach ($machines as $mach): ?>
                                    <?php
                                    $type_horaire = $mach['type_horaire'];
                                    $shifts = ($type_horaire === '12h') ? $shifts12 : $shifts8;
                                    $show = false;
                                    foreach ($shifts as $shift) {
                                        if ($shift_label === ($shift[0] . ' - ' . $shift[1])) {
                                            $show = true;
                                            break;
                                        }
                                    }
                                    ?>
                                    <td>
                                        <?php if ($show): ?>
                                            <select class="form-select" name="assignment[<?= $mach['id_mach'] . '_' . $shift_label ?>]" required>
                                                <option value="">Sélectionner un opérateur</option>
                                                <?php 
                                                // Build a set of already assigned operator IDs for this week
                                                $assigned_ops = [];
                                                foreach ($assignments as $v) {
                                                    if (!empty($v)) $assigned_ops[] = $v;
                                                }
                                                foreach ($operators as $op): ?>
                                                    <option value="<?= htmlspecialchars($op['id_op']) ?>" <?= in_array($op['id_op'], $assigned_ops) ? 'disabled' : '' ?>>
                                                        <?= htmlspecialchars($op['nom_complet']) ?> (<?= htmlspecialchars($op['CIN']) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-success">Enregistrer le planning</button>
            </div>
        </form>
    </div>
    <?php
    exit;
}

// Normal mode: display planning for the latest week
$planning = $pdo->prepare("SELECT * FROM historique_planing WHERE date_action = ? ORDER BY id_ordre, heure_debut");
$planning->execute([$latestDate]);
$planningRows = $planning->fetchAll(PDO::FETCH_ASSOC);

// Get all shift times used in this planning
$shifts = [];
foreach ($planningRows as $row) {
    if (!in_array($row['shift_travaille'], $shifts)) {
        $shifts[] = $row['shift_travaille'];
    }
}

?>
<div class="container mt-4">
    <h2 class="mb-3">Planning Hebdomadaire des Opérateurs</h2>
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">Le planning de la semaine prochaine a été généré avec succès !</div>
    <?php endif; ?>
    <form method="POST" class="mb-3 d-inline-block">
        <button type="submit" name="generate_next_planning" class="btn btn-primary">
            Générer le planning de la semaine prochaine
        </button>
    </form>
    <?php if (!empty($planningRows)): ?>
        <form method="GET" class="d-inline-block ms-2">
            <input type="hidden" name="section" value="planning">
            <input type="hidden" name="edit_all" value="1">
            <button type="submit" class="btn btn-warning">Éditer tout le planning</button>
        </form>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle text-center">
            <thead class="table-dark">
                <tr>
                    <th>Shift</th>
                    <?php foreach ($machines as $mach): ?>
                        <th><?php echo htmlspecialchars($mach['nom_mach']); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($shifts as $shift): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($shift); ?></strong></td>
                        <?php foreach ($machines as $mach): ?>
                            <td>
                                <?php
                                $found = false;
                                foreach ($planningRows as $row) {
                                    if ($row['id_ordre'] == $mach['id_mach'] && $row['shift_travaille'] == $shift) {
                                        echo htmlspecialchars($row['nom_op']) . '<br><span class="text-muted">' . htmlspecialchars($row['cin']) . '</span>';
                                        $found = true;
                                        break; // Only one operator per machine per shift
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
    </div>
<?php
// Edit all planning mode
if (isset($_GET['edit_all']) && $_GET['edit_all'] == '1' && !empty($planningRows)) {
    // Get active operators
    $operators = $pdo->query("SELECT * FROM operateures WHERE actif = 1")->fetchAll(PDO::FETCH_ASSOC);
    // Group planning by machine/shift
    $planning_map = [];
    foreach ($planningRows as $row) {
        $planning_map[$row['id_ordre']][$row['shift_travaille']] = $row;
    }
    // Handle form submit
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_all_planning'])) {
        $assignments = $_POST['assignment'] ?? [];
        foreach ($assignments as $mach_id => $shifts) {
            foreach ($shifts as $shift_label => $op_id) {
                // Get operator info
                $op = array_values(array_filter($operators, function($o) use ($op_id) { return $o['id_op'] == $op_id; }));
                if ($op) $op = $op[0];
                else continue;
                // Find historique_planing id
                $hist_id = isset($planning_map[$mach_id][$shift_label]['id_hist_info']) ? $planning_map[$mach_id][$shift_label]['id_hist_info'] : null;
                if ($hist_id) {
                    $stmt = $pdo->prepare("UPDATE historique_planing SET id_op=?, nom_op=?, cin=? WHERE id_hist_info=?");
                    $stmt->execute([$op['id_op'], $op['nom_complet'], $op['CIN'], $hist_id]);
                }
            }
        }
        header('Location: admin_dashboard.php?section=planning&success=1');
        exit;
    }
    ?>
    <div class="container mt-4">
        <h2 class="mb-3">Éditer tout le planning de la semaine</h2>
        <form method="POST">
            <input type="hidden" name="edit_all_planning" value="1">
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle text-center">
                    <thead class="table-dark">
                        <tr>
                            <th>Shift</th>
                            <?php foreach (
                                $machines as $mach): ?>
                                <th><?= htmlspecialchars($mach['nom_mach']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shifts as $shift_label): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($shift_label) ?></strong></td>
                                <?php foreach ($machines as $mach): ?>
                                    <td>
                                        <?php
                                        $row = $planning_map[$mach['id_mach']][$shift_label] ?? null;
                                        $selected = $row ? $row['id_op'] : '';
                                        ?>
                                        <select class="form-select" name="assignment[<?= $mach['id_mach'] ?>][<?= htmlspecialchars($shift_label) ?>]" required>
                                            <option value="">Sélectionner un opérateur</option>
                                            <?php 
            // Build a set of already assigned operator IDs for this week, except for this cell
            $assigned_ops = [];
            foreach ($planning_map as $mid => $shifts_map) {
                foreach ($shifts_map as $slabel => $row) {
                    if ($mid == $mach['id_mach'] && $slabel == $shift_label) continue; // skip current cell
                    if (!empty($row['id_op'])) $assigned_ops[] = $row['id_op'];
                }
            }
            foreach ($operators as $op): ?>
                                                <option value="<?= htmlspecialchars($op['id_op']) ?>" <?= ($planning_map[$mach['id_mach']][$shift_label]['id_op'] ?? '') == $op['id_op'] ? 'selected' : '' ?> <?= in_array($op['id_op'], $assigned_ops) ? 'disabled' : '' ?>>
                                                    <?= htmlspecialchars($op['nom_complet']) ?> (<?= htmlspecialchars($op['CIN']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-success">Enregistrer les modifications</button>
                <a href="admin_dashboard.php?section=planning" class="btn btn-secondary ms-2">Annuler</a>
            </div>
        </form>
    </div>
    <?php
    exit;
}
?>
</div>
