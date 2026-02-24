<?php
/**
 * Veritabanı Bağlantı Dosyası
 * Değerler .env dosyasından okunur. Örnek: .env.example
 * - $pdo: Ana veritabanı (personel takip)
 * - $pdoReport: Raporlama veritabanı (koka_hy)
 */

require_once __DIR__ . '/load_env.php';

$host     = getenv('DB_HOST')     ?: 'mysqldb';
$dbname   = getenv('DB_NAME')     ?: 'zersoftn_personel_takip';
$username = getenv('DB_USER')     ?: 'zersoftn_personel_takip';
$password = getenv('DB_PASS')     ?: '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// Raporlama veritabanı (isteğe bağlı)
$pdoReport = null;
$hostReport   = getenv('DB_REPORT_HOST') ?: $host;
$dbnameReport = getenv('DB_REPORT_NAME') ?: '';
$usernameReport = getenv('DB_REPORT_USER') ?: '';
$passwordReport = getenv('DB_REPORT_PASS') ?: '';

if ($dbnameReport !== '' && $usernameReport !== '') {
    try {
        $pdoReport = new PDO(
            "mysql:host=$hostReport;dbname=$dbnameReport;charset=utf8mb4",
            $usernameReport,
            $passwordReport
        );
        $pdoReport->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdoReport->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $pdoReport = null;
    }
}
