<?php
// PDF çıktısından önce hiçbir şey gönderilmemeli
ob_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

ob_end_clean();

$vendorPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendorPath)) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: text/html; charset=utf-8');
    die('TCPDF kütüphanesi bulunamadı. Lütfen "composer install" çalıştırın.');
}
require_once $vendorPath;

$tcpdfPath = __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
if (!file_exists($tcpdfPath)) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: text/html; charset=utf-8');
    die('TCPDF bulunamadı.');
}
require_once $tcpdfPath;

$bakiye_filtre = isset($_GET['bakiye_filtre']) ? (int)$_GET['bakiye_filtre'] : 0; // 0 = tümü, 1 = bakiyesizleri gizle

// Kalan FM alacakları – tüm dönem, filtre yok
$kalanAlacaklar = [];
try {
    $sql = "SELECT p.id, p.ad_soyad,
            COALESCE((SELECT SUM(fm.tutar) FROM fazla_mesai fm WHERE fm.personel_id = p.id), 0) AS toplam_fm,
            COALESCE((SELECT SUM(o.tutar) FROM fazla_mesai_odeme o WHERE o.personel_id = p.id), 0) AS toplam_odeme
            FROM personel_listesi p
            WHERE p.aktif = 1
            ORDER BY p.ad_soyad";
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $toplamFm = (float)$row['toplam_fm'];
        $toplamOdeme = (float)$row['toplam_odeme'];
        $kalanAlacaklar[] = [
            'ad_soyad' => $row['ad_soyad'],
            'toplam_fm' => $toplamFm,
            'toplam_odeme' => $toplamOdeme,
            'kalan_alacak' => $toplamFm - $toplamOdeme
        ];
    }
} catch (PDOException $e) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: text/html; charset=utf-8');
    die('Veritabanı hatası: ' . htmlspecialchars($e->getMessage()));
}

if ($bakiye_filtre === 1) {
    $kalanAlacaklar = array_values(array_filter($kalanAlacaklar, function ($a) {
        return (float)$a['kalan_alacak'] != 0;
    }));
}

class FazlaMesaiAlacakPDF extends TCPDF {
    private $bakiyeFiltre = 0;

    public function setBakiyeFiltre($v) {
        $this->bakiyeFiltre = (int)$v;
    }

    public function Header() {
        $this->SetFont('dejavusans', 'B', 14);
        $this->Cell(0, 8, 'Kalan Fazla Mesai Alacakları Raporu', 0, 1, 'C');
        $this->SetFont('dejavusans', '', 9);
        $sub = 'Tüm dönem – ' . date('d.m.Y');
        if ($this->bakiyeFiltre === 1) {
            $sub .= ' (bakiyesizler gizli)';
        }
        $this->Cell(0, 6, $sub, 0, 1, 'C');
        $this->Ln(4);
    }

    public function Footer() {
        $this->SetY(-12);
        $this->SetFont('dejavusans', 'I', 8);
        $this->Cell(0, 10, 'Sayfa ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

$pdf = new FazlaMesaiAlacakPDF('P', PDF_UNIT, 'A4', true, 'UTF-8', false);
$pdf->setBakiyeFiltre($bakiye_filtre);
$pdf->SetCreator('Personel Takip Sistemi');
$pdf->SetAuthor('ZERSOFT');
$pdf->SetTitle('Kalan FM Alacakları');
$pdf->SetSubject('Kalan Fazla Mesai Alacakları');
$pdf->SetMargins(10, 22, 10);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(8);
$pdf->SetAutoPageBreak(true, 12);
$pdf->SetFont('dejavusans', '', 9);
$pdf->AddPage();

$wPersonel = 70;
$wFm = 35;
$wOdeme = 35;
$wKalan = 40;

// Tablo başlığı
$pdf->SetFont('dejavusans', 'B', 10);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell($wPersonel, 7, 'Personel', 1, 0, 'L', true);
$pdf->Cell($wFm, 7, 'Toplam FM', 1, 0, 'R', true);
$pdf->Cell($wOdeme, 7, 'Toplam Ödeme', 1, 0, 'R', true);
$pdf->Cell($wKalan, 7, 'Kalan Alacak', 1, 1, 'R', true);

$pdf->SetFont('dejavusans', '', 9);
$fill = false;
$sumFm = 0;
$sumOdeme = 0;
$sumKalan = 0;

foreach ($kalanAlacaklar as $a) {
    $sumFm += $a['toplam_fm'];
    $sumOdeme += $a['toplam_odeme'];
    $sumKalan += $a['kalan_alacak'];
    $pdf->SetFillColor($fill ? 248 : 255, 248, 248);
    $ad = mb_convert_encoding($a['ad_soyad'], 'UTF-8', 'UTF-8');
    $pdf->Cell($wPersonel, 6, $ad, 1, 0, 'L', $fill);
    $pdf->Cell($wFm, 6, formatMoney($a['toplam_fm']), 1, 0, 'R', $fill);
    $pdf->Cell($wOdeme, 6, formatMoney($a['toplam_odeme']), 1, 0, 'R', $fill);
    if ($a['kalan_alacak'] > 0) {
        $pdf->SetTextColor(0, 128, 0);
    } elseif ($a['kalan_alacak'] < 0) {
        $pdf->SetTextColor(200, 0, 0);
    }
    $pdf->Cell($wKalan, 6, formatMoney($a['kalan_alacak']), 1, 1, 'R', $fill);
    $pdf->SetTextColor(0, 0, 0);
    $fill = !$fill;
}

// Toplam satırı
$pdf->SetFont('dejavusans', 'B', 10);
$pdf->SetFillColor(212, 224, 245);
$pdf->Cell($wPersonel, 7, 'TOPLAM', 1, 0, 'L', true);
$pdf->Cell($wFm, 7, formatMoney($sumFm), 1, 0, 'R', true);
$pdf->Cell($wOdeme, 7, formatMoney($sumOdeme), 1, 0, 'R', true);
$pdf->Cell($wKalan, 7, formatMoney($sumKalan), 1, 1, 'R', true);

$pdf->Output('kalan_fm_alacaklari.pdf', 'I');
exit;
