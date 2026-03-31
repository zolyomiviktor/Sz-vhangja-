<?php
// event_view.php - Esemény részletei
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$event_id = (int) ($_GET['id'] ?? 0);

if ($event_id === 0) {
    header("Location: events.php");
    exit;
}

// Esemény adatok
$stmt = $pdo->prepare("SELECT e.*, u.nickname as creator_name,
                       (SELECT COUNT(*) FROM event_participants WHERE event_id = e.id AND status = 'going') as going_count,
                       (SELECT status FROM event_participants WHERE event_id = e.id AND user_id = ?) as user_status
                       FROM events e
                       JOIN users u ON e.creator_id = u.id
                       WHERE e.id = ?");
$stmt->execute([$user_id, $event_id]);
$event = $stmt->fetch();

if (!$event) {
    header("Location: events.php");
    exit;
}

// Résztvevők
$stmt = $pdo->prepare("SELECT ep.*, u.nickname, u.profile_image
                       FROM event_participants ep
                       JOIN users u ON ep.user_id = u.id
                       WHERE ep.event_id = ? AND ep.status = 'going'
                       ORDER BY ep.joined_at ASC");
$stmt->execute([$event_id]);
$participants = $stmt->fetchAll();

$success = $_GET['success'] ?? '';
?>

<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= htmlspecialchars($event['name']) ?> - Szívhangja
    </title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>

<body>
    <?php include 'header.php'; ?>

    <main id="main-content" role="main">
        <div class="container" style="max-width: 1000px; margin: 0 auto; padding: 2rem;">
            <nav aria-label="Breadcrumb" style="margin-bottom: 1rem;">
                <a href="events.php" style="color: var(--primary-color);">← Vissza az eseményekhez</a>
            </nav>

            <?php if ($success): ?>
                <div role="alert"
                    style="background: #e8f5e9; color: #2e7d32; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 1fr 300px; gap: 2rem;">
                <!-- Fő tartalom -->
                <div>
                    <div class="card">
                        <div style="display: flex; gap: 2rem; align-items: start; margin-bottom: 2rem;">
                            <div
                                style="text-align: center; min-width: 100px; padding: 1rem; background: var(--primary-color); color: white; border-radius: 12px;">
                                <div style="font-size: 2.5rem; font-weight: bold; line-height: 1;">
                                    <?= date('d', strtotime($event['event_date'])) ?>
                                </div>
                                <div style="font-size: 1rem; margin-top: 0.25rem;">
                                    <?= date('M Y', strtotime($event['event_date'])) ?>
                                </div>
                                <div style="font-size: 0.9rem; margin-top: 0.5rem;">
                                    <?= date('H:i', strtotime($event['event_date'])) ?>
                                </div>
                            </div>
                            <div style="flex: 1;">
                                <h1 style="margin: 0 0 1rem 0;">
                                    <?= htmlspecialchars($event['name']) ?>
                                </h1>
                                <p style="color: var(--text-muted); margin: 0 0 0.5rem 0;">
                                    <span class="badge"
                                        style="background: <?= $event['type'] === 'online' ? '#2196f3' : '#4caf50' ?>; color: white; padding: 0.3rem 0.7rem; border-radius: 6px;">
                                        <?= $event['type'] === 'online' ? '💻 Online' : '📍 Személyes' ?>
                                    </span>
                                </p>
                                <?php if ($event['location']): ?>
                                    <p style="color: var(--text-muted); margin: 0;">
                                        📍
                                        <?= htmlspecialchars($event['location']) ?>
                                    </p>
                                <?php endif; ?>
                                <p style="color: var(--text-muted); margin: 0.5rem 0 0 0; font-size: 0.9rem;">
                                    Szervező:
                                    <?= htmlspecialchars($event['creator_name']) ?>
                                </p>
                            </div>
                        </div>

                        <?php if ($event['description']): ?>
                            <div
                                style="padding: 1.5rem; background: var(--bg-soft); border-radius: 8px; margin-bottom: 2rem;">
                                <h3 style="margin: 0 0 1rem 0;">Leírás</h3>
                                <?= nl2br(htmlspecialchars($event['description'])) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($event['accessibility_info']): ?>
                            <div
                                style="padding: 1.5rem; background: #e8f5e9; border-radius: 8px; border-left: 4px solid #4caf50;">
                                <h3 style="margin: 0 0 0.5rem 0; color: #2e7d32;">♿ Akadálymentességi információk</h3>
                                <p style="margin: 0; color: #2e7d32;">
                                    <?= nl2br(htmlspecialchars($event['accessibility_info'])) ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Oldalsáv -->
                <aside>
                    <div class="card">
                        <h3 style="margin: 0 0 1rem 0;">Részvétel</h3>

                        <?php if ($event['user_status'] === 'going'): ?>
                            <p
                                style="background: #e8f5e9; color: #2e7d32; padding: 1rem; border-radius: 8px; text-align: center; font-weight: 600; margin-bottom: 1rem;">
                                ✓ Részt veszel
                            </p>
                            <form method="POST" action="event_actions.php">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="event_id" value="<?= $event_id ?>">
                                <button type="submit" class="btn"
                                    style="width: 100%; background: var(--text-muted);">Lemondás</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="event_actions.php">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="join">
                                <input type="hidden" name="event_id" value="<?= $event_id ?>">
                                <button type="submit" class="btn" style="width: 100%;">Részt veszek</button>
                            </form>
                        <?php endif; ?>

                        <p style="text-align: center; color: var(--text-muted); margin: 1rem 0 0 0; font-size: 0.9rem;">
                            <?= $event['going_count'] ?> résztvevő
                        </p>
                    </div>

                    <!-- Résztvevők listája -->
                    <?php if (count($participants) > 0): ?>
                        <div class="card" style="margin-top: 1rem;">
                            <h3 style="margin: 0 0 1rem 0;">Résztvevők</h3>
                            <?php foreach (array_slice($participants, 0, 10) as $participant): ?>
                                <div style="display: flex; gap: 0.75rem; align-items: center; margin-bottom: 0.75rem;">
                                    <img src="<?= $participant['profile_image'] ? 'uploads/' . htmlspecialchars($participant['profile_image']) : 'assets/avatar-default.png' ?>"
                                        alt="" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">
                                    <a href="profile.php?id=<?= $participant['user_id'] ?>"
                                        style="color: var(--primary-color); text-decoration: none;">
                                        <?= htmlspecialchars($participant['nickname']) ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                            <?php if (count($participants) > 10): ?>
                                <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.5rem 0 0 0;">
                                    és még
                                    <?= count($participants) - 10 ?> résztvevő...
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </aside>
            </div>
        </div>
    </main>

    <footer role="contentinfo" style="text-align: center; padding: 2rem;">
        <p>&copy; 2024 Szívhangja</p>
    </footer>
</body>

</html>