<?php
// admin/login.php
require_once '../db.php';
require_once '../email_helper.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$step = isset($_SESSION['admin_2fa_pending']) ? 2 : 1;

// Log helper
function log_admin_access($pdo, $username, $success, $details)
{
    $stmt = $pdo->prepare("INSERT INTO admin_access_logs (ip_address, username, success, details) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_SERVER['REMOTE_ADDR'], $username, $success ? 1 : 0, $details]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['email'], $_POST['password'])) {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            if ($admin['is_active'] == 0) {
                $error = "A fiókod deaktiválva van.";
                log_admin_access($pdo, $email, 0, "Account deactivated.");
            } else {
                // Success: Direct Login
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_role'] = $admin['role'];
                $_SESSION['admin_nickname'] = $admin['nickname'];

                log_admin_access($pdo, $email, 1, "Login successful.");
                header("Location: index.php");
                exit;
            }
        } else {
            $error = "Hibás adatok vagy nincs jogosultságod.";
            log_admin_access($pdo, $email, 0, "Invalid credentials or unauthorized.");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <title>Admin Belépés - Szívhangja</title>
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

        .login-card {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }

        .logo {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
            text-align: center;
            margin-bottom: 2rem;
            display: block;
            text-decoration: none;
        }
    </style>
</head>

<body>

    <div class="login-card">
        <a href="../index.php" class="logo">Szívhangja Admin</a>

        <?php if ($error): ?>
            <div role="alert"
                style="background: #ffebee; color: #c62828; padding: 1rem; margin-bottom: 1rem; border-radius: 4px; border: 1px solid #ffcdd2;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Admin Email</label>
                <input type="email" name="email" required
                    style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div class="form-group">
                <label>Jelszó</label>
                <input type="password" name="password" required
                    style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <button type="submit" class="btn" style="width: 100%;">Belépés</button>
        </form>
    </div>

</body>

</html>