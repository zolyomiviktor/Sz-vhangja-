<?php
// header.php - Modern, Minimalista Üveghatással
require_once 'db.php';
require_once 'avatar_helper.php';
require_once 'notification_helper.php';

// Aktuális oldal neve
$current_page = basename($_SERVER['PHP_SELF']);

// Bejelentkezett felhasználó adatainak lekérése (ha van session)
$user_data = null;
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    try {
        $stmt_header = $pdo->prepare("SELECT nickname, profile_image FROM users WHERE id = ?");
        $stmt_header->execute([$uid]);
        $user_data = $stmt_header->fetch();
        if ($user_data) {
            $user_data['unread_count'] = get_unread_count($uid);
        }
    } catch (PDOException $e) {
        // Hiba esetén nem omlik össze, csak nem lesz avatar
    }
}
?>
<style>
/* CRITICAL UI FIX - Header stability */
.avatar-small, img.avatar-small { width: 36px !important; height: 36px !important; border-radius: 50% !important; object-fit: cover !important; flex-shrink: 0 !important; border: 2px solid white !important; }
.avatar-medium, img.avatar-medium { width: 60px !important; height: 60px !important; border-radius: 50% !important; object-fit: cover !important; }
.avatar-large, img.avatar-large, .card-photo-edge { width: 100% !important; height: 100% !important; border-radius: inherit !important; object-fit: cover !important; display: block !important; }
header { position: sticky !important; top: 0 !important; z-index: 1000 !important; height: 70px !important; }
.nav-icons { display: flex !important; gap: 15px !important; align-items: center !important; }
.user-profile-header { display: flex !important; align-items: center !important; gap: 10px !important; max-height: 44px !important; overflow: hidden !important; }
.large-profile-card { max-width: 500px !important; margin: 0 auto !important; border-radius: 32px !important; overflow: hidden !important; aspect-ratio: 3/4 !important; }
</style>



<a href="#main-content" class="skip-link">Ugrás a tartalomra</a>

<header role="banner" class="glass-header">
    <nav aria-label="Fő menü">
        <!-- Bal oldal: Logó -->
        <a href="index.php" class="nav-logo" aria-label="Szívhangja főoldal">
            Szívhangja
        </a>

        <?php if ($user_data): ?>
            <!-- Navigációs Menü (Modernizált Szöveg) -->
            <ul class="nav-links">
                <li><a href="browse.php" class="nav-link<?= $current_page == 'browse.php' ? ' active' : '' ?>">Böngészés</a></li>
                <li><a href="user_wall.php" class="nav-link<?= $current_page == 'user_wall.php' ? ' active' : '' ?>">Fal</a></li>
                <li><a href="messages.php" class="nav-link<?= $current_page == 'messages.php' ? ' active' : '' ?>">Üzenetek</a></li>
                <li><a href="notifications.php" class="nav-link<?= $current_page == 'notifications.php' ? ' active' : '' ?>">Értesítések</a></li>
                <li><a href="profile.php" class="nav-link<?= $current_page == 'profile.php' ? ' active' : '' ?>">Profilom</a></li>
            </ul>

            <!-- Jobb oldal: Felhasználó -->
            <div class="nav-right">
                <a href="profile.php" class="user-profile-header">
                    <div class="user-info-text">
                        <span class="user-name"><?= htmlspecialchars($user_data['nickname']) ?></span>
                    </div>
                    <?= render_avatar($user_data, 'small') ?>
                </a>
                <a href="logout.php" class="logout-minimal" aria-label="Kijelentkezés">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-log-out"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                </a>
            </div>

        <?php else: ?>
            <!-- Vendég nézet -->
            <div class="nav-guest">
                <a href="login.php" class="btn secondary btn-small">Bejelentkezés</a>
                <a href="register.php" class="btn btn-small">Regisztráció</a>
            </div>
        <?php endif; ?>
    </nav>

</header>