<?php
// user_wall.php - Felhasználói üzenőfal / Profil frissítések
require_once 'db.php';
require_once 'header.php';

// Pagination variables
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Fetch users with recent bio updates
// We use bio_updated_at if available, otherwise fallback logic if needed (but we added the column)
// We filter for approved users who are not hidden
$sql = "SELECT id, nickname, bio, profile_image, bio_updated_at 
        FROM users 
        WHERE status = 'approved' 
          AND is_hidden = 0 
          AND bio_updated_at IS NOT NULL 
        ORDER BY bio_updated_at DESC 
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();

// Count total for pagination
$countSql = "SELECT COUNT(*) FROM users WHERE status = 'approved' AND is_hidden = 0 AND bio_updated_at IS NOT NULL";
$total_updates = $pdo->query($countSql)->fetchColumn();
$total_pages = ceil($total_updates / $limit);

// Helper function for relative time
function time_elapsed_string($datetime, $full = false)
{
    if (!$datetime)
        return '';
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    // Hungarian translation for time units
    $string = [
        'y' => 'éve',
        'm' => 'hónapja',
        'w' => 'hete',
        'd' => 'napja',
        'h' => 'órája',
        'i' => 'perce',
        's' => 'másodperce',
    ];
    $string_v = [
        'y' => 'év',
        'm' => 'hónap',
        'w' => 'hét',
        'd' => 'nap',
        'h' => 'óra',
        'i' => 'perc',
        's' => 'másodperc',
    ];

    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v; // e.g. 2 napja
            // Just return the largest unit
            return "Frissítette a bemutatkozóját – " . $v;
        }
    }
    return 'Épp most';
}

function get_profile_image_url($image_path)
{
    if (!empty($image_path) && file_exists('uploads/' . $image_path)) {
        return 'uploads/' . htmlspecialchars($image_path);
    }
    return null; // Will trigger default avatar in HTML
}

?>

<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Felhasználói Fal - Szívhangja</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <style>
        /* User Wall Specific Styles */
        .wall-container {
            max-width: 600px;
            /* Narrow readable feed */
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .wall-title {
            text-align: center;
            margin-bottom: 2rem;
            color: var(--text-main, #333);
            font-size: 1.8rem;
        }

        .wall-feed {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        /* Card Component */
        .wall-card {
            background: var(--bg-card, #fff);
            border: 1px solid var(--border-color, #e0e0e0);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .wall-card:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border-color: var(--primary-color, #e91e63);
        }

        /* Card Header: Image + Name + Valid */
        .card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .card-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--bg-soft, #f5f5f5);
            flex-shrink: 0;
            background: #eee;
        }

        .card-user-info {
            display: flex;
            flex-direction: column;
        }

        .card-username {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-main, #000);
            text-decoration: none;
        }

        .card-username:hover {
            text-decoration: underline;
            color: var(--primary-color, #e91e63);
        }

        .card-meta {
            font-size: 0.85rem;
            color: var(--text-muted, #666);
        }

        /* Bio Content */
        .card-content {
            font-size: 1rem;
            color: var(--text-body, #333);
            line-height: 1.6;
        }

        .bio-text {
            word-wrap: break-word;
        }

        .read-more {
            color: var(--primary-color, #e91e63);
            font-weight: 600;
            text-decoration: none;
            margin-left: 0.5rem;
            font-size: 0.9rem;
        }

        .read-more:hover {
            text-decoration: underline;
        }

        /* Default Avatar SVG placeholder style */
        .default-avatar-wall {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #757575;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 3rem;
        }

        .page-link {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border-color, #ccc);
            border-radius: 5px;
            text-decoration: none;
            color: var(--text-main, #333);
            background: #fff;
        }

        .page-link.active {
            background: var(--primary-color, #e91e63);
            color: #fff;
            border-color: var(--primary-color, #e91e63);
        }

        @media (max-width: 600px) {
            .wall-container {
                padding: 1rem;
            }

            .wall-card {
                padding: 1rem;
            }

            .card-avatar {
                width: 50px;
                height: 50px;
            }
        }
    </style>
</head>

<body>

    <main class="wall-container" role="main">
        <h1 class="wall-title">Legfrissebb Adatlapok</h1>

        <ul class="wall-feed" style="list-style: none; padding: 0;" role="list">
            <?php if (count($users) > 0): ?>
                <?php foreach ($users as $user): ?>
                    <li>
                        <article class="wall-card">
                            <header class="card-header">
                                <a href="profile.php?id=<?= $user['id'] ?>"
                                    aria-label="<?= htmlspecialchars($user['nickname']) ?> profiljának megtekintése">
                                    <div class="card-avatar">
                                        <?= render_avatar($user, 'medium') ?>
                                    </div>
                                </a>

                                <div class="card-user-info">
                                    <a href="profile.php?id=<?= $user['id'] ?>" class="card-username">
                                        <?= htmlspecialchars($user['nickname']) ?>
                                    </a>
                                    <time class="card-meta" datetime="<?= htmlspecialchars($user['bio_updated_at']) ?>">
                                        <?= time_elapsed_string($user['bio_updated_at']) ?>
                                    </time>
                                </div>
                            </header>

                            <div class="card-content">
                                <?php
                                $bio = $user['bio'];
                                $max_len = 150;
                                if (mb_strlen($bio) > $max_len) {
                                    $display_bio = mb_substr($bio, 0, $max_len) . '...';
                                    $has_more = true;
                                } else {
                                    $display_bio = $bio;
                                    $has_more = false;
                                }
                                // Convert line breaks and escape
                                $clean_bio = nl2br(htmlspecialchars($display_bio));
                                ?>

                                <div class="bio-text">
                                    <?= $clean_bio ?: '<span class="empty-placeholder" style="color:#717171;">(Nincs bemutatkozás megadva)</span>' ?>
                                    <?php if ($has_more): ?>
                                        <a href="profile.php?id=<?= $user['id'] ?>" class="read-more"
                                            aria-label="Tovább olvasom <?= htmlspecialchars($user['nickname']) ?> bemutatkozását">Tovább
                                            olvasom</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li style="text-align: center; color: #666; padding: 2rem;">
                    <p>Még nincsenek frissítések.</p>
                </li>
            <?php endif; ?>
        </ul>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav class="pagination" aria-label="Lapozás">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="page-link">« Előző</a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?= $i ?>" class="page-link <?= $i == $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="page-link">Következő »</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

    </main>

    <?php include 'footer.php'; ?>

</body>

</html>