<?php
// generate_next_planning.php
// This script generates the next week's planning for both 8h and 12h shifts, based on the last planning in historique_planing.

require_once __DIR__ . '/connexion.php'; // uses $pdo (PDO)

// 1. Get all machines that are in function
$machines = $pdo->query("SELECT id_mach FROM machine WHERE en_fonction = 1")->fetchAll(PDO::FETCH_ASSOC);

// 2. Get all active operators (assuming 'actif' column means present)
$operators = $pdo->query("SELECT * FROM operateures WHERE actif = 1")->fetchAll(PDO::FETCH_ASSOC);

// 3. Get the last planning date
$lastDate = $pdo->query("SELECT MAX(date_action) FROM historique_planing")->fetchColumn();
if (!$lastDate) {
    $lastDate = date('Y-m-d'); // fallback if no planning exists yet
}

// 4. Get the last week's planning
$latestPlanning = $pdo->prepare("SELECT * FROM historique_planing WHERE date_action = ? ORDER BY id_ordre, heure_debut");
$latestPlanning->execute([$lastDate]);
$latestPlanning = $latestPlanning->fetchAll(PDO::FETCH_ASSOC);

// 5. Determine shift types from fabrication table
// If any machine has a fabrication order with a 12h period, use 12h shifts for that machine
$shiftTypes = [];
foreach ($machines as $m) {
    $stmt = $pdo->prepare("SELECT type_horaire FROM machine WHERE id_mach = ?");
    $stmt->execute([$m['id_mach']]);
    $type = $stmt->fetchColumn();
    $shiftTypes[$m['id_mach']] = $type === '12h' ? '12h' : '8h';
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

// 6. Prepare next week's planning by rotating both shift and machine for each operator
$nextPlanning = [];
$date_action = date('Y-m-d', strtotime($lastDate . ' +7 days'));

// Separate operators and machines by shift type
// (helper function removed, not needed)

// Build operator and machine lists by shift type
$ops8 = [];
$ops12 = [];
foreach ($operators as $op) {
    // Assign by shift type if you have this info, else put all in 8h
    $ops8[] = $op;
}
$machines8 = [];
$machines12 = [];
foreach ($machines as $m) {
    if ($shiftTypes[$m['id_mach']] === '12h') {
        $machines12[] = $m;
    } else {
        $machines8[] = $m;
    }
}

// Get last week's assignments for rotation
$lastAssignments = $latestPlanning;

function rotateIndex($current, $count) {
    return ($current + 1) % $count;
}

// Helper: get all unique shifts for a type
function getShifts($type) {
    return $type === '12h' ? [
        ['06:00:00', '18:00:00'],
        ['18:00:00', '06:00:00'],
    ] : [
        ['06:00:00', '14:00:00'],
        ['14:00:00', '22:00:00'],
        ['22:00:00', '06:00:00'],
    ];
}

// Build planning for 8h and 12h separately
foreach ([['ops'=>$ops8,'machines'=>$machines8,'type'=>'8h'], ['ops'=>$ops12,'machines'=>$machines12,'type'=>'12h']] as $group) {
    $ops = $group['ops'];
    $machines = $group['machines'];
    $type = $group['type'];
    $shifts = getShifts($type);
    if (count($ops) === 0 || count($machines) === 0) continue;

    // Find last week assignments for this group
    $groupLast = array_values(array_filter($lastAssignments, function($row) use ($machines, $shifts) {
        $machIds = array_column($machines, 'id_mach');
        $shiftNames = array_map(function($s){return $s[0].' - '.$s[1];}, $shifts);
        return in_array($row['id_ordre'], $machIds) && in_array($row['shift_travaille'], $shiftNames);
    }));

    // Map: op id => [last machine idx, last shift idx]
    $opMap = [];
    foreach ($groupLast as $row) {
        $opId = $row['id_op'];
        $machIdx = array_search($row['id_ordre'], array_column($machines, 'id_mach'));
        $shiftIdx = array_search($row['shift_travaille'], array_map(function($s){return $s[0].' - '.$s[1];}, $shifts));
        if ($machIdx !== false && $shiftIdx !== false) {
            $opMap[$opId] = ['machine' => $machIdx, 'shift' => $shiftIdx];
        }
    }

    // Assign each operator to next machine and next shift
    foreach ($ops as $op) {
        $last = $opMap[$op['id_op']] ?? ['machine'=>-1,'shift'=>-1];
        $nextMach = rotateIndex($last['machine'], count($machines));
        $nextShift = rotateIndex($last['shift'], count($shifts));
        $machine = $machines[$nextMach];
        $shift = $shifts[$nextShift];
        $nextPlanning[] = [
            'shift_travaille' => $shift[0] . ' - ' . $shift[1],
            'id_op' => $op['id_op'],
            'date_action' => $date_action,
            'id_ordre' => $machine['id_mach'],
            'nom_op' => $op['nom_complet'],
            'cin' => $op['CIN'],
            'heure_debut' => $shift[0],
            'heure_fin' => $shift[1]
        ];
    }
}


// 7. Insert new planning into historique_planing
$stmt = $pdo->prepare("INSERT INTO historique_planing (shift_travaille, id_op, date_action, id_ordre, nom_op, cin, heure_debut, heure_fin) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
foreach ($nextPlanning as $row) {
    $stmt->execute([
        $row['shift_travaille'],
        $row['id_op'],
        $row['date_action'],
        $row['id_ordre'],
        $row['nom_op'],
        $row['cin'],
        $row['heure_debut'],
        $row['heure_fin']
    ]);
}
echo "✅ Next week's planning has been generated and saved successfully.";
?>
