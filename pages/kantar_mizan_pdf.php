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

$baslangic = isset($_GET['baslangic']) ? $_GET['baslangic'] : date('Y-m-01');
$bitis     = isset($_GET['bitis'])     ? $_GET['bitis']     : date('Y-m-d');
$musteri   = isset($_GET['musteri'])   ? trim((string)$_GET['musteri']) : '';
$cikis_firma = isset($_GET['cikis_firma']) ? (int)$_GET['cikis_firma'] : 0;

if (!$pdoReport) {
    header('Content-Type: text/html; charset=utf-8');
    die('Raporlama veritabanı bağlantısı yok.');
}
if ($cikis_firma <= 0) {
    header('Content-Type: text/html; charset=utf-8');
    die('Çıkış firması seçilmedi.');
}

$tarihBas = str_replace('-', '', $baslangic) . '000000';
$tarihBit = str_replace('-', '', $bitis) . '999999';
$tarihDevirBit = str_replace('-', '', $baslangic) . '000000';

try {
    $st = $pdoReport->prepare("SELECT COALESCE(NULLIF(TRIM(FirmaAdi),''), CONCAT('ID ', ?)) AS adi FROM Cari WHERE CariID = ? LIMIT 1");
    $st->execute([$cikis_firma, $cikis_firma]);
    $cikisFirmaAdi = (string)($st->fetchColumn() ?: ('ID ' . $cikis_firma));

    $sqlMizan = "SELECT FirmaAdi,
            SUM(CASE WHEN tarih < ? AND ((islemTipi = 'GELİR TAHAKKUK' AND cikisFirmaID = ?) OR islemTipi = 'GELİR TAHSİLAT')
                THEN COALESCE(genelTutar,0) ELSE 0 END) AS devir,
            SUM(CASE WHEN islemTipi = 'GELİR TAHAKKUK' AND cikisFirmaID = ? AND tarih >= ? AND tarih <= ?
                THEN COALESCE(genelTutar,0) ELSE 0 END) AS donem_satis,
            SUM(CASE WHEN islemTipi = 'GELİR TAHSİLAT' AND tarih >= ? AND tarih <= ?
                THEN COALESCE(genelTutar,0) ELSE 0 END) AS donem_tahsilat,
            SUM(CASE WHEN islemTipi = 'GELİR TAHAKKUK' AND cikisFirmaID = ? AND tarih >= ? AND tarih <= ?
                THEN COALESCE(dokumNetKg,0) ELSE 0 END) AS donem_net_kg
            FROM SahadanSatis
            WHERE status = 1
              AND (cikisFirmaID = ? OR islemTipi = 'GELİR TAHSİLAT')";
    $paramsMizan = [
        $tarihDevirBit, $cikis_firma,
        $cikis_firma, $tarihBas, $tarihBit,
        $tarihBas, $tarihBit,
        $cikis_firma, $tarihBas, $tarihBit,
        $cikis_firma
    ];
    if ($musteri !== '') {
        $sqlMizan .= " AND FirmaAdi = ?";
        $paramsMizan[] = $musteri;
    }
    $sqlMizan .= " GROUP BY FirmaAdi ORDER BY FirmaAdi";
    $stmtMizan = $pdoReport->prepare($sqlMizan);
    $stmtMizan->execute($paramsMizan);
    $mizanListe = $stmtMizan->fetchAll(PDO::FETCH_ASSOC);
    foreach ($mizanListe as &$m) {
        $m['bakiye'] = (float)($m['devir'] ?? 0) + (float)($m['donem_satis'] ?? 0) + (float)($m['donem_tahsilat'] ?? 0);
    }
    unset($m);
    $mizanListe = array_values(array_filter($mizanListe, function ($m) {
        return abs((float)$m['devir']) > 0.0001
            || abs((float)$m['donem_satis']) > 0.0001
            || abs((float)$m['donem_tahsilat']) > 0.0001
            || abs((float)$m['bakiye']) > 0.0001;
    }));
} catch (PDOException $e) {
    header('Content-Type: text/html; charset=utf-8');
    die('Veritabanı hatası: ' . htmlspecialchars($e->getMessage()));
}

class MizanPDF extends TCPDF {
    public function Header() {
        $this->SetFont('dejavusans', 'B', 11);
        $this->Cell(0, 6, 'Kantar Mizan Raporu', 0, 1, 'C');
        $this->SetFont('dejavusans', '', 9);
        $this->Cell(0, 5, date('d.m.Y H:i'), 0, 1, 'C');
        $this->Ln(1);
    }
    public function Footer() {
        $this->SetY(-10);
        $this->SetFont('dejavusans', 'I', 8);
        $this->Cell(0, 8, 'Sayfa ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

$pdf = new MizanPDF('P', PDF_UNIT, 'A4', true, 'UTF-8', false);
$pdf->SetCreator('OYS - Ocak Yönetim Sistemi');
$pdf->SetAuthor('ZERSOFT');
$pdf->SetTitle('Mizan - ' . $cikisFirmaAdi . ' - ' . $baslangic . '_' . $bitis);
$pdf->SetMargins(12, 16, 12);
$pdf->SetHeaderMargin(4);
$pdf->SetFooterMargin(8);
$pdf->SetAutoPageBreak(true, 12);
$pdf->SetFont('dejavusans', '', 8);
$pdf->AddPage();

$pdf->SetFont('dejavusans', '', 9);
$filtreMetin = ' | Çıkış: ' . $cikisFirmaAdi;
if ($musteri !== '') $filtreMetin .= ' | Müşteri: ' . $musteri;
$pdf->Cell(0, 5, 'Dönem: ' . $baslangic . ' – ' . $bitis . $filtreMetin, 0, 1, 'L');
$pdf->SetFont('dejavusans', '', 7);
$pdf->Cell(0, 4, 'Devir = dönem başı · Aldığı = dönem satış · Ödediği = dönem tahsilat · Bakiye = Devir+Aldığı+Ödediği (eksi = borçlu)', 0, 1, 'L');
$pdf->Ln(2);

$colW = [55, 28, 28, 28, 22, 30];
$headers = ['Müşteri', 'Devir', 'Aldığı/Satış', 'Ödediği/Tahsilat', 'Net(kg)', 'Bakiye'];
$pdf->SetFillColor(230, 230, 230);
$pdf->SetFont('dejavusans', 'B', 7);
foreach ($headers as $i => $h) {
    $pdf->Cell($colW[$i], 6, $h, 1, 0, ($i >= 1 ? 'R' : 'L'), true);
}
$pdf->Ln();
$pdf->SetFont('dejavusans', '', 7);
$fill = false;
$sumDevir = 0; $sumAldi = 0; $sumOdedi = 0; $sumKg = 0; $sumBakiye = 0;
foreach ($mizanListe as $m) {
    $devir = (float)($m['devir'] ?? 0);
    $aldi = (float)($m['donem_satis'] ?? 0);
    $odedi = (float)($m['donem_tahsilat'] ?? 0);
    $kg = (float)($m['donem_net_kg'] ?? 0);
    $bakiye = (float)($m['bakiye'] ?? 0);
    $sumDevir += $devir;
    $sumAldi += $aldi;
    $sumOdedi += $odedi;
    $sumKg += $kg;
    $sumBakiye += $bakiye;
    $pdf->Cell($colW[0], 5, mb_substr($m['FirmaAdi'] ?? '-', 0, 32), 1, 0, 'L', $fill);
    $pdf->Cell($colW[1], 5, number_format($devir, 2, ',', '.'), 1, 0, 'R', $fill);
    $pdf->Cell($colW[2], 5, number_format($aldi, 2, ',', '.'), 1, 0, 'R', $fill);
    $pdf->Cell($colW[3], 5, number_format($odedi, 2, ',', '.'), 1, 0, 'R', $fill);
    $pdf->Cell($colW[4], 5, number_format($kg, 0, ',', '.'), 1, 0, 'R', $fill);
    $pdf->Cell($colW[5], 5, number_format($bakiye, 2, ',', '.'), 1, 1, 'R', $fill);
    $fill = !$fill;
}
if (!empty($mizanListe)) {
    $pdf->SetFont('dejavusans', 'B', 7);
    $pdf->SetFillColor(220, 220, 220);
    $pdf->Cell($colW[0], 6, 'Toplam', 1, 0, 'L', true);
    $pdf->Cell($colW[1], 6, number_format($sumDevir, 2, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell($colW[2], 6, number_format($sumAldi, 2, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell($colW[3], 6, number_format($sumOdedi, 2, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell($colW[4], 6, number_format($sumKg, 0, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell($colW[5], 6, number_format($sumBakiye, 2, ',', '.'), 1, 1, 'R', true);
}

$pdf->Output('mizan_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $cikisFirmaAdi) . '_' . $baslangic . '_' . $bitis . '.pdf', 'I');
exit;
