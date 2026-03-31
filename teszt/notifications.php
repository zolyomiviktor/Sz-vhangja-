<?php
// notifications.php - Értesítések listája
require 'db.php';
require 'notification_helper.php';

// Csak bejelentkezve
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Olvasottnak jelölés (ha kérték)
if (isset($_POST['mark_all_read'])) {
    // CSRF ellenőrzése
    if (isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$user_id]);
        header("Location: notifications.php");
        exit;
    }
}

// Egyedi olvasottnak jelölés és átirányítás
if (isset($_GET['mark_read'])) {
    $notif_id = (int) $_GET['mark_read'];

    // Ellenőrizzük, hogy a miénk-e és lekérjük a linket
    $stmt = $pdo->prepare("SELECT link FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->execute([$notif_id, $user_id]);
    $notif = $stmt->fetch();

    if ($notif) {
        // Olvasottnak jelölés
        $update = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $update->execute([$notif_id]);

        // Átirányítás a célra (vagy vissza a listára ha nincs link)
        $destination = $notif['link'] ? $notif['link'] : 'notifications.php';
        header("Location: " . $destination);
        exit;
    }
}

// Értesítések lekérése (legfrissebb elöl)
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Értesítések - Szívhangja</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>

<body>

    <?php include 'header.php'; ?>

    <main id="main-content" role="main">
        <div class="flex-between mb-2">
            <h1>Értesítéseim</h1>

            <?php if (count($notifications) > 0 && get_unread_count($user_id) > 0): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="mark_all_read" value="1">
                    <button type="submit" class="btn secondary" style="font-size: 0.9rem; padding: 0.5rem 1rem;">Összes
                        olvasottnak jelölése</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (count($notifications) > 0): ?>
            <ul style="list-style: none; padding: 0;" role="list">
                <?php foreach ($notifications as $notif): ?>
                    <li>
                        <?php
                        // Ikon kiválasztása típus alapján
                        $icon = 'ℹ️';
                        $icon_label = 'Információ';
                        if ($notif['type'] == 'message') {
                            $icon = '✉️';
                            $icon_label = 'Üzenet';
                        }
                        if ($notif['type'] == 'profile_view') {
                            $icon = '👀';
                            $icon_label = 'Profil megtekintés';
                        }
                        if ($notif['type'] == 'approval') {
                            $icon = '✅';
                            $icon_label = 'Jóváhagyás';
                        }
                        if ($notif['type'] == 'deletion') {
                            $icon = '🗑️';
                            $icon_label = 'Törlés';
                        }

                        // Stílusok meghatározása állapot alapján
                        $bg_style = $notif['is_read'] ? 'background: #fff;' : 'background: #e3f2fd; border-left: 4px solid #2196f3;';
                        $aria_status = $notif['is_read'] ? 'Olvasott értesítés' : 'Olvasatlan értesítés';
                        ?>

                        <?php if ($notif['link']): ?>
                            <a href="notifications.php?mark_read=<?= $notif['id'] ?>"
                                style="text-decoration: none; color: inherit; display: block; margin-bottom: 1rem;">
                            <?php else: ?>
                                <div style="margin-bottom: 1rem;">
                                <?php endif; ?>

                                <div class="card notification-card"
                                    style="padding: 1.5rem; margin: 0; display: flex; align-items: flex-start; gap: 1rem; <?= $bg_style ?>">
                                    <div style="font-size: 1.5rem;" role="img" aria-label="<?= $icon_label ?>:">
                                        <?= $icon ?>
                                    </div>
                                    <div>
                                        <div class="sr-only"><?= $aria_status ?></div>
                                        <span class="meta">
                                            <?= date('Y.m.d H:i', strtotime($notif['created_at'])) ?>
                                        </span>
                                        <p class="text" style="font-weight: <?= $notif['is_read'] ? '400' : '600' ?>;">
                                            <?= htmlspecialchars($notif['message']) ?>
                                        </p>
                                    </div>
                                </div>

                                <?php if ($notif['link']): ?>
                            </a>
                        <?php else: ?>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="empty-state">
                <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <h3>Nincsenek értesítéseid</h3>
                <p>Minden csendes. Nézz vissza később!</p>
                <a href="browse.php" class="btn">Böngészés</a>
            </div>
        <?php endif; ?>

    </main>

    <?php include 'footer.php'; ?>

</body>

</html>