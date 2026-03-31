<?php
require 'auth_check.php';

// 1. Error Reporting (Dev Mode)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'auth_check.php';

// 2. Input Validation & Defaults
$search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$raw_status = $_GET['status'] ?? 'all';
$valid_statuses = ['active', 'pending', 'banned', 'approved', 'rejected'];
$status_filter = in_array($raw_status, $valid_statuses) ? $raw_status : 'all';

// Pagination placeholders (can be expanded later)
$limit = 50;
$offset = 0;

$users = [];
$error_message = '';

try {
    // 4. SQL Construction
    $query = "SELECT id, nickname, last_name, email, status, role, created_at, ip_address FROM users WHERE 1=1";
    $params = [];

    if ($search) {
        $query .= " AND (nickname LIKE ? OR email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($status_filter !== 'all') {
        $query .= " AND status = ?";
        $params[] = $status_filter;
    }

    // Explicit ORDER BY and LIMIT
    $query .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

} catch (PDOException $e) {
    // 5. Graceful Fallback
    $error_message = "Felhasználók nem tölthetők be: " . htmlspecialchars($e->getMessage());
    // Log error internally if logging system exists
    // error_log($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <title>Felhasználók Kezelése - Admin</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .admin-table th,
        .admin-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .admin-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #555;
        }

        .admin-table tr:hover {
            background-color: #f1f3f4;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .badge-active {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-banned {
            background: #ffebee;
            color: #c62828;
        }

        .badge-pending {
            background: #fff3e0;
            color: #ef6c00;
        }

        .filter-bar {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>

<body>

    <header
        style="background: var(--primary-color); color: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center;">
        <div style="font-weight: bold;">Szívhangja Admin</div>
        <div><a href="index.php" style="color: white; text-decoration: none;">&larr; Vissza a Dashboardra</a></div>
    </header>

    <div class="container" style="max-width: 1400px; margin-top: 2rem;">
        <h1>Felhasználók Kezelése</h1>

        <?php if ($error_message): ?>
            <div role="alert"
                style="background: #ffebee; color: #c62828; padding: 1rem; margin-bottom: 2rem; border-radius: 8px; border: 1px solid #ffcdd2;">
                <strong>Hiba:</strong> <?= $error_message ?>
            </div>
        <?php endif; ?>

        <form class="filter-bar" method="GET">
            <input type="text" name="search" placeholder="Név vagy Email keresése..."
                value="<?= htmlspecialchars($search) ?>"
                style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; flex: 1; min-width: 200px;">

            <select name="status" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Minden státusz</option>
                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Aktív (active)</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Várakozó (pending)</option>
                <option value="banned" <?= $status_filter === 'banned' ? 'selected' : '' ?>>Tiltott (banned)</option>
            </select>

            <button type="submit" class="btn" style="min-height: auto; padding: 0.5rem 1.5rem;">Szűrés</button>
        </form>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Felhasználó</th>
                    <th>Email</th>
                    <th>Státusz</th>
                    <th>Szerepkör</th>
                    <th>Regisztrált</th>
                    <th>Műveletek</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td>#<?= $u['id'] ?></td>
                        <td>
                            <div style="font-weight: bold; font-size: 1.1em; color: var(--primary-color);">
                                <?= htmlspecialchars($u['last_name']) ?>
                            </div>
                            <div style="font-weight: bold; margin-bottom: 2px;">@<?= htmlspecialchars($u['nickname']) ?>
                            </div>
                            <small style="color: #999;">IP: <?= htmlspecialchars($u['ip_address'] ?? 'N/A') ?></small>
                        </td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <span
                                class="badge badge-<?= $u['status'] === 'active' || $u['status'] === 'approved' ? 'active' : ($u['status'] === 'banned' ? 'banned' : 'pending') ?>">
                                <?= htmlspecialchars($u['status']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($u['role']) ?></td>
                        <td><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
                        <td>
                            <a href="user_view.php?id=<?= $u['id'] ?>" class="btn secondary"
                                style="min-height: auto; padding: 4px 10px; font-size: 0.8rem;">Kezelés</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (count($users) === 0): ?>
            <p style="text-align: center; margin-top: 2rem; color: #666;">Nincs a feltételeknek megfelelő felhasználó.</p>
        <?php endif; ?>
    </div>

</body>

</html>