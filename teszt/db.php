<?php
// db.php - Adatbázis kapcsolat és Biztonsági Konfiguráció

// 1. Biztonságos Session Beállítások
// Ezeket a session_start() előtt kell beállítani
ini_set('session.cookie_httponly', 1); // XSS ellen: JS nem fér hozzá a sütihez
ini_set('session.use_only_cookies', 1); // URL-ben ne legyen session ID
ini_set('session.cookie_samesite', 'Lax'); // CSRF ellen védelem, de kompatibilisabb mint a Strict

// Session indítása (ha még nem megy)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. CSRF Token Generálás
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 3. Adatbázis Kapcsolat
$host = 'localhost';
$db = 'szivhang_db';
$user = 'root';
$pass = ''; // XAMPP alapértelmezett jelszó (üres)
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Biztonsági okból élesben ne írjuk ki a pontos hibát a usernek, csak logoljuk
    // throw new \PDOException($e->getMessage(), (int)$e->getCode());
    die("Adatbázis kapcsolódási hiba. Kérlek próbáld később.");
}
?>