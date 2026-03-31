<?php
require 'db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'notification_helper.php'; // Helper betöltése

// Felhasználó betöltése (Saját vagy másé)
$user_id = $_SESSION['user_id']; // Alapértelmezett: saját
$is_own_profile = true;

if (isset($_GET['id'])) {
    $requested_id = (int) $_GET['id'];
    if ($requested_id !== $user_id) {
        $user_id = $requested_id;
        $is_own_profile = false;
    }
}

// Adatok lekérdezése (+ status, is_hidden)
$stmt = $pdo->prepare("SELECT id, nickname, birth_date, gender, mobility_status, bio, profile_image, status, is_hidden, registration_date, last_login, unread_messages, residence, nationality, marital_status, children, sexual_orientation, height_cm, weight_kg, body_type, hair_color, hair_style, eye_color, piercing, tattoo, education, occupation, smoking, languages, hobbies, horoscope_sign, horoscope_text, ideal_age_min, ideal_age_max, ideal_residence FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Galéria képek lekérdezése
$stmt_gallery = $pdo->prepare("SELECT * FROM user_images WHERE user_id = ? ORDER BY created_at DESC");
$stmt_gallery->execute([$user_id]);
$gallery_images = $stmt_gallery->fetchAll();

$error_msg = '';
if (!$user) {
    $error_msg = "A felhasználó nem található.";
}

// Ha más profilját nézem, és nincs jóváhagyva, akkor hiba (biztonság)
if (!$error_msg && !$is_own_profile && $user['status'] !== 'approved') {
    $error_msg = "Ez a profil nem elérhető.";
}

// Profil megtekintés értesítés (csak ha más profilját nézem ÉS létezik/jóváhagyott a user)
if (!$is_own_profile) {
    $viewer_id = $_SESSION['user_id'];

    // Spam védelem
    if (!isset($_SESSION['viewed_profiles'])) {
        $_SESSION['viewed_profiles'] = [];
    }

    if (!in_array($user_id, $_SESSION['viewed_profiles'])) {

        // Lekérjük a néző nevét
        $stmt_viewer = $pdo->prepare("SELECT nickname FROM users WHERE id = ?");
        $stmt_viewer->execute([$viewer_id]);
        $viewer_name = $stmt_viewer->fetchColumn();

        // Értesítés létrehozása
        if (!$error_msg) {
            create_notification(
                $user_id,
                'profile_view',
                "$viewer_name megnézte a profilodat.",
                "profile.php?id=$viewer_id",
                "Valaki megnézte a profilodat!",
                "Szia!\n\n$viewer_name megnézte az adatlapodat a Szívhangján."
            );
        }

        // Megjelöljük, hogy láttuk
        $_SESSION['viewed_profiles'][] = $user_id;
    }
}

// Életkor számítás
$age_text = "Kor nincs megadva";
if (!empty($user['birth_date'])) {
    $birthDate = new DateTime($user['birth_date']);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
    $age_text = $age . " éves";
}

$location_placeholder = $user['residence'] ?: "Helyszín nincs megadva";

// Horoszkóp számítás
function get_zodiac($date)
{
    if (empty($date))
        return "Nincs megadva";
    $zodiac = [
        ['name' => 'Bak', 'start' => '01-01', 'end' => '01-19'],
        ['name' => 'Vízöntő', 'start' => '01-20', 'end' => '02-18'],
        ['name' => 'Halak', 'start' => '02-19', 'end' => '03-20'],
        ['name' => 'Kos', 'start' => '03-21', 'end' => '04-19'],
        ['name' => 'Bika', 'start' => '04-20', 'end' => '05-20'],
        ['name' => 'Ikrek', 'start' => '05-21', 'end' => '06-20'],
        ['name' => 'Rák', 'start' => '06-21', 'end' => '07-22'],
        ['name' => 'Oroszlán', 'start' => '07-23', 'end' => '08-22'],
        ['name' => 'Szűz', 'start' => '08-23', 'end' => '09-22'],
        ['name' => 'Mérleg', 'start' => '09-23', 'end' => '10-22'],
        ['name' => 'Skorpió', 'start' => '10-23', 'end' => '11-21'],
        ['name' => 'Nyilas', 'start' => '11-22', 'end' => '12-21'],
        ['name' => 'Bak', 'start' => '12-22', 'end' => '12-31'],
    ];
    $m = date('m', strtotime($date));
    $d = date('d', strtotime($date));
    $search = $m . '-' . $d;
    foreach ($zodiac as $z) {
        if ($search >= $z['start'] && $search <= $z['end'])
            return $z['name'];
    }
    return "Ismeretlen";
}

$calculated_horoscope = $user['horoscope_sign'] ?: get_zodiac($user['birth_date']);

// Helper a "Nincs megadva" szöveghez
function display_val($val, $suffix = '')
{
    if ($val === null || $val === '' || $val === 0)
        return '<span class="empty" aria-hidden="true">Nincs megadva</span><span class="sr-only">Adat nincs megadva</span>';
    return htmlspecialchars($val) . $suffix;
}
?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_own_profile ? "Saját Profil" : htmlspecialchars($user['nickname']) . " profilja" ?> - Szívhangja
    </title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>

<body>

    <?php include 'header.php'; ?>

    <main id="main-content" role="main">

        <?php if (isset($_SESSION['success'])): ?>
            <div role="alert" class="alert" style="background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7;">
                <?= $_SESSION['success'];
                unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div role="alert" class="alert" style="background: #ffebee; color: #c62828; border: 1px solid #ef5350;">
                <?= $_SESSION['error'];
                unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['updated'])): ?>
            <div role="alert" class="alert" style="background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                A profilod sikeresen frissült!
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['msg_sent'])): ?>
            <div role="alert" class="alert" style="background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                Üzenet sikeresen elküldve!
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="empty-state">
                <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                    stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <h3>Hiba történt</h3>
                <p><?= htmlspecialchars($error_msg) ?></p>
                <a href="browse.php" class="btn">Vissza a böngészéshez</a>
            </div>
        <?php else: ?>

            <div class="card" style="padding: 2.5rem;">

                <!-- FELSŐ SZEKCIÓ: 2 OSZLOPOS GRID (Header + Galéria/Sidebar) -->
                <div class="profile-header-grid">

                    <!-- BAL OLDAL: PROFIL HEADER (FLEX) -->
                    <div class="profile-header">
                        <div class="profile-photo-container">
                            <?= render_avatar($user, 'large') ?>
                        </div>

                        <div class="profile-info">
                            <div
                                style="margin-bottom: 0.5rem; display: flex; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                                <h1 style="margin: 0; font-size: 2.2rem; color: var(--text-main);">
                                    <?= htmlspecialchars($user['nickname']) ?>
                                </h1>

                                <span class="status-badge verified" aria-label="Ellenőrzött felhasználó">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                    </svg>
                                    Ellenőrzött
                                </span>

                                <?php if ($is_own_profile || (isset($user['last_login']) && strtotime($user['last_login']) > strtotime('-15 minutes'))): ?>
                                    <span class="status-badge online" aria-label="Jelenleg elérhető">
                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor" stroke="none"
                                            aria-hidden="true">
                                            <circle cx="12" cy="12" r="10"></circle>
                                        </svg>
                                        Online
                                    </span>
                                <?php endif; ?>
                            </div>

                            <p class="profile-subtitle">
                                <span><?= $age_text ?></span>
                                <span aria-hidden="true" class="dot-separator">•</span>
                                <span><?= htmlspecialchars($user['gender'] === 'ferfi' ? 'Férfi' : ($user['gender'] === 'no' ? 'Nő' : 'Egyéb')) ?></span>
                                <?php if ($user['residence']): ?>
                                    <span aria-hidden="true" class="dot-separator">•</span>
                                    <span><?= htmlspecialchars($user['residence']) ?></span>
                                <?php endif; ?>
                            </p>

                            <div class="flex-gap" style="align-items: center; flex-wrap: wrap;">
                                <?php if ($is_own_profile): ?>
                                    <a href="edit_profile.php" class="btn secondary"
                                        style="padding: 0.6rem 1.2rem; font-size: 0.9rem;">Profil szerkesztése</a>

                                    <form action="toggle_visibility.php" method="POST" style="margin: 0;">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <button type="submit" class="btn <?= $user['is_hidden'] ? 'secondary' : 'warning' ?>"
                                            style="padding: 0.6rem 1.2rem; font-size: 0.9rem;"
                                            aria-pressed="<?= $user['is_hidden'] ? 'true' : 'false' ?>">
                                            <?= $user['is_hidden'] ? 'Profil megjelenítése' : 'Profil elrejtése' ?>
                                        </button>
                                    </form>
                                    <p class="help-text" style="width: 100%; margin-top: 0.5rem; font-size: 0.8rem;">
                                        <?= $user['is_hidden'] ? 'Jelenleg el vagy rejtve a böngészési találatok közül.' : 'Ha elrejted a profilod, mások nem látnak a böngészésnél.' ?>
                                    </p>
                                <?php else: ?>
                                    <a href="messages.php?recipient_id=<?= $user['id'] ?>" class="btn"
                                        style="padding: 0.6rem 1.2rem; font-size: 0.9rem;">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                        </svg>
                                        Üzenet
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- JOBB OLDAL: GALÉRIA / MINI SIDEBAR -->
                    <div class="profile-gallery-column">
                        <?php if (count($gallery_images) > 0): ?>
                            <h3 style="font-size: 1rem; color: var(--text-muted); margin-bottom: 0.5rem;">Galéria</h3>
                            <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                <?php foreach ($gallery_images as $img): ?>
                                    <?php if (file_exists('uploads/' . $img['image_path'])): ?>
                                        <img src="uploads/<?= htmlspecialchars($img['image_path']) ?>" class="gallery-thumb"
                                            onclick="document.getElementById('main-profile-img').src = this.src; document.querySelectorAll('.gallery-thumb').forEach(el => el.classList.remove('active')); this.classList.add('active');"
                                            alt="Galéria kép - <?= htmlspecialchars($user['nickname']) ?>"
                                            onerror="this.src='assets/avatar-default.png'">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p style="color: #aaa; font-style: italic;">Nincsenek további képek a galériában.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 2rem 0;">

                <!-- TARTALMI BLOKKOK -->
                <div class="grid-responsive"
                    style="grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 3rem;">

                    <!-- BAL OLDAL: BEMUTATKOZÁS ÉS RÉSZLETEK -->
                    <div>
                        <div class="block">
                            <h3>Magamról</h3>
                            <p style="white-space: pre-line; color: var(--text-main);">
                                <?php if ($user['bio']): ?>
                                    <?= nl2br(htmlspecialchars($user['bio'])) ?>
                                <?php else: ?>
                                    <em style="color: #aaa;">A felhasználó még nem töltötte ki a bemutatkozását.</em>
                                <?php endif; ?>
                            </p>
                        </div>

                        <div class="block">
                            <h3>Személyes adatok</h3>
                            <dl class="data-list">
                                <div class="dl-group">
                                    <dt class="label">Családi állapot:</dt>
                                    <dd class="value <?= !$user['marital_status'] ? 'empty' : '' ?>">
                                        <?php
                                        $ms_map = ['single' => 'Egyedülálló', 'married' => 'Házas', 'divorced' => 'Elvált', 'widowed' => 'Özvegy'];
                                        $ms_val = $user['marital_status'] ? ($ms_map[$user['marital_status']] ?? $user['marital_status']) : '';
                                        echo $ms_val ?: '<span aria-hidden="true">Nincs megadva</span><span class="sr-only">Adat nincs megadva</span>';
                                        ?>
                                    </dd>
                                </div>
                                <div class="dl-group">
                                    <dt class="label">Gyermekek:</dt>
                                    <dd class="value <?= !$user['children'] ? 'empty' : '' ?>">
                                        <?= $user['children'] ?: '<span aria-hidden="true">Nincs megadva</span><span class="sr-only">Adat nincs megadva</span>' ?>
                                    </dd>
                                </div>
                                <div class="dl-group">
                                    <dt class="label">Végzettség:</dt>
                                    <dd class="value <?= !$user['education'] ? 'empty' : '' ?>">
                                        <?= $user['education'] ?: '<span aria-hidden="true">Nincs megadva</span><span class="sr-only">Adat nincs megadva</span>' ?>
                                    </dd>
                                </div>
                                <div class="dl-group">
                                    <dt class="label">Foglalkozás:</dt>
                                    <dd class="value <?= !$user['occupation'] ? 'empty' : '' ?>">
                                        <?= $user['occupation'] ?: '<span aria-hidden="true">Nincs megadva</span><span class="sr-only">Adat nincs megadva</span>' ?>
                                    </dd>
                                </div>
                                <div class="dl-group">
                                    <dt class="label">Beszélt nyelvek:</dt>
                                    <dd class="value <?= !$user['languages'] ? 'empty' : '' ?>">
                                        <?= $user['languages'] ?: '<span aria-hidden="true">Nincs megadva</span><span class="sr-only">Adat nincs megadva</span>' ?>
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div class="block">
                            <h3>Külső jellemzők</h3>
                            <dl class="data-list">
                                <div class="dl-group">
                                    <dt class="label">Magasság:</dt>
                                    <dd class="value=" <?= !$user['height_cm'] ? 'empty' : '' ?>">
                                        <?= $user['height_cm'] ? $user['height_cm'] . ' cm' : 'Nincs megadva' ?>
                                    </dd>
                                </div>
                                <div class="dl-group">
                                    <dt class="label">Testalkat:</dt>
                                    <dd class="value=" <?= !$user['body_type'] ? 'empty' : '' ?>">
                                        <?= $user['body_type'] ?: 'Nincs megadva' ?>
                                    </dd>
                                </div>
                                <div class="dl-group">
                                    <dt class="label">Szem színe:</dt>
                                    <dd class="value=" <?= !$user['eye_color'] ? 'empty' : '' ?>">
                                        <?= $user['eye_color'] ?: 'Nincs megadva' ?>
                                    </dd>
                                </div>
                                <div class="dl-group">
                                    <dt class="label">Haj színe:</dt>
                                    <dd class="value=" <?= !$user['hair_color'] ? 'empty' : '' ?>">
                                        <?= $user['hair_color'] ?: 'Nincs megadva' ?>
                                    </dd>
                                </div>
                                <div class="dl-group">
                                    <dt class="label">Mozgásállapot:</dt>
                                    <dd class="value">
                                        <?= $user['mobility_status'] ?: 'Nincs megadva' ?>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- JOBB OLDAL: HOROSZKÓP ÉS IDEÁL -->
                    <div>
                        <div class="block" style="background: var(--bg-soft); padding: 1.5rem; border-radius: 12px;">
                            <h3>Horoszkóp</h3>
                            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                                <span style="font-size: 2rem;" role="img" aria-label="Kiemelt állapot">✨</span>
                                <div>
                                    <strong
                                        style="display: block; font-size: 1.1rem;"><?= htmlspecialchars($calculated_horoscope) ?></strong>
                                    <span
                                        style="font-size: 0.9rem; font-style: italic; color: var(--text-muted); display: block; word-break: break-word;"><?= display_val($user['horoscope_text']) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="block">
                            <h3>Ideális társ</h3>
                            <dl class="data-list">
                                <?php if (!empty($user['ideal_age_min']) && !empty($user['ideal_age_max'])): ?>
                                    <div class="dl-group">
                                        <dt class="label">Életkor:</dt>
                                        <dd class="value"><?= (int) $user['ideal_age_min'] ?> -
                                            <?= (int) $user['ideal_age_max'] ?> év
                                        </dd>
                                    </div>
                                <?php endif; ?>
                                <div class="dl-group">
                                    <dt class="label">Lakóhely:</dt>
                                    <dd class="value <?= !$user['ideal_residence'] ? 'empty' : '' ?>">
                                        <?= $user['ideal_residence'] ?: 'Nincs megadva' ?>
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <?php if ($user['hobbies']): ?>
                            <div class="block">
                                <h3>Hobbik és Érdeklődés</h3>
                                <div class="flex-gap" style="gap: 8px;">
                                    <?php
                                    $hobbies = explode(',', $user['hobbies']);
                                    foreach ($hobbies as $hobby):
                                        if (trim($hobby)):
                                            ?>
                                            <span
                                                style="background: #eee; padding: 4px 10px; border-radius: 20px; font-size: 0.9rem; color: #555;">
                                                <?= htmlspecialchars(trim($hobby)) ?>
                                            </span>
                                            <?php
                                        endif;
                                    endforeach;
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>

                    </div>

                </div>

            </div>

        <?php endif; ?>
    </main>

    <?php include 'footer.php'; ?>
</body>

</html>