<?php
// api_messages.php - Üzenet küldése AJAX-on keresztül
require 'db.php';
require 'email_helper.php';
require_once 'encryption_helper.php';
require_once 'notification_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Bejelentkezés szükséges.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Érvénytelen metódus.']);
    exit;
}

// CSRF Ellenőrzés
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Érvénytelen CSRF token.']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$recipient_id = isset($_POST['recipient_id']) ? (int) $_POST['recipient_id'] : 0;
$body = isset($_POST['body']) ? trim($_POST['body']) : '';

if (empty($body)) {
    echo json_encode(['success' => false, 'error' => 'Nem küldhetsz üres üzenetet!']);
    exit;
}

if (empty($recipient_id)) {
    echo json_encode(['success' => false, 'error' => 'Válassz címzettet!']);
    exit;
}

try {
    // TITKOSÍTÁS
    $encrypted_body = encrypt_message($body);

    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, recipient_id, body) VALUES (?, ?, ?)");
    if ($stmt->execute([$current_user_id, $recipient_id, $encrypted_body])) {

        // Értesítés küldése
        try {
            $stmt_sender = $pdo->prepare("SELECT nickname FROM users WHERE id = ?");
            $stmt_sender->execute([$current_user_id]);
            $sender_name = $stmt_sender->fetchColumn();

            create_notification(
                $recipient_id,
                'message',
                "Új üzeneted érkezett tőle: $sender_name",
                "messages.php",
                "Új üzeneted érkezett - Szívhangja",
                "Szia!\n\nÚj privát üzeneted érkezett tőle: $sender_name.\nJelentkezz be az oldalra az olvasáshoz!"
            );
        } catch (Exception $e) {
            // Értesítési hiba nem szakítja meg a folyamatot
        }

        echo json_encode(['success' => true, 'message' => 'Üzenet sikeresen elküldve!']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Hiba történt az üzenet mentésekor.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Adatbázis hiba: ' . $e->getMessage()]);
}
