<?php
// admin_approval.php - Adminisztrációs felület jóváhagyáshoz
require 'db.php'; // Session és DB
require 'email_helper.php';

// Jogosultság ellenőrzése
if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    die("Hiba: Nincs jogosultságod az oldal megtekintéséhez.");
}

$message = '';

// Jóváhagyás / Elutasítás logika
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF ellenőrzés
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Biztonsági hiba: Érvénytelen CSRF token.");
    }

    $target_user_id = $_POST['user_id'];
    $action = $_POST['action']; // 'approve' vagy 'reject'

    if ($target_user_id && in_array($action, ['approve', 'reject'])) {
        $new_status = ($action === 'approve') ? 'approved' : 'rejected';

        try {
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            if ($stmt->execute([$new_status, $target_user_id])) {

                // Email küldés szimulációja
                $stmt_email = $pdo->prepare("SELECT email, nickname FROM users WHERE id = ?");
                $stmt_email->execute([$target_user_id]);
                $user_data = $stmt_email->fetch();

                if ($action === 'approve') {
                    require_once 'notification_helper.php';

                    create_notification(
                        $target_user_id,
                        'approval',
                        "A felhasználói fiókodat jóváhagytuk! Most már használhatod az oldalt.",
                        "profile.php", // Link a saját profilra
                        "Fiók aktiválva - Szívhangja",
                        "Kedves " . $user_data['nickname'] . "!\n\nÖrömmel értesítünk, hogy regisztrációd a Szívhangja társkeresőn jóváhagyásra került.\nMost már bejelentkezhetsz."
                    );

                    $message = "Siker! " . htmlspecialchars($user_data['nickname']) . " fiókja aktiválva. (Értesítés + Email kiküldve)";

                } else {
                    $message = "Siker! " . htmlspecialchars($user_data['nickname']) . " kérelme elutasítva.";
                }

            }
        } catch (PDOException $e) {
            $message = "Hiba történt: " . $e->getMessage();
        }
    }
}

// Várakozó felhasználók lekérdezése
$stmt = $pdo->prepare("SELECT id, nickname, email, created_at, bio FROM users WHERE status = 'pending' ORDER BY created_at ASC");
$stmt->execute();
$pending_users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Fiók Jóváhagyás</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">

</head>

<body>

    <?php include 'header.php'; ?>

    <main id="main-content" role="main">
        <h1>Fiók Jóváhagyások (Admin)</h1>

        <?php if ($message): ?>
            <div role="alert"
                style="background: #e3f2fd; color: #0d47a1; padding: 1rem; margin-bottom: 1rem; border: 1px solid #90caf9;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (count($pending_users) > 0): ?>
            <p><?= count($pending_users) ?> várakozó kérelem.</p>

            <?php foreach ($pending_users as $user): ?>
                <div class="admin-card">
                    <h3><?= htmlspecialchars($user['nickname']) ?> <small
                            style="color: #666; font-weight: normal;">(<?= htmlspecialchars($user['email']) ?>)</small></h3>
                    <p><strong>Regisztrált:</strong> <?= $user['created_at'] ?></p>
                    <?php if (!empty($user['bio'])): ?>
                        <p><strong>Bemutatkozás:</strong><br> <?= nl2br(htmlspecialchars($user['bio'])) ?></p>
                    <?php endif; ?>

                    <div class="action-buttons">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn-approve">Jóváhagyás</button>
                        </form>

                        <form method="POST" onsubmit="return confirm('Biztosan elutasítod?');">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" class="btn-reject">Elutasítás</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php else: ?>
            <div class="card">
                <p>Jelenleg nincs függőben lévő regisztráció. Minden rendben! ✅</p>
            </div>
        <?php endif; ?>

    </main>
</body>

</html>