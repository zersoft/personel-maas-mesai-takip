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
        COALESCE(b.aciklama, '') AS aciklama,
        COALESCE(b.kesinti_aciklama, '') AS kesinti_aciklama,
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
        // Header'i sayfanin en ustunde sabit konumda ciz
        $this->SetY(4);
        $this->SetFont('dejavusans', 'B', 13);
        $this->Cell(0, 6, 'Bordro Odeme Ozeti', 0, 1, 'L');
        $this->SetFont('dejavusans', 'B', 10);
        $this->Cell(0, 5, 'Donem: ' . getTurkishMonthName($this->reportAy) . ' ' . $this->reportYil, 0, 1, 'R');
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
$pdf->SetMargins(6, 22, 6);
$pdf->SetHeaderMargin(2);
$pdf->SetFooterMargin(6);
$pdf->SetAutoPageBreak(true, 8);
$pdf->SetFont('dejavusans', '', 8);
$pdf->AddPage();

// Kolon genislikleri (A4 landscape) – tutar alanlari alttoplam tasmasin diye genis
$wPersonel = 44;
$wBrut = 28;
$wIlave = 24;
$wNotes = 32;
$wKesinti = 24;
$wAvans = 24;
$wBanka = 24;
$wNakit = 24;
$wNet = 30;

$tableWidth = $wPersonel + $wBrut + $wIlave + $wNotes + $wKesinti + $wAvans + $wBanka + $wNakit + $wNet;
$pageW = $pdf->GetPageWidth();
$marginL = $pdf->getMargins()['left'];
$marginR = $pdf->getMargins()['right'];
$startX = $marginL + ($pageW - $marginL - $marginR - $tableWidth) / 2;
$pdf->SetX($startX);

$pdf->SetFillColor(235, 235, 235);
$pdf->SetFont('dejavusans', 'B', 7.8);
$pdf->Cell($wPersonel, 5.8, 'Personel', 1, 0, 'L', true);
$pdf->Cell($wBrut, 5.8, 'Brut', 1, 0, 'R', true);
$pdf->Cell($wIlave, 5.8, 'Ilave', 1, 0, 'R', true);
$pdf->Cell($wNotes, 5.8, 'Aciklamalar', 1, 0, 'L', true);
$pdf->Cell($wKesinti, 5.8, 'Kesinti', 1, 0, 'R', true);
$pdf->Cell($wAvans, 5.8, 'Avans', 1, 0, 'R', true);
$pdf->Cell($wBanka, 5.8, 'Banka', 1, 0, 'R', true);
$pdf->Cell($wNakit, 5.8, 'Nakit', 1, 0, 'R', true);
$pdf->Cell($wNet, 5.8, 'Net', 1, 1, 'R', true);

$pdf->SetFont('dejavusans', '', 7.2);
$fill = false;
foreach ($rows as $r) {
    $pdf->SetX($startX);
    $pdf->SetFillColor(248, 248, 248);
    $notesText = '';
    $genel = trim((string)($r['aciklama'] ?? ''));
    $kesinti = trim((string)($r['kesinti_aciklama'] ?? ''));
    if ($genel !== '') {
        $notesText .= 'Genel: ' . $genel;
    }
    if ($kesinti !== '') {
        $notesText .= ($notesText !== '' ? ' | ' : '') . 'Kesinti: ' . $kesinti;
    }
    if ($notesText === '') {
        $notesText = '-';
    }
    $notesText = mb_strimwidth($notesText, 0, 38, '..');

    $pdf->Cell($wPersonel, 5.2, (string)$r['ad_soyad'], 1, 0, 'L', $fill);
    $pdf->Cell($wBrut, 5.2, formatMoney((float)$r['brut_maas']), 1, 0, 'R', $fill);
    $pdf->SetTextColor(22, 163, 74);
    $pdf->Cell($wIlave, 5.2, formatMoney((float)$r['ilave_odeme_toplam']), 1, 0, 'R', $fill);
    $pdf->SetTextColor(70, 70, 70);
    $pdf->SetFont('dejavusans', '', 6);
    $pdf->Cell($wNotes, 5.2, $notesText, 1, 0, 'L', $fill);
    $pdf->SetFont('dejavusans', '', 7.2);
    $pdf->SetTextColor(220, 38, 38);
    $pdf->Cell($wKesinti, 5.2, formatMoney((float)$r['kesinti_toplam']), 1, 0, 'R', $fill);
    $pdf->SetTextColor(180, 83, 9);
    $pdf->Cell($wAvans, 5.2, formatMoney((float)$r['avans_toplam']), 1, 0, 'R', $fill);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($wBanka, 5.2, formatMoney((float)$r['banka_pay']), 1, 0, 'R', $fill);
    $pdf->Cell($wNakit, 5.2, formatMoney((float)$r['nakit_pay']), 1, 0, 'R', $fill);
    $pdf->Cell($wNet, 5.2, formatMoney((float)$r['toplam_odenecek']), 1, 1, 'R', $fill);
    $pdf->SetTextColor(0, 0, 0);
    $fill = !$fill;
}

$pdf->SetFont('dejavusans', 'B', 7.8);
$pdf->SetFillColor(212, 224, 245);
$pdf->SetX($startX);
$pdf->Cell($wPersonel, 5.8, 'Toplam', 1, 0, 'L', true);
$pdf->Cell($wBrut, 5.8, formatMoney($sumBrut), 1, 0, 'R', true);
$pdf->SetTextColor(22, 163, 74);
$pdf->Cell($wIlave, 5.8, formatMoney($sumIlave), 1, 0, 'R', true);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell($wNotes, 5.8, '-', 1, 0, 'L', true);
$pdf->SetTextColor(220, 38, 38);
$pdf->Cell($wKesinti, 5.8, formatMoney($sumKesinti), 1, 0, 'R', true);
$pdf->SetTextColor(180, 83, 9);
$pdf->Cell($wAvans, 5.8, formatMoney($sumAvans), 1, 0, 'R', true);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell($wBanka, 5.8, formatMoney($sumBanka), 1, 0, 'R', true);
$pdf->Cell($wNakit, 5.8, formatMoney($sumNakit), 1, 0, 'R', true);
$pdf->Cell($wNet, 5.8, formatMoney($sumToplam), 1, 1, 'R', true);

$pdf->Output('bordro_odeme_ozeti_' . $ay . '_' . $yil . '.pdf', 'I');
exit;

