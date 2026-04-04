<?php
session_start();
require_once 'db.php';
require_once 'forum_helper.php';

// Felhasználói adatok
$currentUserId = $_SESSION['user_id'] ?? null;
$hasAccess = canAccessForum($pdo, $currentUserId);

// Kategóriák lekérése
$stmt_cats = $pdo->query("SELECT * FROM forum_categories ORDER BY name ASC");
$categories = $stmt_cats->fetchAll();

// Legfrissebb posztok lekérése (Approved)
$stmt_posts = $pdo->query("
    SELECT fp.*, u.nickname as author, fc.name as category_name
    FROM forum_posts fp
    JOIN users u ON fp.user_id = u.id
    JOIN forum_categories fc ON fp.category_id = fc.id
    WHERE fp.is_approved = 1
    ORDER BY fp.created_at DESC
    LIMIT 10
");
$posts = $stmt_posts->fetchAll();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Közösségi Fórum – Szívhangja</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --forum-coral: #ff7f50;
            --forum-pink: #cd1355;
            --glass-bg: rgba(255, 255, 255, 0.7);
            --card-shadow: 0 10px 30px rgba(0,0,0,0.04);
        }
        body { background-color: #f8fafc; }
        
        .forum-hero {
            background: linear-gradient(135deg, var(--forum-coral) 0%, var(--forum-pink) 100%);
            padding: 5rem 2rem; border-radius: 50px; text-align: center;
            margin-top: 2rem; position: relative; overflow: hidden; color: white;
            box-shadow: 0 20px 50px rgba(205, 19, 85, 0.15);
        }
        .forum-hero h1, .forum-hero p { position: relative; z-index: 2; }
        .forum-hero::after {
            content: ''; position: absolute; top: -50%; right: -10%; width: 500px; height: 500px;
            background: rgba(255,255,255,0.1); border-radius: 50%; z-index: 1;
        }

        .forum-grid { display: grid; grid-template-columns: 1fr 340px; gap: 3rem; margin-top: 4rem; }
        @media (max-width: 1100px) { .forum-grid { grid-template-columns: 1fr; } }

        .forum-post-card {
            background: white; border-radius: 32px; padding: 2.2rem;
            border: 1px solid #f1f5f9; transition: all 0.4s ease;
            cursor: pointer; box-shadow: var(--card-shadow);
        }
        .forum-post-card:hover { transform: translateY(-8px); border-color: var(--forum-coral); box-shadow: 0 20px 40px rgba(0,0,0,0.08); }

        .glass-sidebar {
            background: var(--glass-bg); backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.5);
            border-radius: 40px; padding: 2.5rem; position: sticky; top: 120px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.03);
        }

        /* Modal Styles */
        #newPostModal {
            display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            z-index: 9999; align-items: center; justify-content: center; padding: 1.5rem;
        }
        .modal-content {
            background: white; width: 100%; max-width: 600px; border-radius: 40px;
            padding: 3.5rem; position: relative; box-shadow: 0 40px 120px rgba(0,0,0,0.3);
            animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes slideUp { from { transform: translateY(40px); opacity: 0; } }

        .modal-close {
            position: absolute; top: 1.8rem; right: 1.8rem; background: #f1f5f9;
            border: none; width: 44px; height: 44px; border-radius: 50%; cursor: pointer;
            display: flex; align-items: center; justify-content: center; color: #64748b;
        }
        .modal-close:hover { background: #e2e8f0; color: var(--forum-pink); }

        .form-control {
            width: 100%; padding: 1.1rem 1.4rem; border-radius: 18px;
            border: 2px solid #e2e8f0; font-size: 1rem; transition: all 0.2s;
            margin-bottom: 0.5rem; font-family: inherit;
        }
        .form-control:focus { border-color: var(--forum-coral); outline: none; box-shadow: 0 0 0 4px rgba(255,127,80,0.1); }
    </style>
</head>
<body>
    <?php require_once 'header.php'; ?>

    <main id="main-content" style="max-width: 1400px; margin: 0 auto; padding: 0 2rem 6rem;">
        
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert" style="margin-top: 2rem; background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 1rem 2rem; border-radius: 20px;">
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['info'])): ?>
            <div class="alert" style="margin-top: 2rem; background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 1rem 2rem; border-radius: 20px;">
                <?= $_SESSION['info']; unset($_SESSION['info']); ?>
            </div>
        <?php endif; ?>

        <!-- Hero Section -->
        <section class="forum-hero">
            <h1 style="font-size: 3.5rem; margin-bottom: 1rem; font-family: 'Montserrat', sans-serif;">Közösségi Fórum</h1>
            <p style="font-size: 1.3rem; opacity: 0.9; max-width: 700px; margin: 0 auto 3rem; line-height: 1.6;">
                Oszd meg a gondolataidat, kérj tanácsot, vagy csak kapcsolódj ki a közösségünk tagjaival egy barátságos felületen.
            </p>

            <button id="openNewPostBtn" class="btn" style="background: white; color: var(--forum-pink); min-width: 280px; padding: 1.3rem; font-size: 1.1rem; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="margin-right:10px;" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                Új beszélgetés indítása
            </button>
        </section>

        <div class="forum-grid">
            <!-- Latest Conversations -->
            <section aria-labelledby="latest-title">
                <header style="display: flex; align-items: center; gap: 15px; margin-bottom: 3rem;">
                    <div style="width: 10px; height: 35px; background: var(--forum-coral); border-radius: 5px;"></div>
                    <h2 id="latest-title" style="margin: 0; font-size: 2rem; font-family: 'Montserrat', sans-serif;">Friss témák</h2>
                </header>

                <?php if (empty($posts)): ?>
                    <div class="forum-post-card text-center" style="padding: 4rem;">
                        <span style="font-size: 3rem;">💬</span>
                        <h3 style="color: #94a3b8; margin-top: 1rem;">Még nincsenek bejegyzések. Legyél te az első!</h3>
                    </div>
                <?php else: ?>
                    <div style="display: grid; gap: 2rem;">
                        <?php foreach ($posts as $post): ?>
                            <article class="forum-post-card" onclick="window.location.href='post_view.php?id=<?= $post['id'] ?>'">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                                    <span style="background: #fff1f2; color: #e11d48; padding: 0.5rem 1.2rem; border-radius: 50px; font-weight: 800; font-size: 0.75rem; text-transform: uppercase;">
                                        <?= htmlspecialchars($post['category_name']) ?>
                                    </span>
                                    <time style="font-size: 0.85rem; color: #94a3b8; font-weight: 600;"><?= date('Y. m. d.', strtotime($post['created_at'])) ?></time>
                                </div>
                                
                                <h3 style="margin: 0 0 1.2rem; font-size: 1.7rem; color: #1e293b; font-family: 'Montserrat', sans-serif; line-height: 1.3;">
                                    <?= htmlspecialchars($post['title']) ?>
                                </h3>
                                <p style="color: #64748b; line-height: 1.7; font-size: 1.1rem; margin-bottom: 2.5rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?= htmlspecialchars($post['content']) ?>
                                </p>

                                <div style="display: flex; align-items: center; justify-content: space-between; padding-top: 1.5rem; border-top: 1px solid #f1f5f9;">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div style="width: 40px; height: 40px; background: var(--primary-gradient); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800;">
                                            <?= mb_substr($post['is_anonymous'] ? '?' : $post['author'], 0, 1) ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 700; color: #334155;"><?= $post['is_anonymous'] ? 'Anonim Felhasználó' : htmlspecialchars($post['author']) ?></div>
                                            <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase;">Téma indító</div>
                                        </div>
                                    </div>
                                    <span style="color: var(--forum-pink); font-weight: 800; font-size: 0.95rem; display: flex; align-items: center; gap: 8px;">
                                        Olvasom <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                                    </span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Sidebar -->
            <aside>
                <div class="glass-sidebar">
                    <h2 style="font-size: 0.9rem; text-transform: uppercase; letter-spacing: 2px; color: #94a3b8; margin-bottom: 2rem; font-weight: 800;">Kategóriák</h2>
                    <div role="navigation" aria-label="Kategória navigáció" style="display: flex; flex-direction: column;">
                        <?php foreach ($categories as $cat): ?>
                            <a href="category_view.php?id=<?= $cat['id'] ?>" style="display: flex; align-items: center; justify-content: space-between; padding: 1.2rem 1.5rem; background: white; border-radius: 20px; border: 1px solid #f1f5f9; margin-bottom: 0.8rem; transition: all 0.3s; font-weight: 600; color: #475569; text-decoration: none;">
                                <span><?= htmlspecialchars($cat['name']) ?></span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color: var(--forum-coral);"><path d="m9 18 6-6-6-6"/></svg>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </aside>
        </div>
    </main>

    <!-- Post Modal -->
    <div id="newPostModal" role="dialog" aria-modal="true" aria-labelledby="new-post-title">
        <div class="modal-content">
            <button class="modal-close" aria-label="Bezárás">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>

            <h2 id="new-post-title" style="margin: 0 0 0.5rem; color: var(--forum-pink); font-family: 'Montserrat', sans-serif; font-size: 2.2rem;">Új beszélgetés</h2>
            <p style="color: #64748b; margin-bottom: 2.5rem;">Írj valamit, amivel segíthetsz másoknak vagy kérdezz bátran.</p>

            <form action="create_post.php" method="POST">
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 0.8rem; font-size: 0.9rem;">Válassz kategóriát</label>
                    <select name="category_id" class="form-control" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 0.8rem; font-size: 0.9rem;">Bejegyzés címe</label>
                    <input type="text" name="title" class="form-control" placeholder="Rövid, lényegretörő cím" required>
                </div>

                <div class="form-group" style="margin-bottom: 2rem;">
                    <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 0.8rem; font-size: 0.9rem;">Gondolataid</label>
                    <textarea name="content" class="form-control" rows="5" placeholder="Itt fejtheted ki bővebben..." required style="resize: none;"></textarea>
                </div>

                <div style="background: #f8fafc; padding: 1.2rem; border-radius: 20px; display: flex; align-items: center; gap: 12px; margin-bottom: 3rem;">
                    <input type="checkbox" name="is_anonymous" id="anon_cb" value="1" style="width: 20px; height: 20px; accent-color: var(--forum-pink);">
                    <label for="anon_cb" style="margin: 0; font-weight: 600; cursor: pointer; color: #475569;">Névtelenül szeretnék posztolni</label>
                </div>

                <button type="submit" class="btn" style="width: 100%; border-radius: 20px; padding: 1.3rem; font-weight: 800; font-size: 1.1rem; letter-spacing: 0.5px;">Közzététel</button>
            </form>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const openBtn = document.getElementById('openNewPostBtn');
            if (openBtn) {
                openBtn.addEventListener('click', () => {
                    Accessibility.openModal('newPostModal', openBtn);
                });
            }

            const p = new URLSearchParams(window.location.search);
            if (p.get('action') === 'new') {
                setTimeout(() => Accessibility.openModal('newPostModal', openBtn), 500);
            }
        });
    </script>
</body>
</html>
