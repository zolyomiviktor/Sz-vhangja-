<?php
// admin/admins.php
require 'auth_check.php';

if (!$is_super_admin) {
    die("Hiba: Csak Szuper Adminisztrátorok érhetik el ezt az oldalt.");
}

$stmt = $pdo->query("SELECT id, email, role, is_active, force_password_change, created_at FROM admins ORDER BY created_at DESC");
$admins = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <title>Admin Felhasználók - Szívhangja</title>
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

        .badge-inactive {
            background: #ffebee;
            color: #c62828;
        }

        .badge-warning {
            background: #fff3e0;
            color: #ef6c00;
        }
    </style>
</head>

<body>
    <header
        style="background: var(--primary-color); color: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center;">
        <div style="font-weight: bold;">Szívhangja Admin</div>
        <div><a href="index.php" style="color: white; text-decoration: none;">&larr; Dashboard</a></div>
    </header>

    <div class="container" style="max-width: 1200px; margin-top: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h1>Admin Felhasználók</h1>
            <a href="admin_create.php" class="btn">Új Admin hozzáadása</a>
        </div>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Szerepkör</th>
                    <th>Státusz</th>
                    <th>Jelszócsere</th>
                    <th>Létrehozva</th>
                    <th>Műveletek</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($admins as $a): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($a['email']) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($a['role']) ?>
                        </td>
                        <td>
                            <span class="badge badge-<?= $a['is_active'] ? 'active' : 'inactive' ?>">
                                <?= $a['is_active'] ? 'Aktív' : 'Inaktív' ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($a['force_password_change']): ?>
                                <span class="badge badge-warning">Kötelező</span>
                            <?php else: ?>
                                <span style="color: #999;">Nem</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= date('Y-m-d', strtotime($a['created_at'])) ?>
                        </td>
                        <td>
                            <a href="admin_edit.php?id=<?= $a['id'] ?>" class="btn secondary"
                                style="padding: 4px 10px; font-size: 0.8rem;">Szerkesztés</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>

</html>