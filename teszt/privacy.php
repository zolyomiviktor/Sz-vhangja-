<?php
/**
 * privacy.php – Adatkezelési Tájékoztató + GDPR Felhasználói Jogok
 */
require 'db.php';

$is_logged_in = isset($_SESSION['user_id']);
$current_user_id = $is_logged_in ? (int)$_SESSION['user_id'] : null;

// ── GDPR: Adatletöltés ───────────────────────────────────────────────────────
if ($is_logged_in && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'download_data') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Biztonsági hiba: Érvénytelen CSRF token.");
    }

    // Felhasználói alap adatok (jelszó és titkos mezők nélkül)
    $stmt = $pdo->prepare("
        SELECT id, nickname, email, birth_date, gender, bio, profile_image, created_at
        FROM users WHERE id = ?
    ");
    $stmt->execute([$current_user_id]);
    $user_data = $stmt->fetch();

    // Elküldött üzenetek száma
    $stmt_sent = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = ?");
    $stmt_sent->execute([$current_user_id]);
    $sent_count = (int)$stmt_sent->fetchColumn();

    // Fogadott üzenetek száma
    $stmt_recv = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE recipient_id = ?");
    $stmt_recv->execute([$current_user_id]);
    $recv_count = (int)$stmt_recv->fetchColumn();

    // Interakciók (like/pass) – opcionális tábla
    $interactions = [];
    try {
        $stmt_int = $pdo->prepare("SELECT action, target_user_id, created_at FROM interactions WHERE user_id = ? ORDER BY created_at DESC");
        $stmt_int->execute([$current_user_id]);
        $interactions = $stmt_int->fetchAll();
    } catch (PDOException $e) { /* tábla nem létezik */ }

    $export = [
        'export_generated_at'  => date('c'),
        'platform'             => 'Szívhangja',
        'gdpr_notice'          => 'Ez a fájl a GDPR 20. cikke alapján létrehozott adathordozhatósági csomag.',
        'profile'              => [
            'id'           => $user_data['id'],
            'nickname'     => $user_data['nickname'],
            'email'        => $user_data['email'],
            'birth_date'   => $user_data['birth_date'],
            'gender'       => $user_data['gender'],
            'bio'          => $user_data['bio'],
            'profile_image'=> $user_data['profile_image'] ? 'uploads/' . $user_data['profile_image'] : null,
            'registered_at'=> $user_data['created_at'],
        ],
        'messages_summary'     => [
            'sent_count'     => $sent_count,
            'received_count' => $recv_count,
            'note'           => 'Az üzenetek tartalma titkosítva van tárolva és nem kerül exportálásra.',
        ],
        'interactions'         => array_map(fn($r) => [
            'action'         => $r['action'],
            'target_user_id' => $r['target_user_id'],
            'at'             => $r['created_at'],
        ], $interactions),
    ];

    $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $filename = 'szivhangja_adataim_' . date('Ymd_His') . '.json';

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($json));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo $json;
    exit;
}
?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adatkezelési Tájékoztató - Szívhangja</title>
    <meta name="description" content="Szívhangja adatvédelmi tájékoztató és GDPR jogok kezelése.">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <style>
        .gdpr-panel {
            background: linear-gradient(135deg, #fff5f5 0%, #fff0eb 100%);
            border: 2px solid var(--primary-coral);
            border-radius: var(--border-radius-md);
            padding: 2rem;
            margin-top: 2.5rem;
        }

        .gdpr-panel h2 {
            color: var(--primary-color);
            margin-top: 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .gdpr-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .btn-gdpr-download {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--primary-coral);
            color: var(--deep-charcoal);
            font-weight: 700;
            padding: 0.85rem 1.8rem;
            border-radius: 50px;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
            font-family: inherit;
            transition: background 0.2s, transform 0.15s;
            text-decoration: none;
        }

        .btn-gdpr-download:hover {
            background: #ff6b3d;
            transform: translateY(-1px);
        }

        .btn-gdpr-download:focus-visible {
            outline: var(--focus-outline);
            outline-offset: 3px;
        }

        .btn-gdpr-delete {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: transparent;
            color: #c62828;
            font-weight: 700;
            padding: 0.85rem 1.8rem;
            border-radius: 50px;
            border: 2px solid #c62828;
            cursor: pointer;
            font-size: 0.95rem;
            font-family: inherit;
            transition: background 0.2s, color 0.2s;
            text-decoration: none;
        }

        .btn-gdpr-delete:hover {
            background: #ffebee;
        }

        .btn-gdpr-delete:focus-visible {
            outline: 3px solid #c62828;
            outline-offset: 3px;
        }

        .gdpr-note {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 1rem;
        }
    </style>
</head>

<body>

    <?php include 'header.php'; ?>

    <main id="main-content" role="main" style="max-width: 800px; margin: 0 auto;">

        <a href="#main-content" class="skip-link">Ugrás a tartalomra</a>

        <h1>Adatkezelési Tájékoztató</h1>
        <p><em>Utolsó frissítés: <?= date('Y. m. d.') ?></em></p>

        <p>Köszönjük, hogy a Szívhangját használod! Ez a dokumentum röviden és érthetően összefoglalja, hogy mi történik
            az adataiddal.</p>

        <h2>1. Milyen adatokat kezelünk?</h2>
        <p>Csak azokat az adatokat tároljuk, amiket Te magad adsz meg a regisztráció vagy a profil szerkesztése során:
        </p>
        <ul>
            <li><strong>Kötelező adatok:</strong> Becenév, Email cím, Jelszó (titkosítva),
                Születési dátum, Nem.</li>
            <li><strong>Opcionális adatok:</strong> Bemutatkozás, Mozgásállapot leírása.</li>
            <li><strong>Egyéb:</strong> Az általad küldött privát üzenetek tartalma (végponttól-végpontig titkosítva).</li>
        </ul>

        <h2>2. Miért kezeljük ezeket az adatokat?</h2>
        <p>Az adatkezelés célja kizárólag a társkereső szolgáltatás működtetése:</p>
        <ul>
            <li>Hogy létrehozhassuk a profilodat.</li>
            <li>Hogy más felhasználók megtalálhassanak a keresőben (becenév, kor, nem, mozgásállapot alapján).</li>
            <li>Hogy kapcsolatba léphessenek veled (üzenetküldés).</li>
        </ul>
        <p><strong>Fontos:</strong> Az adataidat nem adjuk el harmadik félnek, és nem használjuk fel reklámcélokra.</p>

        <h2>3. Ki láthatja az adataimat?</h2>
        <ul>
            <li>Az email címedet és a jelszavadat <strong>soha senki más</strong> (még a többi felhasználó sem)
                láthatja.</li>
            <li>A profilod publikus részeit (becenév, bemutatkozás, kor, mozgásállapot) a többi regisztrált felhasználó
                láthatja a böngészés során.</li>
        </ul>

        <h2>4. Adatbiztonság</h2>
        <p>Az üzenetek tartalmát <strong>AES-256-GCM</strong> algoritmussal titkosítjuk. Ez hitelesített titkosítás – véd a hozzáférés és az illetéktelen módosítás ellen is. A titkosítási kulcsot a szerveren környezeti változóként tároljuk, elkülönítve a forráskódtól.</p>

        <h2>5. Hogyan törölhetem az adataimat?</h2>
        <p>Bármikor jogodban áll kérni a fiókod és az összes adatod végleges törlését közvetlenül az oldalon.</p>

        <h2>6. Kapcsolat</h2>
        <p>Ha bármilyen kérdésed van az adataiddal kapcsolatban, itt érhetsz el minket:</p>
        <p><strong>Email:</strong> <a href="mailto:adatvedelem@szivhang-pelda.hu"
                aria-label="Adatvédelmi megkeresésekhez: adatvedelem@szivhang-pelda.hu">adatvedelem@szivhang-pelda.hu</a>
        </p>

        <?php if ($is_logged_in): ?>
        <!-- ── GDPR CONTROL PANEL ── -->
        <section class="gdpr-panel" aria-labelledby="gdpr-panel-heading">
            <h2 id="gdpr-panel-heading">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Az én GDPR jogaim
            </h2>
            <p>Az EU GDPR szabályozás alapján joga van <strong>hozzáférni</strong> a tárolt adataihoz és azokat <strong>letölteni</strong>, valamint kérheti azok <strong>végleges törlését</strong>.</p>

            <div class="gdpr-actions">
                <!-- Adatok letöltése -->
                <form method="POST" action="privacy.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="download_data">
                    <button type="submit"
                            class="btn-gdpr-download"
                            aria-label="Összes tárolt adatom letöltése JSON formátumban">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Adataim letöltése (JSON)
                    </button>
                </form>

                <!-- Fiók törlése -->
                <a href="delete_account.php"
                   class="btn-gdpr-delete"
                   role="button"
                   aria-label="Fiók és összes adat végleges törlése">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                    Fiók és adatok törlése
                </a>
            </div>

            <p class="gdpr-note">
                A letöltött JSON fájl tartalmazza a profiladatait és az interakciók listáját. Az üzenetek tartalma titkosítva van tárolva és biztonsági okokból nem kerül az exportba – csak az üzenetszámok. A fiók törlése visszavonhatatlan.
            </p>
        </section>
        <?php endif; ?>

    </main>

    <?php include 'footer.php'; ?>

</body>

</html>