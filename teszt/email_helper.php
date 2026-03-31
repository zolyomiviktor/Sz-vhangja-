<?php
// email_helper.php - Központi email küldő függvény

/**
 * Rendszer email küldése (plain text + UTF-8)
 *
 * @param string $to Címzett email címe
 * @param string $subject Tárgy
 * @param string $body Üzenet törzse
 * @return bool Sikeresség
 */
function send_system_email($to, $subject, $body)
{
    $headers = "From: noreply@szivhang.hu\r\n";
    $headers .= "Reply-To: noreply@szivhang.hu\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // Hiba elnyomása (@), hogy a felületen ne jelenjenek meg szerverhibák,
    // a visszatérési értékkel kezeljük a sikert.
    return @mail($to, $subject, $body, $headers);
}
?>