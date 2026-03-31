<?php
// encryption_helper.php - Titkosítási segédfüggvények

require_once 'encryption_config.php';

/**
 * Üzenet titkosítása adatbázisba mentés előtt
 * @param string $plaintext A titkosítandó szöveg
 * @return string A titkosított, base64 kódolt szöveg
 */
function encrypt_message($plaintext)
{
    if (empty($plaintext))
        return $plaintext;

    $ivLength = openssl_cipher_iv_length(ENCRYPTION_METHOD);
    $iv = openssl_random_pseudo_bytes($ivLength);

    $ciphertext = openssl_encrypt($plaintext, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);

    // IV + Ciphertext összefűzése és base64 kódolása
    return base64_encode($iv . $ciphertext);
}

/**
 * Üzenet visszafejtése megjelenítés előtt
 * @param string $ciphertext A titkosított szöveg
 * @return string A visszafejtett szöveg, vagy az eredeti ha nem sikerült (legacy support)
 */
function decrypt_message($ciphertext)
{
    if (empty($ciphertext))
        return $ciphertext;

    try {
        $data = base64_decode($ciphertext, true);

        // Ha nem base64, akkor valószínűleg régi, titkosítatlan üzenet
        if ($data === false) {
            return $ciphertext;
        }

        $ivLength = openssl_cipher_iv_length(ENCRYPTION_METHOD);

        // Ha rövidebb mint az IV, akkor nem valid titkosított adat
        if (strlen($data) <= $ivLength) {
            return $ciphertext;
        }

        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);

        $decrypted = openssl_decrypt($encrypted, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);

        // Ha a visszafejtés sikertelen (false), akkor adjuk vissza az eredetit
        // Ez védi a "véletlenül base64-nek tűnő" de nem titkosított régi üzeneteket is
        if ($decrypted === false) {
            return $ciphertext;
        }

        return $decrypted;

    } catch (Exception $e) {
        // Hiba esetén fallback az eredeti szövegre
        return $ciphertext;
    }
}
?>