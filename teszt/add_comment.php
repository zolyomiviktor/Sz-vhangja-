<?php
session_start();
require_once 'db.php';
require_once 'forum_helper.php';

$currentUserId = $_SESSION['user_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$currentUserId) {
    header('Location: forum.php');
    exit;
}

$postId = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
$content = trim(filter_input(INPUT_POST, 'content', FILTER_UNSAFE_RAW));

if (!$postId || empty($content)) {
    header('Location: forum.php');
    exit;
}

// Tartalomszűrés
$filterResult = contentFilter($content);
$isApproved = $filterResult['status'] === 'approved' ? 1 : 0;
$flagReason = $filterResult['status'] === 'flagged' ? $filterResult['message'] : null;

try {
    $stmt = $pdo->prepare("
        INSERT INTO forum_comments (post_id, user_id, content, is_approved, flag_reason) 
        VALUES (:post_id, :user_id, :content, :is_approved, :flag_reason)
    ");
    $stmt->execute([
        ':post_id' => $postId,
        ':user_id' => $currentUserId,
        ':content' => $content,
        ':is_approved' => $isApproved,
        ':flag_reason' => $flagReason
    ]);

    if ($isApproved) {
        $_SESSION['success'] = "Hozzászólás sikeresen közzétéve!";
    } else {
        $_SESSION['info'] = "Hozzászólásod moderálásra vár biztonsági okokból.";
    }

} catch (PDOException $e) {
    $_SESSION['error'] = "Hiba történt a mentés során.";
}

header("Location: post_view.php?id=" . $postId);
exit;
