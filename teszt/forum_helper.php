<?php

/**
 * Fórumhoz kapcsolódó segédfüggvények.
 */

/**
 * Ellenőrzi, hogy a megadott felhasználó rendelkezik-e 'verified' státusszal.
 * 
 * @param PDO $pdo Az adatbázis kapcsolat objektuma
 * @param int|null $userId A bejelentkezett felhasználó ID-ja (pl. $_SESSION['user_id'])
 * @return bool True, ha a felhasználó létezik és be van vizsgálva (verified), különben false.
 */
function canAccessForum(PDO $pdo, $userId): bool {
    if (!$userId) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            return true;
        }
    } catch (PDOException $e) {
        error_log("Fórum jogosultság ellenőrzési hiba: " . $e->getMessage());
    }

    return false;
}

/**
 * Tartalom szűrésére használt függvény.
 * A beküldött posztokat és kommenteket vizsgálja meg.
 * 
 * @param string $text A vizsgálandó szöveg.
 * @return array A szűrés eredménye: ['status' => 'rejected'|'flagged'|'approved', 'message' => string]
 */
function contentFilter(string $text): array {
    // 1. Tiltott szavak listája (bővíthető admin felületen keresztül is a jövőben)
    $bannedWords = [
        'tiltottszó1', 'tiltottszó2', 'tiltottszó3'
    ]; // Ezeket érdemes majd valódibb szavakra vagy adatbázis alapú tárolásra cserélni

    $lowerText = mb_strtolower($text, 'UTF-8');

    // 2. Szavankénti szűrés
    foreach ($bannedWords as $word) {
        if (mb_strpos($lowerText, mb_strtolower($word, 'UTF-8')) !== false) {
            return [
                'status' => 'rejected',
                'message' => 'A beküldött szöveg sértő vagy tiltott kifejezéseket tartalmaz. Kérjük, módosítsd a tartalmat.'
            ];
        }
    }

    // 3. Gyanús tartalmak vizsgálata (flagelés adminisztrátori átnézésre)
    $isFlagged = false;
    $flagReasons = [];

    // Külső webes hivatkozások ellenőrzése
    if (preg_match('/(https?:\/\/[^\s]+|www\.[^\s]+)/i', $text)) {
        $isFlagged = true;
        $flagReasons[] = 'Külső hivatkozást (linket) tartalmaz';
    }

    // Telefonszám gyanújának ellenőrzése (Laza ellenőrzés: egy szöveg legalább 8 számjegyet tartalmaz)
    $digits = preg_replace('/[^0-9]/', '', $text);
    if (strlen($digits) >= 8) {
        $isFlagged = true;
        $flagReasons[] = 'Feltételezett telefonszámot tartalmaz';
    }

    if ($isFlagged) {
        return [
            'status' => 'flagged',
            'message' => implode(', ', $flagReasons) // Hasznos naplózáshoz vagy az adminnak
        ];
    }

    // 4. Ha minden teszten átment
    return [
        'status' => 'approved',
        'message' => 'A tartalom megfelelő.'
    ];
}
