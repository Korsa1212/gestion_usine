<?php
session_start();
require 'connexion.php'; // This will define $pdo

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $cin = $_POST['cin'];
    $password = $_POST['password'];

    // Use the correct variable name for the PDO connection
    $stmt = $pdo->prepare("SELECT * FROM admin WHERE cin = ?");
    $stmt->execute([$cin]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin'] = $admin['cin'];
        header("Location: admin_dashboard.php");
        exit;
    } else {
        echo "<script>alert('Invalid CIN or password'); window.location.href='login.php';</script>";
    }
}
?>

