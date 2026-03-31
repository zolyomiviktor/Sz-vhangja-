<?php
require 'db.php';

$message = '';
$error = '';

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    try {
        // Token keresése és érvényesség ellenőrzése
        $stmt = $pdo->prepare("SELECT id, nickname FROM users WHERE activation_token = ? AND activation_expires > NOW() AND is_email_verified = 0");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            // Siker: E-mail megerősítve
            // A státusz marad 'pending', mert az adminnak még jóvá kell hagynia, 
            // de az email már verified.
            $update = $pdo->prepare("UPDATE users SET is_email_verified = 1, activation_token = NULL, activation_expires = NULL WHERE id = ?");
            if ($update->execute([$user['id']])) {
                $message = "Sikeres e-mail megerősítés! A fiókod most már adminisztrátori jóváhagyásra vár.";
            } else {
                $error = "Hiba történt az aktiválás közben.";
            }
        } else {
            $error = "Érvénytelen vagy lejárt aktiváló link.";
        }
    } catch (PDOException $e) {
        $error = "Adatbázis hiba.";
    }
} else {
    $error = "Hiányzó aktiváló token.";
}
?>

<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiók Aktiválás - Szívhangja</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>

<body>
    <?php include 'header.php'; ?>

    <main id="main-content">
        <div class="card" style="max-width: 500px; margin: 2rem auto; text-align: center;">
            <h1 style="margin-top: 0;">Fiók Aktiválás</h1>

            <?php if ($message): ?>
                <div role="alert"
                    style="background: #e8f5e9; color: #2e7d32; padding: 1rem; border: 1px solid #66bb6a; border-radius: 8px;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 1rem;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <p><?= htmlspecialchars($message) ?></p>
                    <a href="login.php" class="btn" style="margin-top: 1rem;">Tovább a bejelentkezéshez</a>
                </div>
            <?php elseif ($error): ?>
                <div role="alert"
                    style="background: #ffebee; color: #c62828; padding: 1rem; border: 1px solid #ef5350; border-radius: 8px;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 1rem;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <p><?= htmlspecialchars($error) ?></p>
                    <a href="register.php" class="btn" style="margin-top: 1rem;">Vissza a regisztrációhoz</a>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>

</html>