<?php
require 'auth_check.php';

$user_id = $_GET['id'] ?? 0;

if (!$user_id) {
    die("Érvénytelen felhasználó ID.");
}

// Műveletek kezelése (Ban, Unban)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $reason = $_POST['reason'] ?? '';

    if ($action === 'ban') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'banned' WHERE id = ?");
        $stmt->execute([$user_id]);

        // Logolás
        $stmt_log = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt_log->execute([$_SESSION['admin_id'], 'ban_user', $user_id, "Ok: $reason", $_SERVER['REMOTE_ADDR']]);

        $success_msg = "Felhasználó kitiltva.";
    } elseif ($action === 'activate') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $stmt->execute([$user_id]);

        $stmt_log = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt_log->execute([$_SESSION['admin_id'], 'activate_user', $user_id, "Kézi aktiválás", $_SERVER['REMOTE_ADDR']]);

        $success_msg = "Felhasználó aktiválva.";
    } elseif ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ?");
        $stmt->execute([$user_id]);

        $stmt_log = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt_log->execute([$_SESSION['admin_id'], 'approve_user', $user_id, "Regisztráció jóváhagyva", $_SERVER['REMOTE_ADDR']]);

        // Opcionális: Email küldés a felhasználónak
        $success_msg = "Regisztráció jóváhagyva!";
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$user_id]);

        $stmt_log = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt_log->execute([$_SESSION['admin_id'], 'reject_user', $user_id, "Regisztráció elutasítva: $reason", $_SERVER['REMOTE_ADDR']]);

        $success_msg = "Regisztráció elutasítva.";
    }
}

// User adatok lekérése
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("Felhasználó nem található.");
}

// Automatikus Jelentés lekérése
$stmt_verify = $pdo->prepare("SELECT * FROM verification_reports WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt_verify->execute([$user_id]);
$verify_report = $stmt_verify->fetch();
?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <title>Adatlap: <?= htmlspecialchars($user['nickname']) ?> - Admin</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            margin-top: 2rem;
        }

        .info-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            border: 1px solid #ddd;
        }

        .action-panel {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 1rem;
            border: 1px solid #eee;
        }

        /* Verification Report Styles */
        .verify-report-card {
            background: #fff;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
            border-left: 5px solid #ccc;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .verify-report-card.risk-low {
            border-left-color: #4caf50;
        }

        .verify-report-card.risk-medium {
            border-left-color: #ff9800;
        }

        .verify-report-card.risk-high {
            border-left-color: #f44336;
        }

        .verify-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 0.5rem;
        }

        .verify-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.85rem;
            text-transform: uppercase;
        }

        .verify-badge-low {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .verify-badge-medium {
            background: #fff3e0;
            color: #e65100;
        }

        .verify-badge-high {
            background: #ffebee;
            color: #c62828;
        }

        .verify-section-title {
            font-size: 0.9rem;
            font-weight: bold;
            color: #555;
            margin-top: 1rem;
            margin-bottom: 0.3rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .verify-content {
            font-size: 0.95rem;
            color: #333;
            line-height: 1.5;
            background: #fcfcfc;
            padding: 0.8rem;
            border-radius: 6px;
            border: 1px solid #f0f0f0;
        }
    </style>
</head>

<body>

    <header style="background: var(--primary-color); color: white; padding: 1rem 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div style="font-weight: bold;">Adatlap Kezelése</div>
            <a href="users.php" style="color: white; text-decoration: none;">&larr; Vissza a listához</a>
        </div>
    </header>

    <div class="container" style="max-width: 1200px;">

        <?php if (isset($success_msg)): ?>
            <div role="alert"
                style="background: #e8f5e9; color: #2e7d32; padding: 1rem; margin-top: 1rem; border-radius: 4px;">
                <?= htmlspecialchars($success_msg) ?>
            </div>
        <?php endif; ?>

        <div class="detail-grid">

            <!-- Bal oldal: Fő infók -->
            <div>
                <div class="info-card" style="text-align: center;">
                    <?php if ($user['profile_image']): ?>
                        <img src="../uploads/<?= htmlspecialchars($user['profile_image']) ?>"
                            style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 3px solid #eee;">
                    <?php endif; ?>
                    <h2 style="margin: 1rem 0 0.2rem;">
                        <?= htmlspecialchars($user['last_name']) ?>
                    </h2>
                    <div
                        style="color: var(--primary-color); font-weight: bold; margin-bottom: 0.5rem; font-size: 1.1em;">
                        @<?= htmlspecialchars($user['nickname']) ?>
                    </div>
                    <p style="color: #666; margin-top: 0;"><?= htmlspecialchars($user['email']) ?></p>

                    <div style="margin-top: 1rem;">
                        <span style="padding: 4px 10px; border-radius: 12px; background: #eee; font-weight: bold;">
                            <?= htmlspecialchars($user['status']) ?>
                        </span>
                        <span
                            style="padding: 4px 10px; border-radius: 12px; background: #e3f2fd; color: #0d47a1; font-weight: bold;">
                            <?= htmlspecialchars($user['role']) ?>
                        </span>
                    </div>

                    <p style="margin-top: 1rem; font-size: 0.9rem; color: #888;">
                        Regisztrált: <?= $user['created_at'] ?><br>
                        IP: <?= htmlspecialchars($user['ip_address'] ?? '-') ?>
                    </p>
                </div>

                <div class="action-panel">
                    <h3>Műveletek</h3>

                    <?php if ($user['status'] === 'pending'): ?>
                        <div style="margin-bottom: 2rem; border-bottom: 1px solid #ddd; padding-bottom: 1rem;">
                            <h4 style="margin-top: 0; color: #ef6c00;">Várakozó regisztráció</h4>
                            <div style="display: flex; gap: 10px;">
                                <form method="POST" style="flex: 1;">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn"
                                        style="background: #2e7d32; width: 100%;">Jóváhagyás</button>
                                </form>
                                <form method="POST" style="flex: 1;" onsubmit="return confirm('Biztosan elutasítod?');">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="reason" value="Admin döntés">
                                    <button type="submit" class="btn"
                                        style="background: #c62828; width: 100%;">Elutasítás</button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($user['status'] !== 'banned'): ?>
                        <form method="POST" onsubmit="return confirm('Biztosan kitiltod ezt a felhasználót?');">
                            <input type="hidden" name="action" value="ban">
                            <label style="display: block; margin-bottom: 5px;">Kitiltás oka:</label>
                            <input type="text" name="reason" required placeholder="Pl. Szabálysértő tartalom"
                                style="width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px;">
                            <button type="submit" class="btn" style="background: #424242; width: 100%;">Felhasználó
                                Kitiltása</button>
                        </form>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="activate">
                            <button type="submit" class="btn" style="background: #2e7d32; width: 100%;">Kitiltás Feloldása
                                (Aktiválás)</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Jobb oldal: Részletek -->
            <div>
                <div class="info-card">
                    <h3>Bemutatkozás</h3>
                    <p style="white-space: pre-line; background: #f9f9f9; padding: 1rem; border-radius: 4px;">
                        <?= htmlspecialchars($user['bio'] ?: 'Nincs megadva.') ?>
                    </p>
                </div>

                <!-- Ide jöhetnének a logok, jelentések, üzenetek ha lennének -->
                <div class="info-card" style="margin-top: 1rem;">
                    <h3>Legutóbbi Jelentések (Róla)</h3>
                    <p style="color: #888; font-style: italic;">(Még nincsenek jelentések ehhez a felhasználóhoz)</p>
                </div>

                <?php if ($verify_report): ?>
                    <div class="verify-report-card risk-<?= htmlspecialchars($verify_report['risk_level']) ?>">
                        <div class="verify-header">
                            <h3 style="margin:0;">📊 Automatikus Ellenőrzés</h3>
                            <span class="verify-badge verify-badge-<?= htmlspecialchars($verify_report['risk_level']) ?>">
                                <?= $verify_report['risk_level'] === 'low' ? '🟢 Alacsony kockázat' :
                                    ($verify_report['risk_level'] === 'medium' ? '🟡 Közepes kockázat' : '🔴 Magas kockázat') ?>
                            </span>
                        </div>

                        <div class="verify-section-title">📝 Rövid összefoglaló</div>
                        <div class="verify-content">
                            <?= htmlspecialchars($verify_report['justification']) ?>
                        </div>

                        <?php if ($verify_report['warnings']): ?>
                            <div class="verify-section-title">⚠️ Észlelt gyanús jelek</div>
                            <div class="verify-content" style="color: #c62828; font-weight: 500;">
                                <?= htmlspecialchars($verify_report['warnings']) ?>
                            </div>
                        <?php endif; ?>

                        <div class="verify-section-title">✅ Ajánlás</div>
                        <div class="verify-content" style="border: 1px solid #ccc; background: #eee; font-weight: bold;">
                            <?php
                            $rec = $verify_report['recommendation'];
                            if ($rec === 'approve')
                                echo '"Jóváhagyható"';
                            elseif ($rec === 'manual_check')
                                echo '"Ellenőrzés javasolt"';
                            else
                                echo '"Elutasítás javasolt"';
                            ?>
                        </div>

                        <p style="font-size: 0.75rem; color: #999; margin-top: 1rem; font-style: italic;">
                            Ez egy automatikus elemzés a megadott adatok alapján. Készült:
                            <?= $verify_report['created_at'] ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="verify-report-card" style="border-left-style: dashed;">
                        <p style="color: #888; text-align: center; margin: 0;">Nincs elérhető automatikus elemzés ehhez a
                            felhasználóhoz.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

</body>

</html>