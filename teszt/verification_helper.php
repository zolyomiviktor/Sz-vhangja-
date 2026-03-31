<?php
/**
 * VerificationHelper - Automated profile verification using local rules
 */
class VerificationHelper
{

    /**
     * Verifies a user profile after registration
     * 
     * @param int $userId The ID of the user to analyze
     */
    public static function verify($userId)
    {
        global $pdo;

        try {
            // 1. Fetch user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user)
                return false;

            $findings = [
                'risk_level' => 'low',
                'warnings' => [],
                'justification' => "A profil adatai megfelelőnek tűnnek.",
                'recommendation' => 'approve',
                'checks' => []
            ];

            // 2. Check Nickname (Length and Gibberish)
            if (strlen($user['nickname']) < 3) {
                $findings['warnings'][] = "Túl rövid becenév";
                $findings['risk_level'] = 'medium';
            }
            // Simple regex for gibberish (mostly consonants or numbers)
            if (preg_match('/[b-df-hj-np-tv-z]{5,}/i', $user['nickname'])) {
                $findings['warnings'][] = "Gyanús betűkombináció a becenévben";
                $findings['risk_level'] = 'medium';
            }

            // 3. Check Bio (Spam, Links, Effort)
            $bio = $user['bio'];
            $spam_keywords = ['pénz', 'nyeremény', 'sex', 'casino', 'kattints', 'akció', 'ingyen'];
            foreach ($spam_keywords as $word) {
                if (stripos($bio, $word) !== false) {
                    $findings['warnings'][] = "Spamgyanús kifejezés: $word";
                    $findings['risk_level'] = 'medium';
                }
            }

            if (preg_match('/https?:\/\/[^\s]+/', $bio) || preg_match('/www\.[^\s]+/', $bio)) {
                $findings['warnings'][] = "Külső link a bemutatkozásban";
                $findings['risk_level'] = 'high';
                $findings['recommendation'] = 'manual_check';
            }

            if (strlen($bio) < 20) {
                $findings['warnings'][] = "Túl rövid bemutatkozás";
                if ($findings['risk_level'] === 'low')
                    $findings['risk_level'] = 'medium';
            }

            // 4. Check IP Address (Multiple registrations)
            if ($user['ip_address']) {
                $stmt_ip = $pdo->prepare("SELECT COUNT(*) FROM users WHERE ip_address = ? AND registration_date > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
                $stmt_ip->execute([$user['ip_address']]);
                $count = $stmt_ip->fetchColumn();

                if ($count > 2) {
                    $findings['warnings'][] = "Több regisztráció erről az IP-ről az elmúlt 24 órában ($count)";
                    $findings['risk_level'] = 'high';
                    $findings['recommendation'] = 'manual_check';
                }
            }

            // 5. Check Profile Image
            if (!$user['profile_image']) {
                $findings['warnings'][] = "Nincs profilkép feltöltve";
                if ($findings['risk_level'] === 'low')
                    $findings['risk_level'] = 'medium';
            }

            // Finalize justification
            if ($findings['risk_level'] === 'high') {
                $findings['justification'] = "Több súlyos kockázati tényező merült fel. Manuális ellenőrzés javasolt.";
                $findings['recommendation'] = 'manual_check';
            } elseif ($findings['risk_level'] === 'medium') {
                $findings['justification'] = "Néhány gyanús mintát észleltünk. Ellenőrzés javasolt.";
                $findings['recommendation'] = 'manual_check';
            }

            return self::saveReport($userId, $findings);

        } catch (Exception $e) {
            error_log("Verification Error (User $userId): " . $e->getMessage());
            return false;
        }
    }

    private static function saveReport($userId, $data)
    {
        global $pdo;

        $stmt = $pdo->prepare("INSERT INTO verification_reports 
            (user_id, risk_level, justification, warnings, recommendation, checks_performed) 
            VALUES (?, ?, ?, ?, ?, ?)");

        return $stmt->execute([
            $userId,
            $data['risk_level'],
            $data['justification'],
            implode(", ", $data['warnings']),
            $data['recommendation'],
            json_encode($data['checks'] ?? [])
        ]);
    }
}
