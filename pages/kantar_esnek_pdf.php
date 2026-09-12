<?php
ob_start();
@ini_set('memory_limit', '512M');
@set_time_limit(180);

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

$baslangic    = isset($_GET['baslangic']) ? $_GET['baslangic'] : date('Y-m-01');
$bitis        = isset($_GET['bitis'])     ? $_GET['bitis']     : date('Y-m-d');
$musteri      = isset($_GET['musteri'])   ? trim((string)$_GET['musteri']) : '';
$plaka        = isset($_GET['plaka'])     ? trim((string)$_GET['plaka']) : '';
$cikis_firma  = isset($_GET['cikis_firma']) ? (int)$_GET['cikis_firma'] : 0;
$hareket      = isset($_GET['hareket'])   ? trim((string)$_GET['hareket']) : '';
if (!in_array($hareket, ['', 'satis', 'tahsilat'], true)) {
    $hareket = '';
}
$gidecegi_yer = isset($_GET['gidecegi_yer']) ? trim((string)$_GET['gidecegi_yer']) : '';
$malzeme      = isset($_GET['malzeme'])      ? trim((string)$_GET['malzeme']) : '';

$baslik = isset($_GET['baslik']) && trim((string)$_GET['baslik']) !== '' ? trim((string)$_GET['baslik']) : 'Gideceği Yer & Malzeme Bazlı Sevkiyat Raporu';
$grup1  = isset($_GET['grup1']) ? trim((string)$_GET['grup1']) : 'gidecegiYer';
$grup2  = isset($_GET['grup2']) ? trim((string)$_GET['grup2']) : 'dokumTipi';

$gecerliGruplar = [
    'gidecegiYer' => 'Gideceği Yer',
    'dokumTipi'   => 'Malzeme',
    'FirmaAdi'    => 'Müşteri',
    'plaka'       => 'Plaka',
    'tarih'       => 'Tarih',
    'cikisFirma'  => 'Çıkış Firması',
    'yok'         => 'Yok'
];
if (!array_key_exists($grup1, $gecerliGruplar)) $grup1 = 'gidecegiYer';
if (!array_key_exists($grup2, $gecerliGruplar)) $grup2 = 'dokumTipi';

$tumKolonlar = [
    'kantar_fis'    => ['baslik' => 'KantarFiş', 'hizalama' => 'L', 'weight' => 14],
    'irsaliye_no'   => ['baslik' => 'irsaliyeNo', 'hizalama' => 'L', 'weight' => 19],
    'tarih'         => ['baslik' => 'tarih', 'hizalama' => 'C', 'weight' => 15],
    'saat'          => ['baslik' => 'saat', 'hizalama' => 'C', 'weight' => 12],
    'plaka'         => ['baslik' => 'plaka', 'hizalama' => 'L', 'weight' => 15],
    'dokum_net_kg'  => ['baslik' => 'dokumNetKilo', 'hizalama' => 'R', 'weight' => 18],
    'malzeme'       => ['baslik' => 'Malzeme', 'hizalama' => 'L', 'weight' => 65],
    'gidecegi_yer'  => ['baslik' => 'gidecegiYer', 'hizalama' => 'L', 'weight' => 65],
    'musteri'       => ['baslik' => 'Müşteri', 'hizalama' => 'L', 'weight' => 60],
    'brim_fiyat'    => ['baslik' => 'Birim Fiyat', 'hizalama' => 'R', 'weight' => 14],
    'tutar'         => ['baslik' => 'Tutar', 'hizalama' => 'R', 'weight' => 18],
    'kdv'           => ['baslik' => 'KDV', 'hizalama' => 'R', 'weight' => 14],
    'genel_tutar'   => ['baslik' => 'Genel Tutar', 'hizalama' => 'R', 'weight' => 19],
    'hareket'       => ['baslik' => 'Hareket', 'hizalama' => 'C', 'weight' => 13],
    'cikis_firmasi' => ['baslik' => 'Çıkış Firması', 'hizalama' => 'L', 'weight' => 24]
];

$varsayilanKolonlar = ['kantar_fis', 'irsaliye_no', 'tarih', 'saat', 'plaka', 'dokum_net_kg', 'malzeme', 'gidecegi_yer'];

if (isset($_GET['kolonlar']) && is_array($_GET['kolonlar'])) {
    $seciliKolonlar = array_values(array_intersect($_GET['kolonlar'], array_keys($tumKolonlar)));
    if (empty($seciliKolonlar)) {
        $seciliKolonlar = $varsayilanKolonlar;
    }
} else {
    $seciliKolonlar = $varsayilanKolonlar;
}

// Kolon genişliklerini 280mm toplam alana ölçekle
$totalWeight = 0;
foreach ($seciliKolonlar as $ck) {
    $totalWeight += $tumKolonlar[$ck]['weight'];
}
$colW = [];
$totalAllocated = 0;
foreach ($seciliKolonlar as $idx => $ck) {
    if ($idx === count($seciliKolonlar) - 1) {
        $colW[$ck] = round(280 - $totalAllocated, 1);
    } else {
        $w = round(($tumKolonlar[$ck]['weight'] / $totalWeight) * 280, 1);
        $colW[$ck] = $w;
        $totalAllocated += $w;
    }
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
        $st = $pdoReport->prepare("SELECT COALESCE(NULLIF(TRIM(FirmaAdi),''), CONCAT('ID ', ?)) FROM Cari WHERE CariID = ? LIMIT 1");
        $st->execute([$cikis_firma, $cikis_firma]);
        $cikisFirmaAdi = (string)($st->fetchColumn() ?: ('ID ' . $cikis_firma));
    } catch (PDOException $e) {
        $cikisFirmaAdi = 'ID ' . $cikis_firma;
    }
}

try {
    $sql = "SELECT s.id, s.Kimlik, s.fisNo, s.okunanFisNo, s.irsaliyeNo, s.irsaliyeSeri, s.tarih,
                   s.islemZamanDamgasi, s.saat, s.plaka, s.dokumNetKg, s.dokumTipi, s.gidecegiYer,
                   s.FirmaAdi, s.brimFiyat, s.dokumTutar, s.kdv, s.genelTutar, s.islemTipi, s.cikisFirmaID,
                   COALESCE(c.FirmaAdi, CONCAT('ID ', s.cikisFirmaID)) AS cikisFirmaAdi
            FROM SahadanSatis s
            LEFT JOIN Cari c ON c.CariID = s.cikisFirmaID
            WHERE s.status = 1 AND s.tarih BETWEEN ? AND ?";
    $params = [$tarihBas, $tarihBit];

    if ($musteri !== '') {
        $sql .= " AND s.FirmaAdi = ?";
        $params[] = $musteri;
    }
    if ($plaka !== '') {
        $sql .= " AND s.plaka LIKE ?";
        $params[] = '%' . $plaka . '%';
    }
    if ($gidecegi_yer !== '') {
        $sql .= " AND s.gidecegiYer = ?";
        $params[] = $gidecegi_yer;
    }
    if ($malzeme !== '') {
        $sql .= " AND s.dokumTipi = ?";
        $params[] = $malzeme;
    }
    if ($hareket === 'satis') {
        $sql .= " AND s.islemTipi = 'GELİR TAHAKKUK'";
        if ($cikis_firma > 0) {
            $sql .= " AND s.cikisFirmaID = ?";
            $params[] = $cikis_firma;
        }
    } elseif ($hareket === 'tahsilat') {
        $sql .= " AND s.islemTipi = 'GELİR TAHSİLAT'";
    } elseif ($cikis_firma > 0) {
        $sql .= " AND (s.cikisFirmaID = ? OR s.islemTipi = 'GELİR TAHSİLAT')";
        $params[] = $cikis_firma;
    }

    $sql .= " ORDER BY s.tarih DESC, s.islemZamanDamgasi DESC";
    $stmt = $pdoReport->prepare($sql);
    $stmt->execute($params);
    $liste = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    header('Content-Type: text/html; charset=utf-8');
    die('Veritabanı hatası: ' . htmlspecialchars($e->getMessage()));
}

$getGrupDeger = function($r, $gType) {
    if ($gType === 'gidecegiYer') {
        $v = trim((string)($r['gidecegiYer'] ?? ''));
        return $v !== '' ? $v : 'Belirtilmemiş';
    } elseif ($gType === 'dokumTipi') {
        $v = trim((string)($r['dokumTipi'] ?? ''));
        return $v !== '' ? $v : 'Belirtilmemiş';
    } elseif ($gType === 'FirmaAdi') {
        $v = trim((string)($r['FirmaAdi'] ?? ''));
        return $v !== '' ? $v : 'Belirtilmemiş';
    } elseif ($gType === 'plaka') {
        $v = trim((string)($r['plaka'] ?? ''));
        return $v !== '' ? $v : 'Plakasız';
    } elseif ($gType === 'tarih') {
        return pdfFormatSahaTarih($r['tarih'] ?? null);
    } elseif ($gType === 'cikisFirma') {
        $v = trim((string)($r['cikisFirmaAdi'] ?? ''));
        return $v !== '' ? $v : 'Belirtilmemiş';
    }
    return '';
};

$hasGrup1 = ($grup1 !== 'yok');
$hasGrup2 = ($grup2 !== 'yok' && $grup2 !== $grup1);

$esnekGruplanmis = [];
$esnekGenelToplam = ['sefer' => 0, 'net_kg' => 0, 'tutar' => 0];

foreach ($liste as $r) {
    $kg = (float)($r['dokumNetKg'] ?? 0);
    $tut = (float)($r['dokumTutar'] ?? 0);
    $esnekGenelToplam['sefer']++;
    $esnekGenelToplam['net_kg'] += $kg;
    $esnekGenelToplam['tutar'] += $tut;

    if ($hasGrup1 && $hasGrup2) {
        $k1 = $getGrupDeger($r, $grup1);
        $k2 = $getGrupDeger($r, $grup2);

        if (!isset($esnekGruplanmis[$k1])) {
            $esnekGruplanmis[$k1] = [
                'toplam' => ['sefer' => 0, 'net_kg' => 0, 'tutar' => 0],
                'alt_gruplar' => []
            ];
        }
        $esnekGruplanmis[$k1]['toplam']['sefer']++;
        $esnekGruplanmis[$k1]['toplam']['net_kg'] += $kg;
        $esnekGruplanmis[$k1]['toplam']['tutar'] += $tut;

        if (!isset($esnekGruplanmis[$k1]['alt_gruplar'][$k2])) {
            $esnekGruplanmis[$k1]['alt_gruplar'][$k2] = [
                'toplam' => ['sefer' => 0, 'net_kg' => 0, 'tutar' => 0],
                'satirlar' => []
            ];
        }
        $esnekGruplanmis[$k1]['alt_gruplar'][$k2]['toplam']['sefer']++;
        $esnekGruplanmis[$k1]['alt_gruplar'][$k2]['toplam']['net_kg'] += $kg;
        $esnekGruplanmis[$k1]['alt_gruplar'][$k2]['toplam']['tutar'] += $tut;
        $esnekGruplanmis[$k1]['alt_gruplar'][$k2]['satirlar'][] = $r;
    } elseif ($hasGrup1) {
        $k1 = $getGrupDeger($r, $grup1);
        if (!isset($esnekGruplanmis[$k1])) {
            $esnekGruplanmis[$k1] = [
                'toplam' => ['sefer' => 0, 'net_kg' => 0, 'tutar' => 0],
                'satirlar' => []
            ];
        }
        $esnekGruplanmis[$k1]['toplam']['sefer']++;
        $esnekGruplanmis[$k1]['toplam']['net_kg'] += $kg;
        $esnekGruplanmis[$k1]['toplam']['tutar'] += $tut;
        $esnekGruplanmis[$k1]['satirlar'][] = $r;
    } else {
        $esnekGruplanmis['duz_liste'][] = $r;
    }
}

class EsnekPDF extends TCPDF {
    protected $raporBaslik = '';
    public function setRaporBaslik($b) { $this->raporBaslik = $b; }
    public function Header() {
        $this->SetFont('dejavusans', 'B', 11);
        $this->Cell(0, 6, $this->raporBaslik, 0, 1, 'C');
        $this->SetFont('dejavusans', '', 8);
        $this->Cell(0, 5, date('d.m.Y H:i'), 0, 1, 'C');
        $this->Ln(1);
    }
    public function Footer() {
        $this->SetY(-10);
        $this->SetFont('dejavusans', 'I', 8);
        $this->Cell(0, 8, 'Sayfa ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

$pdf = new EsnekPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
$pdf->setRaporBaslik($baslik);
$pdf->SetCreator('OYS - Ocak Yönetim Sistemi');
$pdf->SetAuthor('ZERSOFT');
$pdf->SetTitle($baslik . ' - ' . $baslangic . '_' . $bitis);
$pdf->SetMargins(8, 16, 8);
$pdf->SetHeaderMargin(4);
$pdf->SetFooterMargin(8);
$pdf->SetAutoPageBreak(true, 10);
$pdf->AddPage();

$pdf->SetFont('dejavusans', '', 8);
$filtreMetin = '';
if ($cikisFirmaAdi !== '') $filtreMetin .= ' | Çıkış: ' . $cikisFirmaAdi;
if ($musteri !== '') $filtreMetin .= ' | Müşteri: ' . $musteri;
if ($gidecegi_yer !== '') $filtreMetin .= ' | Gideceği Yer: ' . $gidecegi_yer;
if ($malzeme !== '') $filtreMetin .= ' | Malzeme: ' . $malzeme;
if ($plaka !== '') $filtreMetin .= ' | Plaka: ' . $plaka;
if ($hareket === 'satis') $filtreMetin .= ' | Hareket: Satış';
elseif ($hareket === 'tahsilat') $filtreMetin .= ' | Hareket: Tahsilat';

$grupBilgi = '1. Grup: ' . $gecerliGruplar[$grup1];
if ($hasGrup2) $grupBilgi .= ' | 2. Grup: ' . $gecerliGruplar[$grup2];

$pdf->Cell(0, 5, 'Dönem: ' . pdfFormatSahaTarih($tarihBas) . ' – ' . pdfFormatSahaTarih($tarihBit) . ' (' . $grupBilgi . ')' . $filtreMetin, 0, 1, 'L');
$pdf->Ln(2);

$renderVal = function($colKey, $r) {
    switch ($colKey) {
        case 'kantar_fis':
            $fis = !empty($r['okunanFisNo']) ? $r['okunanFisNo'] : (!empty($r['fisNo']) ? $r['fisNo'] : (!empty($r['Kimlik']) ? $r['Kimlik'] : $r['id']));
            return (string)$fis;
        case 'irsaliye_no':
            return (string)(($r['irsaliyeSeri'] ?? '') . ($r['irsaliyeNo'] ?? ''));
        case 'tarih':
            return pdfFormatSahaTarih($r['tarih'] ?? null);
        case 'saat':
            return pdfFormatSahaZaman($r['islemZamanDamgasi'] ?? null);
        case 'plaka':
            return (string)($r['plaka'] ?? '-');
        case 'dokum_net_kg':
            return number_format((float)($r['dokumNetKg'] ?? 0), 0, ',', '.');
        case 'malzeme':
            return (string)($r['dokumTipi'] ?? '-');
        case 'gidecegi_yer':
            return (string)($r['gidecegiYer'] ?? '-');
        case 'musteri':
            return (string)($r['FirmaAdi'] ?? '-');
        case 'brim_fiyat':
            return number_format((float)($r['brimFiyat'] ?? 0), 2, ',', '.');
        case 'tutar':
            return number_format((float)($r['dokumTutar'] ?? 0), 2, ',', '.');
        case 'kdv':
            return number_format((float)($r['kdv'] ?? 0), 2, ',', '.');
        case 'genel_tutar':
            return number_format((float)($r['genelTutar'] ?? 0), 2, ',', '.');
        case 'hareket':
            $isTahsilat = (isset($r['islemTipi']) && $r['islemTipi'] === 'GELİR TAHSİLAT');
            return $isTahsilat ? 'Tahsilat' : 'Satış';
        case 'cikis_firmasi':
            return (string)($r['cikisFirmaAdi'] ?? '-');
        default:
            return '';
    }
};

$printHeaders = function() use ($pdf, $seciliKolonlar, $tumKolonlar, $colW) {
    $pdf->SetFont('dejavusans', 'B', 7);
    $pdf->SetFillColor(230, 230, 230);
    foreach ($seciliKolonlar as $ck) {
        $pdf->Cell($colW[$ck], 5.5, $tumKolonlar[$ck]['baslik'], 1, 0, $tumKolonlar[$ck]['hizalama'], true);
    }
    $pdf->Ln();
};

$printSubtotalRow = function($label, $sefer, $kg, $tutar, $fillR, $fillG, $fillB, $fontSize = 6.5) use ($pdf, $seciliKolonlar, $tumKolonlar, $colW) {
    $pdf->SetFont('dejavusans', 'B', $fontSize);
    $pdf->SetFillColor($fillR, $fillG, $fillB);

    $summaryColKeys = ['dokum_net_kg', 'tutar', 'genel_tutar', 'brim_fiyat', 'kdv'];
    $firstSummaryIdx = false;
    foreach ($seciliKolonlar as $idx => $ck) {
        if (in_array($ck, $summaryColKeys, true)) {
            $firstSummaryIdx = $idx;
            break;
        }
    }

    $txt = $label . ' (' . number_format($sefer, 0, ',', '.') . ' Sefer)';

    if ($firstSummaryIdx === false || $firstSummaryIdx === 0) {
        $firstTextDone = false;
        foreach ($seciliKolonlar as $ck) {
            $align = $tumKolonlar[$ck]['hizalama'];
            $w = $colW[$ck];
            if (!$firstTextDone) {
                $pdf->Cell($w, 5, $txt, 1, 0, 'L', true);
                $firstTextDone = true;
            } elseif ($ck === 'dokum_net_kg') {
                $pdf->Cell($w, 5, number_format($kg, 0, ',', '.') . ' kg', 1, 0, 'R', true);
            } elseif ($ck === 'tutar' || $ck === 'genel_tutar') {
                $pdf->Cell($w, 5, number_format($tutar, 2, ',', '.') . ' ₺', 1, 0, 'R', true);
            } else {
                $pdf->Cell($w, 5, '', 1, 0, 'L', true);
            }
        }
    } else {
        $mergedW = 0;
        for ($i = 0; $i < $firstSummaryIdx; $i++) {
            $mergedW += $colW[$seciliKolonlar[$i]];
        }
        $pdf->Cell($mergedW, 5, $txt, 1, 0, 'L', true);

        for ($i = $firstSummaryIdx; $i < count($seciliKolonlar); $i++) {
            $ck = $seciliKolonlar[$i];
            $w = $colW[$ck];
            if ($ck === 'dokum_net_kg') {
                $pdf->Cell($w, 5, number_format($kg, 0, ',', '.') . ' kg', 1, 0, 'R', true);
            } elseif ($ck === 'tutar' || $ck === 'genel_tutar') {
                $pdf->Cell($w, 5, number_format($tutar, 2, ',', '.') . ' ₺', 1, 0, 'R', true);
            } else {
                $pdf->Cell($w, 5, '', 1, 0, 'L', true);
            }
        }
    }
    $pdf->Ln();
};

$printHeaders();

if (empty($esnekGruplanmis) || ($hasGrup1 && empty($esnekGruplanmis)) || (!$hasGrup1 && empty($esnekGruplanmis['duz_liste']))) {
    $pdf->SetFont('dejavusans', '', 8);
    $pdf->Cell(280, 8, 'Kayıt bulunamadı.', 1, 1, 'C');
} elseif ($hasGrup1 && $hasGrup2) {
    foreach ($esnekGruplanmis as $k1 => $g1Data) {
        $g1Sefer = $g1Data['toplam']['sefer'];
        $g1Kg    = $g1Data['toplam']['net_kg'];
        $g1Tut   = $g1Data['toplam']['tutar'];

        // 1. Seviye Grup Başlığı
        $pdf->SetFont('dejavusans', 'B', 8);
        $pdf->SetFillColor(217, 237, 247);
        $headerText = $gecerliGruplar[$grup1] . ': ' . $k1 . ' | ' . number_format($g1Sefer, 0, ',', '.') . ' Sefer - ' . number_format($g1Kg, 0, ',', '.') . ' kg';
        if ($g1Tut > 0) $headerText .= ' - ' . number_format($g1Tut, 2, ',', '.') . ' ₺';
        $pdf->Cell(280, 6, $headerText, 1, 1, 'L', true);

        foreach ($g1Data['alt_gruplar'] as $k2 => $g2Data) {
            $g2Sefer = $g2Data['toplam']['sefer'];
            $g2Kg    = $g2Data['toplam']['net_kg'];
            $g2Tut   = $g2Data['toplam']['tutar'];

            // 2. Seviye Grup Başlığı
            $pdf->SetFont('dejavusans', 'B', 7);
            $pdf->SetFillColor(245, 245, 245);
            $pdf->Cell(280, 5, '    -- ' . $gecerliGruplar[$grup2] . ': ' . $k2 . ' (' . number_format($g2Sefer, 0, ',', '.') . ' Sefer)', 1, 1, 'L', true);

            // Satırlar
            $pdf->SetFont('dejavusans', '', 6.5);
            $fill = false;
            foreach ($g2Data['satirlar'] as $r) {
                foreach ($seciliKolonlar as $ck) {
                    $maxChar = (int)max(15, round($colW[$ck] * 0.9));
                    $pdf->Cell($colW[$ck], 4.5, mb_substr($renderVal($ck, $r), 0, $maxChar), 1, 0, $tumKolonlar[$ck]['hizalama'], $fill);
                }
                $pdf->Ln();
                $fill = !$fill;
            }

            // 2. Seviye Alt Toplam
            $printSubtotalRow('    Alt Toplam (' . $k2 . ')', $g2Sefer, $g2Kg, $g2Tut, 252, 248, 227, 6.5);
        }

        // 1. Seviye Ara Toplam
        $printSubtotalRow($k1 . ' Toplamı', $g1Sefer, $g1Kg, $g1Tut, 232, 240, 254, 7);
    }
} elseif ($hasGrup1) {
    foreach ($esnekGruplanmis as $k1 => $g1Data) {
        $g1Sefer = $g1Data['toplam']['sefer'];
        $g1Kg    = $g1Data['toplam']['net_kg'];
        $g1Tut   = $g1Data['toplam']['tutar'];

        // 1. Seviye Grup Başlığı
        $pdf->SetFont('dejavusans', 'B', 8);
        $pdf->SetFillColor(217, 237, 247);
        $pdf->Cell(280, 6, $gecerliGruplar[$grup1] . ': ' . $k1 . ' | ' . number_format($g1Sefer, 0, ',', '.') . ' Sefer - ' . number_format($g1Kg, 0, ',', '.') . ' kg', 1, 1, 'L', true);

        // Satırlar
        $pdf->SetFont('dejavusans', '', 6.5);
        $fill = false;
        foreach ($g1Data['satirlar'] as $r) {
            foreach ($seciliKolonlar as $ck) {
                $maxChar = (int)max(15, round($colW[$ck] * 0.9));
                $pdf->Cell($colW[$ck], 4.5, mb_substr($renderVal($ck, $r), 0, $maxChar), 1, 0, $tumKolonlar[$ck]['hizalama'], $fill);
            }
            $pdf->Ln();
            $fill = !$fill;
        }

        // 1. Seviye Alt Toplam
        $printSubtotalRow($k1 . ' Alt Toplamı', $g1Sefer, $g1Kg, $g1Tut, 232, 240, 254, 7);
    }
} else {
    $pdf->SetFont('dejavusans', '', 6.5);
    $fill = false;
    foreach ($esnekGruplanmis['duz_liste'] ?? [] as $r) {
        foreach ($seciliKolonlar as $ck) {
            $maxChar = (int)max(15, round($colW[$ck] * 0.9));
            $pdf->Cell($colW[$ck], 4.5, mb_substr($renderVal($ck, $r), 0, $maxChar), 1, 0, $tumKolonlar[$ck]['hizalama'], $fill);
        }
        $pdf->Ln();
        $fill = !$fill;
    }
}

// Genel Toplam
if ($esnekGenelToplam['sefer'] > 0) {
    $printSubtotalRow('GENEL TOPLAM', $esnekGenelToplam['sefer'], $esnekGenelToplam['net_kg'], $esnekGenelToplam['tutar'], 220, 220, 220, 7.5);
}

$cleanFileName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baslik) . '_' . $baslangic . '_' . $bitis . '.pdf';
$pdf->Output($cleanFileName, 'I');
exit;
