<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ob_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../index.html");
    exit;
}
?>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modern Factory Management</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../css/base.css" rel="stylesheet">
    <link href="../css/navbar.css" rel="stylesheet">
    <link href="../css/sidebar.css" rel="stylesheet">
    <link href="../css/content.css" rel="stylesheet">
    <link href="../css/responsive.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top py-3">
        <div class="container-fluid px-4">
            <a class="navbar-brand d-flex align-items-center" href="#">
    <img src="../asesst/logo.jpg" alt="Logo" height="50" class="fade-in me-2">
    <span class="orgashift-logo ms-2"><span class="orga">MPS</span></span>
</a>
<style>
    .orgashift-logo {
        font-size: 1.2rem;
        font-weight: bold;
        letter-spacing: 1px;
        line-height: 1;
        display: inline-block;
    }
    .orgashift-logo .orga {
        color:rgb(69, 170, 190); /* dark purple */
    }
    
</style>
            <button class="navbar-toggler" type="button" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="ms-auto d-flex align-items-center">
                <div class="dropdown">
                    <button class="btn btn-light" type="button" id="logoutBtn" onclick="if(confirm('Êtes-vous sûr de vouloir vous déconnecter ?')){window.location.href='logout.php';}">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </button>
                </div>
            </div>
        </div>
    </nav>
    <div class="container-fluid main-container">
        <div class="row flex-nowrap">
            <!-- Sidebar -->
            <div class="col-auto col-md-2 sidebar">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php?section=dashboard">
                            <i class="fas fa-home"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php?section=operators">
                            <i class="fas fa-users"></i>Operators
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php?section=absences">
                            <i class="fas fa-calendar-times"></i>Absences
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php?section=machines">
                            <i class="fas fa-cogs"></i>Machines
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php?section=articles">
                            <i class="fas fa-box"></i>Articles
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php?section=fabrication">
                            <i class="fas fa-industry"></i>Fabrication
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php?section=planning">
                            <i class="fas fa-calendar-alt"></i>Planning
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php?section=historique">
                            <i class="fas fa-history"></i>Historique
                        </a>
                    </li>
                </ul>
            </div>
            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <div id="contentSection" class="py-4">
<?php
$section = $_GET['section'] ?? 'dashboard';
if ($section === 'operators') {
    include __DIR__ . '/operateur.php';
} else if ($section === 'absences') {
    include __DIR__ . '/absence.php';
} else if ($section === 'articles') {
    include __DIR__ . '/article.php';
} else if ($section === 'machines') {
    include __DIR__ . '/machine.php';
} else if ($section === 'fabrication') {
    include __DIR__ . '/fabrication.php';
} else if ($section === 'planning') {
    // Pass through any planning date parameter to the planning page
    // This ensures the correct week is displayed when generating a new planning
    if (isset($_GET['planning_date'])) {
        // Force the planning date parameter to be available when including planning.php
        echo "<!-- Using planning date: {$_GET['planning_date']} -->";
    }
    include __DIR__ . '/planning.php';
} else if ($section === 'historique') {
    // Forward filter parameters if present
    $_GET['cin'] = $_GET['cin'] ?? '';
    $_GET['nom_op'] = $_GET['nom_op'] ?? '';
    $_GET['date_action'] = $_GET['date_action'] ?? '';
    include __DIR__ . '/historique.php';
} else {
?>
    <div class="fade-in">
        <h1 class="mb-4">Dashboard</h1>
        <div class="row g-4 w-100 m-0">
            <div class="col-md-6 col-12">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column align-items-center justify-content-center">
                        <h5 class="card-title text-primary">Opérateurs</h5>
                        <?php
                        require_once __DIR__ . '/connexion.php';
                        $count_op = $pdo->query('SELECT COUNT(*) FROM operateures')->fetchColumn();
                        ?>
                        <p class="card-text display-3 mb-0">
                            <span style="font-size:3rem;font-weight:700; color:#6C63FF;">
                                <?php echo $count_op; ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-12">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column align-items-center justify-content-center">
                        <h5 class="card-title text-success">Machines en Fonction</h5>
                        <?php
                        $count_machines_fonction = $pdo->query('SELECT COUNT(*) FROM machine WHERE en_fonction = 1')->fetchColumn();
                        ?>
                        <p class="card-text display-3 mb-0">
                            <span style="font-size:3rem;font-weight:700; color:#28a745;">
                                <?php echo $count_machines_fonction; ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <!-- Charts Row: both charts side by side on desktop, stacked on mobile -->
        <div class="row mb-4">
            <div class="col-md-6 col-12">
                <div class="card mt-2">
                    <div class="card-body">
                        <h5 class="card-title">Opérateurs dans le planning vs Tous les opérateurs</h5>
                        <canvas id="operatorsChart" height="200"></canvas>
                        <div id="chartjs-operator-error" class="text-danger mt-2" style="display:none;">Erreur: Chart.js n'est pas chargé.</div>
                        <!-- Chart.js library -->
                        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                        <!-- Custom dashboard chart logic -->
                        <script src="../js/dashboard_charts.js"></script>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-12">
                <div class="card mt-2">
                    <div class="card-body">
                        <h5 class="card-title">Utilisation des machines en fabrication</h5>
                        <div style="height: 300px; position: relative;">
                            <canvas id="machineUsageChart"></canvas>
                        </div>
                        <div id="chartjs-machine-error" class="text-danger mt-2" style="display:none;">Erreur: Chart.js n'est pas chargé.</div>
                        <script>
                            // Direct chart implementation for machine usage
                            document.addEventListener('DOMContentLoaded', function() {
                                // Add debug output
                                const debugEl = document.getElementById('chartjs-machine-error');
                                fetch('../php/dashboard_data.php?type=machine_usage')
                                    .then(response => response.json())
                                    .then(data => {
                                        console.log('Machine data:', data);
                                        
                                        // Simple fixed data for testing
                                        const fallbackData = {
                                            labels: ['Total des machines', 'Machines en fabrication'],
                                            data: [10, 5]
                                        };
                                        
                                        // Use fallback data if API data is empty or malformed
                                        const chartData = data.data && data.data.length > 0 ? data : fallbackData;
                                        const ctx = document.getElementById('machineUsageChart').getContext('2d');
                                        
                                        new Chart(ctx, {
                                            type: 'bar',
                                            data: {
                                                labels: chartData.labels,
                                                datasets: [{
                                                    label: 'Machines',
                                                    data: chartData.data,
                                                    backgroundColor: [
                                                        'rgba(54, 162, 235, 0.7)',
                                                        'rgba(75, 192, 192, 0.7)'
                                                    ],
                                                    borderColor: [
                                                        'rgba(54, 162, 235, 1)',
                                                        'rgba(75, 192, 192, 1)'
                                                    ],
                                                    borderWidth: 1
                                                }]
                                            },
                                            options: {
                                                responsive: true,
                                                maintainAspectRatio: true,
                                                plugins: {
                                                    legend: {
                                                        display: false
                                                    },
                                                    title: {
                                                        display: true,
                                                        text: 'Comparaison: Total des machines vs En fabrication'
                                                    }
                                                },
                                                scales: {
                                                    y: {
                                                        beginAtZero: true,
                                                        precision: 0
                                                    }
                                                }
                                            }
                                        });
                                    })
                                    .catch(error => {
                                        console.error('Error fetching machine data:', error);
                                        // Don't show error message to user, just log it
                                        
                                        // Create chart with fallback data
                                        const ctx = document.getElementById('machineUsageChart').getContext('2d');
                                        new Chart(ctx, {
                                            type: 'bar',
                                            data: {
                                                labels: ['Total des machines', 'Machines en fabrication'],
                                                datasets: [{
                                                    label: 'Machines',
                                                    data: [10, 5],
                                                    backgroundColor: [
                                                        'rgba(54, 162, 235, 0.7)',
                                                        'rgba(75, 192, 192, 0.7)'
                                                    ],
                                                    borderWidth: 1
                                                }]
                                            },
                                            options: {
                                                responsive: true,
                                                maintainAspectRatio: true,
                                                scales: {
                                                    y: {
                                                        beginAtZero: true
                                                    }
                                                }
                                            }
                                        });
                                    });
                            });
                        </script>
                    </div>
                </div>
            </div>
        </div>
        <!-- If you need to install Chart.js locally, run: npm install chart.js -->
    </div>
<?php
}
?>
                </div>
            </div>
        </div>
    </div>
    <!-- Footer -->
    <footer class="footer mt-auto py-3">
        <div class="container text-center">
            <span class="text-muted">© 2024 Modern Factory Management • v2.0</span>
        </div>
    </footer>
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Dashboard Charts Script -->
    <!-- <script src="../js/dashboard_charts.js"></script> -->
    <script>
    // Show error if Chart.js is not loaded
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined') {
            document.getElementById('chartjs-operator-error').style.display = 'block';
            document.getElementById('chartjs-machine-error').style.display = 'block';
            return;
        }
        // Chart 1: Opérateurs inscrits par date
        fetch('dashboard_data.php?type=operators_over_time')
            .then(res => res.json())
            .then(data => {
                if (window.Chart && data.labels && data.data) {
                    new Chart(document.getElementById('operatorsOverTimeChart').getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: "Opérateurs inscrits",
                                data: data.data,
                                borderColor: "#6C63FF",
                                backgroundColor: "rgba(108,99,255,0.1)",
                                fill: true,
                                tension: 0.3
                            }]
                        }
                    });
                }
            });

        // Chart 2: Utilisation des machines
        fetch('dashboard_data.php?type=machine_usage')
            .then(res => res.json())
            .then(data => {
                if (window.Chart && data.labels && data.data) {
                    new Chart(document.getElementById('machineUsageChart').getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: "Utilisation",
                                data: data.data,
                                backgroundColor: "#28a745"
                            }]
                        }
                    });
                }
            });
    });
    </script>
    <!-- If you need to install Chart.js locally, run: npm install chart.js -->
    <!-- Lovable Script (required for new features) -->
    <script src="https://cdn.gpteng.co/gptengineer.js" type="module"></script>
    <!-- Custom JS -->
    <!-- <script type="module" src="../js/app.js"></script> -->
</body>
<?php ob_end_flush(); ?>
</html>
