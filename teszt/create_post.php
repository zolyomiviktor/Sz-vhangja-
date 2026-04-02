<?php
session_start();

// Feltételezzük a db.php meglétét, ami a $pdo-t biztosítja
require_once 'db.php'; 
require_once 'forum_helper.php';

// Csak POST kéréseken keresztül lehet posztolni
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forum.php');
    exit;
}

$currentUserId = $_SESSION['user_id'] ?? null;

// 1. Jogosultság ellenőrzése
if (!canAccessForum($pdo, $currentUserId)) {
    $_SESSION['error'] = 'Csak regisztrált felhasználók tehetnek közzé bejegyzést a fórumban.';
    header('Location: forum.php');
    exit;
}

// Bemeneti adatok begyűjtése
$categoryId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
$title = trim(filter_input(INPUT_POST, 'title', FILTER_UNSAFE_RAW));
$content = trim(filter_input(INPUT_POST, 'content', FILTER_UNSAFE_RAW));
$isAnonymous = isset($_POST['is_anonymous']) && $_POST['is_anonymous'] === '1' ? 1 : 0;

// Validálás
if (!$categoryId || empty($title) || empty($content)) {
    $_SESSION['error'] = 'Kérjük, minden kötelező mezőt (kategória, cím, tartalom) tölts ki!';
    // Ideális esetben a form oldalra irányítunk vissza, de az egyszerűség kedvéért most a főoldalra:
    header('Location: forum.php');
    exit;
}

// 2. Tartalomszűrés (A címet is érdemes ellenőrizni, így összefűzzük a tartalommal teszteléskor)
$fullTextToCheck = $title . " " . $content;
$filterResult = contentFilter($fullTextToCheck);

$isApproved = 1;
$flagReason = null;

// Szűrési logika lekezelése
if ($filterResult['status'] === 'rejected') {
    // Visszadobjuk
    $_SESSION['error'] = $filterResult['message'];
    header('Location: forum.php');
    exit;
} elseif ($filterResult['status'] === 'flagged') {
    // Moderációra vár, is_approved = 0
    $isApproved = 0;
    $flagReason = $filterResult['message'];
    $_SESSION['info'] = 'A posztodat rögzítettük, de egy moderátornak jóvá kell hagynia a megjelenése előtt.';
} else {
    // Minden szuper
    $_SESSION['success'] = 'A poszt sikeresen közzétéve!';
}

// 3. Mentés az adatbázisba
try {
    $stmt = $pdo->prepare("
        INSERT INTO forum_posts 
        (category_id, user_id, title, content, is_anonymous, is_approved, flag_reason) 
        VALUES 
        (:category_id, :user_id, :title, :content, :is_anonymous, :is_approved, :flag_reason)
    ");
    
    $stmt->execute([
        ':category_id' => $categoryId,
        ':user_id' => $currentUserId,
        ':title' => $title,
        ':content' => $content,
        ':is_anonymous' => $isAnonymous,
        ':is_approved' => $isApproved,
        ':flag_reason' => $flagReason
    ]);

    // Opcionálisan lekérhetjük a beillesztett poszt azonosítóját, ha egyből arra oldalra vinnénk:
    // $newPostId = $pdo->lastInsertId();

    header('Location: forum.php');
    exit;

} catch (PDOException $e) {
    // Adatbázis hiba esetén érdemes logolni a pontos részleteket, és egy általános hibaüzenetet mutatni:
    error_log('Hiba a fórum poszt mentésekor: ' . $e->getMessage());
    $_SESSION['error'] = 'Technikai hiba történt a közzététel során (Adatbázis hiba).';
    header('Location: forum.php');
    exit;
}
