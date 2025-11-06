<?php
require_once '../config/db.php';
header('Content-Type: application/json; charset=utf-8');

$ay = isset($_GET['ay']) ? (int)$_GET['ay'] : 0;
$yil = isset($_GET['yil']) ? (int)$_GET['yil'] : 0;
if ($ay < 1 || $ay > 12 || $yil < 2000 || $yil > 2100) {
    echo json_encode(['banka'=>0,'nakit'=>0,'toplam'=>0]);
    exit;
}

try {
    $bankaToplamStmt = $pdo->prepare("SELECT SUM(
        GREATEST(
            b.sgk_banka - GREATEST((COALESCE(b.izin_kesintisi,0)+COALESCE(b.sgk_kesintisi,0)+COALESCE(b.diger_kesintiler,0)) - (b.brut_maas - b.sgk_banka), 0)
            - COALESCE(b.banka_avans, a.banka_avans, 0)
        , 0) + COALESCE(b.ek_odenek_banka,0)
    ) as toplam
    FROM bordro b
    LEFT JOIN (
        SELECT personel_id, SUM(banka_tutari) AS banka_avans, SUM(nakit_tutari) AS nakit_avans
        FROM avans_takip
        WHERE ( (bordro_ay = ? AND bordro_yil = ?) OR (bordro_ay IS NULL AND bordro_yil IS NULL AND MONTH(tarih) = ? AND YEAR(tarih) = ?) )
        GROUP BY personel_id
    ) a ON a.personel_id = b.personel_id
    WHERE b.ay = ? AND b.yil = ?");
    $bankaToplamStmt->execute([$ay, $yil, $ay, $yil, $ay, $yil]);
    $banka = (float)($bankaToplamStmt->fetch()['toplam'] ?? 0);

    $nakitToplamStmt = $pdo->prepare("SELECT SUM(
        GREATEST((b.brut_maas - b.sgk_banka) - (COALESCE(b.izin_kesintisi,0)+COALESCE(b.sgk_kesintisi,0)+COALESCE(b.diger_kesintiler,0))
        - COALESCE(b.nakit_avans, a.nakit_avans, 0), 0) + COALESCE(b.ek_odenek_nakit,0)
    ) as toplam
    FROM bordro b
    LEFT JOIN (
        SELECT personel_id, SUM(banka_tutari) AS banka_avans, SUM(nakit_tutari) AS nakit_avans
        FROM avans_takip
        WHERE ( (bordro_ay = ? AND bordro_yil = ?) OR (bordro_ay IS NULL AND bordro_yil IS NULL AND MONTH(tarih) = ? AND YEAR(tarih) = ?) )
        GROUP BY personel_id
    ) a ON a.personel_id = b.personel_id
    WHERE b.ay = ? AND b.yil = ?");
    $nakitToplamStmt->execute([$ay, $yil, $ay, $yil, $ay, $yil]);
    $nakit = (float)($nakitToplamStmt->fetch()['toplam'] ?? 0);

    $toplamStmt = $pdo->prepare("SELECT SUM(
        GREATEST(b.brut_maas - (COALESCE(b.izin_kesintisi,0)+COALESCE(b.sgk_kesintisi,0)+COALESCE(b.diger_kesintiler,0)), 0)
        + COALESCE(b.ek_odenek_banka,0) + COALESCE(b.ek_odenek_nakit,0)
    ) as toplam FROM bordro b WHERE b.ay = ? AND b.yil = ?");
    $toplamStmt->execute([$ay, $yil]);
    $toplam = (float)($toplamStmt->fetch()['toplam'] ?? 0);

    echo json_encode(['banka'=>$banka,'nakit'=>$nakit,'toplam'=>$toplam]);
} catch (Throwable $e) {
    echo json_encode(['banka'=>0,'nakit'=>0,'toplam'=>0]);
}


