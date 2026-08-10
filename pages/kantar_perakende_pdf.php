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
$plaka     = isset($_GET['plaka'])     ? trim((string)$_GET['plaka']) : '';
$cikis_firma = isset($_GET['cikis_firma']) ? (int)$_GET['cikis_firma'] : 0;
$hareket   = isset($_GET['hareket']) ? trim((string)$_GET['hareket']) : '';
if (!in_array($hareket, ['', 'satis', 'tahsilat'], true)) {
    $hareket = '';
}

if (!$pdoReport) {
    header('Content-Type: text/html; charset=utf-8');
    die('Raporlama veritabanı bağlantısı yok.');
}

$tarihBas = str_replace('-', '', $baslangic) . '000000';
$tarihBit = str_replace('-', '', $bitis) . '999999';

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

$cikisFirmaAdi = '';
if ($cikis_firma > 0) {
    try {
        $st = $pdoReport->prepare("SELECT COALESCE(NULLIF(TRIM(FirmaAdi),''), CONCAT('ID ', ?)) AS adi FROM Cari WHERE CariID = ? LIMIT 1");
        $st->execute([$cikis_firma, $cikis_firma]);
        $cikisFirmaAdi = (string)($st->fetchColumn() ?: ('ID ' . $cikis_firma));
    } catch (PDOException $e) {
        $cikisFirmaAdi = 'ID ' . $cikis_firma;
    }
}

try {
    $sql = "SELECT id, FirmaAdi, plaka, dokumTipi, dokumNetKg, brimFiyat, dokumTutar, kdv, genelTutar,
                   irsaliyeNo, irsaliyeSeri, islemTipi, tarih, islemZamanDamgasi
            FROM SahadanSatis WHERE status = 1 AND tarih BETWEEN ? AND ?";
    $params = [$tarihBas, $tarihBit];
    if ($musteri !== '') {
        $sql .= " AND FirmaAdi = ?";
        $params[] = $musteri;
    }
    if ($plaka !== '') {
        $sql .= " AND plaka LIKE ?";
        $params[] = '%' . $plaka . '%';
    }
    if ($hareket === 'satis') {
        $sql .= " AND islemTipi = 'GELİR TAHAKKUK'";
        if ($cikis_firma > 0) {
            $sql .= " AND cikisFirmaID = ?";
            $params[] = $cikis_firma;
        }
    } elseif ($hareket === 'tahsilat') {
        $sql .= " AND islemTipi = 'GELİR TAHSİLAT'";
    } elseif ($cikis_firma > 0) {
        $sql .= " AND (cikisFirmaID = ? OR islemTipi = 'GELİR TAHSİLAT')";
        $params[] = $cikis_firma;
    }
    $sql .= " ORDER BY tarih DESC, islemZamanDamgasi DESC";
    $stmt = $pdoReport->prepare($sql);
    $stmt->execute($params);
    $liste = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $toplamNetKg = 0;
    $toplamSatisPerakende = 0;
    $toplamTahsilatPerakende = 0;
    foreach ($liste as $r) {
        $gt = (float)($r['genelTutar'] ?? 0);
        if (isset($r['islemTipi']) && $r['islemTipi'] === 'GELİR TAHSİLAT') {
            $toplamTahsilatPerakende += $gt;
        } else {
            $toplamSatisPerakende += $gt;
        }
        $toplamNetKg += (float)($r['dokumNetKg'] ?? 0);
    }
} catch (PDOException $e) {
    header('Content-Type: text/html; charset=utf-8');
    die('Veritabanı hatası: ' . htmlspecialchars($e->getMessage()));
}

class PerakendePDF extends TCPDF {
    public function Header() {
        $this->SetFont('dejavusans', 'B', 11);
        $this->Cell(0, 6, 'Perakende Satış Listesi', 0, 1, 'C');
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

$pdf = new PerakendePDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
$pdf->SetCreator('OYS - Ocak Yönetim Sistemi');
$pdf->SetAuthor('ZERSOFT');
$pdf->SetTitle('Perakende Satış - ' . $baslangic . '_' . $bitis);
$pdf->SetMargins(8, 16, 8);
$pdf->SetHeaderMargin(4);
$pdf->SetFooterMargin(8);
$pdf->SetAutoPageBreak(true, 10);
$pdf->SetFont('dejavusans', '', 7);
$pdf->AddPage();

$pdf->SetFont('dejavusans', '', 9);
$filtreMetin = '';
if ($cikisFirmaAdi !== '') $filtreMetin .= ' | Çıkış: ' . $cikisFirmaAdi;
if ($musteri !== '') $filtreMetin .= ' | Müşteri: ' . $musteri;
if ($plaka !== '') $filtreMetin .= ' | Plaka: ' . $plaka;
if ($hareket === 'satis') $filtreMetin .= ' | Hareket: Satış';
elseif ($hareket === 'tahsilat') $filtreMetin .= ' | Hareket: Tahsilat';
$pdf->Cell(0, 5, 'Dönem: ' . $baslangic . ' – ' . $bitis . $filtreMetin, 0, 1, 'L');
$pdf->Ln(1);

$colW = [18, 12, 26, 14, 12, 30, 14, 14, 14, 12, 18, 18];
$headers = ['Tarih', 'Saat', 'Müşteri', 'Plaka', 'Hareket', 'Döküm Tipi', 'Net(kg)', 'B.Fiyat', 'Tutar', 'KDV', 'Tutar(₺)', 'İrsaliye'];
$pdf->SetFillColor(230, 230, 230);
$pdf->SetFont('dejavusans', 'B', 6);
foreach ($headers as $i => $h) {
    $pdf->Cell($colW[$i], 6, $h, 1, 0, ($i >= 6 ? 'R' : 'L'), true);
}
$pdf->Ln();
$pdf->SetFont('dejavusans', '', 6);
$fill = false;
foreach ($liste as $r) {
    $isTahsilat = (isset($r['islemTipi']) && $r['islemTipi'] === 'GELİR TAHSİLAT');
    $gt = (float)($r['genelTutar'] ?? 0);
    $dt = (float)($r['dokumTutar'] ?? 0);
    $pdf->Cell($colW[0], 5, pdfFormatSahaTarih($r['tarih'] ?? null), 1, 0, 'L', $fill);
    $pdf->Cell($colW[1], 5, pdfFormatSahaZaman($r['islemZamanDamgasi'] ?? null), 1, 0, 'L', $fill);
    $pdf->Cell($colW[2], 5, mb_substr($r['FirmaAdi'] ?? '-', 0, 18), 1, 0, 'L', $fill);
    $pdf->Cell($colW[3], 5, mb_substr($r['plaka'] ?? '-', 0, 10), 1, 0, 'L', $fill);
    $pdf->Cell($colW[4], 5, $isTahsilat ? 'Tahsilat' : 'Satış', 1, 0, 'L', $fill);
    $pdf->Cell($colW[5], 5, mb_substr($r['dokumTipi'] ?? '-', 0, 20), 1, 0, 'L', $fill);
    $pdf->Cell($colW[6], 5, number_format((float)($r['dokumNetKg'] ?? 0), 0, ',', '.'), 1, 0, 'R', $fill);
    $pdf->Cell($colW[7], 5, number_format((float)($r['brimFiyat'] ?? 0), 2, ',', '.'), 1, 0, 'R', $fill);
    $pdf->Cell($colW[8], 5, number_format($dt, 2, ',', '.'), 1, 0, 'R', $fill);
    $pdf->Cell($colW[9], 5, number_format((float)($r['kdv'] ?? 0), 2, ',', '.'), 1, 0, 'R', $fill);
    $pdf->Cell($colW[10], 5, number_format($gt, 2, ',', '.'), 1, 0, 'R', $fill);
    $pdf->Cell($colW[11], 5, ($r['irsaliyeSeri'] ?? '') . ($r['irsaliyeNo'] ?? ''), 1, 1, 'L', $fill);
    $fill = !$fill;
}

if (!empty($liste)) {
    $pdf->SetFont('dejavusans', 'B', 7);
    $pdf->SetFillColor(220, 220, 220);
    $pdf->Cell($colW[0] + $colW[1] + $colW[2] + $colW[3] + $colW[4] + $colW[5], 6, 'Özet', 1, 0, 'R', true);
    $pdf->Cell($colW[6], 6, number_format($toplamNetKg, 0, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell($colW[7] + $colW[8] + $colW[9], 6, '', 1, 0, 'R', true);
    $pdf->Cell($colW[10], 6, number_format($toplamSatisPerakende + $toplamTahsilatPerakende, 2, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell($colW[11], 6, 'Sat: ' . number_format($toplamSatisPerakende, 0, ',', '.') . ' Tah: ' . number_format($toplamTahsilatPerakende, 0, ',', '.'), 1, 1, 'L', true);
}

$pdf->Output('perakende_satis_' . $baslangic . '_' . $bitis . '.pdf', 'I');
exit;
