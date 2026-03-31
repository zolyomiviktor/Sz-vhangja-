<?php
// admin/admin_edit.php
require 'auth_check.php';

if (!$is_super_admin) {
    die("Hiba: Csak Szuper Adminisztrátorok szerkeszthetik az adminokat.");
}

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$id]);
$admin = $stmt->fetch();

if (!$admin) {
    die("Admin nem található.");
}

// Prevent editing self to avoid locking yourself out
$is_self = ($admin['id'] == $_SESSION['admin_id']);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? $admin['role'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $force_password_change = isset($_POST['force_password_change']) ? 1 : 0;

    // Deactivation logic
    if ($is_self && $is_active == 0) {
        $error = "Saját magadat nem deaktiválhatod!";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE admins SET role = ?, is_active = ?, force_password_change = ? WHERE id = ?");
            $stmt->execute([$role, $is_active, $force_password_change, $id]);
            $success = "Változtatások elmentve!";
            // Update local object for display
            $admin['role'] = $role;
            $admin['is_active'] = $is_active;
            $admin['force_password_change'] = $force_password_change;
        } catch (PDOException $e) {
            $error = "Hiba történt: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <title>Admin Szerkesztése - Szívhangja</title>
    <link rel="stylesheet" href="../style.css">
</head>

<body>
    <header
        style="background: var(--primary-color); color: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center;">
        <div style="font-weight: bold;">Szívhangja Admin</div>
        <div><a href="admins.php" style="color: white; text-decoration: none;">&larr; Vissza a listához</a></div>
    </header>

    <div class="container" style="max-width: 600px; margin-top: 2rem;">
        <h1>Admin Szerkesztése:
            <?= htmlspecialchars($admin['email']) ?>
        </h1>

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
                    <input type="text" value="<?= htmlspecialchars($admin['email']) ?>" readonly
                        style="width: 100%; padding: 10px; border: 1px solid #ddd; background: #eee; border-radius: 4px;">
                </div>

                <div class="form-group">
                    <label>Jogosultsági szint</label>
                    <select name="role" <?= $is_self ? 'disabled' : '' ?> style="width: 100%; padding: 10px; border: 1px
                        solid #ddd; border-radius: 4px;">
                        <option value="super_admin" <?= $admin['role'] === 'super_admin' ? 'selected' : '' ?>>Szuper
                            Adminisztrátor</option>
                        <option value="admin" <?= $admin['role'] === 'admin' ? 'selected' : '' ?>>Adminisztrátor</option>
                        <option value="moderator" <?= $admin['role'] === 'moderator' ? 'selected' : '' ?>>Moderátor
                        </option>
                    </select>
                    <?php if ($is_self): ?>
                        <input type="hidden" name="role" value="<?= $admin['role'] ?>">
                        <small style="color: #666;">A saját szerepkörödet nem módosíthatod itt.</small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" <?= $admin['is_active'] ? 'checked' : '' ?>
                         <?= $is_self ? 'disabled' : '' ?>>
                        Fiók aktív
                    </label>
                    <?php if ($is_self): ?>
                        <br><small style="color: #666;">Saját magadat nem deaktiválhatod.</small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="force_password_change" <?= $admin['force_password_change'] ? 'checked' : '' ?>>
                        Kötelező jelszócsere a következő belépéskor
                    </label>
                </div>

                <button type="submit" class="btn" style="width: 100%;">Mentés</button>
            </form>
        </div>
    </div>
</body>

</html>