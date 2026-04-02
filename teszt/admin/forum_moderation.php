<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require 'auth_check.php';

$msg = '';
$error = '';

// Kérések kezelése (elfogadás / törlés)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = (int) $_POST['item_id'];
    $item_type = $_POST['item_type'] ?? 'post'; // 'post' vagy 'comment'
    $action = $_POST['action'];

    try {
        if ($item_type === 'post') {
            if ($action === 'approve') {
                $stmt = $pdo->prepare("UPDATE forum_posts SET is_approved = 1, flag_reason = NULL WHERE id = ?");
                $stmt->execute([$item_id]);
                $msg = "A poszt engedélyezve lett és publikussá vált.";
            } elseif ($action === 'delete') {
                $stmt = $pdo->prepare("DELETE FROM forum_posts WHERE id = ?");
                $stmt->execute([$item_id]);
                $msg = "A poszt véglegesen törölve lett.";
            }
        } elseif ($item_type === 'comment') {
            if ($action === 'approve') {
                $stmt = $pdo->prepare("UPDATE forum_comments SET is_approved = 1, flag_reason = NULL WHERE id = ?");
                $stmt->execute([$item_id]);
                $msg = "A hozzászólás engedélyezve lett.";
            } elseif ($action === 'delete') {
                $stmt = $pdo->prepare("DELETE FROM forum_comments WHERE id = ?");
                $stmt->execute([$item_id]);
                $msg = "A hozzászólás véglegesen törölve lett.";
            }
        }
    } catch (Exception $e) {
        $error = "Hiba történt a művelet során: " . $e->getMessage();
    }
}

// Várolólistán lévő (flagged) posztok lekérése
$stmt = $pdo->prepare("
    SELECT fp.id, fp.title, fp.content, fp.flag_reason, fp.created_at, u.nickname as author
    FROM forum_posts fp
    JOIN users u ON fp.user_id = u.id
    WHERE fp.is_approved = 0
    ORDER BY fp.created_at ASC
");
$stmt->execute();
$flagged_posts = $stmt->fetchAll();

// Várolólistán lévő kommentek lekérése
$stmt = $pdo->prepare("
    SELECT fc.id, fc.content, fc.flag_reason, fc.created_at, u.nickname as author, fp.title as post_title
    FROM forum_comments fc
    JOIN users u ON fc.user_id = u.id
    JOIN forum_posts fp ON fc.post_id = fp.id
    WHERE fc.is_approved = 0
    ORDER BY fc.created_at ASC
");
$stmt->execute();
$flagged_comments = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Fórum Moderáció - Admin</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .report-card { background: white; border: 1px solid #ddd; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem; border-left: 5px solid #fbbc04; }
        .report-actions { display: flex; gap: 1rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee; }
        .badge { padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.8rem; font-weight: bold; text-transform: uppercase; background: #fff3e0; color: #e65100; }
    </style>
</head>
<body>
    <header style="background: var(--primary-color, #ff4081); color: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center;">
        <div style="font-weight: bold; font-size: 1.2rem;">Szívhangja Admin - Fórum Moderáció</div>
        <div>
            Szia, <?= htmlspecialchars($admin_user['nickname'] ?? 'Admin') ?>!
            <a href="moderation.php" style="color: white; margin-left:1rem; text-decoration: underline;">Általános Moderáció</a>
            <a href="index.php" style="color: white; margin-left:1rem; text-decoration: underline;">Vissza a pultra</a>
        </div>
    </header>

    <div class="container" style="max-width: 1000px; margin-top: 2rem;">
        <h1>Ellenőrzésre Váró Fórum Tartalmak</h1>

        <?php if ($msg): ?>
            <div class="alert" style="background: #e8f5e9; color: #2e7d32; padding: 1rem; margin-bottom: 1.5rem; border-radius: 4px; border: 1px solid #a5d6a7;">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert" style="background: #ffebee; color: #c62828; padding: 1rem; margin-bottom: 1.5rem; border-radius: 4px; border: 1px solid #ef9a9a;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (count($flagged_posts) === 0 && count($flagged_comments) === 0): ?>
            <div class="card" style="text-align: center; padding: 4rem 2rem;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
                <h3>Nincs moderálásra váró tartalom!</h3>
                <p style="color: #666;">Minden poszt és komment rendben van.</p>
            </div>
        <?php endif; ?>

        <!-- POSZTOK -->
        <?php foreach ($flagged_posts as $post): ?>
            <div class="report-card">
                <div style="display: flex; justify-content: space-between; margin-bottom: 1rem; font-size: 0.9rem; color: #666;">
                    <span>Szerző: <strong><?= htmlspecialchars($post['author']) ?></strong></span>
                    <span>Beküldve: <?= date('Y. m. d. H:i', strtotime($post['created_at'])) ?></span>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <span class="badge">POSZT (Téma)</span>
                    <strong style="margin-left: 0.5rem; color: #d32f2f;">Ok: <?= htmlspecialchars($post['flag_reason'] ?? 'Gyanús tartalom') ?></strong>
                </div>

                <div style="background: #f8f9fa; border: 1px solid #eee; padding: 1rem; border-radius: 8px;">
                    <h3 style="margin-top: 0;"><?= htmlspecialchars($post['title']) ?></h3>
                    <p style="white-space: pre-wrap; font-size: 0.95rem; margin-top: 0.5rem;"><?= htmlspecialchars($post['content']) ?></p>
                </div>

                <div class="report-actions">
                    <form method="POST" style="flex: 1;" onsubmit="return confirm('Biztosan törlöd ezt a posztot?');">
                        <input type="hidden" name="item_id" value="<?= $post['id'] ?>">
                        <input type="hidden" name="item_type" value="post">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="btn" style="width: 100%; background-color: #ea4335; color: white; border: none; padding: 0.8rem; border-radius: 8px; cursor: pointer;">Törlés (Tiltott)</button>
                    </form>

                    <form method="POST" style="flex: 1;">
                        <input type="hidden" name="item_id" value="<?= $post['id'] ?>">
                        <input type="hidden" name="item_type" value="post">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="btn" style="width: 100%; background-color: #34a853; color: white; border: none; padding: 0.8rem; border-radius: 8px; cursor: pointer;">Engedélyezés (Publikál)</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- KOMMENTEK -->
        <?php foreach ($flagged_comments as $comment): ?>
            <div class="report-card" style="border-left-color: #4285f4;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 1rem; font-size: 0.9rem; color: #666;">
                    <span>Szerző: <strong><?= htmlspecialchars($comment['author']) ?></strong></span>
                    <span>Beküldve: <?= date('Y. m. d. H:i', strtotime($comment['created_at'])) ?></span>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <span class="badge" style="background:#e3f2fd; color:#1976d2;">KOMMENT</span>
                    <strong style="margin-left: 0.5rem; color: #d32f2f;">Ok: <?= htmlspecialchars($comment['flag_reason'] ?? 'Gyanús tartalom') ?></strong>
                </div>

                <div style="background: #f8f9fa; border: 1px solid #eee; padding: 1rem; border-radius: 8px;">
                    <div style="font-size: 0.8rem; color: #666; margin-bottom: 0.5rem;">Célzott Poszt: <em style="color:#000;"><?= htmlspecialchars($comment['post_title']) ?></em></div>
                    <p style="white-space: pre-wrap; font-size: 0.95rem; margin-top: 0.5rem; border-left: 3px solid #ccc; padding-left: 0.5rem;"><?= htmlspecialchars($comment['content']) ?></p>
                </div>

                <div class="report-actions">
                    <form method="POST" style="flex: 1;" onsubmit="return confirm('Biztosan törlöd ezt a kommentet?');">
                        <input type="hidden" name="item_id" value="<?= $comment['id'] ?>">
                        <input type="hidden" name="item_type" value="comment">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="btn" style="width: 100%; background-color: #ea4335; color: white; border: none; padding: 0.8rem; border-radius: 8px; cursor: pointer;">Törlés</button>
                    </form>

                    <form method="POST" style="flex: 1;">
                        <input type="hidden" name="item_id" value="<?= $comment['id'] ?>">
                        <input type="hidden" name="item_type" value="comment">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="btn" style="width: 100%; background-color: #34a853; color: white; border: none; padding: 0.8rem; border-radius: 8px; cursor: pointer;">Engedélyezés</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
