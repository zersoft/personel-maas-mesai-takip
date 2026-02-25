<?php
if (session_status() === PHP_SESSION_NONE) {
    $appSessionPath = __DIR__ . '/../storage/sessions';
    if (is_dir($appSessionPath) && is_writable($appSessionPath)) {
        ini_set('session.save_path', $appSessionPath);
    }
    @session_start();
}
require_once '../config/db.php';
require_once '../includes/auth.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');

$personel_id = isset($_GET['personel_id']) ? (int)$_GET['personel_id'] : 0;
$ay = isset($_GET['ay']) ? (int)$_GET['ay'] : 0;
$yil = isset($_GET['yil']) ? (int)$_GET['yil'] : 0;

if ($personel_id <= 0 || $ay < 1 || $ay > 12 || $yil < 2000 || $yil > 2100) {
    echo json_encode(['banka'=>0,'nakit'=>0]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT 
            COALESCE(SUM(banka_tutari),0) AS banka,
            COALESCE(SUM(nakit_tutari),0) AS nakit
        FROM avans_takip 
        WHERE personel_id = ? AND (
            (bordro_ay = ? AND bordro_yil = ?) OR (bordro_ay IS NULL AND bordro_yil IS NULL AND MONTH(tarih) = ? AND YEAR(tarih) = ?)
        )");
    $stmt->execute([$personel_id, $ay, $yil, $ay, $yil]);
    $row = $stmt->fetch();
    echo json_encode(['banka'=>(float)($row['banka'] ?? 0),'nakit'=>(float)($row['nakit'] ?? 0)]);
} catch (Throwable $e) {
    echo json_encode(['banka'=>0,'nakit'=>0]);
}


