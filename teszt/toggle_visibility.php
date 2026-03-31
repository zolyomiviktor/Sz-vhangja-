<?php
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF védelem
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Biztonsági hiba: Érvénytelen munkamenet.";
        header("Location: profile.php");
        exit;
    }

    $user_id = $_SESSION['user_id'];

    // Aktuális állapot lekérése
    $stmt = $pdo->prepare("SELECT is_hidden FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if ($user) {
        $new_status = $user['is_hidden'] ? 0 : 1;

        $update = $pdo->prepare("UPDATE users SET is_hidden = ? WHERE id = ?");
        if ($update->execute([$new_status, $user_id])) {
            $_SESSION['success'] = $new_status ? "Profilodat elrejtettük a böngészési listákból." : "Profilod ismét látható mindenki számára.";
        } else {
            $_SESSION['error'] = "Hiba történt a módosítás során.";
        }
    }
}

header("Location: profile.php");
exit;
