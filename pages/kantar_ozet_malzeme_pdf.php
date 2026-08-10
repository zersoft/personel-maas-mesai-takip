<?php
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
    die('TCPDF bulunamadı. Lütfen "composer install" çalıştırın.');
}
require_once $vendorPath;
$tcpdfPath = __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
if (!file_exists($tcpdfPath)) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: text/html; charset=utf-8');
    die('TCPDF bulunamadı.');
}
require_once $tcpdfPath;

$baslangic       = isset($_GET['baslangic']) ? $_GET['baslangic'] : date('Y-m-01');
$bitis           = isset($_GET['bitis'])     ? $_GET['bitis']     : date('Y-m-d');
$malzeme_bos_gizle = isset($_GET['malzeme_bos_gizle']) ? (int)$_GET['malzeme_bos_gizle'] : 0;
$musteri         = isset($_GET['musteri']) ? trim((string)$_GET['musteri']) : '';
$cikis_firma     = isset($_GET['cikis_firma']) ? (int)$_GET['cikis_firma'] : 0;
$hareket         = isset($_GET['hareket']) ? trim((string)$_GET['hareket']) : '';
if (!in_array($hareket, ['', 'satis', 'tahsilat'], true)) {
    $hareket = '';
}

if (!$pdoReport) {
    header('Content-Type: text/html; charset=utf-8');
    die('Raporlama veritabanı bağlantısı yok.');
}

$tarihBas = str_replace('-', '', $baslangic) . '000000';
$tarihBit = str_replace('-', '', $bitis) . '999999';

$cikisFirmaAdi = '';
if ($cikis_firma > 0) {
    try {
        $st = $pdoReport->prepare("SELECT COALESCE(NULLIF(TRIM(FirmaAdi),''), CONCAT('ID ', ?)) FROM Cari WHERE CariID = ? LIMIT 1");
        $st->execute([$cikis_firma, $cikis_firma]);
        $cikisFirmaAdi = (string)($st->fetchColumn() ?: ('ID ' . $cikis_firma));
    } catch (PDOException $e) {
        $cikisFirmaAdi = 'ID ' . $cikis_firma;
    }
}

try {
    $sql = "SELECT COALESCE(NULLIF(TRIM(dokumTipi),''), '-') AS malzeme,
            SUM(CASE WHEN islemTipi = 'GELİR TAHAKKUK' AND tarih >= ? AND tarih <= ? THEN COALESCE(dokumNetKg,0) ELSE 0 END) AS donem_net_kg,
            SUM(CASE WHEN islemTipi = 'GELİR TAHAKKUK' AND tarih >= ? AND tarih <= ? THEN COALESCE(genelTutar,0) ELSE 0 END) AS donem_tutar
            FROM SahadanSatis
            WHERE status = 1 AND tarih BETWEEN ? AND ?";
    $params = [$tarihBas, $tarihBit, $tarihBas, $tarihBit, $tarihBas, $tarihBit];
    if ($hareket === 'tahsilat') {
        $sql .= " AND 1=0";
    } else {
        $sql .= " AND islemTipi = 'GELİR TAHAKKUK'";
        if ($cikis_firma > 0) {
            $sql .= " AND cikisFirmaID = ?";
            $params[] = $cikis_firma;
        }
    }
    if ($musteri !== '') {
        $sql .= " AND FirmaAdi = ?";
        $params[] = $musteri;
    }
    $sql .= " GROUP BY COALESCE(NULLIF(TRIM(dokumTipi),''), '-') ORDER BY malzeme";
    $stmt = $pdoReport->prepare($sql);
    $stmt->execute($params);
    $ozetMalzemeListe = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($malzeme_bos_gizle === 0) {
        $ozetMalzemeListe = array_values(array_filter($ozetMalzemeListe, function ($m) {
            return (float)($m['donem_net_kg'] ?? 0) != 0 || (float)($m['donem_tutar'] ?? 0) != 0;
        }));
    }
} catch (PDOException $e) {
    header('Content-Type: text/html; charset=utf-8');
    die('Veritabanı hatası: ' . htmlspecialchars($e->getMessage()));
}

$sumDonemKg = $sumDonemTutar = 0;
foreach ($ozetMalzemeListe as $m) {
    $sumDonemKg += (float)($m['donem_net_kg'] ?? 0);
    $sumDonemTutar += (float)($m['donem_tutar'] ?? 0);
}
foreach ($ozetMalzemeListe as &$m) {
    $m['oran'] = $sumDonemKg > 0 ? ((float)($m['donem_net_kg'] ?? 0) / $sumDonemKg) * 100 : 0;
}
unset($m);

class OzetMalzemePDF extends TCPDF {
    public function Header() {
        $this->SetFont('dejavusans', 'B', 12);
        $this->Cell(0, 6, 'Özet Malzeme Satış Raporu', 0, 1, 'C');
        $this->SetFont('dejavusans', '', 9);
        $this->Cell(0, 5, date('d.m.Y H:i'), 0, 1, 'C');
        $this->Ln(2);
    }
    public function Footer() {
        $this->SetY(-10);
        $this->SetFont('dejavusans', 'I', 8);
        $this->Cell(0, 8, 'Sayfa ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

$pdf = new OzetMalzemePDF('P', PDF_UNIT, 'A4', true, 'UTF-8', false);
$pdf->SetCreator('OYS - Ocak Yönetim Sistemi');
$pdf->SetAuthor('ZERSOFT');
$pdf->SetTitle('Özet Malzeme Satış - ' . $baslangic . '_' . $bitis);
$pdf->SetMargins(12, 18, 12);
$pdf->SetHeaderMargin(4);
$pdf->SetFooterMargin(8);
$pdf->SetAutoPageBreak(true, 10);
$pdf->SetFont('dejavusans', '', 9);
$pdf->AddPage();

$pdf->Cell(0, 5, 'Dönem: ' . $baslangic . ' – ' . $bitis . ($malzeme_bos_gizle === 0 ? ' (dönemde satışı olmayanlar gizli)' : ' (tüm malzemeler)')
    . ($cikisFirmaAdi !== '' ? ' | Çıkış: ' . $cikisFirmaAdi : '')
    . ($musteri !== '' ? ' | Müşteri: ' . $musteri : '')
    . ($hareket === 'satis' ? ' | Hareket: Satış' : ($hareket === 'tahsilat' ? ' | Hareket: Tahsilat' : '')), 0, 1, 'L');
$pdf->Ln(2);

$colW = [70, 32, 36, 24];
$headers = ['Malzeme', 'Dönem Net (kg)', 'Dönem Tutar (₺)', 'Oran (%)'];
$pdf->SetFillColor(230, 230, 230);
$pdf->SetFont('dejavusans', 'B', 8);
foreach ($headers as $i => $h) {
    $pdf->Cell($colW[$i], 7, $h, 1, 0, $i >= 1 ? 'R' : 'L', true);
}
$pdf->Ln();
$pdf->SetFont('dejavusans', '', 8);
$fill = false;
foreach ($ozetMalzemeListe as $m) {
    $pdf->Cell($colW[0], 6, mb_substr($m['malzeme'] ?? '-', 0, 45), 1, 0, 'L', $fill);
    $pdf->Cell($colW[1], 6, number_format((float)($m['donem_net_kg'] ?? 0), 0, ',', '.'), 1, 0, 'R', $fill);
    $pdf->Cell($colW[2], 6, number_format((float)($m['donem_tutar'] ?? 0), 2, ',', '.'), 1, 0, 'R', $fill);
    $pdf->Cell($colW[3], 6, number_format((float)($m['oran'] ?? 0), 1, ',', '.') . '%', 1, 1, 'R', $fill);
    $fill = !$fill;
}

if (!empty($ozetMalzemeListe)) {
    $pdf->SetFont('dejavusans', 'B', 8);
    $pdf->SetFillColor(220, 220, 220);
    $pdf->Cell($colW[0], 7, 'Toplam', 1, 0, 'L', true);
    $pdf->Cell($colW[1], 7, number_format($sumDonemKg, 0, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell($colW[2], 7, number_format($sumDonemTutar, 2, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell($colW[3], 7, '100%', 1, 1, 'R', true);
}

$pdf->Ln(2);
$pdf->SetFont('dejavusans', 'I', 7);
$pdf->Cell(0, 4, 'Sadece satış (TAHAKKUK) hareketleri. Oran: seçili dönemdeki toplam miktara (kg) göre pay.', 0, 1, 'L');

$pdf->Output('ozet_malzeme_satis_' . $baslangic . '_' . $bitis . '.pdf', 'I');
exit;
