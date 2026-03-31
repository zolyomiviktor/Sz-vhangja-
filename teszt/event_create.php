<?php
// event_create.php - Új esemény létrehozása
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Biztonsági hiba: Érvénytelen CSRF token.");
    }

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type = $_POST['type'] ?? 'offline';
    $location = trim($_POST['location'] ?? '');
    $event_date = $_POST['event_date'] ?? '';
    $accessibility_info = trim($_POST['accessibility_info'] ?? '');

    if (empty($name) || mb_strlen($name) < 3) {
        $error = "Az esemény neve kötelező és legalább 3 karakter hosszú kell, hogy legyen!";
    } elseif (empty($event_date)) {
        $error = "Az esemény dátumának megadása kötelező!";
    } elseif (!in_array($type, ['online', 'offline'])) {
        $error = "Érvénytelen esemény típus!";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO events (name, description, type, location, event_date, accessibility_info, creator_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $description, $type, $location, $event_date, $accessibility_info, $user_id]);
            $event_id = $pdo->lastInsertId();

            header("Location: event_view.php?id=$event_id&success=" . urlencode("Esemény sikeresen létrehozva!"));
            exit;
        } catch (PDOException $e) {
            error_log("Esemény létrehozási hiba: " . $e->getMessage());
            $error = "Hiba történt az esemény létrehozása során.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Új esemény létrehozása - Szívhangja</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>

<body>
    <?php include 'header.php'; ?>

    <main id="main-content" role="main">
        <div class="container" style="max-width: 800px; margin: 0 auto; padding: 2rem;">
            <h1>Új esemény létrehozása</h1>

            <?php if ($error): ?>
                <div role="alert"
                    style="background: #ffebee; color: #c62828; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="event_create.php" class="card">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="form-group">
                    <label for="name">Esemény neve (kötelező):</label>
                    <input type="text" id="name" name="name" required minlength="3"
                        value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" aria-label="Esemény neve">
                </div>

                <div class="form-group">
                    <label for="description">Leírás:</label>
                    <textarea id="description" name="description" rows="5" placeholder="Miről szól ez az esemény?"
                        aria-label="Esemény leírása"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="type">Esemény típusa:</label>
                    <select id="type" name="type" aria-label="Esemény típusa" onchange="toggleLocation(this.value)">
                        <option value="offline" <?= ($_POST['type'] ?? 'offline') === 'offline' ? 'selected' : '' ?>>
                            Személyes találkozó
                        </option>
                        <option value="online" <?= ($_POST['type'] ?? '') === 'online' ? 'selected' : '' ?>>
                            Online esemény
                        </option>
                    </select>
                </div>

                <div class="form-group" id="location-group">
                    <label for="location">Helyszín:</label>
                    <input type="text" id="location" name="location"
                        value="<?= htmlspecialchars($_POST['location'] ?? '') ?>" placeholder="pl. Budapest, V. kerület"
                        aria-label="Helyszín">
                </div>

                <div class="form-group">
                    <label for="event_date">Dátum és időpont (kötelező):</label>
                    <input type="datetime-local" id="event_date" name="event_date" required
                        value="<?= htmlspecialchars($_POST['event_date'] ?? '') ?>"
                        aria-label="Esemény dátuma és időpontja">
                </div>

                <div class="form-group">
                    <label for="accessibility_info">♿ Akadálymentességi információk:</label>
                    <textarea id="accessibility_info" name="accessibility_info" rows="3"
                        placeholder="pl. Kerekesszékkel megközelíthető, tolmács biztosított"
                        aria-label="Akadálymentességi információk"><?= htmlspecialchars($_POST['accessibility_info'] ?? '') ?></textarea>
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn">Esemény létrehozása</button>
                    <a href="events.php" class="btn" style="background: var(--text-muted);">Mégse</a>
                </div>
            </form>
        </div>
    </main>

    <footer role="contentinfo" style="text-align: center; padding: 2rem;">
        <p>&copy; 2024 Szívhangja</p>
    </footer>

    <script>
        function toggleLocation(type) {
            const locationGroup = document.getElementById('location-group');
            if (type === 'online') {
                locationGroup.style.display = 'none';
            } else {
                locationGroup.style.display = 'block';
            }
        }
        // Initial state
        toggleLocation(document.getElementById('type').value);
    </script>
</body>

</html>