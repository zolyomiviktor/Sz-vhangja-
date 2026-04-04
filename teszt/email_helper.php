<?php
// email_helper.php - Központi email küldő függvény (PHPMailer + SMTP)

// PHPMailer fájlok beillesztése a libs mappából
require_once __DIR__ . '/libs/PHPMailer/Exception.php';
require_once __DIR__ . '/libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/libs/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Segédfüggvény a .env fájl olvasásához (ha getenv/$_ENV nincs beállítva).
 */
function get_env_var(string $varName): string|false
{
    $val = getenv($varName);
    if ($val !== false && $val !== '') return $val;

    $envPath = __DIR__ . '/.env';
    if (!is_readable($envPath)) return false;

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_starts_with($line, $varName . '=')) {
            return trim(substr($line, strlen($varName) + 1), '"\'');
        }
    }
    return false;
}

/**
 * Rendszer email küldése SMTP-n keresztül PHPMailer segítségével.
 *
 * @param string $to Címzett email címe
 * @param string $subject Tárgy
 * @param string $body Üzenet törzse (sima szöveg)
 * @return bool Sikeresség
 */
function send_system_email($to, $subject, $body)
{
    $mail = new PHPMailer(true);

    try {
        // SMTP Beállítások a .env alapján
        $mail->isSMTP();
        $mail->Host       = get_env_var('SMTP_HOST') ?: 'smtp.example.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = get_env_var('SMTP_USER') ?: 'noreply@szivhang.hu';
        $mail->Password   = get_env_var('SMTP_PASS') ?: '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)(get_env_var('SMTP_PORT') ?: 587);

        // Kódolás és feladó
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(get_env_var('SMTP_USER') ?: 'noreply@szivhang.hu', 'Szívhangja');
        $mail->addAddress($to);

        // Tartalom
        $mail->isHTML(false); // Sima szöveg
        $mail->Subject = $subject;
        $mail->Body    = $body;

        return $mail->send();
    } catch (Exception $e) {
        // Hibanaplózás, a felhasználónak ne dobjunk 500-as hibát
        error_log("Email küldési hiba: {$mail->ErrorInfo}");
        return false;
    }
}
?>