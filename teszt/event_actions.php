<?php
// event_actions.php - Esemény műveletek API
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: events.php");
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Biztonsági hiba: Érvénytelen CSRF token.");
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$event_id = (int) ($_POST['event_id'] ?? 0);

if ($event_id === 0) {
    header("Location: events.php?error=" . urlencode("Érvénytelen esemény!"));
    exit;
}

try {
    switch ($action) {
        case 'join':
            // Részvétel jelzése
            $stmt = $pdo->prepare("INSERT INTO event_participants (event_id, user_id, status) VALUES (?, ?, 'going')
                                   ON DUPLICATE KEY UPDATE status = 'going'");
            $stmt->execute([$event_id, $user_id]);

            header("Location: event_view.php?id=$event_id&success=" . urlencode("Sikeresen jeleztél részvételt!"));
            exit;

        case 'cancel':
            // Részvétel lemondása
            $stmt = $pdo->prepare("DELETE FROM event_participants WHERE event_id = ? AND user_id = ?");
            $stmt->execute([$event_id, $user_id]);

            header("Location: event_view.php?id=$event_id&success=" . urlencode("Részvételed lemondva."));
            exit;

        default:
            throw new Exception("Ismeretlen művelet!");
    }
} catch (Exception $e) {
    error_log("Esemény művelet hiba: " . $e->getMessage());
    header("Location: event_view.php?id=$event_id&error=" . urlencode($e->getMessage()));
    exit;
}
