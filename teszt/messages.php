<?php
require 'db.php'; // Session start itt van!
require 'email_helper.php';
require_once 'encryption_helper.php'; // Titkosítás beemelése


// Csak bejelentkezett felhasználóknak
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Pánikgomb Logika (Jelentés / Blokkolás)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['report', 'report_block'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Biztonsági hiba: Érvénytelen CSRF token.");
    }

    $target_id = (int)$_POST['target_id'];
    $action_to_log = $_POST['action'] === 'report_block' ? 'block' : 'report';

    try {
        // Rögzítjük az interakciót (pl. 'report' vagy 'block') a felhasználói interakciók között
        $stmt = $pdo->prepare("INSERT INTO interactions (user_id, target_id, action) VALUES (?, ?, ?)");
        $stmt->execute([$current_user_id, $target_id, $action_to_log]);

        // Ugyanakkor küldjük be a Jelentések (reports) táblába is, hogy lássa az Admin!
        $reason = $_POST['action'] === 'report_block' ? "Pánikgomb: Jelentés és Azonnali Blokkolás a chatből." : "Pánikgomb: Moderátori jelentés a chatből.";
        $stmt_rep = $pdo->prepare("INSERT INTO reports (content_type, content_id, reporter_id, reason, status) VALUES ('user', ?, ?, ?, 'pending')");
        $stmt_rep->execute([$target_id, $current_user_id, $reason]);

        if ($_POST['action'] === 'report_block') {
            // Blokkolás esetén töröljük az esetleges matchet
            $del_match = $pdo->prepare("DELETE FROM matches WHERE (user_one_id = ? AND user_two_id = ?) OR (user_one_id = ? AND user_two_id = ?)");
            $del_match->execute([$current_user_id, $target_id, $target_id, $current_user_id]);
            
            $_SESSION['success_msg'] = "Felhasználó jelentve és blokkolva.";
            header("Location: messages.php");
            exit;
        } else {
            $success = "A felhasználót sikeresen jelentetted a moderátoroknak.";
        }
    } catch (PDOException $e) {
        $error = "Hiba történt a jelentés során: " . $e->getMessage();
    }
}

// 1. Üzenet Küldése Logika
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    // CSRF Ellenőrzés
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Biztonsági hiba: Érvénytelen CSRF token.");
    }

    $recipient_id = $_POST['recipient_id'];
    $body = trim($_POST['body']);

    if (empty($body)) {
        $error = "Nem küldhetsz üres üzenetet!";
    } elseif (empty($recipient_id)) {
        $error = "Válassz címzettet!";
    } else {
        try {
            // TITKOSÍTÁS: Mielőtt elmentjük, titkosítjuk az üzenetet
            $encrypted_body = encrypt_message($body);

            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, recipient_id, body) VALUES (?, ?, ?)");
            if ($stmt->execute([$current_user_id, $recipient_id, $encrypted_body])) {

                // Email és Értesítés küldése (notification_helper kezeli mindkettőt)
                require_once 'notification_helper.php';

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
                        // Az emailben NEM küldjük el a titkos üzenet tartalmát, csak értesítést!
                        "Szia!\n\nÚj privát üzeneted érkezett tőle: $sender_name.\nJelentkezz be az oldalra az olvasáshoz!"
                    );

                } catch (Exception $e) {
                    // Hiba esetén tovább
                }

                // Átirányítás ugyanarra a beszélgetésre
                header("Location: messages.php?recipient_id=" . $recipient_id);
                exit;
            } else {
                $error = "Hiba történt az üzenet mentésekor.";
            }
        } catch (PDOException $e) {
            $error = "Adatbázis hiba: " . $e->getMessage();
        }
    }
}

// 2. Olvasottnak jelölés (ha megnyitunk egy konkrét beszélgetést)
$other_user_id = isset($_GET['recipient_id']) ? (int) $_GET['recipient_id'] : (isset($_POST['recipient_id']) ? (int) $_POST['recipient_id'] : null);

if ($other_user_id) {
    try {
        $update_stmt = $pdo->prepare("
            UPDATE messages 
            SET read_at = NOW(), is_read = 1 
            WHERE recipient_id = ? AND sender_id = ? AND read_at IS NULL
        ");
        $update_stmt->execute([$current_user_id, $other_user_id]);
    } catch (PDOException $e) {
        // Csendben folytatjuk
    }
}

// 3. Beszélgetések vagy konkrét üzenetváltás lekérdezése
if ($other_user_id) {
    // KONKRÉT BESZÉLGETÉS (Thread View)
    $stmt = $pdo->prepare("
        SELECT m.*, u.nickname as sender_name
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE (m.recipient_id = ? AND m.sender_id = ?) 
           OR (m.recipient_id = ? AND m.sender_id = ?)
        ORDER BY m.sent_at ASC
    ");
    $stmt->execute([$current_user_id, $other_user_id, $other_user_id, $current_user_id]);
    $messages = $stmt->fetchAll();

    // Szükségünk van a partner nevére is a címhez
    $stmt_p = $pdo->prepare("SELECT nickname FROM users WHERE id = ?");
    $stmt_p->execute([$other_user_id]);
    $partner_name = $stmt_p->fetchColumn() ?: 'Ismeretlen';
} else {
    // BESZÉLGETÉSEK LISTÁJA (Inbox View)
    // Az utolsó üzenet alapján csoportosítva minden partnerrel
    $sql = "
        SELECT 
            partner.id as partner_id,
            partner.nickname as partner_name,
            m.id as last_msg_id,
            m.body as last_msg_body,
            m.sent_at as last_msg_time,
            m.sender_id as last_msg_sender,
            m.read_at as last_msg_read_at,
            m.is_read as last_msg_is_read
        FROM (
            SELECT 
                MAX(id) as max_id,
                CASE 
                    WHEN sender_id = :uid1 THEN recipient_id 
                    ELSE sender_id 
                END as partner_id
            FROM messages 
            WHERE sender_id = :uid2 OR recipient_id = :uid3
            GROUP BY partner_id
        ) last_msgs
        JOIN messages m ON last_msgs.max_id = m.id
        JOIN users partner ON last_msgs.partner_id = partner.id
        ORDER BY m.sent_at DESC
    ";
    $stmt_conv = $pdo->prepare($sql);
    $stmt_conv->execute([
        ':uid1' => $current_user_id,
        ':uid2' => $current_user_id,
        ':uid3' => $current_user_id
    ]);
    $conversations = $stmt_conv->fetchAll();
}

// 4. Felhasználók listája a "Címzett" legördülőhöz (kivéve magunkat)
$stmt_users = $pdo->prepare("SELECT id, nickname, email FROM users WHERE id != ? ORDER BY nickname");
$stmt_users->execute([$current_user_id]);
$users = $stmt_users->fetchAll();
?>


<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Üzenetek - Szívhangja</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <style>
        /* Messenger-Style Layout */
        .messenger-container {
            display: flex;
            height: calc(100vh - 120px);
            max-width: 1400px;
            margin: 2rem auto;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        /* Left Sidebar - Conversation List */
        .conversation-sidebar {
            width: 320px;
            border-right: 1px solid #e0e0e0;
            display: flex;
            flex-direction: column;
            background: #fff;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e0e0e0;
        }

        .sidebar-header h2 {
            margin: 0;
            font-size: 1.5rem;
            color: var(--text-main);
        }

        .conversation-list {
            flex: 1;
            overflow-y: auto;
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .conversation-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            gap: 12px;
            align-items: flex-start;
            text-decoration: none;
            color: inherit;
        }

        .conversation-item:hover {
            background: #f5f5f5;
        }

        .conversation-item.active {
            background: #fff0eb; /* Nagyon világos korall */
            border-left: 4px solid var(--primary-coral);
        }

        .conversation-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .conversation-info {
            flex: 1;
            min-width: 0;
        }

        .conversation-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 4px;
        }

        .conversation-name {
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--text-main);
        }

        .conversation-time {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .conversation-snippet {
            font-size: 0.85rem;
            color: #666;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .conversation-status {
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 4px;
        }

        .conversation-status.new {
            color: var(--primary-color);
        }

        .conversation-status.waiting {
            color: #757575;
        }

        /* Right Side - Chat Area */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #f5f5f5;
        }

        .chat-header {
            padding: 1.5rem;
            background: #fff;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chat-header h2 {
            margin: 0;
            font-size: 1.2rem;
            color: var(--text-main);
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .message-bubble {
            display: flex;
            gap: 12px;
            max-width: 70%;
        }

        .message-bubble.sent {
            align-self: flex-end;
            flex-direction: row-reverse;
        }

        .message-bubble.received {
            align-self: flex-start;
        }

        .bubble-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #9e9e9e 0%, #757575 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .bubble-content {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .bubble-text {
            padding: 12px 16px;
            border-radius: 18px;
            line-height: 1.4;
            word-wrap: break-word;
        }

        .message-bubble.sent .bubble-text {
            background: var(--primary-coral);
            color: var(--text-main);
            border-bottom-right-radius: 4px;
            font-weight: 500;
        }

        .message-bubble.received .bubble-text {
            background: #fff;
            color: var(--text-main);
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .bubble-meta {
            font-size: 0.75rem;
            color: #999;
            padding: 0 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .message-bubble.sent .bubble-meta {
            justify-content: flex-end;
        }

        .bubble-status {
            color: #2e7d32;
            font-weight: 500;
        }

        /* Chat Input */
        .chat-input-container {
            padding: 1.5rem;
            background: #fff;
            border-top: 1px solid #e0e0e0;
        }

        .chat-input-form {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }

        .chat-input-wrapper {
            flex: 1;
            position: relative;
        }

        .chat-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e0e0e0;
            border-radius: 24px;
            font-size: 0.95rem;
            resize: none;
            font-family: inherit;
            min-height: 48px;
            max-height: 120px;
        }

        .chat-input:focus {
            outline: var(--focus-outline);
            outline-offset: -2px;
            border-color: var(--primary-coral);
        }

        .chat-send-btn {
            background: var(--primary-coral);
            color: var(--text-main);
            border: none;
            padding: 12px 24px;
            border-radius: 24px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
            white-space: nowrap;
        }

        .chat-send-btn:hover {
            background: #ff6b3d;
            transform: scale(1.05);
        }

        .chat-send-btn:focus-visible {
            outline: var(--focus-outline);
            outline-offset: 3px;
        }

        /* Empty States */
        .empty-chat {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            padding: 2rem;
        }

        .empty-chat svg {
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .messenger-container {
                height: calc(100vh - 80px);
                margin: 0;
                border-radius: 0;
            }

            .conversation-sidebar {
                width: 100%;
                border-right: none;
            }

            .chat-area {
                display: none;
            }

            .messenger-container.chat-open .conversation-sidebar {
                display: none;
            }

            .messenger-container.chat-open .chat-area {
                display: flex;
            }
        }

        /* Accessibility */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border-width: 0;
        }

        /* Panic Button & Glassmorphism Modal */
        .panic-button {
            background-color: #ff3b30;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s, box-shadow 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 10px rgba(255, 59, 48, 0.3);
        }

        .panic-button:hover {
            background-color: #d32f2f;
            transform: scale(1.05);
            box-shadow: 0 6px 14px rgba(255, 59, 48, 0.4);
        }

        .glass-modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
        }

        .glass-modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .glass-modal-content {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 24px;
            padding: 2.5rem 2rem;
            width: 90%;
            max-width: 420px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            text-align: center;
            transform: translateY(20px);
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .glass-modal-overlay.active .glass-modal-content {
            transform: translateY(0);
        }

        .glass-modal-title {
            color: #d32f2f;
            font-size: 1.5rem;
            margin-bottom: 0.75rem;
            margin-top: 0;
            font-weight: 800;
        }

        .glass-modal-desc {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-bottom: 2rem;
            line-height: 1.5;
        }

        .glass-modal-btn {
            display: block;
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            margin-bottom: 12px;
            transition: all 0.2s ease;
            border: none;
        }

        .btn-report-only {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        .btn-report-only:hover {
            background: #ffcdd2;
            transform: translateY(-2px);
        }

        .btn-report-block {
            background: linear-gradient(135deg, #d32f2f, #b71c1c);
            color: white;
            box-shadow: 0 4px 12px rgba(198, 40, 40, 0.3);
        }
        .btn-report-block:hover {
            box-shadow: 0 6px 16px rgba(198, 40, 40, 0.4);
            transform: translateY(-2px);
        }

        .btn-cancel-modal {
            background: transparent;
            color: var(--text-main);
            border: 1px solid #e0e0e0;
        }
        .btn-cancel-modal:hover {
            background: #f5f5f5;
        }
    </style>
</head>

<body>

    <?php include 'header.php'; ?>

    <main id="main-content" role="main">
        <?php if ($error): ?>
            <div role="alert" class="alert"
                style="background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; max-width: 1400px; margin: 1rem auto; padding: 1rem; border-radius: 8px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php 
        $display_success = $success ?: ($_SESSION['success_msg'] ?? '');
        if ($display_success): 
            unset($_SESSION['success_msg']);
        ?>
            <div role="alert" class="alert"
                style="background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; max-width: 1400px; margin: 1rem auto; padding: 1rem; border-radius: 8px;">
                <?= htmlspecialchars($display_success) ?>
            </div>
        <?php endif; ?>

        <div class="messenger-container <?= $other_user_id ? 'chat-open' : '' ?>">
            <!-- Left Sidebar: Conversation List -->
            <aside class="conversation-sidebar" aria-label="Beszélgetések listája">
                <div class="sidebar-header">
                    <h2>Üzenetek</h2>
                </div>

                <?php if (!$other_user_id): ?>
                    <?php if (count($conversations) > 0): ?>
                        <ul class="conversation-list" role="list">
                            <?php foreach ($conversations as $conv):
                                $status_text = 'Beszélgetés folyamatban';
                                $status_class = '';

                                if ($conv['last_msg_sender'] == $current_user_id) {
                                    $status_text = 'Válaszra vár';
                                    $status_class = 'waiting';
                                } elseif (!$conv['last_msg_read_at']) {
                                    $status_text = 'Új üzenet';
                                    $status_class = 'new';
                                }

                                $snippet = decrypt_message($conv['last_msg_body']);
                                $initials = mb_substr($conv['partner_name'], 0, 1);
                                ?>
                                <li>
                                    <a href="messages.php?recipient_id=<?= $conv['partner_id'] ?>" class="conversation-item">
                                        <div class="conversation-avatar" aria-hidden="true">
                                            <?= htmlspecialchars($initials) ?>
                                        </div>
                                        <div class="conversation-info">
                                            <div class="conversation-header">
                                                <span class="conversation-name">
                                                    <?= htmlspecialchars($conv['partner_name']) ?>
                                                </span>
                                                <span class="conversation-time">
                                                    <?= date('H:i', strtotime($conv['last_msg_time'])) ?>
                                                </span>
                                            </div>
                                            <div class="conversation-snippet">
                                                <?= ($conv['last_msg_sender'] == $current_user_id ? 'Te: ' : '') . htmlspecialchars(mb_substr($snippet, 0, 40)) . (mb_strlen($snippet) > 40 ? '...' : '') ?>
                                            </div>
                                            <div class="conversation-status <?= $status_class ?>">
                                                <?= $status_text ?>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="empty-chat">
                            <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.5">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            </svg>
                            <h3>Még nincs üzeneted</h3>
                            <p>Kezdeményezz beszélgetést a böngészésnél!</p>
                            <a href="browse.php" class="btn">Böngészés</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <ul class="conversation-list" role="list">
                        <li>
                            <div class="conversation-item active">
                                <div class="conversation-avatar" aria-hidden="true">
                                    <?= htmlspecialchars(mb_substr($partner_name, 0, 1)) ?>
                                </div>
                                <div class="conversation-info">
                                    <div class="conversation-name">
                                        <?= htmlspecialchars($partner_name) ?>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>
                <?php endif; ?>
            </aside>

            <!-- Right Side: Chat Area -->
            <section class="chat-area" aria-label="Beszélgetés">
                <?php if ($other_user_id): ?>
                    <div class="chat-header">
                        <h2>
                            <?= htmlspecialchars($partner_name) ?>
                        </h2>
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <button id="panic-btn" class="panic-button" aria-label="Pánikgomb: Probléma jelentése és letiltás">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                                    <line x1="12" y1="9" x2="12" y2="13"></line>
                                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                                </svg>
                                Jelentés
                            </button>
                            <a href="messages.php" class="btn secondary" style="font-size: 0.9rem;">← Vissza</a>
                        </div>
                    </div>

                    <div class="chat-messages" role="log" aria-live="polite">
                        <?php if (count($messages) > 0): ?>
                            <?php foreach ($messages as $msg):
                                $is_sent = $msg['sender_id'] == $current_user_id;
                                $initials = mb_substr($msg['sender_name'], 0, 1);
                                ?>
                                <article class="message-bubble <?= $is_sent ? 'sent' : 'received' ?>" role="article">
                                    <div class="bubble-avatar" aria-hidden="true">
                                        <?= htmlspecialchars($initials) ?>
                                    </div>
                                    <div class="bubble-content">
                                        <div class="bubble-text">
                                            <?= nl2br(htmlspecialchars(decrypt_message($msg['body']))) ?>
                                        </div>
                                        <div class="bubble-meta">
                                            <span>
                                                <?= date('H:i', strtotime($msg['sent_at'])) ?>
                                            </span>
                                            <?php if ($is_sent): ?>
                                                <?php if ($msg['read_at']): ?>
                                                    <span class="bubble-status" aria-label="Az üzenetet elolvasták">
                                                        Látta
                                                    </span>
                                                <?php else: ?>
                                                    <span aria-label="Még nem olvasta">Elküldve</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-chat">
                                <p>Még nem váltottatok üzenetet.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="chat-input-container">
                        <form action="messages.php?recipient_id=<?= $other_user_id ?>" method="POST"
                            class="chat-input-form">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="recipient_id" value="<?= $other_user_id ?>">

                            <div class="chat-input-wrapper">
                                <textarea name="body" class="chat-input" placeholder="Írj üzenetet…" required rows="1"
                                    aria-label="Üzenet szövege"></textarea>
                            </div>

                            <button type="submit" name="send_message" class="chat-send-btn" aria-label="Üzenet küldése">
                                Küldés
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="empty-chat">
                        <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.5">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                        <h3>Válassz egy beszélgetést</h3>
                        <p>Kattints egy beszélgetésre a bal oldalon az üzenetek megtekintéséhez</p>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <?php if ($other_user_id): ?>
        <!-- Panic Modal -->
        <div id="panic-modal" class="glass-modal-overlay" aria-hidden="true" role="dialog" aria-labelledby="panic-modal-title">
            <div class="glass-modal-content">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#d32f2f" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 1rem;">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                <h3 id="panic-modal-title" class="glass-modal-title">Probléma jelentése</h3>
                <p class="glass-modal-desc">Kérjük, válaszd ki, milyen műveletet szeretnél végrehajtani a felhasználóval kapcsolatban.</p>
                
                <form id="panic-form" action="messages.php?recipient_id=<?= $other_user_id ?>" method="POST">
                    <input type="hidden" name="action" id="panic-action" value="">
                    <input type="hidden" name="target_id" value="<?= $other_user_id ?>">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <button type="button" class="glass-modal-btn btn-report-only" onclick="submitPanic('report')">
                        Csak jelentem a moderátoroknak
                    </button>
                    <button type="button" class="glass-modal-btn btn-report-block" onclick="submitPanic('report_block')">
                        Jelentem és azonnal blokkolom
                    </button>
                    <button type="button" class="glass-modal-btn btn-cancel-modal" onclick="closePanicModal()">
                        Mégsem
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </main>

    <?php include 'footer.php'; ?>

    <script>
        // Auto-resize textarea
        document.addEventListener('DOMContentLoaded', function () {
            const textarea = document.querySelector('.chat-input');
            if (textarea) {
                textarea.addEventListener('input', function () {
                    this.style.height = 'auto';
                    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
                });

                // Enter to send, Shift+Enter for new line
                textarea.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        this.closest('form').submit();
                    }
                });

                // Auto-scroll to bottom
                const chatMessages = document.querySelector('.chat-messages');
                if (chatMessages) {
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }
            }

            // Panic Button Logic
            const panicBtn = document.getElementById('panic-btn');
            const panicModal = document.getElementById('panic-modal');
            
            if (panicBtn && panicModal) {
                panicBtn.addEventListener('click', () => {
                    panicModal.classList.add('active');
                    panicModal.setAttribute('aria-hidden', 'false');
                });
            }

            // Close modal on outside click
            if (panicModal) {
                panicModal.addEventListener('click', (e) => {
                    if (e.target === panicModal) {
                        closePanicModal();
                    }
                });
            }
        });

        function closePanicModal() {
            const panicModal = document.getElementById('panic-modal');
            if (panicModal) {
                panicModal.classList.remove('active');
                panicModal.setAttribute('aria-hidden', 'true');
            }
        }

        function submitPanic(actionStr) {
            const panicAction = document.getElementById('panic-action');
            const panicForm = document.getElementById('panic-form');
            if (panicAction && panicForm) {
                panicAction.value = actionStr;
                panicForm.submit();
            }
        }
    </script>
</body>

</html>