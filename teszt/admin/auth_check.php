<?php
// admin/auth_check.php
require_once '../db.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch admin data
$stmt = $pdo->prepare("SELECT role, nickname, is_active, force_password_change FROM admins WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$current_admin = $stmt->fetch();

if (!$current_admin || $current_admin['is_active'] == 0) {
    // If admin is deactivated or not found, clear session and redirect
    unset($_SESSION['admin_id']);
    die("Hiba: Nincs jogosultságod vagy fiókod deaktiválva.");
}

// Ensure password change if forced
$current_page = basename($_SERVER['PHP_SELF']);
if ($current_admin['force_password_change'] == 1 && $current_page !== 'change_password.php' && $current_page !== 'logout.php') {
    header("Location: change_password.php");
    exit;
}

// Make admin data available to admin pages
$admin_user = $current_admin;
$is_super_admin = ($admin_user['role'] === 'super_admin');
?>