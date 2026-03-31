<?php
// groups.php - Csoportok listája
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Szűrés kezelése
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// Csoportok lekérdezése
$sql = "SELECT g.*, u.nickname as creator_name, 
        (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count,
        (SELECT COUNT(*) FROM group_members WHERE group_id = g.id AND user_id = ?) as is_member
        FROM groups g
        JOIN users u ON g.creator_id = u.id
        WHERE 1=1";

$params = [$user_id];

if ($filter === 'my_groups') {
    $sql .= " AND EXISTS (SELECT 1 FROM group_members WHERE group_id = g.id AND user_id = ?)";
    $params[] = $user_id;
} elseif ($filter === 'public') {
    $sql .= " AND g.type = 'public'";
} elseif ($filter === 'private') {
    $sql .= " AND g.type = 'private'";
}

if (!empty($search)) {
    $sql .= " AND (g.name LIKE ? OR g.description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
}

$sql .= " ORDER BY g.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$groups = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Csoportok - Szívhangja</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>

<body>
    <?php include 'header.php'; ?>

    <main id="main-content" role="main">
        <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h1>Csoportok</h1>
                <a href="group_create.php" class="btn">Új csoport létrehozása</a>
            </div>

            <!-- Keresés és szűrés -->
            <div class="card" style="margin-bottom: 2rem;">
                <form method="GET" action="groups.php" style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 200px;">
                        <label for="search" class="sr-only">Keresés</label>
                        <input type="text" id="search" name="search" placeholder="Keresés csoportok között..."
                            value="<?= htmlspecialchars($search) ?>" aria-label="Keresés csoportok között">
                    </div>
                    <div>
                        <label for="filter" class="sr-only">Szűrés</label>
                        <select id="filter" name="filter" aria-label="Csoportok szűrése">
                            <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Összes csoport</option>
                            <option value="my_groups" <?= $filter === 'my_groups' ? 'selected' : '' ?>>Csoportjaim</option>
                            <option value="public" <?= $filter === 'public' ? 'selected' : '' ?>>Nyilvános</option>
                            <option value="private" <?= $filter === 'private' ? 'selected' : '' ?>>Zárt</option>
                        </select>
                    </div>
                    <button type="submit" class="btn">Keresés</button>
                </form>
            </div>

            <!-- Csoportok listája -->
            <ul style="display: grid; gap: 1.5rem; list-style: none; padding: 0;" aria-label="Csoportok listája">
                <?php if (count($groups) > 0): ?>
                    <?php foreach ($groups as $group): ?>
                        <li>
                            <article class="card" style="display: flex; gap: 1.5rem; align-items: start;">
                                <div style="flex: 1;">
                                    <h2 style="margin: 0 0 0.5rem 0;">
                                        <a href="group_view.php?id=<?= $group['id'] ?>"
                                            style="color: var(--primary-color); text-decoration: none;">
                                            <?= htmlspecialchars($group['name']) ?>
                                        </a>
                                    </h2>
                                    <p style="color: var(--text-muted); margin: 0 0 0.5rem 0;">
                                        <span class="badge"
                                            aria-label="Csoport típusa: <?= $group['type'] === 'public' ? 'Nyilvános' : 'Zárt' ?>"
                                            style="background: <?= $group['type'] === 'public' ? '#4caf50' : '#ff9800' ?>; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.85rem;">
                                            <?= $group['type'] === 'public' ? 'Nyilvános' : 'Zárt' ?>
                                        </span>
                                        ·
                                        <?= $group['member_count'] ?> tag
                                        · Létrehozta:
                                        <?= htmlspecialchars($group['creator_name']) ?>
                                    </p>
                                    <?php if ($group['description']): ?>
                                        <p style="margin: 0.5rem 0 0 0; color: var(--text-color);">
                                            <?= htmlspecialchars(mb_substr($group['description'], 0, 150)) ?>
                                            <?= mb_strlen($group['description']) > 150 ? '...' : '' ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <?php if ($group['is_member'] > 0): ?>
                                        <span class="badge" aria-label="Ön már tagja ennek a csoportnak"
                                            style="background: var(--primary-color); color: white; padding: 0.5rem 1rem;">Tag
                                            vagy</span>
                                    <?php endif; ?>
                                    <a href="group_view.php?id=<?= $group['id'] ?>" class="btn"
                                        aria-label="<?= htmlspecialchars($group['name']) ?> csoport részletei">Megtekintés</a>
                                </div>
                            </article>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="card" style="text-align: center; padding: 3rem;">
                        <p style="color: var(--text-muted); font-size: 1.1rem;">
                            Nincs megjeleníthető csoport.
                        </p>
                        <a href="group_create.php" class="btn" style="margin-top: 1rem;">Hozz létre egy új csoportot!</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </main>

    <?php include 'footer.php'; ?>
</body>

</html>