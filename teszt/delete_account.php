<?php
// delete_account.php - Fiók törlése
require 'db.php';
require 'email_helper.php';

// Csak bejelentkezve
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF ellenőrzés
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Biztonsági hiba: Érvénytelen CSRF token.");
    }

    $password = $_POST['password'];

    // Jelszó ellenőrzése
    $stmt = $pdo->prepare("SELECT password, email, nickname FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Sikeres jelszó -> Törlés és Email

        // 1. Email küldése és Értesítés mentése (bár törlés után nem látszik a webes, de a kérés szerint mentjük)
        require_once 'notification_helper.php';

        create_notification(
            $user_id,
            'deletion',
            "A fiókodat sikeresen törölted.",
            null,
            "Fiók törölve - Szívhangja",
            "Kedves " . $user['nickname'] . "!\n\nEzúton igazoljuk vissza, hogy a Szívhangja fiókodat véglegesen töröltük.\nSajnáljuk, hogy elmégy!\n\nÜdvözlettel,\nA Szívhangja Csapata"
        );

        // 2. Kép törlése (ha van)
        $stmt_img = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
        $stmt_img->execute([$user_id]);
        $img = $stmt_img->fetch();
        if ($img && $img['profile_image']) {
            $file_path = './uploads/' . $img['profile_image'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }

        // 3. Adatbázis törlés (ON DELETE CASCADE miatt az üzenetek is törlődnek elvileg, 
        // de ha nincs beállítva, akkor azokat is kéne. Feltételezzük a CASCADE-t vagy töröljük manuálisan)
        // Biztosra megyünk:
        $pdo->prepare("DELETE FROM messages WHERE sender_id = ? OR recipient_id = ?")->execute([$user_id, $user_id]);

        $stmt_del = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt_del->execute([$user_id])) {
            // 4. Kijelentkeztetés
            session_destroy();
            header("Location: index.php?account_deleted=1");
            exit;
        } else {
            $error = "Hiba történt a törlés során.";
        }

    } else {
        $error = "Hibás jelszó!";
    }
}
?>

<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiók Törlése - Szívhangja</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <style>
        .danger-zone {
            border: 2px solid #c62828;
            padding: 2rem;
            border-radius: 8px;
            background-color: #ffebee;
            color: #b71c1c;
        }

        .btn-danger {
            background-color: #c62828;
            color: white;
            border: none;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: bold;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-danger:hover {
            background-color: #b71c1c;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <main id="main-content" role="main">
        <h1>Fiók Törlése</h1>

        <?php if ($error): ?>
            <div role="alert"
                style="background: #fff; color: #c62828; padding: 1rem; border: 1px solid #c62828; margin-bottom: 1rem;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="card danger-zone">
            <h2>Biztosan törölni szeretnéd a fiókodat?</h2>
            <p>Ez a művelet <strong>nem visszavonható</strong>. Minden adatod, üzeneted és profilképed véglegesen
                törlődik.</p>

            <form method="POST" action="delete_account.php" style="margin-top: 2rem;">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="form-group">
                    <label for="password" style="color: inherit;">Add meg a jelszavad a megerősítéshez:</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div style="display: flex; gap: 1.5rem; align-items: center; margin-top: 2rem;">
                    <button type="submit" class="btn-danger"
                        onclick="return confirm('VÉGLEGES DÖNTÉS: Tényleg törlöd a fiókodat?');">Végleges
                        Törlés</button>
                    <a href="edit_profile.php" style="color: var(--text-main); font-weight: 600;">Mégsem,
                        visszalépek</a>
                </div>
            </form>
        </div>
    </main>
</body>

</html>