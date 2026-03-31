<?php
// admin/change_password.php
require_once '../db.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch admin data directly to check force_password_change without redirect loop
$stmt = $pdo->prepare("SELECT force_password_change FROM admins WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch();

if (!$admin) {
    header("Location: logout.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    // Validation
    if (strlen($new_pass) < 10) {
        $error = "A jelszónak legalább 10 karakter hosszúnak kell lennie.";
    } elseif (!preg_match('/[A-Z]/', $new_pass) || !preg_match('/[a-z]/', $new_pass) || !preg_match('/[0-9]/', $new_pass)) {
        $error = "A jelszónak tartalmaznia kell kisbetűt, nagybetűt és számot.";
    } elseif ($new_pass !== $confirm_pass) {
        $error = "A két jelszó nem egyezik meg.";
    } else {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE admins SET password_hash = ?, force_password_change = 0 WHERE id = ?");
        if ($stmt->execute([$hashed, $_SESSION['admin_id']])) {
            $success = "A jelszó sikeresen megváltoztatva! Most már használhatod a rendszert.";
            // Redirect after 2 seconds or show a button
        } else {
            $error = "Hiba történt a jelszó módosítása során.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <title>Jelszócsere - Szívhangja Admin</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        body {
            background-color: #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }

        .card {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
    </style>
</head>

<body>
    <div class="card">
        <h2>Kötelező jelszócsere</h2>
        <p>Az első belépéskor vagy adminisztrátori kérésre meg kell változtatnod a jelszavadat.</p>

        <?php if ($error): ?>
            <div
                style="background: #ffebee; color: #c62828; padding: 10px; margin-bottom: 1rem; border-radius: 4px; border: 1px solid #ffcdd2;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div
                style="background: #e8f5e9; color: #2e7d32; padding: 10px; margin-bottom: 1rem; border-radius: 4px; border: 1px solid #c8e6c9;">
                <?= htmlspecialchars($success) ?>
            </div>
            <a href="index.php" class="btn" style="display: block; text-align: center; text-decoration: none;">Tovább a
                Dashboardra</a>
        <?php else: ?>
            <form method="POST">
                <div class="form-group">
                    <label>Új jelszó</label>
                    <input type="password" name="new_password" required
                        style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <small style="color: #666;">Minimum 10 karakter, kisbetű, nagybetű és szám.</small>
                </div>
                <div class="form-group">
                    <label>Jelszó megerősítése</label>
                    <input type="password" name="confirm_password" required
                        style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <button type="submit" class="btn" style="width: 100%;">Jelszó mentése</button>
            </form>
        <?php endif; ?>
    </div>
</body>

</html>