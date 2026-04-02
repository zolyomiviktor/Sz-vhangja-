<?php
// report_handler.php - Pánikgomb (Jelentés és Blokkolás) központi feldolgozója
require_once 'db.php';
require_once 'encryption_helper.php';

// 1. Session-alapú hitelesítés
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];

// 2. Csak POST kérés fogadása és CSRF védelem
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Biztonsági hiba: Érvénytelen CSRF token.");
}

// 3. Bemeneti adatok validálása (Prepared statements elleni védelem implicit a PDO használatával)
$target_id = (int)($_POST['target_id'] ?? 0);
$action = $_POST['action'] ?? ''; // 'report' vagy 'report_block'

if ($target_id <= 0 || !in_array($action, ['report', 'report_block'])) {
    $_SESSION['error_msg'] = "Érvénytelen adatok a jelentéshez.";
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?: "messages.php"));
    exit;
}

try {
    // 4. Kontextus lekérése: Az üzenetváltás utolsó 5 sora
    // Biztonságosan, prepared statement-tel kérjük le a két fél közötti legutóbbi üzeneteket
    $stmt_msgs = $pdo->prepare("
        SELECT m.sender_id, m.body, m.sent_at, u.nickname as sender_name
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE (m.sender_id = ? AND m.recipient_id = ?)
           OR (m.sender_id = ? AND m.recipient_id = ?)
        ORDER BY m.sent_at DESC
        LIMIT 5
    ");
    $stmt_msgs->execute([$current_user_id, $target_id, $target_id, $current_user_id]);
    $last_messages = array_reverse($stmt_msgs->fetchAll());

    $chat_context = "";
    if (empty($last_messages)) {
        $chat_context = "Nincsenek korábbi üzenetek a két fél között.";
    } else {
        foreach ($last_messages as $msg) {
            // Titkosított üzenet dekódolása a kontextushoz
            $decrypted_body = decrypt_message($msg['body']);
            $chat_context .= "[" . $msg['sent_at'] . "] " . $msg['sender_name'] . ": " . $decrypted_body . "\n";
        }
    }

    // 5. Mentés a reports táblába
    $reason = ($action === 'report_block') 
        ? "Pánikgomb: Jelentés és azonnali blokkolás a chatből." 
        : "Pánikgomb: Moderátori jelentés a chatből.";
    
    // A chat_context-et az imént hozzáadott oszlopba mentjük
    $stmt_rep = $pdo->prepare("
        INSERT INTO reports (content_type, content_id, reporter_id, reason, chat_context, status) 
        VALUES ('user', ?, ?, ?, ?, 'pending')
    ");
    $stmt_rep->execute([$target_id, $current_user_id, $reason, $chat_context]);

    // Admin email értesítés (új funkció)
    try {
        require_once 'email_helper.php';
        // A super_admin és admin szerepkörű aktív felhasználókat keressük az admins táblában
        $stmt_admins = $pdo->query("SELECT email, nickname FROM admins WHERE is_active = 1");
        while ($admin = $stmt_admins->fetch()) {
            $subject = "SÜRGŐS: Pánikgomb riasztás - Szívhangja";
            $messageBody = "Kedves " . $admin['nickname'] . "!\n\n";
            $messageBody .= "Egy felhasználó használta a Pánikgombot a chat felületen.\n\n";
            $messageBody .= "Művelet: " . ($action === 'report_block' ? "Jelentés és azonnali blokkolás" : "Csak jelentés") . "\n";
            $messageBody .= "Jelentő (ID): " . $current_user_id . "\n";
            $messageBody .= "Jelentett felhasználó (ID): " . $target_id . "\n\n";
            
            $host = $_SERVER['HTTP_HOST'];
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
            $messageBody .= "Direkt link a jelentett profiljához (admin felületen):\n";
            $messageBody .= $protocol . $host . "/teszt/admin/user_view.php?id=" . $target_id . "\n\n";
            
            $messageBody .= "Kontextus (utolsó 5 üzenet):\n";
            $messageBody .= $chat_context . "\n\n";
            
            $messageBody .= "Kérjük, mielőbb vizsgáld ki az esetet az adminisztrációs felületen!\n";
            
            send_system_email($admin['email'], $subject, $messageBody);
        }
    } catch (Exception $e) {
        error_log("Pánikgomb email küldési hiba: " . $e->getMessage());
    }

    // 6. Opcionális blokkolás kezelése
    if ($action === 'report_block') {
        // Frissítjük a blocks táblát
        $stmt_block = $pdo->prepare("INSERT IGNORE INTO blocks (blocker_id, blocked_id) VALUES (?, ?)");
        $stmt_block->execute([$current_user_id, $target_id]);

        // Naplózzuk az interakciót is a rendszerben
        $stmt_int = $pdo->prepare("INSERT INTO interactions (user_id, target_id, action) VALUES (?, ?, 'block')");
        $stmt_int->execute([$current_user_id, $target_id]);

        // Blokkolás esetén a matchet is töröljük, hogy ne láthassák többé egymást
        $stmt_del_match = $pdo->prepare("
            DELETE FROM matches 
            WHERE (user_one_id = ? AND user_two_id = ?) 
               OR (user_one_id = ? AND user_two_id = ?)
        ");
        $stmt_del_match->execute([$current_user_id, $target_id, $target_id, $current_user_id]);

        $_SESSION['success_msg'] = "Sikeres jelentés. A felhasználót blokkoltuk, többé nem láthatjátok egymást.";
    } else {
        // Csak jelentés rögzítése az interactions-be is
        $stmt_int = $pdo->prepare("INSERT INTO interactions (user_id, target_id, action) VALUES (?, ?, 'report')");
        $stmt_int->execute([$current_user_id, $target_id]);

        $_SESSION['success_msg'] = "Köszönjük a bejelentést! A moderátorok hamarosan felülvizsgálják az esetet.";
    }

    // Visszatérés a chat felületre
    header("Location: messages.php");
    exit;

} catch (PDOException $e) {
    // Hiba esetén naplózunk és értesítjük a felhasználót
    error_log("Report Handler Error: " . $e->getMessage());
    $_SESSION['error_msg'] = "Rendszerhiba történt a jelentés feldolgozása során. Kérjük, próbáld újra később.";
    header("Location: messages.php");
    exit;
}
?>
