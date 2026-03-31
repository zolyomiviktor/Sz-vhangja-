<?php
// report_actions.php - Jelentések kezelése
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die("Bejelentkezés szükséges.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Biztonsági hiba: Érvénytelen CSRF token.");
}

$reporter_id = $_SESSION['user_id'];
$content_type = $_POST['content_type'] ?? '';
$content_id = (int) ($_POST['content_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');

// Validáció
$allowed_types = ['group_post', 'group_comment', 'forum_topic', 'forum_reply', 'event', 'user'];
if (!in_array($content_type, $allowed_types) || $content_id <= 0 || empty($reason)) {
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?: "index.php") . "&error=" . urlencode("Érvénytelen jelentési adatok!"));
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO reports (content_type, content_id, reporter_id, reason, status) VALUES (?, ?, ?, ?, 'pending')");
    $stmt->execute([$content_type, $content_id, $reporter_id, $reason]);

    header("Location: " . ($_SERVER['HTTP_REFERER'] ?: "index.php") . "&success=" . urlencode("Köszönjük! A jelentést rögzítettük és moderátorunk hamarosan felülvizsgálja."));
} catch (PDOException $e) {
    error_log("Jelentés hiba: " . $e->getMessage());
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?: "index.php") . "&error=" . urlencode("Rendszerhiba történt a jelentés során."));
}
exit;
