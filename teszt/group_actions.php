<?php
// group_actions.php - Csoportműveletek API
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: groups.php");
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Biztonsági hiba: Érvénytelen CSRF token.");
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$group_id = (int) ($_POST['group_id'] ?? 0);

if ($group_id === 0) {
    header("Location: groups.php?error=" . urlencode("Érvénytelen csoport!"));
    exit;
}

try {
    switch ($action) {
        case 'join':
            // Csatlakozás csoporthoz
            $stmt = $pdo->prepare("SELECT type FROM groups WHERE id = ?");
            $stmt->execute([$group_id]);
            $group = $stmt->fetch();

            if (!$group) {
                throw new Exception("A csoport nem található!");
            }

            // Ellenőrzés: már tag-e
            $stmt = $pdo->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ?");
            $stmt->execute([$group_id, $user_id]);
            if ($stmt->fetch()) {
                throw new Exception("Már tagja vagy ennek a csoportnak!");
            }

            $stmt = $pdo->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'member')");
            $stmt->execute([$group_id, $user_id]);

            header("Location: group_view.php?id=$group_id&success=" . urlencode("Sikeresen csatlakoztál a csoporthoz!"));
            exit;

        case 'leave':
            // Kilépés csoportból
            $stmt = $pdo->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
            $stmt->execute([$group_id, $user_id]);

            header("Location: groups.php?success=" . urlencode("Sikeresen kiléptél a csoportból!"));
            exit;

        case 'post':
            // Bejegyzés létrehozása
            $content = trim($_POST['content'] ?? '');

            if (empty($content)) {
                throw new Exception("A bejegyzés tartalma nem lehet üres!");
            }

            // Ellenőrzés: tag-e
            $stmt = $pdo->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ?");
            $stmt->execute([$group_id, $user_id]);
            if (!$stmt->fetch()) {
                throw new Exception("Csak tagok posztolhatnak a csoportba!");
            }

            $stmt = $pdo->prepare("INSERT INTO group_posts (group_id, user_id, content) VALUES (?, ?, ?)");
            $stmt->execute([$group_id, $user_id, $content]);

            header("Location: group_view.php?id=$group_id&success=" . urlencode("Bejegyzés sikeresen közzétéve!"));
            exit;

        case 'delete_post':
            // Bejegyzés törlése
            $post_id = (int) ($_POST['post_id'] ?? 0);

            if ($post_id === 0) {
                throw new Exception("Érvénytelen bejegyzés!");
            }

            // Ellenőrzés: admin vagy saját bejegyzés
            $stmt = $pdo->prepare("SELECT gp.user_id, gm.role 
                                   FROM group_posts gp
                                   LEFT JOIN group_members gm ON gm.group_id = gp.group_id AND gm.user_id = ?
                                   WHERE gp.id = ? AND gp.group_id = ?");
            $stmt->execute([$user_id, $post_id, $group_id]);
            $post = $stmt->fetch();

            if (!$post || ($post['user_id'] != $user_id && $post['role'] !== 'admin')) {
                throw new Exception("Nincs jogosultságod törölni ezt a bejegyzést!");
            }

            $stmt = $pdo->prepare("DELETE FROM group_posts WHERE id = ?");
            $stmt->execute([$post_id]);

            header("Location: group_view.php?id=$group_id&success=" . urlencode("Bejegyzés sikeresen törölve!"));
            exit;

        case 'comment':
            // Komment hozzáadása
            $post_id = (int) ($_POST['post_id'] ?? 0);
            $content = trim($_POST['content'] ?? '');

            if ($post_id === 0 || empty($content)) {
                throw new Exception("Érvénytelen komment!");
            }

            // Ellenőrzés: tag-e
            $stmt = $pdo->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ?");
            $stmt->execute([$group_id, $user_id]);
            if (!$stmt->fetch()) {
                throw new Exception("Csak tagok kommentelhetnek!");
            }

            $stmt = $pdo->prepare("INSERT INTO group_comments (post_id, user_id, content) VALUES (?, ?, ?)");
            $stmt->execute([$post_id, $user_id, $content]);

            header("Location: group_post.php?id=$post_id&success=" . urlencode("Komment sikeresen hozzáadva!"));
            exit;

        default:
            throw new Exception("Ismeretlen művelet!");
    }
} catch (Exception $e) {
    error_log("Csoportművelet hiba: " . $e->getMessage());
    header("Location: group_view.php?id=$group_id&error=" . urlencode($e->getMessage()));
    exit;
} catch (PDOException $e) {
    error_log("Adatbázis hiba: " . $e->getMessage());
    header("Location: group_view.php?id=$group_id&error=" . urlencode("Rendszerhiba történt."));
    exit;
}
