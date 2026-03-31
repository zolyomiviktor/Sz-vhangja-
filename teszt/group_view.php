<?php
// group_view.php - Csoport részletei
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$group_id = (int) ($_GET['id'] ?? 0);

if ($group_id === 0) {
    header("Location: groups.php");
    exit;
}

// Csoport adatok
$stmt = $pdo->prepare("SELECT g.*, u.nickname as creator_name,
                       (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count,
                       (SELECT role FROM group_members WHERE group_id = g.id AND user_id = ?) as user_role
                       FROM groups g
                       JOIN users u ON g.creator_id = u.id
                       WHERE g.id = ?");
$stmt->execute([$user_id, $group_id]);
$group = $stmt->fetch();

if (!$group) {
    header("Location: groups.php");
    exit;
}

$is_member = !empty($group['user_role']);
$is_admin = $group['user_role'] === 'admin';

// Zárt csoport esetén csak tagok láthatják
if ($group['type'] === 'private' && !$is_member) {
    die("Ez egy zárt csoport. Csak tagok férhetnek hozzá.");
}

// Bejegyzések lekérdezése
$stmt_posts = $pdo->prepare("SELECT gp.*, u.nickname, u.profile_image,
                              (SELECT COUNT(*) FROM group_comments WHERE post_id = gp.id) as comment_count
                              FROM group_posts gp
                              JOIN users u ON gp.user_id = u.id
                              WHERE gp.group_id = ?
                              ORDER BY gp.created_at DESC
                              LIMIT 20");
$stmt_posts->execute([$group_id]);
$posts = $stmt_posts->fetchAll();

// Tagok lekérdezése
$stmt_members = $pdo->prepare("SELECT gm.*, u.nickname, u.profile_image
                                FROM group_members gm
                                JOIN users u ON gm.user_id = u.id
                                WHERE gm.group_id = ?
                                ORDER BY gm.role DESC, gm.joined_at ASC
                                LIMIT 10");
$stmt_members->execute([$group_id]);
$members = $stmt_members->fetchAll();

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= htmlspecialchars($group['name']) ?> - Szívhangja
    </title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>

<body>
    <?php include 'header.php'; ?>

    <main id="main-content" role="main">
        <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 2rem;">

            <div id="status-messages" aria-live="polite">
                <?php if ($success): ?>
                    <div role="alert"
                        style="background: #e8f5e9; color: #2e7d32; padding: 1rem; border: 1px solid #66bb6a; margin-bottom: 1rem;">
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div role="alert"
                        style="background: #ffebee; color: #c62828; padding: 1rem; border: 1px solid #ef5350; margin-bottom: 1rem;">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Csoport fejléc -->
            <div class="card" style="margin-bottom: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: start; gap: 2rem;">
                    <div style="flex: 1;">
                        <h1 style="margin: 0 0 0.5rem 0;">
                            <?= htmlspecialchars($group['name']) ?>
                        </h1>
                        <p style="color: var(--text-muted); margin: 0 0 1rem 0;">
                            <span class="badge"
                                style="background: <?= $group['type'] === 'public' ? '#4caf50' : '#ff9800' ?>; color: white; padding: 0.25rem 0.5rem; border-radius: 4px;">
                                <?= $group['type'] === 'public' ? 'Nyilvános' : 'Zárt' ?>
                            </span>
                            ·
                            <?= $group['member_count'] ?> tag
                            · Létrehozta:
                            <?= htmlspecialchars($group['creator_name']) ?>
                        </p>
                        <?php if ($group['description']): ?>
                            <p style="margin: 0;">
                                <?= nl2br(htmlspecialchars($group['description'])) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <?php if ($is_member): ?>
                            <form method="POST" action="group_actions.php" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="leave">
                                <input type="hidden" name="group_id" value="<?= $group_id ?>">
                                <button type="submit" class="btn" style="background: var(--text-muted);"
                                    onclick="return confirm('Biztosan kilépsz a csoportból?')">Kilépés</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="group_actions.php" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="join">
                                <input type="hidden" name="group_id" value="<?= $group_id ?>">
                                <button type="submit" class="btn">Csatlakozás</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 300px; gap: 2rem;">
                <!-- Bejegyzések -->
                <div>
                    <?php if ($is_member): ?>
                        <div class="card" style="margin-bottom: 2rem;">
                            <h2 style="margin: 0 0 1rem 0;">Új bejegyzés</h2>
                            <form method="POST" action="group_actions.php">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="post">
                                <input type="hidden" name="group_id" value="<?= $group_id ?>">
                                <textarea name="content" rows="4" placeholder="Írj valamit a csoportnak..." required
                                    aria-label="Bejegyzés tartalma"
                                    style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px;"></textarea>
                                <button type="submit" class="btn" style="margin-top: 1rem;">Közzététel</button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <h2>Bejegyzések</h2>
                    <?php if (count($posts) > 0): ?>
                        <?php foreach ($posts as $post): ?>
                            <article class="card" style="margin-bottom: 1.5rem;">
                                <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                                    <img src="<?= $post['profile_image'] ? 'uploads/' . htmlspecialchars($post['profile_image']) : 'assets/avatar-default.png' ?>"
                                        alt="<?= htmlspecialchars($post['nickname']) ?>"
                                        style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover;">
                                    <div>
                                        <strong>
                                            <?= htmlspecialchars($post['nickname']) ?>
                                        </strong>
                                        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0;">
                                            <?= date('Y. m. d. H:i', strtotime($post['created_at'])) ?>
                                        </p>
                                    </div>
                                </div>
                                <p style="margin: 0 0 1rem 0;">
                                    <?= nl2br(htmlspecialchars($post['content'])) ?>
                                </p>
                                <div
                                    style="display: flex; gap: 1rem; align-items: center; color: var(--text-muted); font-size: 0.9rem;">
                                    <a href="group_post.php?id=<?= $post['id'] ?>" style="color: var(--primary-color);">
                                        <?= $post['comment_count'] ?> komment
                                    </a>
                                    <?php if ($is_admin || $post['user_id'] == $user_id): ?>
                                        <form method="POST" action="group_actions.php" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="action" value="delete_post">
                                            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                            <input type="hidden" name="group_id" value="<?= $group_id ?>">
                                            <button type="submit"
                                                style="background: none; border: none; color: #c62828; cursor: pointer; font-size: 0.9rem;"
                                                onclick="return confirm('Biztosan törlöd ezt a bejegyzést?')">Törlés</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="card" style="text-align: center; padding: 2rem;">
                            <p style="color: var(--text-muted);">Még nincsenek bejegyzések ebben a csoportban.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Oldalsáv: Tagok -->
                <aside>
                    <div class="card">
                        <h3 style="margin: 0 0 1rem 0;">Tagok (
                            <?= $group['member_count'] ?>)
                        </h3>
                        <?php foreach ($members as $member): ?>
                            <div style="display: flex; gap: 0.75rem; align-items: center; margin-bottom: 1rem;">
                                <img src="<?= $member['profile_image'] ? 'uploads/' . htmlspecialchars($member['profile_image']) : 'assets/avatar-default.png' ?>"
                                    alt="<?= htmlspecialchars($member['nickname']) ?>"
                                    style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                <div style="flex: 1;">
                                    <a href="profile.php?id=<?= $member['user_id'] ?>"
                                        style="color: var(--primary-color); text-decoration: none;">
                                        <?= htmlspecialchars($member['nickname']) ?>
                                    </a>
                                    <?php if ($member['role'] === 'admin'): ?>
                                        <span class="badge"
                                            style="background: var(--primary-color); color: white; padding: 0.15rem 0.4rem; font-size: 0.75rem; margin-left: 0.25rem;">Admin</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($group['member_count'] > 10): ?>
                            <a href="group_members.php?id=<?= $group_id ?>"
                                style="color: var(--primary-color); font-size: 0.9rem;">Összes tag megtekintése</a>
                        <?php endif; ?>
                    </div>
                </aside>
            </div>
        </div>
    </main>

    <footer role="contentinfo" style="text-align: center; padding: 2rem;">
        <p>&copy; 2024 Szívhangja.</p>
    </footer>
</body>

</html>