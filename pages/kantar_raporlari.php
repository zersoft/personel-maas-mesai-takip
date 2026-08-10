<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Kantar Raporları';

$rapor = isset($_GET['rapor']) ? $_GET['rapor'] : 'perakende';
if (!in_array($rapor, ['perakende', 'ozet', 'ozet_malzeme', 'cari', 'kantar_takip', 'mizan'], true)) {
    $rapor = 'perakende';
}

// Tarih: pratik seçenek veya özel aralık (varsayılan: bugün)
$today = date('Y-m-d');
$periyot = isset($_GET['periyot']) ? $_GET['periyot'] : '';
if ($periyot === '' && !isset($_GET['baslangic']) && !isset($_GET['bitis'])) {
    $periyot = 'bugun';
}
if ($periyot === 'dun') {
    $baslangic = $bitis = date('Y-m-d', strtotime('-1 day'));
} elseif ($periyot === 'bugun') {
    $baslangic = $bitis = $today;
} elseif ($periyot === 'gecen_hafta') {
    $baslangic = date('Y-m-d', strtotime('monday last week'));
    $bitis = date('Y-m-d', strtotime('sunday last week'));
} elseif ($periyot === 'bu_hafta') {
    $baslangic = date('Y-m-d', strtotime('monday this week'));
    $bitis = $today;
} elseif ($periyot === 'gecen_ay') {
    $baslangic = date('Y-m-01', strtotime('first day of last month'));
    $bitis = date('Y-m-t', strtotime('last day of last month'));
} elseif ($periyot === 'bu_ay') {
    $baslangic = date('Y-m-01');
    $bitis = $today;
} elseif ($periyot === 'gecen_yil') {
    $baslangic = date('Y-01-01', strtotime('first day of last year'));
    $bitis = date('Y-12-31', strtotime('last day of last year'));
} elseif ($periyot === 'bu_yil') {
    $baslangic = date('Y-01-01');
    $bitis = $today;
} else {
    $baslangic = isset($_GET['baslangic']) ? $_GET['baslangic'] : date('Y-m-01');
    $bitis     = isset($_GET['bitis'])     ? $_GET['bitis']     : $today;
}
$musteri   = isset($_GET['musteri'])   ? trim($_GET['musteri']) : '';
$plaka     = isset($_GET['plaka'])     ? trim($_GET['plaka']) : '';
$cikis_firma = isset($_GET['cikis_firma']) ? (int)$_GET['cikis_firma'] : 0; // cikisFirmaID (Cari.CariID)
$hareket   = isset($_GET['hareket']) ? trim($_GET['hareket']) : ''; // '' | satis | tahsilat
if (!in_array($hareket, ['', 'satis', 'tahsilat'], true)) {
    $hareket = '';
}
$cari_firma = isset($_GET['cari_firma']) ? trim($_GET['cari_firma']) : '';
$ozet_bakiyesiz = isset($_GET['ozet_bakiyesiz']) ? (int)$_GET['ozet_bakiyesiz'] : 0; // 0 = bakiyesizleri gizle (varsayılan), 1 = göster
$malzeme_bos_gizle = isset($_GET['malzeme_bos_gizle']) ? (int)$_GET['malzeme_bos_gizle'] : 0; // 0 = dönemde satışı olmayan malzemeyi gizle, 1 = tümünü göster

$tarihBas = str_replace('-', '', $baslangic) . '000000';
$tarihBit = str_replace('-', '', $bitis) . '999999';
// Cari devir: seçilen başlangıçtan önceki tüm hareketlerin bakiyesi
$tarihDevirBit = str_replace('-', '', $baslangic) . '000000';

/** SahadanSatis tarih/saat */
function formatSahaTarih($tarih) {
    if ($tarih === null || $tarih === '') return '-';
    $s = (string)$tarih;
    if (strlen($s) >= 8) return substr($s, 0, 4) . '-' . substr($s, 4, 2) . '-' . substr($s, 6, 2);
    return $s;
}
function formatSahaZaman($zamanDamgasi) {
    if ($zamanDamgasi === null || $zamanDamgasi === '') return '-';
    $s = (string)$zamanDamgasi;
    if (strlen($s) >= 14) return substr($s, 8, 2) . ':' . substr($s, 10, 2) . ':' . substr($s, 12, 2);
    return $s;
}

$liste = [];
$toplamNetKg = 0;
$toplamGenel = 0;
$toplamSatisPerakende = 0;
$toplamTahsilatPerakende = 0;
$ozetListe = [];
$ozetMalzemeListe = [];
$cariListe = [];
$cariDevir = null;
$musteriListesi = [];
$cikisFirmaListesi = []; // [cikisFirmaID => FirmaAdi]
$kantarTakipOzet = []; // müşteri bazlı özet (kantar_takip raporu)
$mizanListe = []; // mizan: devir, dönem satış/tahsilat, bakiye
$cikisFirmaAdi = '';
$raporDbHata = null;

if ($pdoReport) {
    try {
        // Müşteri listesi (cari ekstre / perakende dropdown)
        $musteriStmt = $pdoReport->query("SELECT DISTINCT FirmaAdi FROM SahadanSatis WHERE status = 1 AND FirmaAdi IS NOT NULL AND FirmaAdi != '' ORDER BY FirmaAdi");
        $musteriListesi = $musteriStmt->fetchAll(PDO::FETCH_COLUMN);

        // Çıkış firması: aynı Cari tablosu (Tur = Grup Firma), cikisFirmaID = Cari.CariID
        $cikisStmt = $pdoReport->query("
            SELECT c.CariID AS id,
                   COALESCE(NULLIF(TRIM(c.FirmaAdi), ''), CONCAT('ID ', c.CariID)) AS adi
            FROM Cari c
            WHERE c.status = 1
              AND c.CariID > 0
              AND c.Tur = 'Grup Firma'
              AND c.FirmaAdi IS NOT NULL AND TRIM(c.FirmaAdi) != ''
            ORDER BY adi
        ");
        foreach ($cikisStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cid = (int)$row['id'];
            if (!isset($cikisFirmaListesi[$cid])) {
                $cikisFirmaListesi[$cid] = $row['adi'];
            }
        }
        // SahadanSatis'te geçen ama listede olmayanları da ekle
        $eksikStmt = $pdoReport->query("
            SELECT DISTINCT s.cikisFirmaID AS id,
                   COALESCE(NULLIF(TRIM(c.FirmaAdi), ''), CONCAT('ID ', s.cikisFirmaID)) AS adi
            FROM SahadanSatis s
            LEFT JOIN Cari c ON c.CariID = s.cikisFirmaID
            WHERE s.status = 1 AND s.cikisFirmaID > 0
        ");
        foreach ($eksikStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cid = (int)$row['id'];
            if (!isset($cikisFirmaListesi[$cid])) {
                $cikisFirmaListesi[$cid] = $row['adi'];
            }
        }
        asort($cikisFirmaListesi, SORT_STRING | SORT_FLAG_CASE);

        // Kantar Takip / Mizan: varsayılan çıkış firması = "KANTAR TAKİP"
        if (($rapor === 'kantar_takip' || $rapor === 'mizan') && $cikis_firma <= 0) {
            foreach ($cikisFirmaListesi as $cid => $adi) {
                if (mb_strtoupper(trim($adi), 'UTF-8') === 'KANTAR TAKİP') {
                    $cikis_firma = (int)$cid;
                    break;
                }
            }
            if ($cikis_firma <= 0 && !empty($cikisFirmaListesi)) {
                // isimde "KANTAR" geçen ilk kayıt
                foreach ($cikisFirmaListesi as $cid => $adi) {
                    if (stripos($adi, 'KANTAR') !== false) {
                        $cikis_firma = (int)$cid;
                        break;
                    }
                }
            }
        }
        if ($cikis_firma > 0 && isset($cikisFirmaListesi[$cikis_firma])) {
            $cikisFirmaAdi = $cikisFirmaListesi[$cikis_firma];
        }

        if ($rapor === 'perakende') {
            $sql = "SELECT id, FirmaAdi, plaka, dokumTipi, dokumNetKg, brimFiyat, dokumTutar, kdv, genelTutar,
                           irsaliyeNo, irsaliyeSeri, islemTipi, tarih, islemZamanDamgasi, cikisFirmaID
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
                // Tahsilat kayıtlarında cikisFirmaID genelde 0; çıkış filtresinde GELİR TAHSİLAT her zaman dahil
                $sql .= " AND (cikisFirmaID = ? OR islemTipi = 'GELİR TAHSİLAT')";
                $params[] = $cikis_firma;
            }
            $sql .= " ORDER BY tarih DESC, islemZamanDamgasi DESC";
            $stmt = $pdoReport->prepare($sql);
            $stmt->execute($params);
            $liste = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($liste as $r) {
                $gt = (float)($r['genelTutar'] ?? 0);
                if (isset($r['islemTipi']) && $r['islemTipi'] === 'GELİR TAHSİLAT') {
                    $toplamTahsilatPerakende += $gt;
                } else {
                    $toplamSatisPerakende += $gt;
                }
                $toplamNetKg += (float)($r['dokumNetKg'] ?? 0);
                $toplamGenel += $gt;
            }
        } elseif ($rapor === 'kantar_takip' && $cikis_firma > 0) {
            // Hareket listesi (çıkış firmasına göre)
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
            foreach ($liste as $r) {
                $gt = (float)($r['genelTutar'] ?? 0);
                if (isset($r['islemTipi']) && $r['islemTipi'] === 'GELİR TAHSİLAT') {
                    $toplamTahsilatPerakende += $gt;
                } else {
                    // Sadece seçili çıkış firmasının satışları
                    if ((int)($r['cikisFirmaID'] ?? 0) === $cikis_firma) {
                        $toplamSatisPerakende += $gt;
                        $toplamNetKg += (float)($r['dokumNetKg'] ?? 0);
                    }
                    $toplamGenel += $gt;
                }
                if (isset($r['islemTipi']) && $r['islemTipi'] === 'GELİR TAHSİLAT') {
                    $toplamGenel += $gt;
                }
            }

            // Müşteri bazlı özet: satış = bu çıkış firması; tahsilat = müşterinin tüm tahsilatı
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
            $kantarTakipOzet = $stmtOzet->fetchAll(PDO::FETCH_ASSOC);
            foreach ($kantarTakipOzet as &$o) {
                $o['bakiye'] = (float)($o['genel_satis'] ?? 0) + (float)($o['genel_tahsilat'] ?? 0);
            }
            unset($o);
            // Dönemde bu çıkış firmasında satışı veya tahsilatı olanlar
            $kantarTakipOzet = array_values(array_filter($kantarTakipOzet, function ($o) {
                return (float)$o['toplam_satis'] != 0 || (float)$o['toplam_tahsilat'] != 0;
            }));
        } elseif ($rapor === 'mizan' && $cikis_firma > 0) {
            // Mizan: Devir | Dönem Satış (aldığı) | Dönem Tahsilat (ödediği) | Kümülatif Bakiye
            // Satış: cikisFirmaID; Tahsilat: her zaman (cikisFirmaID genelde 0)
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
                $devir = (float)($m['devir'] ?? 0);
                $ds = (float)($m['donem_satis'] ?? 0);
                $dt = (float)($m['donem_tahsilat'] ?? 0);
                $m['bakiye'] = $devir + $ds + $dt;
            }
            unset($m);
            // Sıfır satırları gizle
            $mizanListe = array_values(array_filter($mizanListe, function ($m) {
                return abs((float)$m['devir']) > 0.0001
                    || abs((float)$m['donem_satis']) > 0.0001
                    || abs((float)$m['donem_tahsilat']) > 0.0001
                    || abs((float)$m['bakiye']) > 0.0001;
            }));
        } elseif ($rapor === 'ozet') {
            // Müşteri özeti — cikis_firma / musteri / hareket filtreleri
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
                // Sadece bu çıkış firmasından satışı olan müşteriler (+ onların tahsilatları)
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
        } elseif ($rapor === 'ozet_malzeme') {
            // Malzeme özeti — cikis_firma / musteri / hareket (tahsilat seçilirse boş)
            $sql = "SELECT COALESCE(NULLIF(TRIM(dokumTipi),''), '-') AS malzeme,
                    SUM(CASE WHEN islemTipi = 'GELİR TAHAKKUK' AND tarih >= ? AND tarih <= ? THEN COALESCE(dokumNetKg,0) ELSE 0 END) AS donem_net_kg,
                    SUM(CASE WHEN islemTipi = 'GELİR TAHAKKUK' AND tarih >= ? AND tarih <= ? THEN COALESCE(genelTutar,0) ELSE 0 END) AS donem_tutar
                    FROM SahadanSatis
                    WHERE status = 1 AND tarih BETWEEN ? AND ?";
            $params = [$tarihBas, $tarihBit, $tarihBas, $tarihBit, $tarihBas, $tarihBit];
            if ($hareket === 'tahsilat') {
                // Malzeme özeti satış bazlı; tahsilat seçilince sonuç yok
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
        } elseif ($rapor === 'cari' && $cari_firma !== '') {
            // Cari ekstre — cikis_firma / hareket filtreleri
            $devirSql = "SELECT SUM(COALESCE(genelTutar,0)) AS devir FROM SahadanSatis WHERE status = 1 AND FirmaAdi = ? AND tarih < ?";
            $devirParams = [$cari_firma, $tarihDevirBit];
            if ($hareket === 'satis') {
                $devirSql .= " AND islemTipi = 'GELİR TAHAKKUK'";
                if ($cikis_firma > 0) {
                    $devirSql .= " AND cikisFirmaID = ?";
                    $devirParams[] = $cikis_firma;
                }
            } elseif ($hareket === 'tahsilat') {
                $devirSql .= " AND islemTipi = 'GELİR TAHSİLAT'";
            } elseif ($cikis_firma > 0) {
                $devirSql .= " AND (cikisFirmaID = ? OR islemTipi = 'GELİR TAHSİLAT')";
                $devirParams[] = $cikis_firma;
            }
            $devirStmt = $pdoReport->prepare($devirSql);
            $devirStmt->execute($devirParams);
            $cariDevir = (float)$devirStmt->fetchColumn();

            $sql = "SELECT id, FirmaAdi, tarih, islemZamanDamgasi, islemTipi, dokumTipi, irsaliyeSeri, irsaliyeNo, genelTutar, personelAd, cikisFirmaID
                    FROM SahadanSatis
                    WHERE status = 1 AND FirmaAdi = ? AND tarih BETWEEN ? AND ?";
            $params = [$cari_firma, $tarihBas, $tarihBit];
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
            $sql .= " ORDER BY tarih ASC, islemZamanDamgasi ASC";
            $stmt = $pdoReport->prepare($sql);
            $stmt->execute($params);
            $cariListe = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $bakiye = $cariDevir;
            foreach ($cariListe as &$row) {
                $tutar = (float)($row['genelTutar'] ?? 0);
                $bakiye += $tutar;
                $row['kumulatif_bakiye'] = $bakiye;
            }
            unset($row);
        }
    } catch (PDOException $e) {
        $raporDbHata = $e->getMessage();
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-truck"></i> Kantar Raporları</h4>
</div>

<?php if (!$pdoReport): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i> Raporlama veritabanı bağlantısı yok. Lütfen .env içinde <code>DB_REPORT_*</code> ayarlarını kontrol edin.
    </div>
<?php else: ?>

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item">
            <a class="nav-link <?php echo $rapor === 'perakende' ? 'active' : ''; ?>" href="?rapor=perakende&baslangic=<?php echo urlencode($baslangic); ?>&bitis=<?php echo urlencode($bitis); ?>&musteri=<?php echo urlencode($musteri); ?>&plaka=<?php echo urlencode($plaka); ?>&cikis_firma=<?php echo (int)$cikis_firma; ?>&hareket=<?php echo urlencode($hareket); ?>"><i class="bi bi-cart-check"></i> Perakende Satış</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $rapor === 'kantar_takip' ? 'active' : ''; ?>" href="?rapor=kantar_takip&baslangic=<?php echo urlencode($baslangic); ?>&bitis=<?php echo urlencode($bitis); ?>&cikis_firma=<?php echo (int)$cikis_firma; ?>&hareket=<?php echo urlencode($hareket); ?>"><i class="bi bi-cash-coin"></i> Kantar Takip</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $rapor === 'mizan' ? 'active' : ''; ?>" href="?rapor=mizan&baslangic=<?php echo urlencode($baslangic); ?>&bitis=<?php echo urlencode($bitis); ?>&cikis_firma=<?php echo (int)$cikis_firma; ?>"><i class="bi bi-balance-scale"></i> Mizan</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $rapor === 'ozet' ? 'active' : ''; ?>" href="?rapor=ozet&baslangic=<?php echo urlencode($baslangic); ?>&bitis=<?php echo urlencode($bitis); ?>&ozet_bakiyesiz=<?php echo (int)$ozet_bakiyesiz; ?>&cikis_firma=<?php echo (int)$cikis_firma; ?>&musteri=<?php echo urlencode($musteri); ?>&hareket=<?php echo urlencode($hareket); ?>"><i class="bi bi-pie-chart"></i> Özet Rapor</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $rapor === 'ozet_malzeme' ? 'active' : ''; ?>" href="?rapor=ozet_malzeme&baslangic=<?php echo urlencode($baslangic); ?>&bitis=<?php echo urlencode($bitis); ?>&malzeme_bos_gizle=<?php echo (int)$malzeme_bos_gizle; ?>&cikis_firma=<?php echo (int)$cikis_firma; ?>&musteri=<?php echo urlencode($musteri); ?>&hareket=<?php echo urlencode($hareket); ?>"><i class="bi bi-box-seam"></i> Özet Malzeme Satış</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $rapor === 'cari' ? 'active' : ''; ?>" href="?rapor=cari&baslangic=<?php echo urlencode($baslangic); ?>&bitis=<?php echo urlencode($bitis); ?>&cari_firma=<?php echo urlencode($cari_firma); ?>&cikis_firma=<?php echo (int)$cikis_firma; ?>&hareket=<?php echo urlencode($hareket); ?>"><i class="bi bi-journal-bookmark"></i> Cari Ekstre</a>
        </li>
    </ul>

    <!-- Tarih / periyot seçimi (ortak) -->
    <?php
    $periyotSuffix = ($rapor === 'cari' && $cari_firma !== '') ? '&cari_firma=' . urlencode($cari_firma) : '';
    if (in_array($rapor, ['perakende', 'kantar_takip', 'mizan', 'ozet', 'ozet_malzeme', 'cari'], true)) {
        if ($cikis_firma > 0) $periyotSuffix .= '&cikis_firma=' . (int)$cikis_firma;
        if ($musteri !== '' && $rapor !== 'cari') $periyotSuffix .= '&musteri=' . urlencode($musteri);
        if ($hareket !== '' && $rapor !== 'mizan') $periyotSuffix .= '&hareket=' . urlencode($hareket);
    }
    if ($rapor === 'perakende' && $plaka !== '') $periyotSuffix .= '&plaka=' . urlencode($plaka);
    if ($rapor === 'ozet') $periyotSuffix .= '&ozet_bakiyesiz=' . (int)$ozet_bakiyesiz;
    if ($rapor === 'ozet_malzeme') $periyotSuffix .= '&malzeme_bos_gizle=' . (int)$malzeme_bos_gizle;
    $periyotlar = [
        'dun' => 'Dün', 'bugun' => 'Bugün',
        'gecen_hafta' => 'Geçen Hafta', 'bu_hafta' => 'Bu Hafta',
        'gecen_ay' => 'Geçen Ay', 'bu_ay' => 'Bu Ay',
        'gecen_yil' => 'Geçen Yıl', 'bu_yil' => 'Bu Yıl',
    ];
    ?>
    <div class="d-flex flex-wrap align-items-center gap-1 mb-3" style="font-size:.75rem">
        <?php foreach ($periyotlar as $p => $label): ?>
        <a href="?rapor=<?php echo $rapor; ?>&periyot=<?php echo $p; ?><?php echo $periyotSuffix; ?>" class="btn <?php echo $periyot === $p ? 'btn-dark' : 'btn-outline-secondary'; ?>" style="padding:.2rem .5rem;font-size:.75rem;line-height:1.3"><?php echo $label; ?></a>
        <?php endforeach; ?>
        <span class="text-muted mx-1">|</span>
        <form method="get" class="d-flex align-items-center gap-1 flex-wrap">
            <input type="hidden" name="rapor" value="<?php echo htmlspecialchars($rapor); ?>">
            <?php if ($rapor === 'ozet'): ?>
            <input type="hidden" name="ozet_bakiyesiz" value="<?php echo (int)$ozet_bakiyesiz; ?>">
            <?php endif; ?>
            <?php if ($rapor === 'ozet_malzeme'): ?>
            <input type="hidden" name="malzeme_bos_gizle" value="<?php echo (int)$malzeme_bos_gizle; ?>">
            <?php endif; ?>
            <input type="date" name="baslangic" class="form-control form-control-sm" style="width:auto" value="<?php echo htmlspecialchars($baslangic); ?>">
            <span class="text-muted">–</span>
            <input type="date" name="bitis" class="form-control form-control-sm" style="width:auto" value="<?php echo htmlspecialchars($bitis); ?>">
            <select name="cikis_firma" id="cikisFirmaSelect" class="form-select form-select-sm" style="width:220px" title="Çıkış firması (Cari / Grup Firma)">
                <option value="">-- Tüm çıkış firmaları --</option>
                <?php foreach ($cikisFirmaListesi as $cid => $adi): ?>
                    <option value="<?php echo (int)$cid; ?>" <?php echo $cikis_firma === (int)$cid ? 'selected' : ''; ?>><?php echo htmlspecialchars($adi); ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($rapor === 'cari'): ?>
            <select name="cari_firma" id="cariFirmaSelect" class="form-select form-select-sm" style="width:220px">
                <option value="">-- Müşteri seçin --</option>
                <?php foreach ($musteriListesi as $f): ?>
                    <option value="<?php echo htmlspecialchars($f); ?>" <?php echo $cari_firma === $f ? 'selected' : ''; ?>><?php echo htmlspecialchars($f); ?></option>
                <?php endforeach; ?>
            </select>
            <?php else: ?>
            <select name="musteri" id="musteriSelect" class="form-select form-select-sm" style="width:220px" title="Müşteri (Cari)">
                <option value="">-- Tüm müşteriler --</option>
                <?php foreach ($musteriListesi as $f): ?>
                    <option value="<?php echo htmlspecialchars($f); ?>" <?php echo $musteri === $f ? 'selected' : ''; ?>><?php echo htmlspecialchars($f); ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <?php if ($rapor === 'perakende'): ?>
            <input type="text" name="plaka" class="form-control form-control-sm" style="width:120px" value="<?php echo htmlspecialchars($plaka); ?>" placeholder="Plaka">
            <?php endif; ?>
            <?php if ($rapor !== 'mizan'): ?>
            <select name="hareket" class="form-select form-select-sm" style="width:130px" title="Hareket / İşlem türü">
                <option value="">-- Tüm hareketler --</option>
                <option value="satis" <?php echo $hareket === 'satis' ? 'selected' : ''; ?>>Satış</option>
                <option value="tahsilat" <?php echo $hareket === 'tahsilat' ? 'selected' : ''; ?>>Tahsilat</option>
            </select>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i></button>
            <?php if ($rapor === 'perakende'): ?>
            <a href="kantar_perakende_pdf.php?baslangic=<?php echo urlencode($baslangic); ?>&bitis=<?php echo urlencode($bitis); ?>&musteri=<?php echo urlencode($musteri); ?>&plaka=<?php echo urlencode($plaka); ?>&cikis_firma=<?php echo (int)$cikis_firma; ?>&hareket=<?php echo urlencode($hareket); ?>" target="_blank" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i></a>
            <?php elseif ($rapor === 'kantar_takip'): ?>
            <a href="kantar_takip_pdf.php?baslangic=<?php echo urlencode($baslangic); ?>&bitis=<?php echo urlencode($bitis); ?>&cikis_firma=<?php echo (int)$cikis_firma; ?>&musteri=<?php echo urlencode($musteri); ?>&hareket=<?php echo urlencode($hareket); ?>" target="_blank" class="btn btn-sm btn-outline-danger <?php echo $cikis_firma <= 0 ? 'disabled' : ''; ?>"><i class="bi bi-file-earmark-pdf"></i></a>
            <?php elseif ($rapor === 'mizan'): ?>
            <a href="kantar_mizan_pdf.php?baslangic=<?php echo urlencode($baslangic); ?>&bitis=<?php echo urlencode($bitis); ?>&cikis_firma=<?php echo (int)$cikis_firma; ?>&musteri=<?php echo urlencode($musteri); ?>" target="_blank" class="btn btn-sm btn-outline-danger <?php echo $cikis_firma <= 0 ? 'disabled' : ''; ?>"><i class="bi bi-file-earmark-pdf"></i></a>
            <?php elseif ($rapor === 'ozet'): ?>
            <a href="kantar_ozet_pdf.php?baslangic=<?php echo urlencode($baslangic); ?>&bitis=<?php echo urlencode($bitis); ?>&ozet_bakiyesiz=<?php echo (int)$ozet_bakiyesiz; ?>&cikis_firma=<?php echo (int)$cikis_firma; ?>&musteri=<?php echo urlencode($musteri); ?>&hareket=<?php echo urlencode($hareket); ?>" target="_blank" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i></a>
            <?php elseif ($rapor === 'ozet_malzeme'): ?>
            <a href="kantar_ozet_malzeme_pdf.php?baslangic=<?php echo urlencode($baslangic); ?>&bitis=<?php echo urlencode($bitis); ?>&malzeme_bos_gizle=<?php echo (int)$malzeme_bos_gizle; ?>&cikis_firma=<?php echo (int)$cikis_firma; ?>&musteri=<?php echo urlencode($musteri); ?>&hareket=<?php echo urlencode($hareket); ?>" target="_blank" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i></a>
            <?php elseif ($rapor === 'cari'): ?>
            <a href="kantar_cari_ekstre_pdf.php?cari_firma=<?php echo urlencode($cari_firma); ?>&baslangic=<?php echo urlencode($baslangic); ?>&bitis=<?php echo urlencode($bitis); ?>&cikis_firma=<?php echo (int)$cikis_firma; ?>&hareket=<?php echo urlencode($hareket); ?>" target="_blank" class="btn btn-sm btn-outline-danger <?php echo $cari_firma === '' ? 'disabled' : ''; ?>"><i class="bi bi-file-earmark-pdf"></i></a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($raporDbHata): ?>
        <div class="alert alert-danger"><i class="bi bi-database-x"></i> <?php echo htmlspecialchars($raporDbHata); ?></div>
    <?php endif; ?>

    <!-- Perakende Satış -->
    <?php if ($rapor === 'perakende' && !$raporDbHata): ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-cart-check"></i> Perakende Satış Listesi</h6>
                <div class="table-responsive">
                    <table class="table table-hover table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Tarih</th><th>Saat</th><th>Müşteri</th><th>Plaka</th><th>Hareket</th><th>Döküm Tipi</th>
                                <th class="text-end">Net (kg)</th><th class="text-end">Birim Fiyat</th><th class="text-end">Tutar</th><th class="text-end">KDV</th><th class="text-end">Tutar (₺)</th><th>İrsaliye</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($liste as $r):
                                $isTahsilat = (isset($r['islemTipi']) && $r['islemTipi'] === 'GELİR TAHSİLAT');
                                $gt = (float)($r['genelTutar'] ?? 0);
                                $dt = (float)($r['dokumTutar'] ?? 0);
                                $kdv = (float)($r['kdv'] ?? 0);
                            ?>
                                <tr>
                                    <td><?php echo formatSahaTarih($r['tarih'] ?? null); ?></td>
                                    <td><?php echo formatSahaZaman($r['islemZamanDamgasi'] ?? null); ?></td>
                                    <td><?php echo htmlspecialchars($r['FirmaAdi'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($r['plaka'] ?? '-'); ?></td>
                                    <td><span class="badge <?php echo $isTahsilat ? 'bg-success' : 'bg-primary'; ?>"><?php echo $isTahsilat ? 'Tahsilat' : 'Satış'; ?></span></td>
                                    <td><?php echo htmlspecialchars($r['dokumTipi'] ?? '-'); ?></td>
                                    <td class="text-end"><?php echo number_format((float)($r['dokumNetKg'] ?? 0), 0, ',', '.'); ?></td>
                                    <td class="text-end"><?php echo number_format((float)($r['brimFiyat'] ?? 0), 2, ',', '.'); ?></td>
                                    <td class="text-end"><?php echo number_format($dt, 2, ',', '.'); ?></td>
                                    <td class="text-end"><?php echo number_format($kdv, 2, ',', '.'); ?></td>
                                    <td class="text-end"><?php echo number_format($gt, 2, ',', '.'); ?></td>
                                    <td><?php echo htmlspecialchars(($r['irsaliyeSeri'] ?? '') . ($r['irsaliyeNo'] ?? '')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php if (!empty($liste)): ?>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="6" class="text-end">Özet</th>
                                <th class="text-end"><?php echo number_format($toplamNetKg, 0, ',', '.'); ?></th>
                                <th colspan="2"></th>
                                <th class="text-end">Satış: <?php echo number_format($toplamSatisPerakende, 2, ',', '.'); ?></th>
                                <th class="text-end">Tahsilat: <?php echo number_format($toplamTahsilatPerakende, 2, ',', '.'); ?></th>
                                <th class="text-end">Bakiye: <?php echo number_format(-$toplamSatisPerakende - $toplamTahsilatPerakende, 2, ',', '.'); ?></th>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
                <?php if (empty($liste)): ?><p class="text-muted mb-0">Kayıt yok.</p><?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Kantar Takip (cikisFirmaID bazlı parasal takip) -->
    <?php if ($rapor === 'kantar_takip' && !$raporDbHata): ?>
        <?php if ($cikis_firma <= 0): ?>
        <div class="alert alert-info mb-0"><i class="bi bi-info-circle"></i> Lütfen çıkış firması seçin. (Varsayılan: Kantar Takip)</div>
        <?php else: ?>
        <div class="alert alert-secondary py-2 small mb-3">
            <i class="bi bi-building"></i> Çıkış firması: <strong><?php echo htmlspecialchars($cikisFirmaAdi !== '' ? $cikisFirmaAdi : ('ID ' . $cikis_firma)); ?></strong>
            — Bu raporda malzemenin hangi firmadan çıktığına göre satış ve tahsilat takibi yapılır.
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="card bg-light border-0 h-100">
                    <div class="card-body py-3">
                        <div class="text-muted small">Dönem Satış</div>
                        <div class="fs-5 fw-bold"><?php echo number_format(abs($toplamSatisPerakende), 2, ',', '.'); ?> ₺</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-light border-0 h-100">
                    <div class="card-body py-3">
                        <div class="text-muted small">Dönem Tahsilat</div>
                        <div class="fs-5 fw-bold text-success"><?php echo number_format($toplamTahsilatPerakende, 2, ',', '.'); ?> ₺</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-light border-0 h-100">
                    <div class="card-body py-3">
                        <div class="text-muted small">Dönem Net Tonaj</div>
                        <div class="fs-5 fw-bold"><?php echo number_format($toplamNetKg, 0, ',', '.'); ?> kg</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-people"></i> Müşteri Özeti (Çıkış: <?php echo htmlspecialchars($cikisFirmaAdi !== '' ? $cikisFirmaAdi : (string)$cikis_firma); ?>)</h6>
                <p class="text-muted small mb-2">Dönem satış/tahsilatı olan müşteriler. Bakiye = genel satış + genel tahsilat (eksi = müşteri borçlu).</p>
                <div class="table-responsive">
                    <table class="table table-hover table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Müşteri</th>
                                <th class="text-end">Dönem Satış (₺)</th>
                                <th class="text-end">Dönem Tahsilat (₺)</th>
                                <th class="text-end">Dönem Net (kg)</th>
                                <th class="text-end">Genel Satış (₺)</th>
                                <th class="text-end">Genel Tahsilat (₺)</th>
                                <th class="text-end">Bakiye (₺)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sumSatis = 0; $sumTahsilat = 0; $sumKg = 0; $sumGenSatis = 0; $sumGenTahsilat = 0;
                            foreach ($kantarTakipOzet as $o):
                                $sumSatis += (float)$o['toplam_satis'];
                                $sumTahsilat += (float)$o['toplam_tahsilat'];
                                $sumKg += (float)($o['donem_net_kg'] ?? 0);
                                $sumGenSatis += (float)($o['genel_satis'] ?? 0);
                                $sumGenTahsilat += (float)($o['genel_tahsilat'] ?? 0);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($o['FirmaAdi'] ?? '-'); ?></td>
                                <td class="text-end"><?php echo number_format((float)$o['toplam_satis'], 2, ',', '.'); ?></td>
                                <td class="text-end"><?php echo number_format((float)$o['toplam_tahsilat'], 2, ',', '.'); ?></td>
                                <td class="text-end"><?php echo number_format((float)($o['donem_net_kg'] ?? 0), 0, ',', '.'); ?></td>
                                <td class="text-end"><?php echo number_format((float)($o['genel_satis'] ?? 0), 2, ',', '.'); ?></td>
                                <td class="text-end"><?php echo number_format((float)($o['genel_tahsilat'] ?? 0), 2, ',', '.'); ?></td>
                                <td class="text-end fw-bold <?php echo (float)$o['bakiye'] < 0 ? 'text-danger' : ((float)$o['bakiye'] > 0 ? 'text-success' : ''); ?>"><?php echo number_format((float)$o['bakiye'], 2, ',', '.'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php if (!empty($kantarTakipOzet)): ?>
                        <tfoot class="table-light">
                            <tr>
                                <th>Toplam</th>
                                <th class="text-end"><?php echo number_format($sumSatis, 2, ',', '.'); ?></th>
                                <th class="text-end"><?php echo number_format($sumTahsilat, 2, ',', '.'); ?></th>
                                <th class="text-end"><?php echo number_format($sumKg, 0, ',', '.'); ?></th>
                                <th class="text-end"><?php echo number_format($sumGenSatis, 2, ',', '.'); ?></th>
                                <th class="text-end"><?php echo number_format($sumGenTahsilat, 2, ',', '.'); ?></th>
                                <th class="text-end"><?php echo number_format($sumGenSatis + $sumGenTahsilat, 2, ',', '.'); ?></th>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
                <?php if (empty($kantarTakipOzet)): ?><p class="text-muted mb-0">Seçili dönemde müşteri hareketi yok.</p><?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-list-ul"></i> Hareket Listesi</h6>
                <div class="table-responsive">
                    <table class="table table-hover table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Tarih</th><th>Saat</th><th>Müşteri</th><th>Plaka</th><th>Hareket</th><th>Döküm Tipi</th>
                                <th class="text-end">Net (kg)</th><th class="text-end">Birim Fiyat</th><th class="text-end">Tutar</th><th class="text-end">KDV</th><th class="text-end">Tutar (₺)</th><th>İrsaliye</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($liste as $r):
                                $isTahsilat = (isset($r['islemTipi']) && $r['islemTipi'] === 'GELİR TAHSİLAT');
                                $gt = (float)($r['genelTutar'] ?? 0);
                                $dt = (float)($r['dokumTutar'] ?? 0);
                                $kdv = (float)($r['kdv'] ?? 0);
                            ?>
                                <tr>
                                    <td><?php echo formatSahaTarih($r['tarih'] ?? null); ?></td>
                                    <td><?php echo formatSahaZaman($r['islemZamanDamgasi'] ?? null); ?></td>
                                    <td><?php echo htmlspecialchars($r['FirmaAdi'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($r['plaka'] ?? '-'); ?></td>
                                    <td><span class="badge <?php echo $isTahsilat ? 'bg-success' : 'bg-primary'; ?>"><?php echo $isTahsilat ? 'Tahsilat' : 'Satış'; ?></span></td>
                                    <td><?php echo htmlspecialchars($r['dokumTipi'] ?? '-'); ?></td>
                                    <td class="text-end"><?php echo number_format((float)($r['dokumNetKg'] ?? 0), 0, ',', '.'); ?></td>
                                    <td class="text-end"><?php echo number_format((float)($r['brimFiyat'] ?? 0), 2, ',', '.'); ?></td>
                                    <td class="text-end"><?php echo number_format($dt, 2, ',', '.'); ?></td>
                                    <td class="text-end"><?php echo number_format($kdv, 2, ',', '.'); ?></td>
                                    <td class="text-end"><?php echo number_format($gt, 2, ',', '.'); ?></td>
                                    <td><?php echo htmlspecialchars(($r['irsaliyeSeri'] ?? '') . ($r['irsaliyeNo'] ?? '')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php if (!empty($liste)): ?>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="6" class="text-end">Özet</th>
                                <th class="text-end"><?php echo number_format($toplamNetKg, 0, ',', '.'); ?></th>
                                <th colspan="2"></th>
                                <th class="text-end">Satış: <?php echo number_format($toplamSatisPerakende, 2, ',', '.'); ?></th>
                                <th class="text-end">Tahsilat: <?php echo number_format($toplamTahsilatPerakende, 2, ',', '.'); ?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
                <?php if (empty($liste)): ?><p class="text-muted mb-0">Kayıt yok.</p><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Mizan (devir + dönem satış/tahsilat + kümülatif bakiye) -->
    <?php if ($rapor === 'mizan' && !$raporDbHata): ?>
        <?php if ($cikis_firma <= 0): ?>
        <div class="alert alert-info mb-0"><i class="bi bi-info-circle"></i> Lütfen çıkış firması seçin. (Varsayılan: Kantar Takip)</div>
        <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-balance-scale"></i> Mizan — <?php echo htmlspecialchars($cikisFirmaAdi !== '' ? $cikisFirmaAdi : ('ID ' . $cikis_firma)); ?></h6>
                <p class="text-muted small mb-2">
                    Dönem: <strong><?php echo htmlspecialchars($baslangic); ?></strong> – <strong><?php echo htmlspecialchars($bitis); ?></strong>.
                    <strong>Devir:</strong> dönem başı bakiyesi ·
                    <strong>Aldığı:</strong> dönem satış (TAHAKKUK) ·
                    <strong>Ödediği:</strong> dönem tahsilat ·
                    <strong>Bakiye:</strong> Devir + Aldığı + Ödediği (eksi = müşteri borçlu).
                    Tahsilatlar çıkış firması filtresinden bağımsız olarak dahildir.
                </p>
                <div class="table-responsive">
                    <table class="table table-hover table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Müşteri (Firma)</th>
                                <th class="text-end">Devir (₺)</th>
                                <th class="text-end">Aldığı / Satış (₺)</th>
                                <th class="text-end">Ödediği / Tahsilat (₺)</th>
                                <th class="text-end">Dönem Net (kg)</th>
                                <th class="text-end">Kümülatif Bakiye (₺)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sumDevir = 0; $sumAldi = 0; $sumOdedi = 0; $sumKg = 0; $sumBakiye = 0;
                            foreach ($mizanListe as $m):
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
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($m['FirmaAdi'] ?? '-'); ?></td>
                                <td class="text-end"><?php echo number_format($devir, 2, ',', '.'); ?></td>
                                <td class="text-end"><?php echo number_format($aldi, 2, ',', '.'); ?></td>
                                <td class="text-end"><?php echo number_format($odedi, 2, ',', '.'); ?></td>
                                <td class="text-end"><?php echo number_format($kg, 0, ',', '.'); ?></td>
                                <td class="text-end fw-bold <?php echo $bakiye < 0 ? 'text-danger' : ($bakiye > 0 ? 'text-success' : ''); ?>"><?php echo number_format($bakiye, 2, ',', '.'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php if (!empty($mizanListe)): ?>
                        <tfoot class="table-light">
                            <tr>
                                <th>Toplam</th>
                                <th class="text-end"><?php echo number_format($sumDevir, 2, ',', '.'); ?></th>
                                <th class="text-end"><?php echo number_format($sumAldi, 2, ',', '.'); ?></th>
                                <th class="text-end"><?php echo number_format($sumOdedi, 2, ',', '.'); ?></th>
                                <th class="text-end"><?php echo number_format($sumKg, 0, ',', '.'); ?></th>
                                <th class="text-end"><?php echo number_format($sumBakiye, 2, ',', '.'); ?></th>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
                <?php if (empty($mizanListe)): ?><p class="text-muted mb-0">Seçili filtrelerde mizan kaydı yok.</p><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Özet Rapor -->
    <?php if ($rapor === 'ozet' && !$raporDbHata): ?>
        <?php
        $ozetQuery = [
            'rapor' => 'ozet',
            'baslangic' => $baslangic,
            'bitis' => $bitis,
            'cikis_firma' => (int)$cikis_firma,
            'musteri' => $musteri,
            'hareket' => $hareket,
        ];
        $ozetUrlGoster = '?' . http_build_query(array_merge($ozetQuery, ['ozet_bakiyesiz' => 1]));
        $ozetUrlGizle  = '?' . http_build_query(array_merge($ozetQuery, ['ozet_bakiyesiz' => 0]));
        $ozetFiltreMetin = [];
        if ($cikis_firma > 0 && $cikisFirmaAdi !== '') $ozetFiltreMetin[] = 'Çıkış: ' . $cikisFirmaAdi;
        if ($musteri !== '') $ozetFiltreMetin[] = 'Müşteri: ' . $musteri;
        if ($hareket === 'satis') $ozetFiltreMetin[] = 'Hareket: Satış';
        if ($hareket === 'tahsilat') $ozetFiltreMetin[] = 'Hareket: Tahsilat';
        ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <h6 class="card-title mb-0"><i class="bi bi-pie-chart"></i> Müşteri Özeti (Toplam Satış, Tahsilat, Bakiye)</h6>
                    <span class="text-muted small">Bakiyesizleri:</span>
                    <?php if ($ozet_bakiyesiz === 1): ?>
                        <a href="<?php echo htmlspecialchars($ozetUrlGizle); ?>" class="btn btn-sm btn-outline-secondary">Gizle</a>
                        <span class="btn btn-sm btn-primary">Göster</span>
                    <?php else: ?>
                        <span class="btn btn-sm btn-primary">Gizle</span>
                        <a href="<?php echo htmlspecialchars($ozetUrlGoster); ?>" class="btn btn-sm btn-outline-secondary">Göster</a>
                    <?php endif; ?>
                </div>
                <?php if (!empty($ozetFiltreMetin)): ?>
                <p class="small mb-1"><span class="badge bg-info text-dark"><?php echo htmlspecialchars(implode(' | ', $ozetFiltreMetin)); ?></span></p>
                <?php endif; ?>
                <p class="text-muted small">İlk iki sütun: seçili tarih aralığı (<?php echo htmlspecialchars($baslangic); ?> – <?php echo htmlspecialchars($bitis); ?>). Genel T.: baştan seçili son tarihe kadar. Bakiye: genel bakiye (eksi = müşteri borçlu). <?php if ($ozet_bakiyesiz === 0): ?>Seçili dönemde satış/tahsilatı olmayan müşteriler gizlenmiştir.<?php endif; ?></p>
                <div class="table-responsive">
                    <table class="table table-hover table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Müşteri (Firma)</th>
                                <th class="text-end">Toplam Satış (₺)</th>
                                <th class="text-end">Toplam Tahsilat (₺)</th>
                                <th class="text-end">Genel T. Satış (₺)</th>
                                <th class="text-end">Genel T. Tahsilat (₺)</th>
                                <th class="text-end">Bakiye (₺)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sumToplamSatis = 0;
                            $sumToplamTahsilat = 0;
                            $sumGenelSatis = 0;
                            $sumGenelTahsilat = 0;
                            foreach ($ozetListe as $o):
                                $sumToplamSatis += (float)$o['toplam_satis'];
                                $sumToplamTahsilat += (float)$o['toplam_tahsilat'];
                                $sumGenelSatis += (float)($o['genel_satis'] ?? 0);
                                $sumGenelTahsilat += (float)($o['genel_tahsilat'] ?? 0);
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($o['FirmaAdi'] ?? '-'); ?></td>
                                    <td class="text-end"><?php echo number_format((float)$o['toplam_satis'], 2, ',', '.'); ?></td>
                                    <td class="text-end"><?php echo number_format((float)$o['toplam_tahsilat'], 2, ',', '.'); ?></td>
                                    <td class="text-end"><?php echo number_format((float)($o['genel_satis'] ?? 0), 2, ',', '.'); ?></td>
                                    <td class="text-end"><?php echo number_format((float)($o['genel_tahsilat'] ?? 0), 2, ',', '.'); ?></td>
                                    <td class="text-end fw-bold <?php echo (float)$o['bakiye'] < 0 ? 'text-danger' : ((float)$o['bakiye'] > 0 ? 'text-success' : ''); ?>"><?php echo number_format((float)$o['bakiye'], 2, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php if (!empty($ozetListe)): ?>
                        <tfoot class="table-light">
                            <tr>
                                <th>Toplam</th>
                                <th class="text-end"><?php echo number_format($sumToplamSatis, 2, ',', '.'); ?></th>
                                <th class="text-end"><?php echo number_format($sumToplamTahsilat, 2, ',', '.'); ?></th>
                                <th class="text-end"><?php echo number_format($sumGenelSatis, 2, ',', '.'); ?></th>
                                <th class="text-end"><?php echo number_format($sumGenelTahsilat, 2, ',', '.'); ?></th>
                                <th class="text-end"><?php echo number_format($sumGenelSatis + $sumGenelTahsilat, 2, ',', '.'); ?></th>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
                <?php if (empty($ozetListe)): ?><p class="text-muted mb-0">Kayıt yok.</p><?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Özet Malzeme Satış -->
    <?php if ($rapor === 'ozet_malzeme' && !$raporDbHata): ?>
        <?php
        $malzemeQuery = [
            'rapor' => 'ozet_malzeme',
            'baslangic' => $baslangic,
            'bitis' => $bitis,
            'cikis_firma' => (int)$cikis_firma,
            'musteri' => $musteri,
            'hareket' => $hareket,
        ];
        $malzemeUrlGoster = '?' . http_build_query(array_merge($malzemeQuery, ['malzeme_bos_gizle' => 1]));
        $malzemeUrlGizle  = '?' . http_build_query(array_merge($malzemeQuery, ['malzeme_bos_gizle' => 0]));
        $malzemeFiltreMetin = [];
        if ($cikis_firma > 0 && $cikisFirmaAdi !== '') $malzemeFiltreMetin[] = 'Çıkış: ' . $cikisFirmaAdi;
        if ($musteri !== '') $malzemeFiltreMetin[] = 'Müşteri: ' . $musteri;
        if ($hareket === 'satis') $malzemeFiltreMetin[] = 'Hareket: Satış';
        if ($hareket === 'tahsilat') $malzemeFiltreMetin[] = 'Hareket: Tahsilat';
        $sumDonemKg = $sumDonemTutar = 0;
        foreach ($ozetMalzemeListe as $m) {
            $sumDonemKg += (float)($m['donem_net_kg'] ?? 0);
            $sumDonemTutar += (float)($m['donem_tutar'] ?? 0);
        }
        foreach ($ozetMalzemeListe as &$m) {
            $m['oran'] = $sumDonemKg > 0 ? ((float)($m['donem_net_kg'] ?? 0) / $sumDonemKg) * 100 : 0;
        }
        unset($m);
        ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <h6 class="card-title mb-0"><i class="bi bi-box-seam"></i> Özet Malzeme Satış (Döküm Tipi Bazında)</h6>
                    <span class="text-muted small">Dönemde satışı olmayanlar:</span>
                    <?php if ($malzeme_bos_gizle === 1): ?>
                        <a href="<?php echo htmlspecialchars($malzemeUrlGizle); ?>" class="btn btn-sm btn-outline-secondary">Gizle</a>
                        <span class="btn btn-sm btn-primary">Göster</span>
                    <?php else: ?>
                        <span class="btn btn-sm btn-primary">Gizle</span>
                        <a href="<?php echo htmlspecialchars($malzemeUrlGoster); ?>" class="btn btn-sm btn-outline-secondary">Göster</a>
                    <?php endif; ?>
                </div>
                <?php if (!empty($malzemeFiltreMetin)): ?>
                <p class="small mb-1"><span class="badge bg-info text-dark"><?php echo htmlspecialchars(implode(' | ', $malzemeFiltreMetin)); ?></span></p>
                <?php endif; ?>
                <p class="text-muted small">Sadece satış (TAHAKKUK) hareketleri. Dönem: <?php echo htmlspecialchars($baslangic); ?> – <?php echo htmlspecialchars($bitis); ?>. Oran: seçili dönemdeki toplam miktara (kg) göre pay. <?php if ($malzeme_bos_gizle === 0): ?>Seçili dönemde satışı olmayan malzemeler gizlenmiştir.<?php endif; ?></p>
                <div class="table-responsive">
                    <table class="table table-hover table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Malzeme (Döküm Tipi)</th>
                                <th class="text-end">Dönem Net (kg)</th>
                                <th class="text-end">Dönem Tutar (₺)</th>
                                <th class="text-end">Oran (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ozetMalzemeListe as $m): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($m['malzeme'] ?? '-'); ?></td>
                                    <td class="text-end"><?php echo number_format((float)($m['donem_net_kg'] ?? 0), 0, ',', '.'); ?></td>
                                    <td class="text-end"><?php echo number_format((float)($m['donem_tutar'] ?? 0), 2, ',', '.'); ?></td>
                                    <td class="text-end"><?php echo number_format((float)($m['oran'] ?? 0), 1, ',', '.'); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php if (!empty($ozetMalzemeListe)): ?>
                        <tfoot class="table-light">
                            <tr>
                                <th>Toplam</th>
                                <th class="text-end"><?php echo number_format($sumDonemKg, 0, ',', '.'); ?></th>
                                <th class="text-end"><?php echo number_format($sumDonemTutar, 2, ',', '.'); ?></th>
                                <th class="text-end">100%</th>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
                <?php if (empty($ozetMalzemeListe)): ?><p class="text-muted mb-0">Kayıt yok.</p><?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Cari Ekstre -->
    <?php if ($rapor === 'cari' && !$raporDbHata): ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-journal-bookmark"></i> Müşteri Cari Ekstre</h6>
                <?php if ($cari_firma === ''): ?>
                    <p class="text-muted mb-0">Cari ekstre için yukarıdan müşteri seçip "Uygula" deyin.</p>
                <?php else: ?>
                    <p class="text-muted small">Müşteri: <strong><?php echo htmlspecialchars($cari_firma); ?></strong> | Dönem: <?php echo htmlspecialchars($baslangic); ?> – <?php echo htmlspecialchars($bitis); ?>
                        <a href="kantar_cari_ekstre_pdf.php?cari_firma=<?php echo urlencode($cari_firma); ?>&baslangic=<?php echo urlencode($baslangic); ?>&bitis=<?php echo urlencode($bitis); ?>" target="_blank" class="btn btn-sm btn-outline-danger ms-2"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                    </p>
                    <p class="text-muted small mb-2">Eksi = müşteri borçlu. Satış bakiyeyi eksiye götürür, tahsilat azaltır.</p>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Tarih</th>
                                    <th>Saat</th>
                                    <th>Hareket</th>
                                    <th>Açıklama (Döküm tipi / Tahsilat türü)</th>
                                    <th>İrsaliye</th>
                                    <th class="text-end">Tutar (₺)</th>
                                    <th class="text-end">Kümülatif Bakiye (₺)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="table-light">
                                    <td>—</td>
                                    <td>—</td>
                                    <td><span class="badge bg-secondary">Devir</span></td>
                                    <td>Önceki dönem bakiyesi (<?php echo $baslangic; ?> öncesi)</td>
                                    <td>—</td>
                                    <td class="text-end">—</td>
                                    <td class="text-end fw-bold"><?php echo number_format($cariDevir, 2, ',', '.'); ?></td>
                                </tr>
                                <?php foreach ($cariListe as $r):
                                    $isTahsilatCari = (isset($r['islemTipi']) && $r['islemTipi'] === 'GELİR TAHSİLAT');
                                    $tutarCari = (float)($r['genelTutar'] ?? 0);
                                ?>
                                    <tr>
                                        <td><?php echo formatSahaTarih($r['tarih'] ?? null); ?></td>
                                        <td><?php echo formatSahaZaman($r['islemZamanDamgasi'] ?? null); ?></td>
                                        <td><span class="badge <?php echo $isTahsilatCari ? 'bg-success' : 'bg-primary'; ?>"><?php echo $isTahsilatCari ? 'Tahsilat' : 'Satış'; ?></span></td>
                                        <td><?php echo htmlspecialchars($r['dokumTipi'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars(($r['irsaliyeSeri'] ?? '') . ($r['irsaliyeNo'] ?? '')); ?></td>
                                        <td class="text-end <?php echo $tutarCari < 0 ? 'text-primary' : 'text-success'; ?>"><?php echo number_format($tutarCari, 2, ',', '.'); ?></td>
                                        <td class="text-end fw-bold"><?php echo number_format((float)($r['kumulatif_bakiye'] ?? 0), 2, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php
                    $kapanis = $cariDevir;
                    foreach ($cariListe as $r) { $kapanis += (float)($r['genelTutar'] ?? 0); }
                    ?>
                    <div class="mt-2 p-2 bg-light rounded">
                        <strong>Kapanış bakiyesi:</strong> <span class="text-end"><?php echo number_format($kapanis, 2, ',', '.'); ?> ₺</span>
                        <span class="text-muted small">(Eksi = müşteri borçlu)</span>
                    </div>
                    <?php if (empty($cariListe)): ?><p class="text-muted small mb-0">Bu dönemde hareket yok.</p><?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
.select2-container--bootstrap-5 .select2-selection { min-height: 31px; }
.select2-container { min-width: 200px; }
</style>
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery === 'undefined' || !jQuery.fn.select2) return;
    var musteriOpts = {
        theme: 'bootstrap-5',
        placeholder: 'Müşteri ara veya seçin...',
        allowClear: true,
        width: '220px',
        language: {
            noResults: function() { return 'Sonuç bulunamadı'; },
            searching: function() { return 'Aranıyor…'; }
        }
    };
    var cikisOpts = {
        theme: 'bootstrap-5',
        placeholder: 'Çıkış firması ara...',
        allowClear: true,
        width: '220px',
        language: {
            noResults: function() { return 'Sonuç bulunamadı'; },
            searching: function() { return 'Aranıyor…'; }
        }
    };
    if (document.getElementById('cikisFirmaSelect')) {
        jQuery('#cikisFirmaSelect').select2(cikisOpts);
    }
    if (document.getElementById('cariFirmaSelect')) {
        jQuery('#cariFirmaSelect').select2(musteriOpts);
    }
    if (document.getElementById('musteriSelect')) {
        jQuery('#musteriSelect').select2(musteriOpts);
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
