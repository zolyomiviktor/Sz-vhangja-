<?php
require 'auth_check.php';

// Quick Stats
$stats = [
    'users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'reports' => $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn(),
    'pending_approvals' => $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn(),
];

// Fetch Unread Notifications
$notifications = $pdo->query("SELECT * FROM admin_notifications WHERE is_read = 0 ORDER BY created_at DESC LIMIT 5")->fetchAll();

?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Szívhangja</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .admin-grid {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 2rem;
        }

        .sidebar {
            background: #fff;
            padding: 1.5rem;
            border-radius: 8px;
            height: calc(100vh - 100px);
            border: 1px solid #ddd;
        }

        .sidebar a {
            display: block;
            padding: 0.8rem;
            color: #333;
            text-decoration: none;
            border-radius: 4px;
            margin-bottom: 0.5rem;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #f0f0f0;
            color: var(--primary-color);
            font-weight: bold;
        }

        .stat-card {
            background: #fff;
            padding: 1.5rem;
            border-radius: 8px;
            border: 1px solid #ddd;
            text-align: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
    </style>
</head>

<body>

    <header
        style="background: var(--primary-color); color: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center;">
        <div style="font-weight: bold; font-size: 1.2rem;">Szívhangja Admin</div>
        <div>
            Szia, <?= htmlspecialchars($admin_user['nickname']) ?>! (<?= $admin_user['role'] ?>)
            <a href="../logout.php"
                style="color: white; margin-left: 1rem; text-decoration: underline;">Kijelentkezés</a>
        </div>
    </header>

    <div class="container" style="max-width: 1400px; margin-top: 2rem;">
        <div class="admin-grid">
            <aside class="sidebar">
                <a href="index.php" class="active">Áttekintés</a>
                <a href="users.php">Felhasználók kezelése</a>
                <?php if ($is_super_admin): ?>
                    <a href="admins.php">Admin felhasználók</a>
                <?php endif; ?>

                <a href="moderation.php">Moderáció
                    <?php if ($stats['reports'] > 0)
                        echo '(' . $stats['reports'] . ')'; ?></a>
                <a href="logs.php">Naplók (Logs)</a>
                <hr style="margin: 1rem 0; border: 0; border-top: 1px solid #eee;">
                <a href="../index.php">Vissza az oldalra</a>
            </aside>

            <main>
                <h1>Vezérlőpult</h1>

                <div class="grid-responsive"
                    style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 2rem;">

                    <!-- Notifications Panel -->
                    <?php if (!empty($notifications)): ?>
                        <div style="grid-column: 1 / -1; margin-bottom: 1rem;">
                            <div class="card">
                                <h3>Értesítések</h3>
                                <ul style="list-style: none; padding: 0;">
                                    <?php foreach ($notifications as $notif): ?>
                                        <li
                                            style="padding: 10px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background-color: #fff3cd; border-left: 4px solid #ffc107; margin-bottom: 5px; border-radius: 4px;">
                                            <div>
                                                <strong><?= htmlspecialchars($notif['type']) === 'registration' ? 'Új regisztráció' : 'Értesítés' ?></strong>:
                                                <?= htmlspecialchars($notif['message']) ?>
                                                <br>
                                                <small style="color: #666;"><?= $notif['created_at'] ?></small>
                                                <?php if ($notif['link']): ?>
                                                    <a href="<?= htmlspecialchars($notif['link']) ?>"
                                                        style="margin-left: 10px; color: var(--primary-color);">Megtekintés</a>
                                                <?php endif; ?>
                                            </div>
                                            <a href="mark_notification_read.php?id=<?= $notif['id'] ?>" class="btn secondary"
                                                style="font-size: 0.8rem; padding: 5px 10px;">Olvasottnak jelöl</a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['users'] ?></div>
                        <div class="stat-label">Összes Felhasználó</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" style="color: #ea4335;"><?= $stats['reports'] ?></div>
                        <div class="stat-label">Függő Jelentés</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" style="color: #fbbc04;"><?= $stats['pending_approvals'] ?></div>
                        <div class="stat-label">Várakozó Regisztráció</div>
                    </div>
                </div>

                <div class="card">
                    <h3>Gyorsműveletek</h3>
                    <div class="flex-gap">
                        <a href="users.php?status=pending" class="btn">Regisztrációk elbírálása</a>
                        <a href="moderation.php" class="btn secondary">Jelentések megtekintése</a>
                    </div>
                </div>
            </main>
        </div>
    </div>

</body>

</html>