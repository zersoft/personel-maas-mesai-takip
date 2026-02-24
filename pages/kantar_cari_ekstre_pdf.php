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

$cari_firma = isset($_GET['cari_firma']) ? trim((string)$_GET['cari_firma']) : '';
$baslangic   = isset($_GET['baslangic']) ? $_GET['baslangic'] : date('Y-m-01');
$bitis       = isset($_GET['bitis'])     ? $_GET['bitis']     : date('Y-m-d');

if ($cari_firma === '' || !$pdoReport) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: text/html; charset=utf-8');
    die('Müşteri seçin veya raporlama veritabanı bağlantısını kontrol edin.');
}

$tarihBas = str_replace('-', '', $baslangic) . '000000';
$tarihBit = str_replace('-', '', $bitis) . '999999';
$tarihDevirBit = str_replace('-', '', $baslangic) . '000000';

function pdfFormatSahaTarih($tarih) {
    if ($tarih === null || $tarih === '') return '-';
    $s = (string)$tarih;
    if (strlen($s) >= 8) return substr($s, 6, 2) . '.' . substr($s, 4, 2) . '.' . substr($s, 0, 4);
    return $s;
}
function pdfFormatSahaZaman($zamanDamgasi) {
    if ($zamanDamgasi === null || $zamanDamgasi === '') return '-';
    $s = (string)$zamanDamgasi;
    if (strlen($s) >= 14) return substr($s, 8, 2) . ':' . substr($s, 10, 2) . ':' . substr($s, 12, 2);
    return $s;
}

try {
    $devirStmt = $pdoReport->prepare("SELECT SUM(COALESCE(genelTutar,0)) AS devir FROM SahadanSatis WHERE status = 1 AND FirmaAdi = ? AND tarih < ?");
    $devirStmt->execute([$cari_firma, $tarihDevirBit]);
    $cariDevir = (float)$devirStmt->fetchColumn();

    $stmt = $pdoReport->prepare("SELECT id, FirmaAdi, tarih, islemZamanDamgasi, islemTipi, dokumTipi, irsaliyeSeri, irsaliyeNo, genelTutar FROM SahadanSatis WHERE status = 1 AND FirmaAdi = ? AND tarih BETWEEN ? AND ? ORDER BY tarih ASC, islemZamanDamgasi ASC");
    $stmt->execute([$cari_firma, $tarihBas, $tarihBit]);
    $cariListe = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $bakiye = $cariDevir;
    foreach ($cariListe as &$row) {
        $tutar = (float)($row['genelTutar'] ?? 0);
        $bakiye += $tutar;
        $row['kumulatif_bakiye'] = $bakiye;
    }
    unset($row);
    $kapanis = $bakiye;
} catch (PDOException $e) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: text/html; charset=utf-8');
    die('Veritabanı hatası: ' . htmlspecialchars($e->getMessage()));
}

class CariEkstrePDF extends TCPDF {
    public function Header() {
        $this->SetFont('dejavusans', 'B', 12);
        $this->Cell(0, 6, 'Müşteri Cari Ekstre', 0, 1, 'C');
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

$pdf = new CariEkstrePDF('P', PDF_UNIT, 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Personel Takip Sistemi');
$pdf->SetAuthor('ZERSOFT');
$pdf->SetTitle('Cari Ekstre - ' . $cari_firma);
$pdf->SetMargins(10, 18, 10);
$pdf->SetHeaderMargin(4);
$pdf->SetFooterMargin(8);
$pdf->SetAutoPageBreak(true, 10);
$pdf->SetFont('dejavusans', '', 8);
$pdf->AddPage();

$pdf->SetFont('dejavusans', 'B', 10);
$pdf->Cell(0, 5, $cari_firma, 0, 1, 'L');
$pdf->SetFont('dejavusans', '', 9);
$pdf->Cell(0, 5, 'Dönem: ' . $baslangic . ' – ' . $bitis, 0, 1, 'L');
$pdf->Ln(2);

$colW = [22, 14, 16, 58, 24, 24, 26];
$headers = ['Tarih', 'Saat', 'Hareket', 'Açıklama', 'İrsaliye', 'Tutar (₺)', 'Bakiye (₺)'];
$pdf->SetFillColor(230, 230, 230);
$pdf->SetFont('dejavusans', 'B', 7);
foreach ($headers as $i => $h) {
    $pdf->Cell($colW[$i], 6, $h, 1, 0, $i >= 5 ? 'R' : 'L', true);
}
$pdf->Ln();
$pdf->SetFillColor(248, 248, 248);
$pdf->SetFont('dejavusans', '', 7);

// İlk satır: Devir
$pdf->Cell($colW[0], 5, '—', 1, 0, 'L', true);
$pdf->Cell($colW[1], 5, '—', 1, 0, 'L', true);
$pdf->Cell($colW[2], 5, 'Devir', 1, 0, 'L', true);
$pdf->Cell($colW[3], 5, 'Önceki dönem bakiyesi (' . $baslangic . ' öncesi)', 1, 0, 'L', true);
$pdf->Cell($colW[4], 5, '—', 1, 0, 'L', true);
$pdf->Cell($colW[5], 5, '—', 1, 0, 'R', true);
$pdf->Cell($colW[6], 5, number_format($cariDevir, 2, ',', '.'), 1, 1, 'R', true);

$fill = false;
foreach ($cariListe as $r) {
    $tutar = (float)($r['genelTutar'] ?? 0);
    $pdf->Cell($colW[0], 5, pdfFormatSahaTarih($r['tarih'] ?? null), 1, 0, 'L', $fill);
    $pdf->Cell($colW[1], 5, pdfFormatSahaZaman($r['islemZamanDamgasi'] ?? null), 1, 0, 'L', $fill);
    $pdf->Cell($colW[2], 5, (isset($r['islemTipi']) && $r['islemTipi'] === 'GELİR TAHSİLAT') ? 'Tahsilat' : 'Satış', 1, 0, 'L', $fill);
    $pdf->Cell($colW[3], 5, mb_substr($r['dokumTipi'] ?? '-', 0, 42), 1, 0, 'L', $fill);
    $pdf->Cell($colW[4], 5, ($r['irsaliyeSeri'] ?? '') . ($r['irsaliyeNo'] ?? ''), 1, 0, 'L', $fill);
    $pdf->Cell($colW[5], 5, number_format($tutar, 2, ',', '.'), 1, 0, 'R', $fill);
    $pdf->Cell($colW[6], 5, number_format((float)($r['kumulatif_bakiye'] ?? 0), 2, ',', '.'), 1, 1, 'R', $fill);
    $fill = !$fill;
}

$pdf->SetFont('dejavusans', 'B', 8);
$pdf->SetFillColor(220, 220, 220);
$pdf->Cell($colW[0] + $colW[1] + $colW[2] + $colW[3] + $colW[4] + $colW[5], 6, 'Kapanış bakiyesi', 1, 0, 'R', true);
$pdf->Cell($colW[6], 6, number_format($kapanis, 2, ',', '.'), 1, 1, 'R', true);

$pdf->Ln(2);
$pdf->SetFont('dejavusans', 'I', 7);
$pdf->Cell(0, 4, 'Eksi bakiye = müşteri borçlu. Satış bakiyeyi eksiye götürür, tahsilat azaltır.', 0, 1, 'L');

$pdf->Output('cari_ekstre_' . preg_replace('/[^a-z0-9_-]/i', '_', $cari_firma) . '_' . $baslangic . '_' . $bitis . '.pdf', 'I');
exit;
