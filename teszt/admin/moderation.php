<?php
require 'auth_check.php';

// Jelentés kezelése
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_id = (int) $_POST['report_id'];
    $action = $_POST['action'];
    $target_user_id = (int) ($_POST['target_user_id'] ?? 0);

    try {
        if ($action === 'dismiss') {
            $stmt = $pdo->prepare("UPDATE reports SET status = 'dismissed' WHERE id = ?");
            $stmt->execute([$report_id]);
            $msg = "Jelentés elutasítva.";
        } elseif ($action === 'ban') {
            if ($target_user_id <= 0)
                throw new Exception("Nem sikerült azonosítani a tiltandó felhasználót.");

            // 1. Felhasználó tiltása
            $stmt = $pdo->prepare("UPDATE users SET status = 'banned' WHERE id = ?");
            $stmt->execute([$target_user_id]);

            // 2. Jelentés lezárása
            $stmt = $pdo->prepare("UPDATE reports SET status = 'resolved' WHERE id = ?");
            $stmt->execute([$report_id]);

            // 3. Logolás
            $stmt_log = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?)");
            $stmt_log->execute([$_SESSION['user_id'], 'ban_user_report', $target_user_id, "Jelentés alapján tiltva (Report ID: $report_id)", $_SERVER['REMOTE_ADDR']]);

            $msg = "Felhasználó kitiltva és jelentés lezárva.";
        } elseif ($action === 'resolve') {
            $stmt = $pdo->prepare("UPDATE reports SET status = 'resolved' WHERE id = ?");
            $stmt->execute([$report_id]);
            $msg = "Jelentés megjelölve megoldottként.";
        }
    } catch (Exception $e) {
        $error = "Hiba: " . $e->getMessage();
    }
}

// Jelentések lekérése
$stmt = $pdo->prepare("
    SELECT r.*, u_reporter.nickname as reporter_name
    FROM reports r 
    JOIN users u_reporter ON r.reporter_id = u_reporter.id
    WHERE r.status = 'pending'
    ORDER BY r.created_at ASC
");
$stmt->execute();
$reports_raw = $stmt->fetchAll();

$reports = [];
foreach ($reports_raw as $r) {
    $r['target_url'] = '#';
    $r['target_user'] = null;
    $r['target_content_preview'] = 'Nincs adat';

    // Tartalom típus szerinti részletek lekérése
    switch ($r['content_type']) {
        case 'user':
            $stmt = $pdo->prepare("SELECT id, nickname, email FROM users WHERE id = ?");
            $stmt->execute([$r['content_id']]);
            $target = $stmt->fetch();
            if ($target) {
                $r['target_user'] = $target;
                $r['target_url'] = "user_view.php?id=" . $target['id'];
                $r['target_content_preview'] = "Profil: " . $target['nickname'];
            }
            break;

        case 'forum_topic':
            $stmt = $pdo->prepare("SELECT ft.id, ft.title, ft.content, u.id as user_id, u.nickname FROM forum_topics ft JOIN users u ON ft.user_id = u.id WHERE ft.id = ?");
            $stmt->execute([$r['content_id']]);
            $target = $stmt->fetch();
            if ($target) {
                $r['target_user'] = ['id' => $target['user_id'], 'nickname' => $target['nickname']];
                $r['target_url'] = "../topic_view.php?id=" . $target['id'];
                $r['target_content_preview'] = "Fórum téma: " . $target['title'];
            }
            break;

        case 'forum_reply':
            $stmt = $pdo->prepare("SELECT fr.id, fr.content, fr.topic_id, u.id as user_id, u.nickname FROM forum_replies fr JOIN users u ON fr.user_id = u.id WHERE fr.id = ?");
            $stmt->execute([$r['content_id']]);
            $target = $stmt->fetch();
            if ($target) {
                $r['target_user'] = ['id' => $target['user_id'], 'nickname' => $target['nickname']];
                $r['target_url'] = "../topic_view.php?id=" . $target['topic_id'];
                $r['target_content_preview'] = "Fórum válasz: " . mb_substr($target['content'], 0, 50) . "...";
            }
            break;

        case 'group_post':
            $stmt = $pdo->prepare("SELECT gp.id, gp.content, gp.group_id, u.id as user_id, u.nickname FROM group_posts gp JOIN users u ON gp.user_id = u.id WHERE gp.id = ?");
            $stmt->execute([$r['content_id']]);
            $target = $stmt->fetch();
            if ($target) {
                $r['target_user'] = ['id' => $target['user_id'], 'nickname' => $target['nickname']];
                $r['target_url'] = "../group_view.php?id=" . $target['group_id'];
                $r['target_content_preview'] = "Csoport bejegyzés: " . mb_substr($target['content'], 0, 50) . "...";
            }
            break;

        case 'event':
            $stmt = $pdo->prepare("SELECT e.id, e.name, u.id as user_id, u.nickname FROM events e JOIN users u ON e.creator_id = u.id WHERE e.id = ?");
            $stmt->execute([$r['content_id']]);
            $target = $stmt->fetch();
            if ($target) {
                $r['target_user'] = ['id' => $target['user_id'], 'nickname' => $target['nickname']];
                $r['target_url'] = "../event_view.php?id=" . $target['id'];
                $r['target_content_preview'] = "Esemény: " . $target['name'];
            }
            break;
    }
    $reports[] = $r;
}

?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <title>Moderáció - Admin</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .report-card {
            background: white;
            border: 1px solid #ddd;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 5px solid #ea4335;
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            color: #666;
            font-size: 0.9rem;
        }

        .report-reason {
            font-weight: bold;
            font-size: 1.1rem;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .report-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }

        .badge {
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
        }

        .type-badge {
            background: #e3f2fd;
            color: #1976d2;
        }
    </style>
</head>

<body>
    <header
        style="background: var(--primary-color); color: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center;">
        <div style="font-weight: bold; font-size: 1.2rem;">Szívhangja Admin - Moderáció</div>
        <div>
            Szia, <?= htmlspecialchars($admin_user['nickname']) ?>!
            <a href="index.php" style="color: white; margin-left:1rem; text-decoration: underline;">Vissza a pultra</a>
        </div>
    </header>

    <div class="container" style="max-width: 1000px; margin-top: 2rem;">
        <h1>Moderációs Sor</h1>

        <?php if ($msg): ?>
            <div class="alert"
                style="background: #e8f5e9; color: #2e7d32; padding: 1rem; margin-bottom: 1.5rem; border-radius: 4px; border: 1px solid #a5d6a7;">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert"
                style="background: #ffebee; color: #c62828; padding: 1rem; margin-bottom: 1.5rem; border-radius: 4px; border: 1px solid #ef9a9a;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (count($reports) === 0): ?>
            <div class="card" style="text-align: center; padding: 4rem 2rem;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">🎉</div>
                <h3>Nincs függőben lévő jelentés!</h3>
                <p style="color: var(--text-muted);">A közösség jelenleg békés.</p>
            </div>
        <?php else: ?>
            <?php foreach ($reports as $r): ?>
                <div class="report-card">
                    <div class="report-header">
                        <span>Jelentő: <strong><?= htmlspecialchars($r['reporter_name']) ?></strong></span>
                        <span>Dátum: <?= date('Y. m. d. H:i', strtotime($r['created_at'])) ?></span>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <span class="badge type-badge"><?= $r['content_type'] ?></span>
                    </div>

                    <div class="report-reason">Ok: <?= htmlspecialchars($r['reason']) ?></div>

                    <div
                        style="background: #f8f9fa; border: 1px solid #eee; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
                        <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.5rem;">Jelentett tartalom:</div>
                        <div style="font-weight: 600; margin-bottom: 0.5rem;">
                            <a href="<?= htmlspecialchars($r['target_url']) ?>" target="_blank"
                                style="color: var(--primary-color);">
                                <?= htmlspecialchars($r['target_content_preview']) ?>
                            </a>
                        </div>
                        <?php if ($r['target_user']): ?>
                            <div style="font-size: 0.9rem;">
                                Szerző: <strong><?= htmlspecialchars($r['target_user']['nickname']) ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="report-actions">
                        <form method="POST" style="flex: 1;">
                            <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                            <input type="hidden" name="action" value="dismiss">
                            <button type="submit" class="btn secondary" style="width: 100%;">Elutasít</button>
                        </form>

                        <form method="POST" style="flex: 1;">
                            <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                            <input type="hidden" name="action" value="resolve">
                            <button type="submit" class="btn"
                                style="width: 100%; background-color: #fbbc04; color: black; border: none;">Megoldva</button>
                        </form>

                        <?php if ($r['target_user']): ?>
                            <form method="POST" style="flex: 1;" onsubmit="return confirm('Biztosan kitiltod a felhasználót?');">
                                <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                                <input type="hidden" name="target_user_id" value="<?= $r['target_user']['id'] ?>">
                                <input type="hidden" name="action" value="ban">
                                <button type="submit" class="btn"
                                    style="width: 100%; background-color: #ea4335; border: none;">Kitiltás (Ban)</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>

</html>