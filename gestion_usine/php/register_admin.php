<?php
require_once __DIR__ . '/connexion.php'; // This defines $pdo

$cin = "Y512417";
$plainPassword = "12345678";

// Hash the password
$hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

// Insert into admin table
try {
    $stmt = $pdo->prepare("INSERT INTO admin (cin, password) VALUES (:cin, :password)");
    $stmt->bindParam(':cin', $cin);
    $stmt->bindParam(':password', $hashedPassword);
    $stmt->execute();

    echo "✅ Admin registered successfully!";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
