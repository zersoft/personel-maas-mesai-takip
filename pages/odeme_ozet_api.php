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

$ay = isset($_GET['ay']) ? (int)$_GET['ay'] : 0;
$yil = isset($_GET['yil']) ? (int)$_GET['yil'] : 0;
if ($ay < 1 || $ay > 12 || $yil < 2000 || $yil > 2100) {
    echo json_encode(['banka'=>0,'nakit'=>0,'toplam'=>0]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT b.*,
        a.banka_avans AS a_banka_avans,
        a.nakit_avans AS a_nakit_avans
        FROM bordro b
        LEFT JOIN (
            SELECT personel_id, SUM(banka_tutari) AS banka_avans, SUM(nakit_tutari) AS nakit_avans
            FROM avans_takip
            WHERE ( (bordro_ay = ? AND bordro_yil = ?) OR (bordro_ay IS NULL AND bordro_yil IS NULL AND MONTH(tarih) = ? AND YEAR(tarih) = ?) )
            GROUP BY personel_id
        ) a ON a.personel_id = b.personel_id
        WHERE b.ay = ? AND b.yil = ?");
    $stmt->execute([$ay, $yil, $ay, $yil, $ay, $yil]);
    $rawRows = $stmt->fetchAll();

    $banka = 0;
    $nakit = 0;
    $toplam = 0;

    foreach ($rawRows as $bRow) {
        $avB = (float)($bRow['banka_avans'] ?? 0);
        $avN = (float)($bRow['nakit_avans'] ?? 0);
        if ($avB == 0 && $avN == 0) {
            $avB = (float)($bRow['a_banka_avans'] ?? 0);
            $avN = (float)($bRow['a_nakit_avans'] ?? 0);
        }

        $calc = calculateBordroPay(
            $bRow['brut_maas'],
            $bRow['sgk_banka'] ?? 0,
            (float)($bRow['ek_odenek_banka'] ?? 0),
            (float)($bRow['ek_odenek_nakit'] ?? ($bRow['ek_odenek'] ?? 0)),
            (float)($bRow['izin_kesintisi'] ?? 0),
            (float)($bRow['sgk_kesintisi'] ?? 0),
            (float)($bRow['diger_kesintiler'] ?? 0),
            $avB,
            $avN
        );

        $banka += $calc['banka_net'];
        $nakit += $calc['nakit_net'];
        $toplam += $calc['toplam_odenecek'];
    }

    echo json_encode(['banka' => $banka, 'nakit' => $nakit, 'toplam' => $toplam]);
} catch (Throwable $e) {
    echo json_encode(['banka' => 0, 'nakit' => 0, 'toplam' => 0]);
}


