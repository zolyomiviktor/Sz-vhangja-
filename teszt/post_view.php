<?php
session_start();
require_once 'db.php';
require_once 'forum_helper.php';

$postId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$postId) {
    header('Location: forum.php');
    exit;
}

// Poszt lekérése
$stmt = $pdo->prepare("
    SELECT fp.*, u.nickname as author, fc.name as category_name 
    FROM forum_posts fp
    JOIN users u ON fp.user_id = u.id
    JOIN forum_categories fc ON fp.category_id = fc.id
    WHERE fp.id = :id AND fp.is_approved = 1
");
$stmt->execute([':id' => $postId]);
$post = $stmt->fetch();

if (!$post) {
    die("A bejegyzés nem található vagy moderálásra vár.");
}

// Kommentek lekérése (Approved + a saját nem jóváhagyott kommentjei)
$stmt_comments = $pdo->prepare("
    SELECT fc.*, u.nickname as author 
    FROM forum_comments fc
    JOIN users u ON fc.user_id = u.id
    WHERE fc.post_id = :post_id 
    AND (fc.is_approved = 1 OR fc.user_id = :current_user_id)
    ORDER BY fc.created_at ASC
");
$stmt_comments->execute([
    ':post_id' => $postId,
    ':current_user_id' => $currentUserId
]);
$comments = $stmt_comments->fetchAll();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($post['title']) ?> – Szívhangja Fórum</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        .post-detail-card {
            background: white; border-radius: 40px; padding: 4rem;
            box-shadow: var(--shadow-soft); margin: 3rem auto; max-width: 900px;
            border: 1px solid rgba(0,0,0,0.03);
        }
        .comment-card {
            background: #fafbfc; border-radius: 24px; padding: 2rem;
            margin-bottom: 1.5rem; border: 1px solid #f0f0f0;
        }
        .comment-form textarea {
            width: 100%; border-radius: 20px; padding: 1.5rem;
            border: 2px solid #f0f0f0; margin-bottom: 1.5rem; transition: border-color 0.3s;
        }
        .comment-form textarea:focus { border-color: var(--primary-coral); outline: none; }
    </style>
</head>
<body>
    <?php require_once 'header.php'; ?>

    <main id="main-content" style="padding: 0 1.5rem;">
        <div style="max-width: 900px; margin: 2rem auto;">
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert" style="margin-bottom: 2rem; background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 1rem 2rem; border-radius: 20px;">
                    <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['info'])): ?>
                <div class="alert" style="margin-bottom: 2rem; background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 1rem 2rem; border-radius: 20px;">
                    <?= $_SESSION['info']; unset($_SESSION['info']); ?>
                </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert" style="margin-bottom: 2rem; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 1rem 2rem; border-radius: 20px;">
                    <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <a href="forum.php" style="color: var(--primary-coral); text-decoration: none; font-weight: 700; display: flex; align-items: center; gap: 8px; margin-bottom: 2rem;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                Vissza a fórumhoz
            </a>

            <article class="post-detail-card">
                <header style="margin-bottom: 2.5rem;">
                    <span style="background: var(--bg-soft); color: var(--primary-coral); padding: 0.5rem 1.2rem; border-radius: 50px; font-weight: 800; font-size: 0.8rem; text-transform: uppercase;">
                        <?= htmlspecialchars($post['category_name']) ?>
                    </span>
                    <h1 style="margin: 1.5rem 0 1rem; font-size: 2.5rem; line-height: 1.2; font-family: 'Montserrat', sans-serif; color: var(--deep-charcoal);">
                        <?= htmlspecialchars($post['title']) ?>
                    </h1>
                    <div style="display: flex; align-items: center; gap: 12px; color: #999; font-weight: 600;">
                        <div style="width: 32px; height: 32px; background: var(--primary-gradient); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                             <?= mb_substr($post['is_anonymous'] ? '?' : $post['author'], 0, 1) ?>
                        </div>
                        <span><?= $post['is_anonymous'] ? 'Anonim Felhasználó' : htmlspecialchars($post['author']) ?></span>
                        <span>•</span>
                        <time><?= date('Y. m. d. H:i', strtotime($post['created_at'])) ?></time>
                    </div>
                </header>

                <div style="font-size: 1.2rem; line-height: 1.8; color: #444; margin-bottom: 4rem;">
                    <?= nl2br(htmlspecialchars($post['content'])) ?>
                </div>

                <section style="border-top: 1px solid #eee; padding-top: 3rem;">
                    <h2 style="font-size: 1.5rem; margin-bottom: 2rem; font-family: 'Montserrat', sans-serif;">Hozzászólások (<?= count($comments) ?>)</h2>

                    <?php foreach ($comments as $comment): ?>
                        <div class="comment-card" style="<?= $comment['is_approved'] == 0 ? 'border-color: #ffddd2; background: #fffaf0;' : '' ?>">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span style="font-weight: 700; color: var(--deep-charcoal);"><?= htmlspecialchars($comment['author']) ?></span>
                                    <?php if ($comment['is_approved'] == 0): ?>
                                        <span style="font-size: 0.7rem; background: #ffbb33; color: white; padding: 2px 8px; border-radius: 4px; font-weight: 800; text-transform: uppercase;">Moderálásra vár</span>
                                    <?php endif; ?>
                                </div>
                                <time style="font-size: 0.8rem; color: #bbb;"><?= date('Y.m.d. H:i', strtotime($comment['created_at'])) ?></time>
                            </div>
                            <p style="margin: 0; color: #666; line-height: 1.6;"><?= nl2br(htmlspecialchars($comment['content'])) ?></p>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($comments)): ?>
                        <p style="color: #bbb; font-style: italic; text-align: center; margin-bottom: 3rem;">Még nincsenek hozzászólások. Oszd meg a véleményed!</p>
                    <?php endif; ?>

                    <!-- Comment Form -->
                    <div class="comment-form" style="margin-top: 3rem; background: var(--bg-soft); padding: 2.5rem; border-radius: 32px;">
                        <h3 style="margin-top: 0; margin-bottom: 1.5rem; font-size: 1.2rem;">Szólj hozzá</h3>
                        <form action="add_comment.php" method="POST">
                            <input type="hidden" name="post_id" value="<?= $postId ?>">
                            <textarea name="content" rows="4" placeholder="Írd le a hozzászólásodat..." required></textarea>
                            <button type="submit" class="btn" style="width: 100%;">Küldés</button>
                        </form>
                    </div>
                </section>
            </article>
        </div>
    </main>

    <?php include 'footer.php'; ?>
</body>
</html>
