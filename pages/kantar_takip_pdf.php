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
$hareket   = isset($_GET['hareket']) ? trim((string)$_GET['hareket']) : '';
if (!in_array($hareket, ['', 'satis', 'tahsilat'], true)) {
    $hareket = '';
}

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
    $st = $pdoReport->prepare("SELECT COALESCE(NULLIF(TRIM(FirmaAdi),''), CONCAT('ID ', ?)) AS adi FROM Cari WHERE CariID = ? LIMIT 1");
    $st->execute([$cikis_firma, $cikis_firma]);
    $cikisFirmaAdi = (string)($st->fetchColumn() ?: ('ID ' . $cikis_firma));

    $sql = "SELECT id, FirmaAdi, plaka, dokumTipi, dokumNetKg, brimFiyat, dokumTutar, kdv, genelTutar,
                   irsaliyeNo, irsaliyeSeri, islemTipi, tarih, islemZamanDamgasi, cikisFirmaID
            FROM SahadanSatis
            WHERE status = 1 AND tarih BETWEEN ? AND ?";
    $params = [$tarihBas, $tarihBit];
    if ($hareket === 'satis') {
        $sql .= " AND islemTipi = 'GELİR TAHAKKUK' AND cikisFirmaID = ?";
        $params[] = $cikis_firma;
    } elseif ($hareket === 'tahsilat') {
        $sql .= " AND islemTipi = 'GELİR TAHSİLAT'";
    } else {
        $sql .= " AND (cikisFirmaID = ? OR islemTipi = 'GELİR TAHSİLAT')";
        $params[] = $cikis_firma;
    }
    if ($musteri !== '') {
        $sql .= " AND FirmaAdi = ?";
        $params[] = $musteri;
    }
    $sql .= " ORDER BY tarih DESC, islemZamanDamgasi DESC";
    $stmt = $pdoReport->prepare($sql);
    $stmt->execute($params);
    $liste = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $toplamNetKg = 0;
    $toplamSatis = 0;
    $toplamTahsilat = 0;
    foreach ($liste as $r) {
        $gt = (float)($r['genelTutar'] ?? 0);
        if (isset($r['islemTipi']) && $r['islemTipi'] === 'GELİR TAHSİLAT') {
            $toplamTahsilat += $gt;
        } elseif ((int)($r['cikisFirmaID'] ?? 0) === $cikis_firma) {
            $toplamSatis += $gt;
            $toplamNetKg += (float)($r['dokumNetKg'] ?? 0);
        }
    }

    $sqlOzet = "SELECT FirmaAdi,
            SUM(CASE WHEN islemTipi = 'GELİR TAHAKKUK' AND cikisFirmaID = ? AND tarih >= ? AND tarih <= ? THEN COALESCE(genelTutar,0) ELSE 0 END) AS toplam_satis,
            SUM(CASE WHEN islemTipi = 'GELİR TAHSİLAT' AND tarih >= ? AND tarih <= ? THEN COALESCE(genelTutar,0) ELSE 0 END) AS toplam_tahsilat,
            SUM(CASE WHEN islemTipi = 'GELİR TAHAKKUK' AND cikisFirmaID = ? AND tarih <= ? THEN COALESCE(genelTutar,0) ELSE 0 END) AS genel_satis,
            SUM(CASE WHEN islemTipi = 'GELİR TAHSİLAT' AND tarih <= ? THEN COALESCE(genelTutar,0) ELSE 0 END) AS genel_tahsilat,
            SUM(CASE WHEN islemTipi = 'GELİR TAHAKKUK' AND cikisFirmaID = ? AND tarih >= ? AND tarih <= ? THEN COALESCE(dokumNetKg,0) ELSE 0 END) AS donem_net_kg
            FROM SahadanSatis
            WHERE status = 1 AND tarih <= ?
              AND (cikisFirmaID = ? OR islemTipi = 'GELİR TAHSİLAT')
            GROUP BY FirmaAdi ORDER BY FirmaAdi";
    $stmtOzet = $pdoReport->prepare($sqlOzet);
    $stmtOzet->execute([
        $cikis_firma, $tarihBas, $tarihBit,
        $tarihBas, $tarihBit,
        $cikis_firma, $tarihBit,
        $tarihBit,
        $cikis_firma, $tarihBas, $tarihBit,
        $tarihBit, $cikis_firma
    ]);
    $ozetListe = $stmtOzet->fetchAll(PDO::FETCH_ASSOC);
    foreach ($ozetListe as &$o) {
        $o['bakiye'] = (float)($o['genel_satis'] ?? 0) + (float)($o['genel_tahsilat'] ?? 0);
    }
    unset($o);
    $ozetListe = array_values(array_filter($ozetListe, function ($o) {
        return (float)$o['toplam_satis'] != 0 || (float)$o['toplam_tahsilat'] != 0;
    }));
    if ($musteri !== '') {
        $ozetListe = array_values(array_filter($ozetListe, function ($o) use ($musteri) {
            return ($o['FirmaAdi'] ?? '') === $musteri;
        }));
    }
} catch (PDOException $e) {
    header('Content-Type: text/html; charset=utf-8');
    die('Veritabanı hatası: ' . htmlspecialchars($e->getMessage()));
}

class KantarTakipPDF extends TCPDF {
    public function Header() {
        $this->SetFont('dejavusans', 'B', 11);
        $this->Cell(0, 6, 'Kantar Takip Raporu', 0, 1, 'C');
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

$pdf = new KantarTakipPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
$pdf->SetCreator('OYS - Ocak Yönetim Sistemi');
$pdf->SetAuthor('ZERSOFT');
$pdf->SetTitle('Kantar Takip - ' . $cikisFirmaAdi . ' - ' . $baslangic . '_' . $bitis);
$pdf->SetMargins(8, 16, 8);
$pdf->SetHeaderMargin(4);
$pdf->SetFooterMargin(8);
$pdf->SetAutoPageBreak(true, 10);
$pdf->SetFont('dejavusans', '', 7);
$pdf->AddPage();

$pdf->SetFont('dejavusans', '', 9);
$filtreMetin = ' | Çıkış: ' . $cikisFirmaAdi;
if ($musteri !== '') $filtreMetin .= ' | Müşteri: ' . $musteri;
if ($hareket === 'satis') $filtreMetin .= ' | Hareket: Satış';
elseif ($hareket === 'tahsilat') $filtreMetin .= ' | Hareket: Tahsilat';
$pdf->Cell(0, 5, 'Dönem: ' . $baslangic . ' – ' . $bitis . $filtreMetin, 0, 1, 'L');
$pdf->Cell(0, 5, 'Dönem Satış: ' . number_format(abs($toplamSatis), 2, ',', '.') . ' ₺  |  Tahsilat: ' . number_format($toplamTahsilat, 2, ',', '.') . ' ₺  |  Net: ' . number_format($toplamNetKg, 0, ',', '.') . ' kg', 0, 1, 'L');
$pdf->Ln(2);

// Müşteri özeti
$pdf->SetFont('dejavusans', 'B', 9);
$pdf->Cell(0, 5, 'Müşteri Özeti', 0, 1, 'L');
$colOzet = [50, 30, 30, 25, 30, 30, 30];
$headersOzet = ['Müşteri', 'Dönem Satış', 'Dönem Tahsilat', 'Net(kg)', 'Genel Satış', 'Genel Tahsilat', 'Bakiye'];
$pdf->SetFillColor(230, 230, 230);
$pdf->SetFont('dejavusans', 'B', 6);
foreach ($headersOzet as $i => $h) {
    $pdf->Cell($colOzet[$i], 6, $h, 1, 0, ($i >= 1 ? 'R' : 'L'), true);
}
$pdf->Ln();
$pdf->SetFont('dejavusans', '', 6);
$fill = false;
$sumSatis = 0; $sumTahsilat = 0; $sumKg = 0; $sumGenSatis = 0; $sumGenTahsilat = 0;
foreach ($ozetListe as $o) {
    $sumSatis += (float)$o['toplam_satis'];
    $sumTahsilat += (float)$o['toplam_tahsilat'];
    $sumKg += (float)($o['donem_net_kg'] ?? 0);
    $sumGenSatis += (float)($o['genel_satis'] ?? 0);
    $sumGenTahsilat += (float)($o['genel_tahsilat'] ?? 0);
    $pdf->Cell($colOzet[0], 5, mb_substr($o['FirmaAdi'] ?? '-', 0, 28), 1, 0, 'L', $fill);
    $pdf->Cell($colOzet[1], 5, number_format((float)$o['toplam_satis'], 2, ',', '.'), 1, 0, 'R', $fill);
    $pdf->Cell($colOzet[2], 5, number_format((float)$o['toplam_tahsilat'], 2, ',', '.'), 1, 0, 'R', $fill);
    $pdf->Cell($colOzet[3], 5, number_format((float)($o['donem_net_kg'] ?? 0), 0, ',', '.'), 1, 0, 'R', $fill);
    $pdf->Cell($colOzet[4], 5, number_format((float)($o['genel_satis'] ?? 0), 2, ',', '.'), 1, 0, 'R', $fill);
    $pdf->Cell($colOzet[5], 5, number_format((float)($o['genel_tahsilat'] ?? 0), 2, ',', '.'), 1, 0, 'R', $fill);
    $pdf->Cell($colOzet[6], 5, number_format((float)$o['bakiye'], 2, ',', '.'), 1, 1, 'R', $fill);
    $fill = !$fill;
}
if (!empty($ozetListe)) {
    $pdf->SetFont('dejavusans', 'B', 6);
    $pdf->SetFillColor(220, 220, 220);
    $pdf->Cell($colOzet[0], 5, 'Toplam', 1, 0, 'L', true);
    $pdf->Cell($colOzet[1], 5, number_format($sumSatis, 2, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell($colOzet[2], 5, number_format($sumTahsilat, 2, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell($colOzet[3], 5, number_format($sumKg, 0, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell($colOzet[4], 5, number_format($sumGenSatis, 2, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell($colOzet[5], 5, number_format($sumGenTahsilat, 2, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell($colOzet[6], 5, number_format($sumGenSatis + $sumGenTahsilat, 2, ',', '.'), 1, 1, 'R', true);
}

$pdf->Ln(4);
$pdf->SetFont('dejavusans', 'B', 9);
$pdf->Cell(0, 5, 'Hareket Listesi', 0, 1, 'L');

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

$pdf->Output('kantar_takip_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $cikisFirmaAdi) . '_' . $baslangic . '_' . $bitis . '.pdf', 'I');
exit;
