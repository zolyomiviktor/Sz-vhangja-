<?php
// group_create.php - Új csoport létrehozása
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Biztonsági hiba: Érvénytelen CSRF token.");
    }

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type = $_POST['type'] ?? 'public';

    if (empty($name) || mb_strlen($name) < 3) {
        $error = "A csoport neve kötelező és legalább 3 karakter hosszú kell, hogy legyen!";
    } elseif (!in_array($type, ['public', 'private'])) {
        $error = "Érvénytelen csoport típus!";
    } else {
        try {
            $pdo->beginTransaction();

            // Csoport létrehozása
            $stmt = $pdo->prepare("INSERT INTO groups (name, description, type, creator_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $description, $type, $user_id]);
            $group_id = $pdo->lastInsertId();

            // Létrehozó hozzáadása admin tagként
            $stmt = $pdo->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'admin')");
            $stmt->execute([$group_id, $user_id]);

            $pdo->commit();

            header("Location: group_view.php?id=$group_id&success=" . urlencode("Csoport sikeresen létrehozva!"));
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Csoport létrehozási hiba: " . $e->getMessage());
            $error = "Hiba történt a csoport létrehozása során.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Új csoport létrehozása - Szívhangja</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>

<body>
    <?php include 'header.php'; ?>

    <main id="main-content" role="main">
        <div class="container" style="max-width: 800px; margin: 0 auto; padding: 2rem;">
            <h1>Új csoport létrehozása</h1>

            <div id="status-messages" aria-live="polite">
                <?php if ($error): ?>
                    <div role="alert"
                        style="background: #ffebee; color: #c62828; padding: 1rem; border: 1px solid #ef5350; margin-bottom: 1rem;">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
            </div>

            <form method="POST" action="group_create.php" class="card">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="form-group">
                    <label for="name">Csoport neve (kötelező):</label>
                    <input type="text" id="name" name="name" required minlength="3"
                        value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" aria-label="Csoport neve">
                </div>

                <div class="form-group">
                    <label for="description">Leírás:</label>
                    <textarea id="description" name="description" rows="5" placeholder="Miről szól ez a csoport?"
                        aria-label="Csoport leírása"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="type">Csoport típusa:</label>
                    <select id="type" name="type" aria-label="Csoport típusa">
                        <option value="public" <?= ($_POST['type'] ?? 'public') === 'public' ? 'selected' : '' ?>>
                            Nyilvános - Bárki csatlakozhat
                        </option>
                        <option value="private" <?= ($_POST['type'] ?? '') === 'private' ? 'selected' : '' ?>>
                            Zárt - Csak tagok láthatják a tartalmat
                        </option>
                    </select>
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn">Csoport létrehozása</button>
                    <a href="groups.php" class="btn" style="background: var(--text-muted);">Mégse</a>
                </div>
            </form>
        </div>
    </main>

    <footer role="contentinfo" style="text-align: center; padding: 2rem;">
        <p>&copy; 2024 Szívhangja.</p>
    </footer>
</body>

</html>