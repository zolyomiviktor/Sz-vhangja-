<?php
require 'auth_check.php';

if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];

    // Mark as read
    $stmt = $pdo->prepare("UPDATE admin_notifications SET is_read = 1 WHERE id = ?");
    $stmt->execute([$id]);
}

// Redirect back to dashboard
header("Location: index.php");
exit;
?>