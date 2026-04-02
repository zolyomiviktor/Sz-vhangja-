<?php
require_once 'db.php';

try {
    $sql = file_get_contents('forum_setup.sql');
    
    // Táblák létrehozása után alapértelmezett kategóriák beszúrása
    $pdo->exec("
        INSERT IGNORE INTO forum_categories (id, name, description) VALUES
        (1, 'Tippek és Trükkök', 'Hasznos tanácsok a mindennapokhoz.'),
        (2, 'Személyes Tapasztalatok', 'Oszd meg a saját történeted másokkal.'),
        (3, 'Segítségkérés', 'Kérdezz bátran a közösségtől!'),
        (4, 'Általános Beszélgetés', 'Bármi, ami nem fér bele a többi kategóriába.')
    ");
    
    echo "<h1 style='color: green;'>Sikeres telepítés és kategóriák feltöltése!</h1>";
    echo "<p>A fórum táblák (kategóriák, posztok, kommentek) létrejöttek a <b>szivhang_db</b> adatbázisban.</p>";
    echo "<a href='admin/forum_moderation.php'>Vissza a Moderációhoz</a>";
    
} catch (Exception $e) {
    echo "<h1 style='color: red;'>Hiba történt!</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
