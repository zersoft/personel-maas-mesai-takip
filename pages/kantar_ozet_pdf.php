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

$baslangic      = isset($_GET['baslangic']) ? $_GET['baslangic'] : date('Y-m-01');
$bitis          = isset($_GET['bitis'])     ? $_GET['bitis']     : date('Y-m-d');
$ozet_bakiyesiz = isset($_GET['ozet_bakiyesiz']) ? (int)$_GET['ozet_bakiyesiz'] : 0;
$musteri        = isset($_GET['musteri']) ? trim((string)$_GET['musteri']) : '';
$cikis_firma    = isset($_GET['cikis_firma']) ? (int)$_GET['cikis_firma'] : 0;
$hareket        = isset($_GET['hareket']) ? trim((string)$_GET['hareket']) : '';
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
    $cikisCondSatis = ($cikis_firma > 0) ? ' AND cikisFirmaID = ' . (int)$cikis_firma : '';
    $sql = "SELECT FirmaAdi,
            SUM(CASE WHEN islemTipi = 'GELİR TAHAKKUK'{$cikisCondSatis} AND tarih >= ? AND tarih <= ? THEN COALESCE(genelTutar,0) ELSE 0 END) AS toplam_satis,
            SUM(CASE WHEN islemTipi = 'GELİR TAHSİLAT' AND tarih >= ? AND tarih <= ? THEN COALESCE(genelTutar,0) ELSE 0 END) AS toplam_tahsilat,
            SUM(CASE WHEN islemTipi = 'GELİR TAHAKKUK'{$cikisCondSatis} AND tarih <= ? THEN COALESCE(genelTutar,0) ELSE 0 END) AS genel_satis,
            SUM(CASE WHEN islemTipi = 'GELİR TAHSİLAT' AND tarih <= ? THEN COALESCE(genelTutar,0) ELSE 0 END) AS genel_tahsilat
            FROM SahadanSatis
            WHERE status = 1 AND tarih <= ?";
    $params = [$tarihBas, $tarihBit, $tarihBas, $tarihBit, $tarihBit, $tarihBit, $tarihBit];
    if ($hareket === 'satis') {
        $sql .= " AND islemTipi = 'GELİR TAHAKKUK'";
        if ($cikis_firma > 0) {
            $sql .= " AND cikisFirmaID = ?";
            $params[] = $cikis_firma;
        }
    } elseif ($hareket === 'tahsilat') {
        $sql .= " AND islemTipi = 'GELİR TAHSİLAT'";
    } elseif ($cikis_firma > 0) {
        $sql .= " AND FirmaAdi IN (
            SELECT DISTINCT FirmaAdi FROM SahadanSatis
            WHERE status = 1 AND islemTipi = 'GELİR TAHAKKUK' AND cikisFirmaID = ?
        )";
        $params[] = $cikis_firma;
    }
    if ($musteri !== '') {
        $sql .= " AND FirmaAdi = ?";
        $params[] = $musteri;
    }
    $sql .= " GROUP BY FirmaAdi ORDER BY FirmaAdi";
    $stmt = $pdoReport->prepare($sql);
    $stmt->execute($params);
    $ozetListe = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($ozetListe as &$o) {
        $o['bakiye'] = (float)($o['genel_satis'] ?? 0) + (float)($o['genel_tahsilat'] ?? 0);
    }
    unset($o);
    if ($ozet_bakiyesiz === 0) {
        $ozetListe = array_values(array_filter($ozetListe, function ($o) {
            return (float)$o['toplam_satis'] != 0 || (float)$o['toplam_tahsilat'] != 0;
        }));
    }
} catch (PDOException $e) {
    header('Content-Type: text/html; charset=utf-8');
    die('Veritabanı hatası: ' . htmlspecialchars($e->getMessage()));
}

$sumToplamSatis = $sumToplamTahsilat = $sumGenelSatis = $sumGenelTahsilat = 0;
foreach ($ozetListe as $o) {
    $sumToplamSatis += (float)$o['toplam_satis'];
    $sumToplamTahsilat += (float)$o['toplam_tahsilat'];
    $sumGenelSatis += (float)($o['genel_satis'] ?? 0);
    $sumGenelTahsilat += (float)($o['genel_tahsilat'] ?? 0);
}

class OzetRaporPDF extends TCPDF {
    public function Header() {
        $this->SetFont('dejavusans', 'B', 12);
        $this->Cell(0, 6, 'Müşteri Özet Raporu', 0, 1, 'C');
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

$pdf = new OzetRaporPDF('P', PDF_UNIT, 'A4', true, 'UTF-8', false);
$pdf->SetCreator('OYS - Ocak Yönetim Sistemi');
$pdf->SetAuthor('ZERSOFT');
$pdf->SetTitle('Özet Rapor - ' . $baslangic . '_' . $bitis);
$pdf->SetMargins(12, 18, 12);
$pdf->SetHeaderMargin(4);
$pdf->SetFooterMargin(8);
$pdf->SetAutoPageBreak(true, 10);
$pdf->SetFont('dejavusans', '', 9);
$pdf->AddPage();

$pdf->Cell(0, 5, 'Dönem: ' . $baslangic . ' – ' . $bitis . ($ozet_bakiyesiz === 0 ? ' (bakiyesizler gizli)' : ' (tüm müşteriler)')
    . ($cikisFirmaAdi !== '' ? ' | Çıkış: ' . $cikisFirmaAdi : '')
    . ($musteri !== '' ? ' | Müşteri: ' . $musteri : '')
    . ($hareket === 'satis' ? ' | Hareket: Satış' : ($hareket === 'tahsilat' ? ' | Hareket: Tahsilat' : '')), 0, 1, 'L');
$pdf->Ln(2);

$colW = [48, 24, 24, 26, 26, 26];
$headers = ['Müşteri', 'Satış', 'Tahs.', 'Gn.Satış', 'Gn.Tahs.', 'Bakiye'];
$pdf->SetFillColor(230, 230, 230);
$pdf->SetFont('dejavusans', 'B', 8);
foreach ($headers as $i => $h) {
    $pdf->Cell($colW[$i], 7, $h, 1, 0, $i >= 1 ? 'R' : 'L', true);
}
$pdf->Ln();
$pdf->SetFont('dejavusans', '', 8);
$fill = false;
foreach ($ozetListe as $o) {
    $pdf->Cell($colW[0], 6, mb_substr($o['FirmaAdi'] ?? '-', 0, 30), 1, 0, 'L', $fill);
    $pdf->Cell($colW[1], 6, number_format((float)$o['toplam_satis'], 2, ',', '.'), 1, 0, 'R', $fill);
    $pdf->Cell($colW[2], 6, number_format((float)$o['toplam_tahsilat'], 2, ',', '.'), 1, 0, 'R', $fill);
    $pdf->Cell($colW[3], 6, number_format((float)($o['genel_satis'] ?? 0), 2, ',', '.'), 1, 0, 'R', $fill);
    $pdf->Cell($colW[4], 6, number_format((float)($o['genel_tahsilat'] ?? 0), 2, ',', '.'), 1, 0, 'R', $fill);
    $pdf->Cell($colW[5], 6, number_format((float)$o['bakiye'], 2, ',', '.'), 1, 1, 'R', $fill);
    $fill = !$fill;
}

if (!empty($ozetListe)) {
    $pdf->SetFont('dejavusans', 'B', 8);
    $pdf->SetFillColor(220, 220, 220);
    $pdf->Cell($colW[0], 7, 'Toplam', 1, 0, 'L', true);
    $pdf->Cell($colW[1], 7, number_format($sumToplamSatis, 2, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell($colW[2], 7, number_format($sumToplamTahsilat, 2, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell($colW[3], 7, number_format($sumGenelSatis, 2, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell($colW[4], 7, number_format($sumGenelTahsilat, 2, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell($colW[5], 7, number_format($sumGenelSatis + $sumGenelTahsilat, 2, ',', '.'), 1, 1, 'R', true);
}

$pdf->Ln(2);
$pdf->SetFont('dejavusans', 'I', 7);
$pdf->Cell(0, 4, 'İlk iki sütun: seçili dönem. Genel T.: baştan son tarihe. Bakiye: genel bakiye (eksi = müşteri borçlu).', 0, 1, 'L');

$pdf->Output('ozet_rapor_' . $baslangic . '_' . $bitis . '.pdf', 'I');
exit;
