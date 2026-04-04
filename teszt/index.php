<?php
require 'db.php';
session_start();

/* ================== KONFIG ================== */
/* Állítsd be a configban vagy itt */
if (!defined('APP_ENV')) {
    define('APP_ENV', 'development'); // production | development
}

/* ================== JOGOSULTSÁG ================== */
$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$isDevEnv = APP_ENV === 'development';

/* ================== CSRF TOKEN ================== */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ================== MIGRÁCIÓ ================== */
$update_msg = '';
$update_ok = false;

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['run_notif_update']) &&
    hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']) &&
    $isAdmin &&
    $isDevEnv
) {
    try {
        $pdo->beginTransaction();

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                type ENUM('profile_view', 'message', 'approval', 'deletion') NOT NULL,
                message TEXT NOT NULL,
                link VARCHAR(255),
                is_read BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_notifications_user
                    FOREIGN KEY (user_id)
                    REFERENCES users(id)
                    ON DELETE CASCADE
            ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE INDEX IF NOT EXISTS idx_notifications_user_id
            ON notifications(user_id)
        ");

        $pdo->commit();

        $update_ok = true;
        $update_msg = "Az értesítések adatbázis frissítése sikeresen lefutott.";

    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('[NOTIFICATION MIGRATION] ' . $e->getMessage());
        $update_msg = "Az adatbázis frissítése nem sikerült.";
    }
}
?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <title>Szívhangja – Akadálymentes társkeresés</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>

<body>

    <?php include 'header.php'; ?>

    <div class="hero">
        <div class="hero-icon" role="img" aria-label="Szívhangja logó szív ikon">
            <svg width="60" height="60" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5
                     2 5.42 4.42 3 7.5 3c1.74 0 3.41.81
                     4.5 2.09C13.09 3.81 14.76 3
                     16.5 3 19.58 3 22 5.42
                     22 8.5c0 3.78-3.4 6.86-8.55
                     11.54L12 21.35z" />
            </svg>
        </div>

        <p class="hero-title" aria-hidden="true">Szívhangja</p>

        <p style="font-size:1.2rem; max-width:600px;">
            Akadálymentes társkeresés mindenkinek.
        </p>

        <?php if ($isLoggedIn): ?>
            <a href="browse.php" class="btn">Böngészés</a>
        <?php else: ?>
            <div class="hero-actions flex-center gap-1">
                <a href="register.php" class="btn">Csatlakozz most</a>
                <a href="login.php" class="btn secondary">Belépés</a>
            </div>
        <?php endif; ?>
    </div>

    <main id="main-content">

        <?php if ($update_msg): ?>
            <div role="alert" class="alert" style="background: <?= $update_ok ? '#e8f5e9' : '#ffebee' ?>;
                    color: <?= $update_ok ? '#2e7d32' : '#c62828' ?>;
                    border:1px solid <?= $update_ok ? '#a5d6a7' : '#ef9a9a' ?>;">
                <?= htmlspecialchars($update_msg) ?>
            </div>
        <?php endif; ?>

        <?php if ($isAdmin && $isDevEnv): ?>
            <section class="card">
                <h2>Admin – Karbantartás</h2>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <button type="submit" name="run_notif_update" class="btn">
                        🔧 Értesítések tábla frissítése
                    </button>
                </form>
                <p style="font-size:0.9rem;color:#666;margin-top:1rem;">
                    Csak fejlesztői környezetben és adminnak látható.
                </p>
            </section>
        <?php endif; ?>

        <section class="card text-center">
            <h2>Miért a Szívhangja?</h2>
            <p>
                Biztonságos és barátságos közösség mozgássérült emberek számára.
            </p>
        </section>

    </main>

    <?php include 'footer.php'; ?>

</body>

</html>