<?php
// PDF'den önce output oluşmaması için buffer
ob_start();

require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

// Require'ların olası çıktısını temizle
ob_end_clean();

$vendorPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendorPath)) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: text/html; charset=utf-8');
    die('TCPDF kütüphanesi bulunamadı. Lütfen "composer install" komutunu çalıştırın.');
}
require_once $vendorPath;

$tcpdfPath = __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
if (file_exists($tcpdfPath)) {
    require_once $tcpdfPath;
}

$ay = isset($_GET['ay']) ? (int)$_GET['ay'] : (int)date('n');
$yil = isset($_GET['yil']) ? (int)$_GET['yil'] : (int)date('Y');
if ($ay < 1 || $ay > 12) $ay = (int)date('n');
if ($yil < 2000 || $yil > 2100) $yil = (int)date('Y');

try {
    $stmt = $pdo->prepare("SELECT p.ad_soyad,
        COALESCE(b.brut_maas, 0) AS brut_maas,
        (COALESCE(b.izin_kesintisi,0)+COALESCE(b.sgk_kesintisi,0)+COALESCE(b.diger_kesintiler,0)) AS kesinti_toplam,
        (COALESCE(b.banka_avans, a.banka_avans, 0) + COALESCE(b.nakit_avans, a.nakit_avans, 0)) AS avans_toplam,
        (COALESCE(b.ek_odenek_banka,0) + COALESCE(b.ek_odenek_nakit,0)) AS ilave_odeme_toplam,
        (GREATEST((b.brut_maas - b.sgk_banka) - (COALESCE(b.izin_kesintisi,0)+COALESCE(b.sgk_kesintisi,0)+COALESCE(b.diger_kesintiler,0)), 0) - COALESCE(b.nakit_avans, a.nakit_avans, 0) + COALESCE(b.ek_odenek_nakit,0)) AS nakit_pay,
        (GREATEST(b.sgk_banka - GREATEST((COALESCE(b.izin_kesintisi,0)+COALESCE(b.sgk_kesintisi,0)+COALESCE(b.diger_kesintiler,0)) - (b.brut_maas - b.sgk_banka), 0), 0) - COALESCE(b.banka_avans, a.banka_avans, 0) + COALESCE(b.ek_odenek_banka,0)) AS banka_pay,
        GREATEST((GREATEST((b.brut_maas - b.sgk_banka) - (COALESCE(b.izin_kesintisi,0)+COALESCE(b.sgk_kesintisi,0)+COALESCE(b.diger_kesintiler,0)), 0) - COALESCE(b.nakit_avans, a.nakit_avans, 0) + COALESCE(b.ek_odenek_nakit,0))
               + (GREATEST(b.sgk_banka - GREATEST((COALESCE(b.izin_kesintisi,0)+COALESCE(b.sgk_kesintisi,0)+COALESCE(b.diger_kesintiler,0)) - (b.brut_maas - b.sgk_banka), 0), 0) - COALESCE(b.banka_avans, a.banka_avans, 0) + COALESCE(b.ek_odenek_banka,0)), 0) AS toplam_odenecek
        FROM bordro b
        LEFT JOIN personel_listesi p ON b.personel_id = p.id
        LEFT JOIN (
            SELECT personel_id, SUM(banka_tutari) AS banka_avans, SUM(nakit_tutari) AS nakit_avans
            FROM avans_takip
            WHERE ((bordro_ay = ? AND bordro_yil = ?) OR (bordro_ay IS NULL AND bordro_yil IS NULL AND MONTH(tarih) = ? AND YEAR(tarih) = ?))
            GROUP BY personel_id
        ) a ON a.personel_id = b.personel_id
        WHERE b.ay = ? AND b.yil = ?
        ORDER BY p.ad_soyad");
    $stmt->execute([$ay, $yil, $ay, $yil, $ay, $yil]);
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: text/html; charset=utf-8');
    die('Veritabanı hatası: ' . htmlspecialchars($e->getMessage()));
}

$sumBrut = 0;
$sumIlave = 0;
$sumKesinti = 0;
$sumAvans = 0;
$sumBanka = 0;
$sumNakit = 0;
$sumToplam = 0;

foreach ($rows as $r) {
    $sumBrut += (float)$r['brut_maas'];
    $sumIlave += (float)$r['ilave_odeme_toplam'];
    $sumKesinti += (float)$r['kesinti_toplam'];
    $sumAvans += (float)$r['avans_toplam'];
    $sumBanka += (float)$r['banka_pay'];
    $sumNakit += (float)$r['nakit_pay'];
    $sumToplam += (float)$r['toplam_odenecek'];
}

class BordroOdemeOzetiPDF extends TCPDF {
    private $reportAy;
    private $reportYil;

    public function setReportPeriod($ay, $yil) {
        $this->reportAy = $ay;
        $this->reportYil = $yil;
    }

    public function Header() {
        $this->SetFont('dejavusans', 'B', 14);
        $this->Cell(0, 8, 'Bordro Odeme Ozeti', 0, 1, 'L');
        $this->SetFont('dejavusans', 'B', 10);
        $this->Cell(0, 6, 'Donem: ' . getTurkishMonthName($this->reportAy) . ' ' . $this->reportYil, 0, 1, 'R');
        $this->Ln(1);
    }

    public function Footer() {
        $this->SetY(-10);
        $this->SetFont('dejavusans', 'I', 8);
        $this->Cell(0, 5, 'Olusturma: ' . date('d.m.Y H:i') . '  |  Sayfa ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

$pdf = new BordroOdemeOzetiPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
$pdf->setReportPeriod($ay, $yil);
$pdf->SetCreator('Personel Takip Sistemi');
$pdf->SetAuthor('ZERSOFT');
$pdf->SetTitle('Bordro Odeme Ozeti - ' . getTurkishMonthName($ay) . ' ' . $yil);
$pdf->SetSubject('Bordro odeme ozeti');
$pdf->SetMargins(8, 14, 8);
$pdf->SetAutoPageBreak(true, 10);
$pdf->SetFont('dejavusans', '', 8.5);
$pdf->AddPage();

// Kolon genislikleri (A4 landscape)
$wPersonel = 65;
$wBrut = 30;
$wIlave = 26;
$wKesinti = 24;
$wAvans = 24;
$wBanka = 24;
$wNakit = 24;
$wNet = 40;

$pdf->SetFillColor(235, 235, 235);
$pdf->SetFont('dejavusans', 'B', 8.5);
$pdf->Cell($wPersonel, 6, 'Personel', 1, 0, 'L', true);
$pdf->Cell($wBrut, 6, 'Brut', 1, 0, 'R', true);
$pdf->Cell($wIlave, 6, 'Ilave Odeme', 1, 0, 'R', true);
$pdf->Cell($wKesinti, 6, 'Kesinti', 1, 0, 'R', true);
$pdf->Cell($wAvans, 6, 'Avans', 1, 0, 'R', true);
$pdf->Cell($wBanka, 6, 'Banka', 1, 0, 'R', true);
$pdf->Cell($wNakit, 6, 'Nakit', 1, 0, 'R', true);
$pdf->Cell($wNet, 6, 'Net Odenecek', 1, 1, 'R', true);

$pdf->SetFont('dejavusans', '', 8);
$fill = false;
foreach ($rows as $r) {
    $pdf->SetFillColor(248, 248, 248);
    $pdf->Cell($wPersonel, 5.6, (string)$r['ad_soyad'], 1, 0, 'L', $fill);
    $pdf->Cell($wBrut, 5.6, formatMoney((float)$r['brut_maas']), 1, 0, 'R', $fill);
    $pdf->SetTextColor(22, 163, 74);
    $pdf->Cell($wIlave, 5.6, formatMoney((float)$r['ilave_odeme_toplam']), 1, 0, 'R', $fill);
    $pdf->SetTextColor(220, 38, 38);
    $pdf->Cell($wKesinti, 5.6, formatMoney((float)$r['kesinti_toplam']), 1, 0, 'R', $fill);
    $pdf->SetTextColor(180, 83, 9);
    $pdf->Cell($wAvans, 5.6, formatMoney((float)$r['avans_toplam']), 1, 0, 'R', $fill);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($wBanka, 5.6, formatMoney((float)$r['banka_pay']), 1, 0, 'R', $fill);
    $pdf->Cell($wNakit, 5.6, formatMoney((float)$r['nakit_pay']), 1, 0, 'R', $fill);
    $pdf->Cell($wNet, 5.6, formatMoney((float)$r['toplam_odenecek']), 1, 1, 'R', $fill);
    $fill = !$fill;
}

$pdf->SetFont('dejavusans', 'B', 8.5);
$pdf->SetFillColor(212, 224, 245);
$pdf->Cell($wPersonel, 6, 'Toplam', 1, 0, 'L', true);
$pdf->Cell($wBrut, 6, formatMoney($sumBrut), 1, 0, 'R', true);
$pdf->SetTextColor(22, 163, 74);
$pdf->Cell($wIlave, 6, formatMoney($sumIlave), 1, 0, 'R', true);
$pdf->SetTextColor(220, 38, 38);
$pdf->Cell($wKesinti, 6, formatMoney($sumKesinti), 1, 0, 'R', true);
$pdf->SetTextColor(180, 83, 9);
$pdf->Cell($wAvans, 6, formatMoney($sumAvans), 1, 0, 'R', true);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell($wBanka, 6, formatMoney($sumBanka), 1, 0, 'R', true);
$pdf->Cell($wNakit, 6, formatMoney($sumNakit), 1, 0, 'R', true);
$pdf->Cell($wNet, 6, formatMoney($sumToplam), 1, 1, 'R', true);

$pdf->Output('bordro_odeme_ozeti_' . $ay . '_' . $yil . '.pdf', 'I');
exit;

