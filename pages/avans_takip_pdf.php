<?php
// Output buffering başlat - PDF çıktısından önce hiçbir şey gönderilmemeli
ob_start();

require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

// Buffer'ı temizle - require'ların ürettiği tüm çıktıyı sil
ob_end_clean();

// TCPDF kütüphanesini yükle
$vendorPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendorPath)) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: text/html; charset=utf-8');
    die('TCPDF kütüphanesi bulunamadı. Lütfen "composer install" komutunu çalıştırın.');
}
require_once $vendorPath;

// TCPDF'i doğrudan yükle
$tcpdfPath = __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
if (file_exists($tcpdfPath)) {
    require_once $tcpdfPath;
}

// Filtre parametreleri
$ay = isset($_GET['ay']) ? (int)$_GET['ay'] : (int)date('n');
$yil = isset($_GET['yil']) ? (int)$_GET['yil'] : (int)date('Y');
$personel_filtre = isset($_GET['personel_id']) ? (int)$_GET['personel_id'] : 0;
if ($ay < 1 || $ay > 12) { $ay = (int)date('n'); }
if ($yil < 2000 || $yil > 2100) { $yil = (int)date('Y'); }

// Personel bazlı gruplandırılmış avans verileri
try {
    $sql = "SELECT 
                a.personel_id,
                p.ad_soyad,
                COUNT(*) AS kayit_sayisi,
                SUM(COALESCE(a.banka_tutari, 0)) AS toplam_banka,
                SUM(COALESCE(a.nakit_tutari, 0)) AS toplam_nakit,
                SUM(COALESCE(a.banka_tutari, 0) + COALESCE(a.nakit_tutari, 0)) AS toplam_avans
            FROM avans_takip a
            LEFT JOIN personel_listesi p ON a.personel_id = p.id
            WHERE ( (a.bordro_ay = ? AND a.bordro_yil = ?) OR (a.bordro_ay IS NULL AND a.bordro_yil IS NULL AND MONTH(a.tarih) = ? AND YEAR(a.tarih) = ?) )";
    $params = [$ay, $yil, $ay, $yil];
    
    if ($personel_filtre > 0) {
        $sql .= " AND a.personel_id = ?";
        $params[] = $personel_filtre;
    }
    
    $sql .= " GROUP BY a.personel_id, p.ad_soyad ORDER BY p.ad_soyad ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $avanslar = $stmt->fetchAll();
    
    // Toplamları hesapla
    $toplam_banka = 0;
    $toplam_nakit = 0;
    $toplam_genel = 0;
    foreach ($avanslar as $avans) {
        $toplam_banka += (float)$avans['toplam_banka'];
        $toplam_nakit += (float)$avans['toplam_nakit'];
        $toplam_genel += (float)$avans['toplam_avans'];
    }
} catch(PDOException $e) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: text/html; charset=utf-8');
    die('Veritabanı hatası: ' . htmlspecialchars($e->getMessage()));
}

// PDF oluştur
class AvansTakipPDF extends TCPDF {
    private $reportAy;
    private $reportYil;
    
    public function setReportPeriod($ay, $yil) {
        $this->reportAy = $ay;
        $this->reportYil = $yil;
    }
    
    public function Header() {
        $this->SetFont('dejavusans', 'B', 16);
        $this->Cell(0, 10, 'Avans Takip Raporu', 0, 1, 'C');
        $this->SetFont('dejavusans', 'B', 14);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 8, 'Dönem: ' . getTurkishMonthName($this->reportAy) . ' ' . $this->reportYil, 0, 1, 'C');
        $this->SetFont('dejavusans', '', 10);
        $this->Ln(5);
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('dejavusans', 'I', 8);
        $this->Cell(0, 10, 'Sayfa ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

$pdf = new AvansTakipPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->setReportPeriod($ay, $yil);
$pdf->SetCreator('Personel Takip Sistemi');
$pdf->SetAuthor('ZERSOFT');
$pdf->SetTitle('Avans Takip Raporu');
$pdf->SetSubject('Avans Takip Raporu');

// Türkçe karakter desteği için DejaVu fontunu kullan
$pdf->SetFont('dejavusans', '', 10);

// Sayfa ekle
$pdf->AddPage();

// Dönem bilgisini tablo üstünde göster
$pdf->SetFont('dejavusans', 'B', 12);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 8, 'Dönem: ' . getTurkishMonthName($ay) . ' ' . $yil, 0, 1, 'L');
$pdf->Ln(3);

// Tablo başlıkları
$pdf->SetFont('dejavusans', 'B', 10);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(80, 8, 'Personel', 1, 0, 'L', true);
$pdf->Cell(35, 8, 'Banka Avans', 1, 0, 'R', true);
$pdf->Cell(35, 8, 'Nakit Avans', 1, 0, 'R', true);
$pdf->Cell(40, 8, 'Toplam Avans', 1, 1, 'R', true);

$pdf->SetFont('dejavusans', '', 9);
$fill = false;

// Her personel için tek satır
foreach ($avanslar as $avans) {
    $pdf->SetFillColor(245, 245, 245);
    // PDF'de HTML escape gerekmez ama özel karakterler için güvenli
    $personelAdi = mb_convert_encoding($avans['ad_soyad'], 'UTF-8', 'UTF-8');
    $pdf->Cell(80, 7, $personelAdi, 1, 0, 'L', $fill);
    $pdf->Cell(35, 7, formatMoney((float)$avans['toplam_banka']), 1, 0, 'R', $fill);
    $pdf->Cell(35, 7, formatMoney((float)$avans['toplam_nakit']), 1, 0, 'R', $fill);
    $pdf->Cell(40, 7, formatMoney((float)$avans['toplam_avans']), 1, 1, 'R', $fill);
    $fill = !$fill;
}

// Alt toplamlar
$pdf->SetFont('dejavusans', 'B', 10);
$pdf->SetFillColor(200, 200, 200);
$pdf->Cell(80, 8, 'TOPLAM', 1, 0, 'L', true);
$pdf->Cell(35, 8, formatMoney($toplam_banka), 1, 0, 'R', true);
$pdf->Cell(35, 8, formatMoney($toplam_nakit), 1, 0, 'R', true);
$pdf->Cell(40, 8, formatMoney($toplam_genel), 1, 1, 'R', true);

// PDF'i çıktıla
$pdf->Output('avans_takip_' . $ay . '_' . $yil . '.pdf', 'I');
exit;

