<?php
// encryption_config.php - Titkosítási kulcsok
// FONTOS: Ezt a fájlt nem szabad verziókövetőbe tenni éles környezetben!
// Apache beállításokkal védeni kell a közvetlen hozzáféréstől.

define('ENCRYPTION_KEY', hex2bin('0d8c0f9c6f89fdf6bf226cddcb97f8a69677bbb250a2b931bb1b483b81dda1e3'));
define('ENCRYPTION_METHOD', 'aes-256-cbc');
?>