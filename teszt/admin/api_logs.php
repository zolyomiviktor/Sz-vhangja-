<?php
require 'auth_check.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Naplók lekérése - LEFT JOIN hogy akkor is látszódjon ha az admin törölve lett
    $stmt = $pdo->prepare("
        SELECT l.*, IFNULL(u.nickname, 'Törölt Admin') as admin_name 
        FROM admin_logs l 
        LEFT JOIN users u ON l.admin_id = u.id 
        ORDER BY l.created_at DESC 
        LIMIT 100
    ");
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $logs
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Adatbázis hiba: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
