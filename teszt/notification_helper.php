<?php
// notification_helper.php - Értesítések és Email központi kezelése
require_once 'db.php';
require_once 'email_helper.php';

/**
 * Új értesítés létrehozása (Adatbázis + Email)
 *
 * @param int $user_id A címzett felhasználó ID-ja
 * @param string $type Típus: profile_view, message, approval, deletion
 * @param string $message Az értesítés szövege (Web)
 * @param string $link Link a művelethez (pl. profile.php?id=X)
 * @param string|null $email_subject Opcionális: Email tárgya (ha van, küld emailt is)
 * @param string|null $email_body Opcionális: Email szövege (ha más, mint a webes)
 */
function create_notification($user_id, $type, $message, $link, $email_subject = null, $email_body = null)
{
    global $pdo;

    // 1. Mentés az adatbázisba
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message, link) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $type, $message, $link]);
    } catch (PDOException $e) {
        // Hiba esetén naplózhatunk, de ne állítsuk meg a futást
        error_log("Notification DB Error: " . $e->getMessage());
    }

    // 2. Email küldése (ha meg van adva tárgy)
    if ($email_subject) {
        try {
            // Címzett email címének lekérése
            $stmt = $pdo->prepare("SELECT email, nickname FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            if ($user && !empty($user['email'])) {
                // Ha nincs külön email body, használjuk a webes üzenetet + linket
                $final_body = $email_body ? $email_body : $message;
                if ($link) {
                    // Abszolút URL generálása (példa)
                    $base_url = "http://localhost/teszt%20honlap/teszt%20honlap/"; // Ezt élesben configból kéne
                    $final_body .= "\n\nMegtekintés: " . $base_url . $link;
                }

                send_system_email($user['email'], $email_subject, $final_body);
            }
        } catch (PDOException $e) {
            error_log("Notification Email Error: " . $e->getMessage());
        }
    }
}

/**
 * Olvasatlan értesítések száma
 */
function get_unread_count($user_id)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}
?>