<?php
// events.php - Események listája
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Szűrés
$filter = $_GET['filter'] ?? 'upcoming';
$type_filter = $_GET['type'] ?? 'all';

$sql = "SELECT e.*, u.nickname as creator_name,
        (SELECT COUNT(*) FROM event_participants WHERE event_id = e.id AND status = 'going') as participant_count,
        (SELECT status FROM event_participants WHERE event_id = e.id AND user_id = ?) as user_status
        FROM events e
        JOIN users u ON e.creator_id = u.id
        WHERE 1=1";

$params = [$user_id];

if ($filter === 'upcoming') {
    $sql .= " AND e.event_date >= NOW()";
} elseif ($filter === 'past') {
    $sql .= " AND e.event_date < NOW()";
} elseif ($filter === 'my_events') {
    $sql .= " AND EXISTS (SELECT 1 FROM event_participants WHERE event_id = e.id AND user_id = ? AND status = 'going')";
    $params[] = $user_id;
}

if ($type_filter !== 'all') {
    $sql .= " AND e.type = ?";
    $params[] = $type_filter;
}

$sql .= " ORDER BY e.event_date ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Események - Szívhangja</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>

<body>
    <?php include 'header.php'; ?>

    <main id="main-content" role="main">
        <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h1>Események</h1>
                <a href="event_create.php" class="btn">Új esemény létrehozása</a>
            </div>

            <!-- Szűrés -->
            <div class="card" style="margin-bottom: 2rem;">
                <form method="GET" action="events.php" style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <div>
                        <label for="filter" class="sr-only">Időszak</label>
                        <select id="filter" name="filter" aria-label="Időszak szűrése">
                            <option value="upcoming" <?= $filter === 'upcoming' ? 'selected' : '' ?>>Közelgő események
                            </option>
                            <option value="past" <?= $filter === 'past' ? 'selected' : '' ?>>Elmúlt események</option>
                            <option value="my_events" <?= $filter === 'my_events' ? 'selected' : '' ?>>Eseményeim</option>
                        </select>
                    </div>
                    <div>
                        <label for="type" class="sr-only">Típus</label>
                        <select id="type" name="type" aria-label="Esemény típusa">
                            <option value="all" <?= $type_filter === 'all' ? 'selected' : '' ?>>Minden típus</option>
                            <option value="online" <?= $type_filter === 'online' ? 'selected' : '' ?>>Online</option>
                            <option value="offline" <?= $type_filter === 'offline' ? 'selected' : '' ?>>Személyes</option>
                        </select>
                    </div>
                    <button type="submit" class="btn">Szűrés</button>
                </form>
            </div>

            <!-- Események listája -->
            <ul style="display: grid; gap: 1.5rem; list-style: none; padding: 0;" aria-label="Események listája">
                <?php if (count($events) > 0): ?>
                    <?php foreach ($events as $event): ?>
                        <li>
                            <article class="card">
                                <div style="display: flex; gap: 2rem; align-items: start;">
                                    <div
                                        style="text-align: center; min-width: 80px; padding: 1rem; background: var(--primary-color); color: white; border-radius: 8px;">
                                        <div style="font-size: 2rem; font-weight: bold; line-height: 1;">
                                            <?= date('d', strtotime($event['event_date'])) ?>
                                        </div>
                                        <div style="font-size: 0.9rem;">
                                            <?= date('M', strtotime($event['event_date'])) ?>
                                        </div>
                                    </div>
                                    <div style="flex: 1;">
                                        <h2 style="margin: 0 0 0.5rem 0;">
                                            <a href="event_view.php?id=<?= $event['id'] ?>"
                                                style="color: var(--primary-color); text-decoration: none;">
                                                <?= htmlspecialchars($event['name']) ?>
                                            </a>
                                        </h2>
                                        <p style="color: var(--text-muted); margin: 0 0 0.5rem 0;">
                                            <span class="badge"
                                                aria-label="Esemény típusa: <?= $event['type'] === 'online' ? 'Online' : 'Személyes' ?>"
                                                style="background: <?= $event['type'] === 'online' ? '#2196f3' : '#4caf50' ?>; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.85rem;">
                                                <?= $event['type'] === 'online' ? 'Online' : 'Személyes' ?>
                                            </span>
                                            ·
                                            <?= date('Y. m. d. H:i', strtotime($event['event_date'])) ?>
                                            <?php if ($event['location']): ?>
                                                ·
                                                <?= htmlspecialchars($event['location']) ?>
                                            <?php endif; ?>
                                        </p>
                                        <?php if ($event['description']): ?>
                                            <p style="margin: 0.5rem 0 0 0;">
                                                <?= htmlspecialchars(mb_substr($event['description'], 0, 150)) ?>
                                                <?= mb_strlen($event['description']) > 150 ? '...' : '' ?>
                                            </p>
                                        <?php endif; ?>
                                        <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem; color: var(--text-muted);">
                                            <?= $event['participant_count'] ?> résztvevő
                                        </p>
                                    </div>
                                    <div>
                                        <?php if ($event['user_status'] === 'going'): ?>
                                            <span class="badge" aria-label="Ön már jelezte részvételét ezen az eseményen"
                                                style="background: var(--primary-color); color: white; padding: 0.5rem 1rem;">Részt
                                                veszel</span>
                                        <?php endif; ?>
                                        <a href="event_view.php?id=<?= $event['id'] ?>" class="btn"
                                            aria-label="<?= htmlspecialchars($event['name']) ?> esemény részletei">Részletek</a>
                                    </div>
                                </div>
                            </article>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="card" style="text-align: center; padding: 3rem;">
                        <p style="color: var(--text-muted); font-size: 1.1rem;">
                            Nincs megjeleníthető esemény.
                        </p>
                        <a href="event_create.php" class="btn" style="margin-top: 1rem;">Hozz létre egy új eseményt!</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </main>

    <?php include 'footer.php'; ?>
</body>

</html>