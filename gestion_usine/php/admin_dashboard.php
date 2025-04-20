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
            <a class="navbar-brand" href="#">
                <img src="../logo.png" alt="Factory Logo" height="40" class="fade-in">
            </a>
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
                        <a class="nav-link" href="#" data-section="machines">
                            <i class="fas fa-cogs"></i>Machines
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-section="articles">
                            <i class="fas fa-box"></i>Articles
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-section="fabrication">
                            <i class="fas fa-industry"></i>Fabrication
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-section="planning">
                            <i class="fas fa-calendar-alt"></i>Planning
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-section="historique">
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
} else {
?>
    <div class="fade-in">
        <h1 class="mb-4">Dashboard Overview</h1>
        <div class="row g-4">
            <div class="col-md-4 col-sm-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title text-primary">Opérateurs</h5>
                        <?php
require_once __DIR__ . '/connexion.php';
$count_op = $pdo->query('SELECT COUNT(*) FROM OPERATEUR')->fetchColumn();
?>
<p class="card-text display-3">
    <span style="font-size:2.8 rem;font-weight:700; color:#6C63FF;"><?php echo $count_op; ?></span><br>
    
</p>
                    </div>
                </div>
            </div>
            <!-- Add more dashboard cards here as needed -->
        </div>
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
    <!-- Lovable Script (required for new features) -->
    <script src="https://cdn.gpteng.co/gptengineer.js" type="module"></script>
    <!-- Custom JS -->
    <!-- <script type="module" src="../js/app.js"></script> -->
</body>
<?php ob_end_flush(); ?>
</html>
