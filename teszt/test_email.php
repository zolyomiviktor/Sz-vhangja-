<?php
// Egyszerű e-mail küldési teszt - TÖRÖLD LE ÉLÉ ELŐTT!
require_once __DIR__ . '/email_helper.php';

$to      = 'zovikm@gmail.com';
$subject = 'Tesztlevél - Szívhangja SMTP';
$body    = "Szia Viktor!\n\nEz egy tesztlevél az SMTP kapcsolat ellenőrzéséhez.\n\nHa ezt olvasod, az email küldés sikeresen működik!\n\nÜdvözlettel,\nSzívhangja Rendszer";

echo "Email küldése: $to\n";
$result = send_system_email($to, $subject, $body);

if ($result) {
    echo "✅ SIKER: Az email sikeresen elküldve!\n";
} else {
    echo "❌ HIBA: Az email küldése sikertelen. Ellenőrizd az SMTP adatokat a .env fájlban!\n";
}
