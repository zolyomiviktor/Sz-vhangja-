<?php
// handle_interaction.php - Like/Pass logika és Match detektálás
require_once 'db.php';

// Csak bejelentkezett felhasználók
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Nincs bejelentkezve']);
    exit;
}

$user_id = $_SESSION['user_id'];

// CSRF ellenőrzés (opcionális, de ajánlott AJAX-nál is)
$headers = getallheaders();
$csrf_token = $headers['X-CSRF-TOKEN'] ?? '';
if ($csrf_token !== $_SESSION['csrf_token']) {
    // echo json_encode(['success' => false, 'message' => 'CSRF hiba']);
    // exit;
}

// Bemeneti adatok lekérése
$data = json_decode(file_get_contents('php://input'), true);
$target_id = $data['target_id'] ?? 0;
$action = $data['action'] ?? '';

if (!$target_id || !in_array($action, ['like', 'pass'])) {
    echo json_encode(['success' => false, 'message' => 'Érvénytelen adatok']);
    exit;
}

try {
    // 1. Interakció rögzítése
    $stmt = $pdo->prepare("INSERT INTO interactions (user_id, target_id, action) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $target_id, $action]);

    $is_match = false;

    // 2. Ha 'like' volt, nézzük meg, van-e kölcsönösség
    if ($action === 'like') {
        $stmt_check = $pdo->prepare("SELECT id FROM interactions WHERE user_id = ? AND target_id = ? AND action = 'like'");
        $stmt_check->execute([$target_id, $user_id]);
        
        if ($stmt_check->fetch()) {
            $is_match = true;
            
            // 3. Match rögzítése (ha még nincs)
            $user_one = min($user_id, $target_id);
            $user_two = max($user_id, $target_id);
            
            $stmt_match = $pdo->prepare("INSERT IGNORE INTO matches (user_one_id, user_two_id) VALUES (?, ?)");
            $stmt_match->execute([$user_one, $user_two]);

            // 4. Értesítés küldése (opcionális, de jó ha van egy külön táblában is)
            // Itt most csak a JSON válaszra hagyatkozunk a frontendnél.
        }
    }

    echo json_encode([
        'success' => true,
        'is_match' => $is_match,
        'message' => $is_match ? 'Match!' : 'Sikeres interakció'
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Adatbázis hiba: ' . $e->getMessage()]);
}
?>
