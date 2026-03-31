<?php
require_once 'db.php';

$success_msg = "";
$error_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error_msg = "Kérjük, töltsön ki minden kötelező mezőt!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Érvénytelen email cím!";
    } else {
        // Itt történne az email küldés (mail() vagy PHPMailer)
        // Mivel nincs konfigurálva a szerver, csak szimuláljuk
        $success_msg = "Köszönjük a megkeresését! Hamarosan válaszolunk a megadott email címre.";

        // Opcionálisan naplózhatjuk is egy táblába, ha lenne ilyen, de a tervben szimuláció szerepelt.
    }
}
?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kapcsolat - Szívhangja</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <style>
        .contact-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            margin-top: 2rem;
        }

        .contact-info-card {
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-soft);
        }

        .contact-method {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .contact-icon {
            width: 40px;
            height: 40px;
            background: var(--bg-soft);
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        @media (max-width: 768px) {
            .contact-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <?php include 'header.php'; ?>

    <main id="main-content" role="main">
        <div style="max-width: 1000px; margin: 0 auto;">
            <h1>Kapcsolat</h1>
            <p>Bármilyen kérdésed vagy észrevételed van, keress minket bizalommal!</p>

            <?php if ($success_msg): ?>
                <div class="alert" style="background-color: #e8f5e9; border: 1px solid #c8e6c9; color: #2e7d32;">
                    <span>
                        <?= htmlspecialchars($success_msg) ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="alert" style="background-color: #ffebee; border: 1px solid #ffcdd2; color: #c62828;">
                    <span>
                        <?= htmlspecialchars($error_msg) ?>
                    </span>
                </div>
            <?php endif; ?>

            <div class="contact-container">
                <!-- Bal oldal: Információ -->
                <div class="contact-info-section">
                    <div class="contact-info-card">
                        <h3>Elérhetőségeink</h3>
                        <div class="contact-method">
                            <div class="contact-icon" aria-hidden="true">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path
                                        d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z">
                                    </path>
                                    <polyline points="22,6 12,13 2,6"></polyline>
                                </svg>
                            </div>
                            <div>
                                <strong>Email:</strong><br>
                                <a href="mailto:info@szivhangja.hu">info@szivhangja.hu</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Jobb oldal: Űrlap -->
                <div class="contact-form-section">
                    <div class="card" style="padding: 2rem;">
                        <h3>Írj nekünk!</h3>
                        <form action="contact.php" method="POST">
                            <div class="form-group">
                                <label for="name">Név <span style="color: red;">*</span></label>
                                <input type="text" id="name" name="name" required placeholder="Hogyan szólíthatunk?">
                            </div>
                            <div class="form-group">
                                <label for="email">Email cím <span style="color: red;">*</span></label>
                                <input type="email" id="email" name="email" required placeholder="pelda@email.hu">
                            </div>
                            <div class="form-group">
                                <label for="subject">Tárgy <span style="color: red;">*</span></label>
                                <input type="text" id="subject" name="subject" required
                                    placeholder="Mi a megkeresés oka?">
                            </div>
                            <div class="form-group">
                                <label for="message">Üzenet <span style="color: red;">*</span></label>
                                <textarea id="message" name="message" rows="5" required
                                    placeholder="Fejtsd ki bővebben mondandódat..."></textarea>
                            </div>
                            <button type="submit" class="btn" style="width: 100%;">Üzenet küldése</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'footer.php'; ?>

</body>

</html>