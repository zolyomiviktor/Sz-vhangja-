<?php
session_start();
require_once 'db.php';
require_once 'forum_helper.php';

$categoryId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$categoryId) {
    header('Location: forum.php');
    exit;
}

// Kategória adatok
$stmt_cat = $pdo->prepare("SELECT * FROM forum_categories WHERE id = :id");
$stmt_cat->execute([':id' => $categoryId]);
$category = $stmt_cat->fetch();

if (!$category) {
    die("A kategória nem található.");
}

// Posztok lekérése ebben a kategóriában
$stmt_posts = $pdo->prepare("
    SELECT fp.*, u.nickname as author, fc.name as category_name
    FROM forum_posts fp
    JOIN users u ON fp.user_id = u.id
    JOIN forum_categories fc ON fp.category_id = fc.id
    WHERE fp.category_id = :category_id AND fp.is_approved = 1
    ORDER BY fp.created_at DESC
");
$stmt_posts->execute([':category_id' => $categoryId]);
$posts = $stmt_posts->fetchAll();

// Összes kategória a sidebarhoz
$categories = $pdo->query("SELECT * FROM forum_categories ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($category['name']) ?> – Szívhangja Fórum</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        .forum-hero {
            background: linear-gradient(145deg, #fff 0%, #fff5f5 100%);
            padding: 4rem 2rem; border-radius: 40px; text-align: center;
            margin-top: 2rem; border: 1px solid rgba(255,127,80,0.1);
        }
        .forum-grid { display: grid; grid-template-columns: 1fr 320px; gap: 3rem; margin-top: 4rem; }
        @media (max-width: 1024px) { .forum-grid { grid-template-columns: 1fr; } }

        .forum-post-card {
            background: white; border-radius: 32px; padding: 2.5rem;
            border: 1px solid #f0f0f0; transition: all 0.4s ease;
            cursor: pointer; position: relative;
        }
        .forum-post-card:hover { transform: translateY(-10px); border-color: var(--primary-coral); box-shadow: var(--shadow-hover); }

        .category-sidebar-link {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1.2rem 1.5rem; background: white; border-radius: 16px;
            border: 1px solid #f0f0f0; margin-bottom: 0.8rem; transition: all 0.2s;
            font-weight: 600; color: var(--text-main);
        }
        .category-sidebar-link.active { border-color: var(--primary-coral); background: var(--bg-soft); color: var(--primary-coral); }
    </style>
</head>
<body>
    <?php require_once 'header.php'; ?>

    <main id="main-content" style="max-width: 1300px; margin: 0 auto; padding: 0 1.5rem 5rem;">
        <section class="forum-hero">
            <h1 style="color: var(--primary-color); font-size: 2.8rem; margin-bottom: 1rem; font-family: 'Montserrat', sans-serif;">
                <?= htmlspecialchars($category['name']) ?>
            </h1>
            <p style="font-size: 1.1rem; color: #888; max-width: 600px; margin: 0 auto;">
                <?= htmlspecialchars($category['description']) ?>
            </p>
        </section>

        <div class="forum-grid">
            <section aria-label="Bejegyzések ebben a kategóriában">
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 2.5rem;">
                    <a href="forum.php" style="color: var(--primary-coral); text-decoration: none; display: flex; align-items: center; gap: 5px; font-weight: 700;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                        Minden kategória
                    </a>
                </div>

                <?php if (empty($posts)): ?>
                    <div class="card text-center" style="padding: 5rem 2rem; border-radius: 32px;">
                        <p style="color: #bbb; font-style: italic;">Ebben a kategóriában még nincsenek bejegyzések.</p>
                        <a href="forum.php?action=new" class="btn" style="margin-top: 1.5rem; display: inline-block;">Legyél te az első!</a>
                    </div>
                <?php else: ?>
                    <div style="display: grid; gap: 2rem;">
                        <?php foreach ($posts as $post): ?>
                            <article class="forum-post-card" onclick="window.location.href='post_view.php?id=<?= $post['id'] ?>'">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                                    <span style="background: var(--bg-soft); color: var(--primary-coral); padding: 0.5rem 1.2rem; border-radius: 50px; font-weight: 800; font-size: 0.75rem; text-transform: uppercase;">
                                        <?= htmlspecialchars($category['name']) ?>
                                    </span>
                                    <time style="font-size: 0.85rem; color: #999; font-weight: 600;"><?= date('Y. m. d.', strtotime($post['created_at'])) ?></time>
                                </div>

                                <h3 style="margin: 0 0 1rem; font-size: 1.5rem; line-height: 1.3; color: var(--deep-charcoal); font-family: 'Montserrat', sans-serif;">
                                    <?= htmlspecialchars($post['title']) ?>
                                </h3>
                                <p style="color: #666; line-height: 1.6; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 2rem;">
                                    <?= htmlspecialchars($post['content']) ?>
                                </p>

                                <div style="display: flex; align-items: center; justify-content: space-between; padding-top: 1.5rem; border-top: 1px solid #f5f5f5;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 32px; height: 32px; background: var(--primary-gradient); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800; font-size: 0.8rem;">
                                            <?= mb_substr($post['author'], 0, 1) ?>
                                        </div>
                                        <span style="font-weight: 700; font-size: 0.9rem; color: #333;"><?= htmlspecialchars($post['author']) ?></span>
                                    </div>
                                    <span style="color: var(--primary-coral); font-weight: 800; font-size: 0.9rem; display: flex; align-items: center; gap: 5px;">
                                        Megnyitás <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                                    </span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <aside>
                <div style="background: white; border-radius: 32px; padding: 2.5rem; border: 1px solid #f0f0f0; position: sticky; top: 120px;">
                    <h2 style="font-size: 1rem; text-transform: uppercase; letter-spacing: 1.5px; color: #999; margin-bottom: 2rem;">Kategóriák</h2>
                    <nav>
                        <?php foreach ($categories as $cat): ?>
                            <a href="category_view.php?id=<?= $cat['id'] ?>" class="category-sidebar-link <?= $cat['id'] == $categoryId ? 'active' : '' ?>">
                                <span><?= htmlspecialchars($cat['name']) ?></span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>
            </aside>
        </div>
    </main>

    <?php include 'footer.php'; ?>
</body>
</html>
