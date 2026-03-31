<?php
// admin/admin_create.php
require 'auth_check.php';

if (!$is_super_admin) {
    die("Hiba: Csak Szuper Adminisztrátorok hozhatnak létre új adminokat.");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'admin';
    $password = $_POST['password'] ?? '';
    $nickname = ($role === 'moderator' ? 'Moderátor' : 'Adminisztrátor');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Érvénytelen email cím.";
    } elseif (strlen($password) < 6) {
        $error = "Az ideiglenes jelszónak legalább 6 karakternek kell lennie.";
    } else {
        try {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admins (email, password_hash, nickname, role, is_active, force_password_change) VALUES (?, ?, ?, ?, 1, 1)");
            $stmt->execute([$email, $hashed, $nickname, $role]);
            $success = "Admin sikeresen létrehozva! Az első belépéskor jelszót kell cserélnie.";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "Ez az email cím már használatban van.";
            } else {
                $error = "Hiba történt: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <title>Új Admin - Szívhangja</title>
    <link rel="stylesheet" href="../style.css">
</head>

<body>
    <header
        style="background: var(--primary-color); color: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center;">
        <div style="font-weight: bold;">Szívhangja Admin</div>
        <div><a href="admins.php" style="color: white; text-decoration: none;">&larr; Vissza a listához</a></div>
    </header>

    <div class="container" style="max-width: 600px; margin-top: 2rem;">
        <h1>Új Admin Létrehozása</h1>

        <?php if ($error): ?>
            <div
                style="background: #ffebee; color: #c62828; padding: 1rem; margin-bottom: 1rem; border-radius: 4px; border: 1px solid #ffcdd2;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div
                style="background: #e8f5e9; color: #2e7d32; padding: 1rem; margin-bottom: 1rem; border-radius: 4px; border: 1px solid #c8e6c9;">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <form method="POST">
                <div class="form-group">
                    <label>Email cím</label>
                    <input type="email" name="email" required
                        style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div class="form-group">
                    <label>Jogosultsági szint</label>
                    <select name="role" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="admin">Adminisztrátor</option>
                        <option value="moderator">Moderátor</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Ideiglenes jelszó</label>
                    <input type="text" name="password" required
                        style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"
                        placeholder="Például: Temp123Key!">
                    <small style="color: #666;">Ezt a jelszót az adminnak az első belépéskor meg kell majd
                        változtatnia.</small>
                </div>
                <button type="submit" class="btn" style="width: 100%;">Létrehozás</button>
            </form>
        </div>
    </div>
</body>

</html>