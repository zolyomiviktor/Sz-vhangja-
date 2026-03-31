<?php
// login.php - Bejelentkezés
require 'db.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $ip = $_SERVER['REMOTE_ADDR'];
    $limit = 5;
    $minutes = 15;

    // 1. Rate Limit Ellenőrzés
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempt_time > (NOW() - INTERVAL ? MINUTE)");
    $stmt_check->execute([$ip, $minutes]);
    $attempts = $stmt_check->fetchColumn();

    if ($attempts >= $limit) {
        $error = "Túl sok sikertelen próbálkozás. Kérlek próbáld újra 15 perc múlva.";
    } else {
        if (empty($email) || empty($password)) {
            $error = "Add meg az email címed és a jelszavad!";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    // Státusz ellenőrzése
                    if ($user['status'] === 'pending') {
                        $error = "A fiókod még jóváhagyásra vár. Kérlek légy türelemmel!";
                    } elseif ($user['status'] === 'rejected') {
                        $error = "A regisztrációs kérelmedet elutasították.";
                    } elseif ($user['status'] === 'approved') {
                        // Sikeres belépés
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['nickname'] = $user['nickname'];

                        // Admin jog mentése sessionbe (opcionális, de hasznos)
                        $_SESSION['is_admin'] = $user['is_admin'] ?? 0;

                        header("Location: profile.php");
                        exit;
                    } else {
                        // Biztonsági ág (ha a status NULL vagy ismeretlen)
                        $error = "Ismeretlen fiók státusz. Kérlek vedd fel a kapcsolatot az adminisztrátorral.";
                    }
                } else {
                    // SIKERTELEN KÍSÉRLET LOGOLÁSA
                    $stmt_log = $pdo->prepare("INSERT INTO login_attempts (ip_address, username) VALUES (?, ?)");
                    $stmt_log->execute([$ip, $email]);

                    $error = "Hibás email cím vagy jelszó!";
                }
            } catch (PDOException $e) {
                $error = "Hiba történt a belépés során."; // $e->getMessage() élesben ne
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bejelentkezés - Szívhangja</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>

<body>
    <?php include 'header.php'; ?>

    <main id="main-content">
        <div class="card" style="max-width: 450px; margin: 4rem auto;">
            <h1 style="margin-top: 0; text-align: center;">Bejelentkezés</h1>

            <?php if ($error): ?>
                <div id="login-error" role="alert" aria-live="assertive"
                    style="background: #ffebee; color: #c62828; padding: 1rem; border: 1px solid #ef5350; margin-bottom: 1.5rem; border-radius: 8px;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="form-group">
                    <label for="email">Email cím:</label>
                    <input type="email" id="email" name="email" required placeholder="pelda@email.hu"
                        aria-invalid="<?= $error ? 'true' : 'false' ?>" <?= $error ? 'aria-describedby="login-error"' : '' ?>>
                </div>

                <div class="form-group" style="margin-bottom: 2rem;">
                    <label for="password">Jelszó:</label>
                    <input type="password" id="password" name="password" required placeholder="********"
                        aria-invalid="<?= $error ? 'true' : 'false' ?>" <?= $error ? 'aria-describedby="login-error"' : '' ?>>
                </div>

                <button type="submit" class="btn" style="width: 100%;"
                    aria-label="Bejelentkezés a fiókba">Belépés</button>
            </form>

            <div style="margin-top: 2rem; text-align: center; font-size: 0.95rem;">
                Nincs még fiókod? <a href="register.php"
                    style="color: var(--primary-color); font-weight: 700;">Regisztrálj itt!</a>
            </div>
        </div>
    </main>
</body>

</html>