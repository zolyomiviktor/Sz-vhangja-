<?php
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];

// --- SZŰRŐ PARAMÉTEREK ---

// --- SZŰRŐ PARAMÉTEREK FELDOLGOZÁSA ---
// Használjunk $_REQUEST-et, hogy GET és POST esetén is működjön (mobilbarát form submit)

// 1. Alap Személyes Adatok
$location = isset($_REQUEST['location']) ? trim($_REQUEST['location']) : '';
$min_age = isset($_REQUEST['min_age']) ? max(18, (int) $_REQUEST['min_age']) : '';
$max_age = isset($_REQUEST['max_age']) ? min(120, (int) $_REQUEST['max_age']) : '';

$gender_val = $_REQUEST['gender'] ?? '';
$gender = in_array($gender_val, ['ferfi', 'no', 'egyeb']) ? $gender_val : '';

$orientation_val = $_REQUEST['sexual_orientation'] ?? ''; // Javítva: sexual_orientation név a formban is legyen egységes
$sexual_orientation = in_array($orientation_val, ['heteroszexualis', 'meleg', 'leszbikus', 'biszexualis']) ? $orientation_val : '';

// 2. Életmód és Család
$children_val = $_REQUEST['children'] ?? '';
$children = in_array($children_val, ['van', 'nincs']) ? $children_val : '';

$smoking_val = $_REQUEST['smoking'] ?? '';
$smoking = in_array($smoking_val, ['soha', 'alkalmas', 'rendszeres']) ? $smoking_val : '';

$goal_val = $_REQUEST['goal'] ?? '';
$goal = in_array($goal_val, ['komoly', 'baratsag']) ? $goal_val : '';

// 3. Akadálymentesség és Állapot
$mobility_status = isset($_REQUEST['mobility_status']) ? trim($_REQUEST['mobility_status']) : '';
$assistive_devices = isset($_REQUEST['assistive_devices']) ? trim($_REQUEST['assistive_devices']) : '';
$is_transport_accessible = isset($_REQUEST['is_transport_accessible']) ? 1 : 0;

// 4. Aktivitás és Média
$last_active_days = isset($_REQUEST['last_active_days']) ? (int) $_REQUEST['last_active_days'] : 0;
$only_with_photo = isset($_REQUEST['only_with_photo']) ? 1 : 0;
$only_online = isset($_REQUEST['only_online']) ? 1 : 0;

// Validálás: Min kor ne legyen nagyobb a Max kornál
if ($min_age && $max_age && $min_age > $max_age) {
    $temp = $min_age;
    $min_age = $max_age;
    $max_age = $temp;
}

// --- SQL LEKÉRDEZÉS ÉPÍTÉSE (Prepared Statements) ---

// Csak az engedélyezett és nem rejtett felhasználók, kivéve a bejelentkezett felhasználót
// --- SQL LEKÉRDEZÉS ÉPÍTÉSE (Prioritás: Teszt) ---
$sql = "SELECT id, nickname, birth_date, gender, bio, residence, profile_image, last_login,
               mobility_status, assistive_devices, is_transport_accessible, 
               children, smoking, looking_for_friendship, sexual_orientation
        FROM users 
        WHERE id != ? 
          AND status = 'approved' 
          AND is_hidden = 0
          AND id NOT IN (SELECT blocked_id FROM blocks WHERE blocker_id = ?)
          AND id NOT IN (SELECT blocker_id FROM blocks WHERE blocked_id = ?)
";

$params = [$current_user_id, $current_user_id, $current_user_id];

// 1. Életkor Szűrés
if ($min_age) {
    $max_birth_date = date('Y-m-d', strtotime("-$min_age years"));
    $sql .= " AND birth_date <= ?";
    $params[] = $max_birth_date;
}
if ($max_age) {
    $min_birth_date = date('Y-m-d', strtotime("-$max_age years"));
    $sql .= " AND birth_date >= ?";
    $params[] = $min_birth_date;
}

// 2. Nem és Orientáció
if ($gender) {
    $sql .= " AND gender = ?";
    $params[] = $gender;
}
if ($sexual_orientation) {
    $sql .= " AND sexual_orientation = ?";
    $params[] = $sexual_orientation;
}

// 3. Lakhely (Szöveges egyezés)
if ($location) {
    $sql .= " AND residence LIKE ?";
    $params[] = "%$location%";
}

// 4. Mozgásállapot és Segédeszköz
if ($mobility_status) {
    $sql .= " AND mobility_status LIKE ?";
    $params[] = "%$mobility_status%";
}
if ($assistive_devices) {
    $sql .= " AND assistive_devices LIKE ?";
    $params[] = "%$assistive_devices%";
}
if ($is_transport_accessible) {
    // 1 = Igen, 0 = Nem (vagy NULL, de itt az igenre szűrünk)
    $sql .= " AND is_transport_accessible = 1";
}

// 5. Család és Életmód
if ($children) {
    // Ha az adatbázisban "van" / "nincs" szöveg van tárolva. Ha nem, módosítani kell a logikát.
    // A register.php alapján szöveges input volt, de próbáljunk LIKE-ot vagy pontos egyezést.
    // Feltételezzük a "van", "nincs" kulcsszavak jelenlétét.
    $sql .= " AND children LIKE ?";
    $params[] = "%$children%";
}
if ($smoking) {
    $sql .= " AND smoking = ?";
    $params[] = $smoking;
}

// 6. Kapcsolati Cél
if ($goal === 'baratsag') {
    $sql .= " AND looking_for_friendship = 1";
} elseif ($goal === 'komoly') {
    $sql .= " AND looking_for_friendship = 0";
}

// 7. Utolsó Aktivitás
if ($last_active_days > 0) {
    $date_limit = date('Y-m-d H:i:s', strtotime("-$last_active_days days"));
    $sql .= " AND last_login >= ?";
    $params[] = $date_limit;
}

// 8. Csak Képes / Online
if ($only_with_photo) {
    $sql .= " AND profile_image IS NOT NULL AND profile_image != ''";
}
if ($only_online) {
    $sql .= " AND last_login >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
}

// Rendezés: Teszt (id=5) elöl, aztán online status
$sql .= " ORDER BY (id = 5) DESC, (last_login >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)) DESC, last_login DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// --- Segédfüggvények ---

function calculateAge($birthDate)
{
    if (!$birthDate)
        return '?';
    $date = new DateTime($birthDate);
    $now = new DateTime();
    return $now->diff($date)->y;
}

function isOnline($lastLogin)
{
    if (!$lastLogin)
        return false;
    $last = new DateTime($lastLogin);
    $now = new DateTime();
    $diff = $now->getTimestamp() - $last->getTimestamp();
    return $diff < 900; // 15 perc
}
?>

<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Szívhangja - Felfedezés</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>

<body class="bg-modern">
    <?php include 'header.php'; ?>

    <div class="browse-layout browse-main">
        <!-- Modernized Filter Sidebar -->
        <aside class="glass-sidebar">
            <form action="browse.php" method="GET">
                <div class="filter-group">
                    <label class="filter-label">Életkor (Min - Max)</label>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <input type="number" name="min_age" value="<?= $min_age ?: 18 ?>" min="18" max="99" class="filter-input">
                        <span>-</span>
                        <input type="number" name="max_age" value="<?= $max_age ?: 99 ?>" min="18" max="120" class="filter-input">
                    </div>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Kit keres?</label>
                    <select name="gender" class="filter-input">
                        <option value="">Mindegy</option>
                        <option value="ferfi" <?= $gender == 'ferfi' ? 'selected' : '' ?>>Férfit</option>
                        <option value="no" <?= $gender == 'no' ? 'selected' : '' ?>>Nőt</option>
                        <option value="egyeb" <?= $gender == 'egyeb' ? 'selected' : '' ?>>Egyéb</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Szexuális orientáció</label>
                    <select name="sexual_orientation" class="filter-input">
                        <option value="">Mindegy</option>
                        <option value="heteroszexualis" <?= $sexual_orientation == 'heteroszexualis' ? 'selected' : '' ?>>Heteroszexuális</option>
                        <option value="meleg" <?= $sexual_orientation == 'meleg' ? 'selected' : '' ?>>Meleg</option>
                        <option value="leszbikus" <?= $sexual_orientation == 'leszbikus' ? 'selected' : '' ?>>Leszbikus</option>
                        <option value="biszexualis" <?= $sexual_orientation == 'biszexualis' ? 'selected' : '' ?>>Biszexuális</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Város</label>
                    <input type="text" name="location" value="<?= htmlspecialchars($location) ?>" placeholder="pl. Budapest" class="filter-input">
                </div>

                <div class="filter-group">
                    <label class="filter-label">Fogyatékosság / Állapot</label>
                    <input type="text" name="mobility_status" value="<?= htmlspecialchars($mobility_status) ?>" placeholder="pl. Kerekesszék, Segítséggel..." class="filter-input">
                    <div style="margin-top:0.5rem;">
                        <input type="text" name="assistive_devices" value="<?= htmlspecialchars($assistive_devices) ?>" placeholder="Segédeszközök (pl. hallókészülék)" class="filter-input">
                    </div>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Életmód és Célok</label>
                    <select name="goal" class="filter-input" style="margin-bottom:0.5rem;">
                        <option value="">Bármilyen cél</option>
                        <option value="komoly" <?= $goal == 'komoly' ? 'selected' : '' ?>>Komoly kapcsolatot</option>
                        <option value="baratsag" <?= $goal == 'baratsag' ? 'selected' : '' ?>>Csak barátságot</option>
                    </select>
                    <select name="smoking" class="filter-input" style="margin-bottom:0.5rem;">
                        <option value="">Dohányzás: Mindegy</option>
                        <option value="soha" <?= $smoking == 'soha' ? 'selected' : '' ?>>Soha nem dohányzik</option>
                        <option value="alkalmas" <?= $smoking == 'alkalmas' ? 'selected' : '' ?>>Alkalmi dohányos</option>
                        <option value="rendszeres" <?= $smoking == 'rendszeres' ? 'selected' : '' ?>>Rendszeres dohányos</option>
                    </select>
                    <select name="children" class="filter-input">
                        <option value="">Gyermek: Mindegy</option>
                        <option value="van" <?= $children == 'van' ? 'selected' : '' ?>>Van gyermeke</option>
                        <option value="nincs" <?= $children == 'nincs' ? 'selected' : '' ?>>Nincs gyermeke</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="checkbox-group" style="font-size:0.85rem; color:var(--text-muted); cursor:pointer;">
                        <input type="checkbox" name="is_transport_accessible" value="1" <?= $is_transport_accessible ? 'checked' : '' ?>>
                        Csak akadálymentesen közlekedők
                    </label>
                </div>

                <button type="submit" class="btn" style="width:100%;">Szűrés alkalmazása</button>
                <a href="browse.php" style="display:block; text-align:center; margin-top:1rem; color:var(--text-muted); font-size:0.8rem;">Szűrők alaphelyzetbe</a>
            </form>
        </aside>

        <!-- Profile Grid Area -->
        <section class="browse-results">
            <?php if (count($users) > 0): ?>
                <div class="browse-grid-container">
                    <?php foreach ($users as $user): 
                        $age = calculateAge($user['birth_date']);
                        $loc = htmlspecialchars($user['residence'] ?: 'Debrecen');
                        $online = isOnline($user['last_login']);
                    ?>
                        <div class="modern-profile-card-wrapper" data-user-id="<?= $user['id'] ?>">
                            <a href="profile.php?id=<?= $user['id'] ?>" class="modern-profile-card">
                                <?php if ($online): ?>
                                    <span class="modern-card-status">Online</span>
                                <?php endif; ?>

                                <div class="modern-card-image">
                                    <?= render_avatar($user, 'large', ['class' => 'profile-img']) ?>
                                </div>
                                
                                <div class="modern-card-info">
                                    <h2 class="modern-card-name"><?= htmlspecialchars($user['nickname']) ?>, <?= $age ?></h2>
                                    <p class="modern-card-meta"><?= $loc ?></p>
                                </div>
                            </a>
                            <!-- Interakciós Gombok -->
                            <div class="card-actions-overlay">
                                <button class="action-btn pass-btn" aria-label="Pass" onclick="handleInteraction(<?= $user['id'] ?>, 'pass')">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                                </button>
                                <button class="action-btn message-btn" aria-label="Üzenet" onclick="location.href='messages.php?recipient_id=<?= $user['id'] ?>'">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-message-square"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                </button>
                                <button class="action-btn like-btn" aria-label="Like" onclick="handleInteraction(<?= $user['id'] ?>, 'like')">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-heart"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-results-modern">
                    <h2>Bővítsd a keresést</h2>
                    <p>Jelenleg nincsenek új profilok a közeledben.</p>
                    <a href="browse.php" class="btn">Szűrés törlése</a>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <?php include 'footer.php'; ?>
    
    <!-- Match Értesítés Overlay -->
    <div id="match-overlay" class="match-overlay-hidden">
        <div class="match-content">
            <h1 class="match-title">Ez egy Match!</h1>
            <p>Kölcsönösen kedvelitek egymást!</p>
            <div class="match-actions">
                <button onclick="closeMatch()" class="btn">Szuper!</button>
                <a id="match-message-link" href="messages.php" class="btn secondary">Üzenet küldése</a>
            </div>
        </div>
    </div>

    <script>
        async function handleInteraction(targetId, action) {
            const cardWrapper = document.querySelector(`.modern-profile-card-wrapper[data-user-id="${targetId}"]`);
            
            // Animáció: kis kártya eltűnés/elcsúszás
            if (action === 'pass') {
                cardWrapper.style.transform = 'translateX(-100px) rotate(-10deg)';
                cardWrapper.style.opacity = '0';
            } else {
                cardWrapper.style.transform = 'translateX(100px) rotate(10deg)';
                cardWrapper.style.opacity = '0';
            }

            setTimeout(() => cardWrapper.remove(), 300);

            try {
                const response = await fetch('handle_interaction.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '<?= $_SESSION['csrf_token'] ?>'
                    },
                    body: JSON.stringify({ target_id: targetId, action: action })
                });

                const result = await response.json();
                
                if (result.success && result.is_match) {
                    showMatch(targetId);
                }
            } catch (error) {
                console.error('Hiba az interakció során:', error);
            }
        }

        function showMatch(partnerId) {
            const overlay = document.getElementById('match-overlay');
            const messageLink = document.getElementById('match-message-link');
            if (messageLink) {
                messageLink.href = `messages.php?recipient_id=${partnerId}`;
            }
            overlay.classList.remove('match-overlay-hidden');
            overlay.classList.add('match-overlay-visible');
        }

        function closeMatch() {
            const overlay = document.getElementById('match-overlay');
            overlay.classList.remove('match-overlay-visible');
            overlay.classList.add('match-overlay-hidden');
        }
    </script>
</body>

</html>