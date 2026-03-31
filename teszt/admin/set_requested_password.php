<?php
require_once '../db.php';

$email = 'zovikm@gmail.com';
$password = 'ViktorMisu1';
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("UPDATE admins SET password_hash = ?, force_password_change = 0 WHERE email = ?");
    $stmt->execute([$hashedPassword, $email]);
    if ($stmt->rowCount() > 0) {
        echo "Sikeres jelszó frissítés a Szuper Admin részére!<br>";
        echo "Email: " . htmlspecialchars($email) . "<br>";
        echo "Új jelszó beállítva: " . htmlspecialchars($password) . "<br>";
        echo "Jelszócsere kényszerítése kikapcsolva.";
    } else {
        echo "Nem található ilyen email című admin vagy a jelszó már ez volt: " . htmlspecialchars($email);
    }
} catch (PDOException $e) {
    echo "Hiba: " . $e->getMessage();
}
unlink(__FILE__);
?>