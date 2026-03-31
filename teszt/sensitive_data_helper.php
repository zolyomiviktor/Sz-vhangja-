<?php
/**
 * sensitive_data_helper.php
 *
 * Biztonságos AES-256-GCM titkosító osztály érzékeny adatokhoz
 * (pl. pontos tartózkodási hely, egészségügyi preferenciák).
 *
 * Algoritmus: AES-256-GCM (hitelesített titkosítás – véd a módosítás ellen)
 * Kulcskezelés: A kulcs kizárólag a .env fájlból töltődik be,
 *               NEM a forráskódból.
 *
 * Formátum (base64): [12 bájt nonce][16 bájt auth tag][ciphertext]
 */

class SensitiveDataEncryptor
{
    private const CIPHER      = 'aes-256-gcm';
    private const NONCE_LEN   = 12;   // GCM ajánlott nonce méret
    private const TAG_LEN     = 16;   // GCM auth tag (max 16 bájt)
    private const KEY_ENV_VAR = 'SENSITIVE_DATA_KEY';

    /** @var string Binary kulcs (32 bájt) */
    private static ?string $key = null;

    // -------------------------------------------------------------------------
    // Kulcs betöltése
    // -------------------------------------------------------------------------

    /**
     * Betölti és cacheli a titkosítási kulcsot a .env-ből / környezetből.
     *
     * @throws RuntimeException Ha a kulcs hiányzik vagy érvénytelen.
     */
    private static function loadKey(): string
    {
        if (self::$key !== null) {
            return self::$key;
        }

        // 1. Megpróbáljuk getenv()-el (Apache SetEnv, CLI, Docker stb.)
        $hexKey = getenv(self::KEY_ENV_VAR);

        // 2. Ha nincs, megpróbáljuk beolvasni a .env fájlból
        if ($hexKey === false || $hexKey === '') {
            $hexKey = self::readEnvFile(self::KEY_ENV_VAR);
        }

        if ($hexKey === false || $hexKey === '') {
            throw new RuntimeException(
                'Hiányzó titkosítási kulcs: a ' . self::KEY_ENV_VAR .
                ' környezeti változó nincs beállítva.'
            );
        }

        $binary = hex2bin($hexKey);
        if ($binary === false || strlen($binary) !== 32) {
            throw new RuntimeException(
                'Érvénytelen titkosítási kulcs: 32 bájtos (64 hex karakteres) kulcs szükséges.'
            );
        }

        self::$key = $binary;
        return self::$key;
    }

    /**
     * Egyszerű .env fájl olvasó (KEY=VALUE sorok).
     * A .env fájl a projekt gyökerében legyen.
     *
     * @param string $varName A keresett változó neve.
     * @return string|false Az érték, vagy false ha nem található.
     */
    private static function readEnvFile(string $varName): string|false
    {
        $envPath = __DIR__ . '/.env';
        if (!is_readable($envPath)) {
            return false;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            // Kihagyjuk a megjegyzéseket
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, $varName . '=')) {
                $value = substr($line, strlen($varName) + 1);
                // Eltávolítjuk az esetleges idézőjeleket
                return trim($value, '"\'');
            }
        }
        return false;
    }

    // -------------------------------------------------------------------------
    // Nyilvános API
    // -------------------------------------------------------------------------

    /**
     * Érzékeny adat titkosítása AES-256-GCM algoritmussal.
     *
     * @param string $plaintext A titkosítandó szöveg (UTF-8).
     * @return string           Base64-kódolt titkosított adat (nonce+tag+cipher).
     * @throws RuntimeException Titkosítási hiba esetén.
     */
    public static function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }

        $key   = self::loadKey();
        $nonce = random_bytes(self::NONCE_LEN);
        $tag   = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',           // AAD (nincs)
            self::TAG_LEN
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Titkosítás sikertelen: ' . openssl_error_string());
        }

        // Formátum: nonce (12) + auth_tag (16) + ciphertext
        return base64_encode($nonce . $tag . $ciphertext);
    }

    /**
     * Titkosított adat visszafejtése és hitelesítése.
     *
     * @param string $encoded   Az encrypt() által visszaadott base64 string.
     * @return string|false     A visszafejtett szöveg, vagy false hiba esetén.
     *                          FALSE = sérült adat / rossz kulcs / manipuláció!
     */
    public static function decrypt(string $encoded): string|false
    {
        if ($encoded === '') {
            return '';
        }

        try {
            $key = self::loadKey();

            $raw = base64_decode($encoded, true);
            if ($raw === false) {
                error_log('[SensitiveDataEncryptor] Érvénytelen base64 adat.');
                return false;
            }

            $minLen = self::NONCE_LEN + self::TAG_LEN;
            if (strlen($raw) <= $minLen) {
                error_log('[SensitiveDataEncryptor] Az adat túl rövid, nem valid ciphertext.');
                return false;
            }

            $nonce      = substr($raw, 0, self::NONCE_LEN);
            $tag        = substr($raw, self::NONCE_LEN, self::TAG_LEN);
            $ciphertext = substr($raw, $minLen);

            $plaintext = openssl_decrypt(
                $ciphertext,
                self::CIPHER,
                $key,
                OPENSSL_RAW_DATA,
                $nonce,
                $tag
            );

            if ($plaintext === false) {
                // GCM auth tag ellenőrzés sikertelen: MANIPULÁLT vagy HIBÁS KULCS
                error_log('[SensitiveDataEncryptor] Hitelesítési hiba – az adat módosítva lett vagy rossz kulcsot használ.');
                return false;
            }

            return $plaintext;

        } catch (RuntimeException $e) {
            error_log('[SensitiveDataEncryptor] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Visszaadja, hogy egy string SensitiveDataEncryptor által titkosított-e.
     * (Egyszerű szanity check – nem kriptográfiai garancia.)
     *
     * @param string $value Az ellenőrizendő érték.
     * @return bool
     */
    public static function isEncrypted(string $value): bool
    {
        if (strlen($value) < 4) {
            return false;
        }
        $raw = base64_decode($value, true);
        return $raw !== false && strlen($raw) > self::NONCE_LEN + self::TAG_LEN;
    }
}
?>
