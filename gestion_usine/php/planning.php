<?php
// planning.php
require_once __DIR__ . '/connexion.php';

date_default_timezone_set('Europe/Paris');

// Handle planning generation
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


// Get latest planning
$latestDate = $pdo->query("SELECT MAX(date_action) FROM historique_planing")->fetchColumn();
$machines = $pdo->query("
    SELECT m.id_mach, m.nom_mach, m.type_horaire 
    FROM machine m 
    JOIN fabrication f ON m.id_mach = f.id_mach 
    WHERE m.en_fonction = 1 
    GROUP BY m.id_mach, m.nom_mach, m.type_horaire
")->fetchAll(PDO::FETCH_ASSOC);

$shifts8 = [
    ['06:00:00', '14:00:00'],
    ['14:00:00', '22:00:00'],
    ['22:00:00', '06:00:00'],
];
$shifts12 = [
    ['06:00:00', '18:00:00'],
    ['18:00:00', '06:00:00'],
];

// First week special case
if (!$latestDate) {
    $today = date('Y-m-d');
    $operators = $pdo->query("SELECT * FROM operateures WHERE actif = 1")->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['first_week_planning'])) {
        $assignments = $_POST['assignment'] ?? [];

        foreach ($machines as $mach) {
            $type_horaire = $mach['type_horaire'];
            $shifts = ($type_horaire === '12h') ? $shifts12 : $shifts8;

            foreach ($shifts as $shift) {
                $shift_label = $shift[0] . ' - ' . $shift[1];
                $key = $mach['id_mach'] . '_' . $shift_label;
                $op_id = $assignments[$key] ?? null;

                if ($op_id) {
                    $op = $pdo->prepare("SELECT * FROM operateures WHERE id_op = ?");
                    $op->execute([$op_id]);
                    $opData = $op->fetch(PDO::FETCH_ASSOC);

                    if ($opData) {
                        $stmt = $pdo->prepare("INSERT INTO historique_planing (shift_travaille, id_op, date_action, id_ordre, nom_op, cin, heure_debut, heure_fin) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $shift_label,
                            $opData['id_op'],
                            $today,
                            $mach['id_mach'],
                            $opData['nom_complet'],
                            $opData['CIN'],
                            $shift[0],
                            $shift[1]
                        ]);
                    }
                }
            }
        }

        header('Location: admin_dashboard.php?section=planning&success=1');
        exit;
    }

    // Display first week form
    ?>
    <div class="container mt-4">
        <h2>Premier Planning Hebdomadaire</h2>
        <form method="POST">
            <input type="hidden" name="first_week_planning" value="1">
            <table class="table table-bordered text-center">
                <thead class="table-dark">
                    <tr>
                        <th>Shift</th>
                        <?php foreach ($machines as $mach): ?>
                            <th><?= htmlspecialchars($mach['nom_mach']) ?><br><small><?= htmlspecialchars($mach['type_horaire']) ?></small></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $all_shifts = [];
                    foreach ($machines as $mach) {
                        $shifts = ($mach['type_horaire'] === '12h') ? $shifts12 : $shifts8;
                        foreach ($shifts as $shift) {
                            $label = $shift[0] . ' - ' . $shift[1];
                            if (!in_array($label, $all_shifts)) $all_shifts[] = $label;
                        }
                    }
                    foreach ($all_shifts as $shift_label): ?>
                        <tr>
                            <td><strong><?= $shift_label ?></strong></td>
                            <?php foreach ($machines as $mach): ?>
                                <?php
                                $shifts = ($mach['type_horaire'] === '12h') ? $shifts12 : $shifts8;
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
                                        <select name="assignment[<?= $mach['id_mach'] . '_' . $shift_label ?>]" class="form-select" required>
                                            <option value="">Sélectionner</option>
                                            <?php foreach ($operators as $op): ?>
                                                <option value="<?= $op['id_op'] ?>"><?= htmlspecialchars($op['nom_complet']) ?> (<?= htmlspecialchars($op['CIN']) ?>)</option>
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
            <button type="submit" class="btn btn-success">Enregistrer</button>
        </form>
    </div>
    <?php
    exit;
}

// Normal Mode (Display current planning)
$planning = $pdo->prepare("SELECT * FROM historique_planing WHERE date_action = ? ORDER BY id_ordre, heure_debut");
$planning->execute([$latestDate]);
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

    <form method="POST" class="mb-3 d-inline-block">
        <button type="submit" name="generate_next_planning" class="btn btn-primary">Générer la semaine prochaine</button>
    </form>

    <?php if (!empty($planningRows)): ?>
        <form method="GET" class="d-inline-block ms-2">
            <input type="hidden" name="section" value="planning">
            <input type="hidden" name="edit_all" value="1">
            <button type="submit" class="btn btn-warning">Éditer le planning</button>
        </form>
    <?php endif; ?>

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
