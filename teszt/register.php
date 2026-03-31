<?php
session_start();

// CSRF token generálás, ha még nincs
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require 'db.php';
require 'config.php';
require 'email_helper.php';

$error = '';
$success = '';
$birth_year = $birth_month = $birth_day = '';

// Hibák megjelenítéséhez (fejlesztéskor)
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. CSRF Védelem
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Biztonsági hiba: Érvénytelen munkamenet. Kérlek frissítsd az oldalt!");
    }

    // 2. Adatok tisztítása és kinyerése
    $nickname = trim($_POST['nickname'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $birth_year = $_POST['birth_year'] ?? '';
    $birth_month = $_POST['birth_month'] ?? '';
    $birth_day = $_POST['birth_day'] ?? '';
    $birth_date = '';
    if (!empty($birth_year) && !empty($birth_month) && !empty($birth_day)) {
        $birth_date = sprintf('%04d-%02d-%02d', $birth_year, $birth_month, $birth_day);
    }
    $residence = trim($_POST['residence'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $sexual_orientation = trim($_POST['sexual_orientation'] ?? '');
    $mobility_status = trim($_POST['mobility_status'] ?? '');
    $bio = trim($_POST['bio'] ?? '');

    // Profilkép feldolgozása
    $profile_image_name = null;
    $image_upload_error = null;

    // 3. Validáció
    $errors = [];

    // Kötelező mezők
    if (empty($nickname))
        $errors[] = "A becenév megadása kötelező.";
    if (empty($last_name))
        $errors[] = "A vezetéknév megadása kötelező.";
    if (empty($email))
        $errors[] = "Az email cím megadása kötelező.";
    if (empty($password))
        $errors[] = "A jelszó megadása kötelező.";
    if (empty($birth_year) || empty($birth_month) || empty($birth_day))
        $errors[] = "A teljes születési dátum megadása kötelező.";
    if (empty($gender))
        $errors[] = "A nem megadása kötelező.";

    // Jelszó hossz
    if (!empty($password) && strlen($password) < 8) {
        $errors[] = "A jelszónak legalább 8 karakternek kell lennie.";
    }

    // Életkor ellenőrzés (18+)
    try {
        $dob = new DateTime($birth_date);
        $now = new DateTime();
        $age = $now->diff($dob)->y;
        if ($age < 18) {
            $errors[] = "A regisztrációhoz legalább 18 évesnek kell lenned.";
        }

        // Érvényes-e a dátum (pl. február 30)
        if ($dob->format('Y-m-d') !== $birth_date) {
            $errors[] = "A megadott dátum nem létezik.";
        }
    } catch (Exception $e) {
        $errors[] = "Helytelen dátum formátum.";
    }

    // Email egyediség
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Ez az email cím már foglalt.";
        }
    }

    // Ha nincs általános hiba, mehet a kép és az INSERT
    if (empty($errors)) {

        // 4. Profilkép feldolgozása (opcionális)
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            $max_size = 5 * 1024 * 1024; // 5MB

            $file_tmp = $_FILES['profile_image']['tmp_name'];
            $file_size = $_FILES['profile_image']['size'];
            $file_type = mime_content_type($file_tmp);

            if (!in_array($file_type, $allowed_types)) {
                $image_upload_error = "Csak JPG, PNG és WebP formátumok engedélyezettek.";
            } elseif ($file_size > $max_size) {
                $image_upload_error = "A kép mérete maximum 5MB lehet.";
            } else {
                // Biztonságos fájlnév generálás
                $extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                $profile_image_name = 'profile_' . bin2hex(random_bytes(16)) . '.' . $extension;
                $upload_dir = './uploads/profile/';

                // Mappa létrehozása ha nem létezik
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $upload_path = $upload_dir . $profile_image_name;

                // Kép átméretezése 4:5 arányra (800x1000)
                $source_image = null;
                switch ($file_type) {
                    case 'image/jpeg':
                    case 'image/jpg':
                        $source_image = imagecreatefromjpeg($file_tmp);
                        break;
                    case 'image/png':
                        $source_image = imagecreatefrompng($file_tmp);
                        break;
                    case 'image/webp':
                        $source_image = imagecreatefromwebp($file_tmp);
                        break;
                }

                if ($source_image) {
                    $orig_width = imagesx($source_image);
                    $orig_height = imagesy($source_image);

                    $target_width = 800;
                    $target_height = 1000;

                    $source_aspect = $orig_width / $orig_height;
                    $target_aspect = $target_width / $target_height;

                    if ($source_aspect > $target_aspect) {
                        // Szélesebb kép
                        $new_width = $orig_height * $target_aspect;
                        $new_height = $orig_height;
                        $src_x = ($orig_width - $new_width) / 2;
                        $src_y = 0;
                    } else {
                        // Magasabb kép
                        $new_width = $orig_width;
                        $new_height = $orig_width / $target_aspect;
                        $src_x = 0;
                        $src_y = ($orig_height - $new_height) / 2;
                    }

                    $resized_image = imagecreatetruecolor($target_width, $target_height);
                    imagecopyresampled(
                        $resized_image,
                        $source_image,
                        0,
                        0,
                        (int) $src_x,
                        (int) $src_y,
                        (int) $target_width,
                        (int) $target_height,
                        (int) $new_width,
                        (int) $new_height
                    );

                    switch ($file_type) {
                        case 'image/jpeg':
                        case 'image/jpg':
                            imagejpeg($resized_image, $upload_path, 90);
                            break;
                        case 'image/png':
                            imagepng($resized_image, $upload_path, 8);
                            break;
                        case 'image/webp':
                            imagewebp($resized_image, $upload_path, 90);
                            break;
                    }

                    imagedestroy($source_image);
                    imagedestroy($resized_image);
                } else {
                    $image_upload_error = "A kép feldolgozása sikertelen.";
                    $profile_image_name = null;
                }
            }
        }

        // 5. Mentés és Email küldés (csak ha nincs képfeltöltési hiba)
        if (!$image_upload_error) {
            try {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $activation_token = bin2hex(random_bytes(32));
                $activation_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

                $sql = "INSERT INTO users (
                            nickname,
                            last_name,
                            email,
                            password,
                            birth_date,
                            gender,
                            sexual_orientation,
                            mobility_status,
                            bio,
                            residence,
                            profile_image,
                            status,
                            is_email_verified,
                            activation_token,
                            activation_expires
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 0, ?, ?)";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $nickname,
                    $last_name,
                    $email,
                    $passwordHash,
                    $birth_date,
                    $gender,
                    $sexual_orientation,
                    $mobility_status,
                    $bio,
                    $residence,
                    $profile_image_name ? 'profile/' . $profile_image_name : null,
                    $activation_token,
                    $activation_expires
                ]);

                $newUserId = $pdo->lastInsertId();

                // --- AUTOMATIKUS ELLENŐRZÉS KEZDETE ---
                try {
                    require_once 'verification_helper.php';
                    VerificationHelper::verify($newUserId);
                } catch (Exception $e) {
                    error_log("Ellenőrzési hiba: " . $e->getMessage());
                }
                // --- AUTOMATIKUS ELLENŐRZÉS VÉGE ---

                // Aktiváló link küldése
                $activation_link = "http://" . $_SERVER['HTTP_HOST'] .
                    rtrim(dirname($_SERVER['PHP_SELF']), '/\\') .
                    "/activate.php?token=$activation_token";

                $subject = "Regisztráció megerősítése - Szívhangja";
                $message = "Kedves $nickname!\n\n";
                $message .= "Köszönjük, hogy regisztráltál a Szívhangja társkeresőre.\n\n";
                $message .= "Kérjük, kattints az alábbi linkre a fiókod megerősítéséhez (a link 24 óráig érvényes):\n";
                $message .= $activation_link . "\n\n";
                $message .= "Üdvözlettel,\nA Szívhangja Csapata";

                if (send_system_email($email, $subject, $message)) {
                    $success = "Admin jóváhagyása után be fog tudni jelentkezni a fiókjába.";

                    // --- ADMIN ÉRTESÍTÉS KEZDETE ---
                    try {
                        // 1. Adatbázis értesítés
                        $notif_msg = "Új felhasználó regisztrált: " . $last_name . " (" . $nickname . ")";
                        $notif_link = "users.php?search=" . urlencode($email); // Link a felhasználó kereséséhez

                        $stmt_notif = $pdo->prepare("INSERT INTO admin_notifications (type, message, link) VALUES ('registration', ?, ?)");
                        $stmt_notif->execute([$notif_msg, $notif_link]);

                        // 2. Email értesítés
                        $admin_subject = "Új regisztráció: $nickname";
                        $admin_body = "Új felhasználó regisztrált az oldalon.\n\n";
                        $admin_body .= "Név: $last_name\n";
                        $admin_body .= "Becenév: $nickname\n";
                        $admin_body .= "Email: $email\n";
                        $admin_body .= "Dátum: " . date('Y-m-d H:i:s') . "\n";
                        $admin_body .= "\nKérlek ellenőrizd a regisztrációt az admin felületen.";

                        send_system_email(ADMIN_EMAIL, $admin_subject, $admin_body);

                    } catch (Exception $e) {
                        // Nem szakítjuk meg a folyamatot, ha az admin értesítés hibázik, csak logoljuk
                        error_log("Admin értesítés hiba: " . $e->getMessage());
                    }
                    // --- ADMIN ÉRTESÍTÉS VÉGE ---

                    $nickname = $last_name = $email = $residence = $gender =
                        $sexual_orientation = $mobility_status = $bio = '';
                    $birth_year = $birth_month = $birth_day = $birth_date = '';
                } else {
                    $success = "A regisztráció sikeres volt, de az aktiváló emailt nem sikerült elküldeni. Kérlek vedd fel a kapcsolatot az adminisztrátorral.";

                    $nickname = $last_name = $email = $residence = $gender =
                        $sexual_orientation = $mobility_status = $bio = '';
                    $birth_year = $birth_month = $birth_day = $birth_date = '';
                }

            } catch (PDOException $e) {
                error_log("Regisztrációs hiba: " . $e->getMessage());
                if ($profile_image_name && file_exists('./uploads/profile/' . $profile_image_name)) {
                    unlink('./uploads/profile/' . $profile_image_name);
                }
                // Fejlesztésnél:
                $error = "Adatbázis hiba: " . $e->getMessage();
                // Élesben inkább:
                // $error = "Adatbázis hiba történt. Kérlek próbáld újra később.";
            }
        } else {
            $error = $image_upload_error;
        }
    } else {
        $error = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Regisztráció - Szívhangja</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <style>
        .form-section {
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1rem;
        }

        .form-section:last-child {
            border-bottom: none;
        }

        .form-section h2 {
            font-size: 1.3rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .help-text {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <main id="main-content">
        <div class="card" style="max-width: 600px; margin: 2rem auto;">
            <h1 style="margin-top: 0; text-align: center;">Regisztráció</h1>

            <?php if ($error): ?>
                <div id="registration-errors" role="alert" aria-live="assertive" style="background: #ffebee; color: #c62828; padding: 1rem;
                        border: 1px solid #ef5350; margin-bottom: 1.5rem; border-radius: 8px;">
                    <strong>Hiba történt a regisztráció során:</strong><br><?= $error ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div role="alert" aria-live="polite" style="background: #e8f5e9; color: #2e7d32; padding: 1rem;
                        border: 1px solid #66bb6a; margin-bottom: 1.5rem; border-radius: 8px;">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if (!$success): ?>
                <form action="register.php" method="POST" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <!-- 1. Személyes adatok -->
                    <fieldset class="form-section">
                        <legend>
                            <h2>1. Személyes adatok</h2>
                        </legend>

                        <div class="form-group">
                            <label for="nickname">Becenév (kötelező):</label>
                            <input type="text" id="nickname" name="nickname" required
                                value="<?= htmlspecialchars($nickname ?? '') ?>" aria-required="true"
                                placeholder="Pl. Peti88">
                        </div>

                        <div class="form-group">
                            <label for="last_name">Vezetéknév (kötelező, csak adminisztrátorok látják):</label>
                            <input type="text" id="last_name" name="last_name" required
                                value="<?= htmlspecialchars($last_name ?? '') ?>" aria-required="true"
                                placeholder="Pl. Kovács">
                        </div>

                        <div class="form-group">
                            <label for="email">Email cím (kötelező):</label>
                            <input type="email" id="email" name="email" required
                                value="<?= htmlspecialchars($email ?? '') ?>" aria-required="true"
                                placeholder="pelda@email.hu" aria-describedby="email-desc">
                        </div>

                        <div class="form-group">
                            <label for="password">Jelszó (min. 8 karakter):</label>
                            <input type="password" id="password" name="password" minlength="8" required aria-required="true"
                                aria-describedby="password-desc">
                            <div id="password-desc" class="help-text">
                                Használj betűket és számokat a biztonságért.
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Születési dátum (18+):</label>
                            <div style="display: flex; gap: 0.5rem;">
                                <select name="birth_year" id="birth_year" required aria-required="true" style="flex: 2;">
                                    <option value="">Év</option>
                                    <?php
                                    $currentYear = date('Y');
                                    for ($y = $currentYear - 18; $y >= $currentYear - 100; $y--): ?>
                                        <option value="<?= $y ?>" <?= ($birth_year == $y) ? 'selected' : '' ?>><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                                <select name="birth_month" id="birth_month" required aria-required="true" style="flex: 2;">
                                    <option value="">Hónap</option>
                                    <?php
                                    for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?= $m ?>" <?= ($birth_month == $m) ? 'selected' : '' ?>><?= $m ?></option>
                                    <?php endfor; ?>
                                </select>
                                <select name="birth_day" id="birth_day" required aria-required="true" style="flex: 1;">
                                    <option value="">Nap</option>
                                    <?php
                                    for ($d = 1; $d <= 31; $d++): ?>
                                        <option value="<?= $d ?>" <?= ($birth_day == $d) ? 'selected' : '' ?>><?= $d ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <?php if (strpos($error, '18 éves') !== false || strpos($error, 'dátum') !== false): ?>
                                <div class="help-text" style="color: #c62828;">Kérjük add meg a teljes dátumot helyesen!</div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="residence">Lakóhely (Város):</label>
                            <input type="text" id="residence" name="residence"
                                value="<?= htmlspecialchars($residence ?? '') ?>" placeholder="pl. Budapest"
                                aria-describedby="residence-desc">
                            <div id="residence-desc" class="help-text">Opcionális, de segít a keresésben.</div>
                        </div>
                    </fieldset>

                    <!-- 2. Identitás és orientáció -->
                    <fieldset class="form-section">
                        <legend>
                            <h2>2. Identitás és orientáció</h2>
                        </legend>

                        <div class="form-group">
                            <label for="gender">Nem:</label>
                            <select id="gender" name="gender" required aria-required="true"
                                aria-invalid="<?= strpos($error, 'nem megadása') !== false ? 'true' : 'false' ?>"
                                <?= strpos($error, 'nem megadása') !== false ? 'aria-describedby="registration-errors"' : '' ?>>
                                <option value="">Válassz...</option>
                                <option value="ferfi" <?= ($gender ?? '') === 'ferfi' ? 'selected' : '' ?>>Férfi</option>
                                <option value="no" <?= ($gender ?? '') === 'no' ? 'selected' : '' ?>>Nő</option>
                                <option value="egyeb" <?= ($gender ?? '') === 'egyeb' ? 'selected' : '' ?>>Egyéb</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="sexual_orientation">Szexuális orientáció:</label>
                            <input type="text" id="sexual_orientation" name="sexual_orientation"
                                value="<?= htmlspecialchars($sexual_orientation ?? '') ?>"
                                placeholder="pl. Heteroszexuális, Meleg, Bi...">
                        </div>
                    </fieldset>

                    <!-- 3. Fogyatékossággal kapcsolatos információk -->
                    <fieldset class="form-section">
                        <legend>
                            <h2>3. Segítségnyújtás és Állapot</h2>
                        </legend>
                        <p class="help-text" style="margin-bottom: 1rem;">
                            Ezek az adatok segítenek, hogy olyan partnert találjunk, aki megérti és elfogadja a helyzetedet.
                        </p>

                        <div class="form-group">
                            <label for="mobility_status">Mozgásállapot / Segédeszköz:</label>
                            <input type="text" id="mobility_status" name="mobility_status"
                                value="<?= htmlspecialchars($mobility_status ?? '') ?>"
                                placeholder="pl. Kerekesszék, Művégtag, Nincs..." aria-describedby="mobility-desc">
                            <div id="mobility-desc" class="help-text">Írd le röviden, ha használsz segédeszközt.</div>
                        </div>

                        <div class="form-group">
                            <label for="bio">Egyéb igények / Bemutatkozás:</label>
                            <textarea id="bio" name="bio" rows="4"
                                placeholder="Írj magadról pár mondatot, és említsd meg, ha van bármilyen speciális igényed a randizáshoz."><?= htmlspecialchars($bio ?? '') ?></textarea>
                        </div>
                    </fieldset>

                    <!-- 4. Profilkép (opcionális) -->
                    <fieldset class="form-section">
                        <legend>
                            <h2>4. Profilkép (opcionális)</h2>
                        </legend>
                        <p class="help-text" style="margin-bottom: 1rem;">
                            Tölts fel egy profilképet, hogy növeld az esélyeidet! Ha most nem töltesz fel képet, később is
                            megteheted.
                        </p>

                        <div class="form-group">
                            <label for="profile_image">Profilkép feltöltése:</label>
                            <label class="custom-file-upload" for="profile_image" id="file-label"
                                aria-label="Profilkép kiválasztása (JPG, PNG vagy WebP, maximum 5MB)">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                    style="vertical-align: middle; margin-right: 8px;" aria-hidden="true">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="17 8 12 3 7 8"></polyline>
                                    <line x1="12" y1="3" x2="12" y2="15"></line>
                                </svg>
                                <span>Kép kiválasztása...</span>
                            </label>
                            <input type="file" id="profile_image" name="profile_image" class="hidden-input"
                                accept="image/jpeg,image/jpg,image/png,image/webp" aria-describedby="profile-image-desc"
                                onchange="document.querySelector('#file-label span').textContent = this.files[0] ? this.files[0].name : 'Kép kiválasztása...';">
                            <div id="profile-image-desc" class="help-text">
                                Elfogadott formátumok: JPG, PNG, WebP. Maximum méret: 5MB. A kép automatikusan 4:5 arányra
                                lesz vágva.
                            </div>
                        </div>
                    </fieldset>

                    <!-- 5. Adatvédelem -->
                    <div class="form-group" style="margin-bottom: 2rem;">
                        <div class="checkbox-group">
                            <input type="checkbox" id="privacy_accept" required aria-required="true"
                                aria-invalid="<?= strpos($error, 'Adatvédelmi') !== false ? 'true' : 'false' ?>"
                                <?= strpos($error, 'Adatvédelmi') !== false ? 'aria-describedby="registration-errors"' : '' ?>>
                            <label for="privacy_accept">
                                Elfogadom az
                                <a href="privacy.php" style="color: var(--primary-color); font-weight: 600;">
                                    Adatvédelmi tájékoztatót
                                </a>
                                és hozzájárulok adataim kezeléséhez.
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn" style="width: 100%; font-size: 1.1rem; padding: 1rem;"
                        aria-label="Regisztráció véglegesítése">
                        Regisztráció
                    </button>
                </form>
            <?php endif; ?>

        </div>
    </main>
</body>

</html>