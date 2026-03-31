<?php
// edit_profile.php - Profil szerkesztése
require 'db.php'; // Session és DB kapcsolat

// Csak bejelentkezett felhasználóknak
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// POST kérések kezelése
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF ellenőrzés
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Biztonsági hiba: Érvénytelen CSRF token.");
    }

    $action = $_POST['action'] ?? 'update_profile';

    // 1. Képkezelő műveletek (galériából indítva)
    if ($action === 'delete_image' || $action === 'set_primary') {
        $img_id = (int) ($_POST['image_id'] ?? 0);

        if ($action === 'delete_image') {
            $stmt = $pdo->prepare("SELECT image_path FROM user_images WHERE id = ? AND user_id = ?");
            $stmt->execute([$img_id, $user_id]);
            $img = $stmt->fetch();

            if ($img) {
                $file_path = './uploads/' . $img['image_path'];
                if (file_exists($file_path))
                    unlink($file_path);
                $pdo->prepare("DELETE FROM user_images WHERE id = ?")->execute([$img_id]);

                $stmt_check = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
                $stmt_check->execute([$user_id]);
                if ($stmt_check->fetchColumn() === $img['image_path']) {
                    $pdo->prepare("UPDATE users SET profile_image = NULL, bio_updated_at = NOW() WHERE id = ?")->execute([$user_id]);
                    $stmt_next = $pdo->prepare("SELECT image_path FROM user_images WHERE user_id = ? LIMIT 1");
                    $stmt_next->execute([$user_id]);
                    $next_img = $stmt_next->fetchColumn();
                    if ($next_img) {
                        $pdo->prepare("UPDATE users SET profile_image = ?, bio_updated_at = NOW() WHERE id = ?")->execute([$next_img, $user_id]);
                    }
                }
                header("Location: edit_profile.php?success=" . urlencode("Kép sikeresen törölve."));
                exit;
            }
        } elseif ($action === 'set_primary') {
            $stmt = $pdo->prepare("SELECT image_path FROM user_images WHERE id = ? AND user_id = ?");
            $stmt->execute([$img_id, $user_id]);
            $img_path = $stmt->fetchColumn();

            if ($img_path) {
                $pdo->prepare("UPDATE users SET profile_image = ?, bio_updated_at = NOW() WHERE id = ?")->execute([$img_path, $user_id]);
                header("Location: edit_profile.php?success=" . urlencode("Profilkép sikeresen frissítve."));
                exit;
            }
        }
    }

    // 2. Profil adatok ÉS képfeltöltés kezelése
    if ($action === 'update_profile') {
        $nickname = trim($_POST['nickname'] ?? '');
        $birth_year = $_POST['birth_year'] ?? '';
        $birth_month = $_POST['birth_month'] ?? '';
        $birth_day = $_POST['birth_day'] ?? '';
        $birth_date = null;
        if (!empty($birth_year) && !empty($birth_month) && !empty($birth_day)) {
            $birth_date = sprintf('%04d-%02d-%02d', $birth_year, $birth_month, $birth_day);
        }
        $gender = $_POST['gender'] ?? '';
        if (!in_array($gender, ['ferfi', 'no', 'egyeb']))
            $gender = 'egyeb';
        $mobility_status = trim($_POST['mobility_status'] ?? '');
        $bio = trim($_POST['bio'] ?? '');

        // Képfeltöltés kezelése
        if (isset($_FILES['profile_images']) && !empty($_FILES['profile_images']['name'][0])) {
            $files = $_FILES['profile_images'];
            $allowedExtensions = ['jpg', 'jpeg', 'png'];
            $maxSize = 10485760; // 10MB
            $uploadCount = 0;

            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $fileTmpPath = $files['tmp_name'][$i];
                    $fileName = $files['name'][$i];
                    $fileNameCmps = explode(".", $fileName);
                    $fileExtension = strtolower(end($fileNameCmps));

                    if (in_array($fileExtension, $allowedExtensions)) {
                        if ($files['size'][$i] <= $maxSize) {
                            $finfo = finfo_open(FILEINFO_MIME_TYPE);
                            $mimeType = finfo_file($finfo, $fileTmpPath);
                            finfo_close($finfo);

                            if (in_array($mimeType, ['image/jpeg', 'image/png', 'image/jpg'])) {
                                $newFileName = 'user_' . $user_id . '_' . time() . '_' . $i . '.' . $fileExtension;
                                if (move_uploaded_file($fileTmpPath, './uploads/' . $newFileName)) {
                                    try {
                                        $pdo->prepare("INSERT INTO user_images (user_id, image_path) VALUES (?, ?)")->execute([$user_id, $newFileName]);
                                        $stmt_check = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
                                        $stmt_check->execute([$user_id]);
                                        if (!$stmt_check->fetchColumn()) {
                                            $pdo->prepare("UPDATE users SET profile_image = ?, bio_updated_at = NOW() WHERE id = ?")->execute([$newFileName, $user_id]);
                                        }
                                        $uploadCount++;
                                    } catch (PDOException $e) {
                                        error_log("Kép mentési hiba: " . $e->getMessage());
                                        $error = "Hiba történt a kép mentésekor.";
                                    }
                                }
                            } else {
                                $error = "A fájl nem valódi képfájl.";
                            }
                        } else {
                            $error = "A kép mérete túl nagy (max 10MB).";
                        }
                    } else {
                        $error = "Csak JPG és PNG formátum engedélyezett.";
                    }
                } elseif ($files['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                    error_log("PHP Feltöltési hiba kód: " . $files['error'][$i]);
                    $error = "Hiba történt a feltöltés során.";
                }
            }
            if ($uploadCount > 0 && empty($error)) {
                $success = "Képek sikeresen feltöltve.";
            }
        }

        // Becenév validáció és adatmentés (csak ha NINCS korábbi hiba a feltöltésnél)
        if (empty($error)) {
            if (empty($nickname) || mb_strlen($nickname) < 2) {
                $error = "A becenév megadása kötelező és legalább 2 karakter hosszú kell, hogy legyen!";
            } else {
                try {
                    $residence = trim($_POST['residence'] ?? '');
                    $nationality = trim($_POST['nationality'] ?? '');
                    $marital_status = trim($_POST['marital_status'] ?? '');
                    if (!in_array($marital_status, ['single', 'married', 'divorced', 'widowed']))
                        $marital_status = null;
                    $children = trim($_POST['children'] ?? '');
                    $sexual_orientation = trim($_POST['sexual_orientation'] ?? '');
                    $height_cm = intval($_POST['height_cm'] ?? 0);
                    $weight_kg = intval($_POST['weight_kg'] ?? 0);
                    $body_type = trim($_POST['body_type'] ?? '');
                    if (!in_array($body_type, ['vékony', 'átlagos', 'sportos', 'telt']))
                        $body_type = null;
                    $hair_color = trim($_POST['hair_color'] ?? '');
                    $hair_style = trim($_POST['hair_style'] ?? '');
                    $eye_color = trim($_POST['eye_color'] ?? '');
                    $piercing = isset($_POST['piercing']) ? 1 : 0;
                    $tattoo = isset($_POST['tattoo']) ? 1 : 0;
                    $education = trim($_POST['education'] ?? '');
                    $occupation = trim($_POST['occupation'] ?? '');
                    $smoking = trim($_POST['smoking'] ?? '');
                    if (!in_array($smoking, ['soha', 'alkalmas', 'rendszeres']))
                        $smoking = null;
                    $languages = trim($_POST['languages'] ?? '');
                    $hobbies = trim($_POST['hobbies'] ?? '');
                    $horoscope_sign = trim($_POST['horoscope_sign'] ?? '');
                    $horoscope_text = trim($_POST['horoscope_text'] ?? '');
                    $ideal_age_min = intval($_POST['ideal_age_min'] ?? 0);
                    $ideal_age_max = intval($_POST['ideal_age_max'] ?? 0);
                    $ideal_residence = trim($_POST['ideal_residence'] ?? '');
                    $interests = trim($_POST['interests'] ?? '');
                    $looking_for_friendship = isset($_POST['looking_for_friendship']) ? 1 : 0;

                    $sql = "UPDATE users SET nickname = ?, birth_date = ?, gender = ?, mobility_status = ?, bio = ?, residence = ?, nationality = ?, marital_status = ?, children = ?, sexual_orientation = ?, height_cm = ?, weight_kg = ?, body_type = ?, hair_color = ?, hair_style = ?, eye_color = ?, piercing = ?, tattoo = ?, education = ?, occupation = ?, smoking = ?, languages = ?, hobbies = ?, horoscope_sign = ?, horoscope_text = ?, ideal_age_min = ?, ideal_age_max = ?, ideal_residence = ?, interests = ?, looking_for_friendship = ?, bio_updated_at = NOW() WHERE id = ?";
                    $params = [$nickname, $birth_date, $gender, $mobility_status, $bio, $residence, $nationality, $marital_status, $children, $sexual_orientation, $height_cm, $weight_kg, $body_type, $hair_color, $hair_style, $eye_color, $piercing, $tattoo, $education, $occupation, $smoking, $languages, $hobbies, $horoscope_sign, $horoscope_text, $ideal_age_min, $ideal_age_max, $ideal_residence, $interests, $looking_for_friendship, $user_id];

                    $stmt = $pdo->prepare($sql);
                    if ($stmt->execute($params)) {
                        $_SESSION['nickname'] = $nickname;
                        if (empty($success)) {
                            header("Location: profile.php?updated=1");
                            exit;
                        }
                    } else {
                        error_log("Profil frissítési hiba: " . implode(" ", $stmt->errorInfo()));
                        $error = "Hiba történt a mentés során.";
                    }
                } catch (PDOException $e) {
                    error_log("Adatbázis hiba: " . $e->getMessage());
                    $error = "Rendszerhiba történt a mentés során.";
                }
            }
        }
    }
}

// URL-ből érkező sikerüzenet kezelése
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}


// Jelenlegi adatok betöltése (ha hiba történt, a POST-olt adatokat használjuk a formban)
$stmt = $pdo->prepare("SELECT nickname, birth_date, gender, mobility_status, bio, profile_image, registration_date, last_login, unread_messages, residence, nationality, marital_status, children, sexual_orientation, height_cm, weight_kg, body_type, hair_color, hair_style, eye_color, piercing, tattoo, education, occupation, smoking, languages, hobbies, horoscope_sign, horoscope_text, ideal_age_min, ideal_age_max, ideal_residence, interests, looking_for_friendship FROM users WHERE id = ?");
$stmt->execute([$user_id]);

$user_db = $stmt->fetch();

if (!$user_db) {
    die("Felhasználó nem található.");
}

// Ha POST-ban vagyunk és volt hiba, tartsuk meg a beírt adatokat
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($error)) {
    $user = [
        'nickname' => $_POST['nickname'] ?? $user_db['nickname'],
        'birth_date' => $birth_date ?? $user_db['birth_date'],
        'gender' => $_POST['gender'] ?? $user_db['gender'],
        'mobility_status' => $_POST['mobility_status'] ?? $user_db['mobility_status'],
        'bio' => $_POST['bio'] ?? $user_db['bio'],
        'residence' => $_POST['residence'] ?? $user_db['residence'],
        'nationality' => $_POST['nationality'] ?? $user_db['nationality'],
        'marital_status' => $_POST['marital_status'] ?? $user_db['marital_status'],
        'children' => $_POST['children'] ?? $user_db['children'],
        'sexual_orientation' => $_POST['sexual_orientation'] ?? $user_db['sexual_orientation'],
        'height_cm' => $_POST['height_cm'] ?? $user_db['height_cm'],
        'weight_kg' => $_POST['weight_kg'] ?? $user_db['weight_kg'],
        'body_type' => $_POST['body_type'] ?? $user_db['body_type'],
        'hair_color' => $_POST['hair_color'] ?? $user_db['hair_color'],
        'hair_style' => $_POST['hair_style'] ?? $user_db['hair_style'],
        'eye_color' => $_POST['eye_color'] ?? $user_db['eye_color'],
        'piercing' => isset($_POST['piercing']) ? 1 : 0,
        'tattoo' => isset($_POST['tattoo']) ? 1 : 0,
        'education' => $_POST['education'] ?? $user_db['education'],
        'occupation' => $_POST['occupation'] ?? $user_db['occupation'],
        'smoking' => $_POST['smoking'] ?? $user_db['smoking'],
        'languages' => $_POST['languages'] ?? $user_db['languages'],
        'hobbies' => $_POST['hobbies'] ?? $user_db['hobbies'],
        'horoscope_sign' => $_POST['horoscope_sign'] ?? $user_db['horoscope_sign'],
        'horoscope_text' => $_POST['horoscope_text'] ?? $user_db['horoscope_text'],
        'ideal_age_min' => $_POST['ideal_age_min'] ?? $user_db['ideal_age_min'],
        'ideal_age_max' => $_POST['ideal_age_max'] ?? $user_db['ideal_age_max'],
        'ideal_residence' => $_POST['ideal_residence'] ?? $user_db['ideal_residence'],
        'profile_image' => $user_db['profile_image']
    ];
} else {
    $user = $user_db;
}


// Galéria képek betöltése
$stmt_gallery = $pdo->prepare("SELECT * FROM user_images WHERE user_id = ? ORDER BY created_at DESC");
$stmt_gallery->execute([$user_id]);
$gallery_images = $stmt_gallery->fetchAll();

if (!$user) {
    die("Felhasználó nem található.");
}
?>

<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Szerkesztése - Szívhangja</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>

<body>

    <?php include 'header.php'; ?>

    <main id="main-content" role="main">
        <h1>Profil Szerkesztése</h1>

        <div id="status-messages" aria-live="polite">
            <?php if ($error): ?>
                <div role="alert"
                    style="background: #ffebee; color: #c62828; padding: 1rem; border-radius: 8px; border: 1px solid #ef5350; margin-bottom: 1rem;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div role="alert"
                    style="background: #e8f5e9; color: #2e7d32; padding: 1rem; border-radius: 8px; border: 1px solid #66bb6a; margin-bottom: 1rem;">
                    <?= htmlspecialchars($success) ?>
                    <div style="margin-top: 0.5rem;">
                        <a href="profile.php" style="color: inherit; font-weight: 600;">Vissza a profilomhoz</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>


        <div class="card" style="max-width: 650px; margin: 0 auto 2rem auto;">
            <label>Jelenlegi galériád:</label>
            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 1rem; margin-top: 1rem;">
                <?php if (count($gallery_images) > 0): ?>
                    <?php foreach ($gallery_images as $img): ?>
                        <div class="gallery-item">
                            <img src="uploads/<?= htmlspecialchars($img['image_path']) ?>" alt="Galéria kép"
                                onerror="this.src='assets/avatar-default.png'">

                            <!-- Törlés gomb (jobb felül) -->
                            <form method="POST" onsubmit="return confirm('Biztosan törlöd ezt a képet?');"
                                style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                                <button type="submit" name="action" value="delete_image" class="delete-btn"
                                    aria-label="Kép törlése: <?= htmlspecialchars($img['image_path']) ?>">
                                    ×
                                </button>
                            </form>

                            <!-- Használat gomb (lent) -->
                            <form method="POST" style="width: 100%;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                                <button type="submit" name="action" value="set_primary" title="Beállítás profilképnek"
                                    class="use-btn"
                                    aria-label="<?= ($user['profile_image'] === $img['image_path']) ? 'Ez a jelenlegi profilképed' : 'Használat profilképként: ' . htmlspecialchars($img['image_path']) ?>"
                                    style="background: <?= ($user['profile_image'] === $img['image_path']) ? '#4caf50' : '#e0e0e0' ?>; color: <?= ($user['profile_image'] === $img['image_path']) ? 'white' : 'black' ?>;">
                                    <?= ($user['profile_image'] === $img['image_path']) ? 'Profilkép' : 'Használat' ?>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="font-size: 0.9rem; color: var(--text-muted);">Még nincsenek feltöltött képeid.</p>
                <?php endif; ?>
            </div>
        </div>

        <form action="edit_profile.php" method="POST" enctype="multipart/form-data" class="card"
            style="max-width: 900px; margin: 0 auto;">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <!-- Alap adatok -->
            <fieldset class="edit-section">
                <legend>Alap adatok</legend>
                <div class="edit-grid">
                    <div class="form-group">
                        <label for="nickname">Becenév (Kötelező):</label>
                        <input type="text" id="nickname" name="nickname"
                            value="<?= htmlspecialchars($user['nickname']) ?>" required aria-label="Becenév">
                    </div>
                    <div class="form-group">
                        <label>Születési dátum:</label>
                        <?php
                        $b_year = $b_month = $b_day = '';
                        if (!empty($user['birth_date'])) {
                            $parts = explode('-', $user['birth_date']);
                            $b_year = $parts[0] ?? '';
                            $b_month = (int) ($parts[1] ?? 0);
                            $b_day = (int) ($parts[2] ?? 0);
                        }
                        ?>
                        <div style="display: flex; gap: 0.5rem;">
                            <select name="birth_year" id="birth_year" style="flex: 2;">
                                <option value="">Év</option>
                                <?php
                                $currentYear = date('Y');
                                for ($y = $currentYear - 18; $y >= $currentYear - 100; $y--): ?>
                                    <option value="<?= $y ?>" <?= ($b_year == $y) ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="birth_month" id="birth_month" style="flex: 2;">
                                <option value="">Hónap</option>
                                <?php
                                for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= ($b_month == $m) ? 'selected' : '' ?>><?= $m ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="birth_day" id="birth_day" style="flex: 1;">
                                <option value="">Nap</option>
                                <?php
                                for ($d = 1; $d <= 31; $d++): ?>
                                    <option value="<?= $d ?>" <?= ($b_day == $d) ? 'selected' : '' ?>><?= $d ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="gender">Nem:</label>
                        <select id="gender" name="gender" aria-label="Nem">
                            <option value="egyeb" <?= $user['gender'] == 'egyeb' ? 'selected' : '' ?>>Egyéb / Nem adom meg
                            </option>
                            <option value="no" <?= $user['gender'] == 'no' ? 'selected' : '' ?>>Nő</option>
                            <option value="ferfi" <?= $user['gender'] == 'ferfi' ? 'selected' : '' ?>>Férfi</option>
                        </select>
                    </div>
                </div>
            </fieldset>

            <!-- Személyes adatok -->
            <fieldset class="edit-section">
                <legend>Személyes adatok</legend>
                <div class="edit-grid">
                    <div class="form-group">
                        <label for="residence">Lakóhely (Település):</label>
                        <input type="text" id="residence" name="residence"
                            value="<?= htmlspecialchars($user['residence'] ?? '') ?>" placeholder="pl. Budapest"
                            aria-label="Lakóhely">
                    </div>
                    <div class="form-group">
                        <label for="nationality">Nemzetiség:</label>
                        <input type="text" id="nationality" name="nationality"
                            value="<?= htmlspecialchars($user['nationality'] ?? '') ?>" placeholder="pl. Magyar"
                            aria-label="Nemzetiség">
                    </div>
                    <div class="form-group">
                        <label for="marital_status">Családi állapot:</label>
                        <select id="marital_status" name="marital_status" aria-label="Családi állapot">
                            <option value="">Nincs megadva</option>
                            <option value="single" <?= $user['marital_status'] == 'single' ? 'selected' : '' ?>>
                                Egyedülálló</option>
                            <option value="married" <?= $user['marital_status'] == 'married' ? 'selected' : '' ?>>Házas
                            </option>
                            <option value="divorced" <?= $user['marital_status'] == 'divorced' ? 'selected' : '' ?>>Elvált
                            </option>
                            <option value="widowed" <?= $user['marital_status'] == 'widowed' ? 'selected' : '' ?>>Özvegy
                            </option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="children">Gyermekei:</label>
                        <input type="text" id="children" name="children"
                            value="<?= htmlspecialchars($user['children'] ?? '') ?>" placeholder="pl. Nincs, 1, 2..."
                            aria-label="Gyermekek száma">
                    </div>
                    <div class="form-group">
                        <label for="sexual_orientation">Szexuális beállítottság:</label>
                        <input type="text" id="sexual_orientation" name="sexual_orientation"
                            value="<?= htmlspecialchars($user['sexual_orientation'] ?? '') ?>"
                            placeholder="pl. Heteroszexuális" aria-label="Szexuális beállítottság">
                    </div>
                </div>
                </div>

                </div>
            </fieldset>

            <!-- Külső jellemzők -->
            <fieldset class="edit-section">
                <legend>Külső jellemzők</legend>
                <div class="edit-grid">
                    <div class="form-group">
                        <label for="height_cm">Magasság (cm):</label>
                        <input type="number" id="height_cm" name="height_cm"
                            value="<?= htmlspecialchars($user['height_cm'] ?? '') ?>"
                            aria-label="Magasság centiméterben">
                    </div>
                    <div class="form-group">
                        <label for="weight_kg">Súly (kg):</label>
                        <input type="number" id="weight_kg" name="weight_kg"
                            value="<?= htmlspecialchars($user['weight_kg'] ?? '') ?>" aria-label="Súly kilogrammban">
                    </div>
                    <div class="form-group">
                        <label for="body_type">Testalkat:</label>
                        <select id="body_type" name="body_type" aria-label="Testalkat">
                            <option value="">Nincs megadva</option>
                            <option value="vékony" <?= $user['body_type'] == 'vékony' ? 'selected' : '' ?>>Vékony</option>
                            <option value="átlagos" <?= $user['body_type'] == 'átlagos' ? 'selected' : '' ?>>Átlagos
                            </option>
                            <option value="sportos" <?= $user['body_type'] == 'sportos' ? 'selected' : '' ?>>Sportos
                            </option>
                            <option value="telt" <?= $user['body_type'] == 'telt' ? 'selected' : '' ?>>Telt</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="hair_color">Hajszín:</label>
                        <input type="text" id="hair_color" name="hair_color"
                            value="<?= htmlspecialchars($user['hair_color'] ?? '') ?>" aria-label="Hajszín">
                    </div>
                    <div class="form-group">
                        <label for="eye_color">Szemszín:</label>
                        <input type="text" id="eye_color" name="eye_color"
                            value="<?= htmlspecialchars($user['eye_color'] ?? '') ?>" aria-label="Szemszín">
                    </div>
                    <div class="form-group">
                        <label class="checkbox-group">
                            <input type="checkbox" name="piercing" <?= ($user['piercing'] ?? 0) ? 'checked' : '' ?>>
                            <span>Piercingem van</span>
                        </label>
                        <label class="checkbox-group">
                            <input type="checkbox" name="tattoo" <?= ($user['tattoo'] ?? 0) ? 'checked' : '' ?>>
                            <span>Tetoválásom van</span>
                        </label>
                    </div>
                </div>
                </div>

                </div>
            </fieldset>

            <!-- Életmód és háttér -->
            <fieldset class="edit-section">
                <legend>Életmód és háttér</legend>
                <div class="edit-grid">
                    <div class="form-group">
                        <label for="education">Iskolai végzettség:</label>
                        <input type="text" id="education" name="education"
                            value="<?= htmlspecialchars($user['education'] ?? '') ?>" aria-label="Iskolai végzettség">
                    </div>
                    <div class="form-group">
                        <label for="occupation">Munkája / Foglalkozása:</label>
                        <input type="text" id="occupation" name="occupation"
                            value="<?= htmlspecialchars($user['occupation'] ?? '') ?>" aria-label="Foglalkozás">
                    </div>
                    <div class="form-group">
                        <label for="smoking">Dohányzás:</label>
                        <select id="smoking" name="smoking" aria-label="Dohányzás">
                            <option value="soha" <?= $user['smoking'] == 'soha' ? 'selected' : '' ?>>Soha</option>
                            <option value="alkalmas" <?= $user['smoking'] == 'alkalmas' ? 'selected' : '' ?>>Alkalmanként
                            </option>
                            <option value="rendszeres" <?= $user['smoking'] == 'rendszeres' ? 'selected' : '' ?>>
                                Rendszeresen</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="mobility_status">Mozgásállapot:</label>
                        <input type="text" id="mobility_status" name="mobility_status"
                            value="<?= htmlspecialchars($user['mobility_status'] ?? '') ?>"
                            placeholder="pl. kerekesszék, bot..." aria-label="Mozgásállapot">
                    </div>
                </div>
                <div class="form-group">
                    <label for="languages">Nyelvtudás:</label>
                    <input type="text" id="languages" name="languages"
                        value="<?= htmlspecialchars($user['languages'] ?? '') ?>" placeholder="pl. Magyar, Angol"
                        aria-label="Nyelvtudás">
                </div>
                <div class="form-group">
                    <label for="hobbies">Hobbik / Érdeklődés:</label>
                    <textarea id="hobbies" name="hobbies" rows="3" placeholder="Mi az, amit szeretsz csinálni?"
                        aria-label="Hobbik"><?= htmlspecialchars($user['hobbies'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label for="interests">Közösségi érdeklődési körök:</label>
                    <textarea id="interests" name="interests" rows="3"
                        placeholder="Milyen témák érdekelnek a közösségben? (pl. sport, művészet, utazás)"
                        aria-label="Közösségi érdeklődési körök"><?= htmlspecialchars($user['interests'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="checkbox-group">
                        <input type="checkbox" name="looking_for_friendship" <?= ($user['looking_for_friendship'] ?? 0) ? 'checked' : '' ?>>
                        <span>Barátkozás is érdekel (nem csak romantikus kapcsolat)</span>
                    </label>
                </div>
            </fieldset>

            <!-- Horoszkóp -->
            <fieldset class="edit-section">
                <legend>Horoszkóp</legend>
                <div class="form-group">
                    <label for="horoscope_sign">Csillagjegy:</label>
                    <input type="text" id="horoscope_sign" name="horoscope_sign"
                        value="<?= htmlspecialchars($user['horoscope_sign'] ?? '') ?>" placeholder="pl. Oroszlán"
                        aria-label="Csillagjegy">
                </div>
                <div class="form-group">
                    <label for="horoscope_text">Horoszkóp leírás:</label>
                    <textarea id="horoscope_text" name="horoscope_text" rows="3" placeholder="Személyes horoszkópod..."
                        aria-label="Horoszkóp leírás"><?= htmlspecialchars($user['horoscope_text'] ?? '') ?></textarea>
                </div>
            </fieldset>

            <!-- Ideális jelölt -->
            <fieldset class="edit-section">
                <legend>Ideális jelölt</legend>
                <div class="edit-grid">
                    <div class="form-group">
                        <label for="ideal_age_min">Életkor (tól):</label>
                        <input type="number" id="ideal_age_min" name="ideal_age_min"
                            value="<?= !empty($user['ideal_age_min']) ? $user['ideal_age_min'] : '' ?>"
                            aria-label="Ideális kor minimum">
                    </div>
                    <div class="form-group">
                        <label for="ideal_age_max">Életkor (ig):</label>
                        <input type="number" id="ideal_age_max" name="ideal_age_max"
                            value="<?= !empty($user['ideal_age_max']) ? $user['ideal_age_max'] : '' ?>"
                            aria-label="Ideális kor maximum">
                    </div>
                    <div class="form-group">
                        <label for="ideal_residence">Lakóhelye:</label>
                        <input type="text" id="ideal_residence" name="ideal_residence"
                            value="<?= htmlspecialchars($user['ideal_residence'] ?? '') ?>"
                            placeholder="pl. Budapest és környéke" aria-label="Ideális jelölt lakóhelye">
                    </div>
                </div>
            </fieldset>

            <!-- Profilkép és Bemutatkozás -->
            <fieldset class="edit-section">
                <legend>Bemutatkozás és Médiák</legend>
                <div class="form-group">
                    <label for="bio">Bemutatkozás:</label>
                    <textarea id="bio" name="bio" rows="5" placeholder="Mesélj magadról néhány mondatban..."
                        aria-describedby="bio-desc"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                    <small id="bio-desc" class="help-text">A jó bemutatkozás segít, hogy könnyebben rád
                        találjanak!</small>
                </div>
                <div class="form-group">
                    <label for="profile_images">Új képek hozzáadása:</label>
                    <div class="file-upload">
                        <input type="file" id="profile_images" name="profile_images[]" accept="image/png, image/jpeg"
                            multiple aria-label="Profilképek feltöltése" aria-describedby="file-upload-desc"
                            style="width: auto; border: none; padding: 0; background: transparent;">
                        <span id="file-upload-desc" class="help-text">Egyszerre több képet is választhatsz! JPG, PNG,
                            max 10MB/kép.</span>
                    </div>
                </div>
            </fieldset>

            <div style="display: flex; gap: 1rem; align-items: center; margin-top: 2rem;">
                <button type="submit" name="action" value="update_profile" class="btn">Változtatások mentése</button>
                <a href="profile.php" class="btn"
                    style="background: var(--text-muted); padding: 0.8rem 1.5rem;">Mégsem</a>
            </div>

            <div
                style="text-align: center; margin-top: 3rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
                <a href="delete_account.php" style="color: #c62828; font-weight: 700; text-decoration: underline;"
                    aria-label="Fiók végleges törlése - Figyelem: ez a művelet nem vonható vissza!"
                    onclick="return confirm('BIZTOSAN TÖRÖLNI SZERETNÉD A FIÓKODAT? Ez a művelet végleges és nem vonható vissza.');">
                    Fiók végleges törlése
                </a>
            </div>
        </form>

    </main>

    <?php include 'footer.php'; ?>

</body>

</html>