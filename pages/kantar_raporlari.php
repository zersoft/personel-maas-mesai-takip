<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Kantar Raporları';

$rapor = isset($_GET['rapor']) ? $_GET['rapor'] : 'perakende';
if (!in_array($rapor, ['perakende', 'ozet', 'ozet_malzeme', 'cari', 'kantar_takip', 'mizan', 'esnek'], true)) {
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

// Esnek Rapor Parametreleri
$baslik = isset($_GET['baslik']) && trim((string)$_GET['baslik']) !== '' ? trim((string)$_GET['baslik']) : 'Gideceği Yer & Malzeme Bazlı Sevkiyat Raporu';
$grup1  = isset($_GET['grup1']) ? trim((string)$_GET['grup1']) : 'gidecegiYer';
$grup2  = isset($_GET['grup2']) ? trim((string)$_GET['grup2']) : 'dokumTipi';
$gidecegi_yer = isset($_GET['gidecegi_yer']) ? trim((string)$_GET['gidecegi_yer']) : '';
$malzeme = isset($_GET['malzeme']) ? trim((string)$_GET['malzeme']) : '';
$yon = isset($_GET['yon']) && $_GET['yon'] === 'dikey' ? 'dikey' : 'yatay';

$gecerliGruplar = [
    'gidecegiYer' => 'Gideceği Yer',
    'dokumTipi'   => 'Malzeme',
    'FirmaAdi'    => 'Müşteri',
    'plaka'       => 'Plaka',
    'tarih'       => 'Tarih',
    'cikisFirma'  => 'Çıkış Firması',
    'yok'         => 'Yok (Gruplama Yapma)'
];
if (!array_key_exists($grup1, $gecerliGruplar)) $grup1 = 'gidecegiYer';
if (!array_key_exists($grup2, $gecerliGruplar)) $grup2 = 'dokumTipi';

$tumKolonlar = [
    'kantar_fis'    => ['baslik' => 'KantarFiş', 'hizalama' => 'start'],
    'irsaliye_no'   => ['baslik' => 'irsaliyeNo', 'hizalama' => 'start'],
    'tarih'         => ['baslik' => 'tarih', 'hizalama' => 'start'],
    'saat'          => ['baslik' => 'saat', 'hizalama' => 'start'],
    'plaka'         => ['baslik' => 'plaka', 'hizalama' => 'start'],
    'dokum_net_kg'  => ['baslik' => 'dokumNetKilo', 'hizalama' => 'end'],
    'malzeme'       => ['baslik' => 'Malzeme', 'hizalama' => 'start'],
    'gidecegi_yer'  => ['baslik' => 'gidecegiYer', 'hizalama' => 'start'],
    'musteri'       => ['baslik' => 'Müşteri', 'hizalama' => 'start'],
    'brim_fiyat'    => ['baslik' => 'Birim Fiyat', 'hizalama' => 'end'],
    'tutar'         => ['baslik' => 'Tutar', 'hizalama' => 'end'],
    'kdv'           => ['baslik' => 'KDV', 'hizalama' => 'end'],
    'genel_tutar'   => ['baslik' => 'Genel Tutar', 'hizalama' => 'end'],
    'hareket'       => ['baslik' => 'Hareket', 'hizalama' => 'center'],
    'cikis_firmasi' => ['baslik' => 'Çıkış Firması', 'hizalama' => 'start']
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
$gidecegiYerListesi = [];
$malzemeListesi = [];
$esnekGruplanmis = [];
$esnekGenelToplam = ['sefer' => 0, 'net_kg' => 0, 'tutar' => 0];
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

        // Gideceği yer listesi (esnek rapor filtresi)
        $gyStmt = $pdoReport->query("SELECT DISTINCT gidecegiYer FROM SahadanSatis WHERE status = 1 AND gidecegiYer IS NOT NULL AND gidecegiYer != '' ORDER BY gidecegiYer");
        $gidecegiYerListesi = $gyStmt->fetchAll(PDO::FETCH_COLUMN);

        // Malzeme listesi (esnek rapor filtresi)
        $malzStmt = $pdoReport->query("SELECT DISTINCT dokumTipi FROM SahadanSatis WHERE status = 1 AND dokumTipi IS NOT NULL AND dokumTipi != '' ORDER BY dokumTipi");
        $malzemeListesi = $malzStmt->fetchAll(PDO::FETCH_COLUMN);

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
        } elseif ($rapor === 'esnek') {
            // Esnek Rapor: çok seviyeli gruplama ve alt toplamlar
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
                    return formatSahaTarih($r['tarih'] ?? null);
                } elseif ($gType === 'cikisFirma') {
                    $v = trim((string)($r['cikisFirmaAdi'] ?? ''));
                    return $v !== '' ? $v : 'Belirtilmemiş';
                }
                return '';
            };

            $hasGrup1 = ($grup1 !== 'yok');
            $hasGrup2 = ($grup2 !== 'yok' && $grup2 !== $grup1);

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
        }
    } catch (PDOException $e) {
        $raporDbHata = $e->getMessage();
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <h4 class="mb-0"><i class="bi bi-truck"></i> Kantar Raporları</h4>
</div>

<?php if (!$pdoReport): ?>
    <div class="alert alert-warning no-print">
        <i class="bi bi-exclamation-triangle"></i> Raporlama veritabanı bağlantısı yok. Lütfen .env içinde <code>DB_REPORT_*</code> ayarlarını kontrol edin.
    </div>
<?php else: ?>

    <ul class="nav nav-tabs mb-3 no-print" role="tablist">
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
        <li class="nav-item">
            <a class="nav-link <?php echo $rapor === 'esnek' ? 'active' : ''; ?>" href="?rapor=esnek&baslangic=<?php echo urlencode($baslangic); ?>&bitis=<?php echo urlencode($bitis); ?>&cikis_firma=<?php echo (int)$cikis_firma; ?>&musteri=<?php echo urlencode($musteri); ?>&plaka=<?php echo urlencode($plaka); ?>&hareket=<?php echo urlencode($hareket); ?>&gidecegi_yer=<?php echo urlencode($gidecegi_yer); ?>&malzeme=<?php echo urlencode($malzeme); ?>&grup1=<?php echo urlencode($grup1); ?>&grup2=<?php echo urlencode($grup2); ?>&baslik=<?php echo urlencode($baslik); ?>&yon=<?php echo urlencode($yon); ?>"><i class="bi bi-diagram-3"></i> Esnek Rapor</a>
        </li>
    </ul>

    <!-- Tarih / periyot seçimi (ortak) -->
    <?php
    $periyotSuffix = ($rapor === 'cari' && $cari_firma !== '') ? '&cari_firma=' . urlencode($cari_firma) : '';
    if (in_array($rapor, ['perakende', 'kantar_takip', 'mizan', 'ozet', 'ozet_malzeme', 'cari', 'esnek'], true)) {
        if ($cikis_firma > 0) $periyotSuffix .= '&cikis_firma=' . (int)$cikis_firma;
        if ($musteri !== '' && $rapor !== 'cari') $periyotSuffix .= '&musteri=' . urlencode($musteri);
        if ($hareket !== '' && $rapor !== 'mizan') $periyotSuffix .= '&hareket=' . urlencode($hareket);
    }
    if ($rapor === 'perakende' && $plaka !== '') $periyotSuffix .= '&plaka=' . urlencode($plaka);
    if ($rapor === 'ozet') $periyotSuffix .= '&ozet_bakiyesiz=' . (int)$ozet_bakiyesiz;
    if ($rapor === 'ozet_malzeme') $periyotSuffix .= '&malzeme_bos_gizle=' . (int)$malzeme_bos_gizle;
    if ($rapor === 'esnek') {
        if ($gidecegi_yer !== '') $periyotSuffix .= '&gidecegi_yer=' . urlencode($gidecegi_yer);
        if ($malzeme !== '') $periyotSuffix .= '&malzeme=' . urlencode($malzeme);
        if ($plaka !== '') $periyotSuffix .= '&plaka=' . urlencode($plaka);
        $periyotSuffix .= '&grup1=' . urlencode($grup1) . '&grup2=' . urlencode($grup2) . '&baslik=' . urlencode($baslik) . '&yon=' . urlencode($yon);
        foreach ($seciliKolonlar as $ck) {
            $periyotSuffix .= '&kolonlar[]=' . urlencode($ck);
        }
    }
    $periyotlar = [
        'dun' => 'Dün', 'bugun' => 'Bugün',
        'gecen_hafta' => 'Geçen Hafta', 'bu_hafta' => 'Bu Hafta',
        'gecen_ay' => 'Geçen Ay', 'bu_ay' => 'Bu Ay',
        'gecen_yil' => 'Geçen Yıl', 'bu_yil' => 'Bu Yıl',
    ];
    ?>
    <div class="d-flex flex-wrap align-items-center gap-1 mb-3 no-print" style="font-size:.75rem">
        <?php foreach ($periyotlar as $p => $label): ?>
        <a href="?rapor=<?php echo $rapor; ?>&periyot=<?php echo $p; ?><?php echo $periyotSuffix; ?>" class="btn <?php echo $periyot === $p ? 'btn-dark' : 'btn-outline-secondary'; ?>" style="padding:.2rem .5rem;font-size:.75rem;line-height:1.3"><?php echo $label; ?></a>
        <?php endforeach; ?>
        <span class="text-muted mx-1">|</span>
        <form method="get" class="d-flex align-items-center gap-1 flex-wrap no-print">
            <input type="hidden" name="rapor" value="<?php echo htmlspecialchars($rapor); ?>">
            <?php if ($rapor === 'ozet'): ?>
            <input type="hidden" name="ozet_bakiyesiz" value="<?php echo (int)$ozet_bakiyesiz; ?>">
            <?php endif; ?>
            <?php if ($rapor === 'ozet_malzeme'): ?>
            <input type="hidden" name="malzeme_bos_gizle" value="<?php echo (int)$malzeme_bos_gizle; ?>">
            <?php endif; ?>
            <?php if ($rapor === 'esnek'): ?>
            <input type="hidden" name="baslik" value="<?php echo htmlspecialchars($baslik); ?>">
            <input type="hidden" name="grup1" value="<?php echo htmlspecialchars($grup1); ?>">
            <input type="hidden" name="grup2" value="<?php echo htmlspecialchars($grup2); ?>">
            <input type="hidden" name="yon" id="esnekYonHidden" value="<?php echo htmlspecialchars($yon); ?>">
            <?php foreach ($seciliKolonlar as $ck): ?>
            <input type="hidden" name="kolonlar[]" value="<?php echo htmlspecialchars($ck); ?>">
            <?php endforeach; ?>
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
            <?php if ($rapor === 'esnek'): ?>
            <select name="gidecegi_yer" id="gidecegiYerSelect" class="form-select form-select-sm" style="width:180px" title="Gideceği Yer">
                <option value="">-- Tüm Gideceği Yerler --</option>
                <?php foreach ($gidecegiYerListesi as $gy): ?>
                    <option value="<?php echo htmlspecialchars($gy); ?>" <?php echo $gidecegi_yer === $gy ? 'selected' : ''; ?>><?php echo htmlspecialchars($gy); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="malzeme" id="malzemeSelect" class="form-select form-select-sm" style="width:180px" title="Malzeme">
                <option value="">-- Tüm Malzemeler --</option>
                <?php foreach ($malzemeListesi as $mlz): ?>
                    <option value="<?php echo htmlspecialchars($mlz); ?>" <?php echo $malzeme === $mlz ? 'selected' : ''; ?>><?php echo htmlspecialchars($mlz); ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <?php if ($rapor === 'perakende' || $rapor === 'esnek'): ?>
            <input type="text" name="plaka" class="form-control form-control-sm" style="width:120px" value="<?php echo htmlspecialchars($plaka); ?>" placeholder="Plaka">
            <?php endif; ?>
            <?php if ($rapor !== 'mizan'): ?>
            <select name="hareket" class="form-select form-select-sm" style="width:130px" title="Hareket / İşlem türü">
                <option value="">-- Tüm hareketler --</option>
                <option value="satis" <?php echo $hareket === 'satis' ? 'selected' : ''; ?>>Satış</option>
                <option value="tahsilat" <?php echo $hareket === 'tahsilat' ? 'selected' : ''; ?>>Tahsilat</option>
            </select>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary btn-sm" title="Filtrele"><i class="bi bi-funnel"></i></button>
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
            <?php elseif ($rapor === 'esnek'): ?>
            <?php
            $esnekPdfParams = [
                'baslangic' => $baslangic,
                'bitis' => $bitis,
                'cikis_firma' => $cikis_firma,
                'musteri' => $musteri,
                'plaka' => $plaka,
                'hareket' => $hareket,
                'gidecegi_yer' => $gidecegi_yer,
                'malzeme' => $malzeme,
                'grup1' => $grup1,
                'grup2' => $grup2,
                'baslik' => $baslik,
                'kolonlar' => $seciliKolonlar,
                'yon' => $yon
            ];
            $esnekPdfParamsYatay = array_merge($esnekPdfParams, ['yon' => 'yatay']);
            $esnekPdfParamsDikey = array_merge($esnekPdfParams, ['yon' => 'dikey']);
            $esnekPdfUrlYatay = 'kantar_esnek_pdf.php?' . http_build_query($esnekPdfParamsYatay);
            $esnekPdfUrlDikey = 'kantar_esnek_pdf.php?' . http_build_query($esnekPdfParamsDikey);
            $esnekPdfUrlCurrent = ($yon === 'dikey') ? $esnekPdfUrlDikey : $esnekPdfUrlYatay;
            ?>
            <select name="yon" id="esnekKagitYonuSelect" class="form-select form-select-sm" style="width:105px" title="Kağıt Yönü (PDF & Yazdırma)" onchange="guncelleKagitYonu(this.value)">
                <option value="yatay" <?php echo $yon === 'yatay' ? 'selected' : ''; ?>>📄 Yatay</option>
                <option value="dikey" <?php echo $yon === 'dikey' ? 'selected' : ''; ?>>📄 Dikey</option>
            </select>
            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#esnekAyarModal" title="Grupları ve Kolonları Özelleştir"><i class="bi bi-sliders"></i> Özelleştir</button>

            <div class="btn-group btn-group-sm">
                <a href="<?php echo htmlspecialchars($esnekPdfUrlCurrent); ?>" target="_blank" class="btn btn-outline-danger" id="btnEsnekPdf" title="PDF Olarak İndir"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                <button type="button" class="btn btn-outline-danger dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="visually-hidden">PDF Seçenekleri</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:0.85rem">
                    <li><h6 class="dropdown-header">PDF Kağıt Yönü</h6></li>
                    <li><a class="dropdown-item <?php echo $yon === 'yatay' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($esnekPdfUrlYatay); ?>" target="_blank"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Yatay PDF (Landscape)</a></li>
                    <li><a class="dropdown-item <?php echo $yon === 'dikey' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($esnekPdfUrlDikey); ?>" target="_blank"><i class="bi bi-file-earmark-text me-1"></i> Dikey PDF (Portrait)</a></li>
                </ul>
            </div>

            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" onclick="kantarYazdir()" title="Yazdır"><i class="bi bi-printer"></i></button>
                <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="visually-hidden">Yazdırma Seçenekleri</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:0.85rem">
                    <li><h6 class="dropdown-header">Yazdırma Kağıt Yönü</h6></li>
                    <li><a class="dropdown-item <?php echo $yon === 'yatay' ? 'active' : ''; ?>" href="javascript:void(0)" onclick="kantarYazdir('yatay')"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Yatay Yazdır (Landscape)</a></li>
                    <li><a class="dropdown-item <?php echo $yon === 'dikey' ? 'active' : ''; ?>" href="javascript:void(0)" onclick="kantarYazdir('dikey')"><i class="bi bi-file-earmark-text me-1"></i> Dikey Yazdır (Portrait)</a></li>
                </ul>
            </div>
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

    <!-- Esnek Rapor -->
    <?php if ($rapor === 'esnek' && !$raporDbHata): ?>
        <?php
        $renderCell = function($colKey, $r) {
            switch ($colKey) {
                case 'kantar_fis':
                    $fis = !empty($r['okunanFisNo']) ? $r['okunanFisNo'] : (!empty($r['fisNo']) ? $r['fisNo'] : (!empty($r['Kimlik']) ? $r['Kimlik'] : $r['id']));
                    return htmlspecialchars((string)$fis);
                case 'irsaliye_no':
                    return htmlspecialchars(($r['irsaliyeSeri'] ?? '') . ($r['irsaliyeNo'] ?? ''));
                case 'tarih':
                    return formatSahaTarih($r['tarih'] ?? null);
                case 'saat':
                    return formatSahaZaman($r['islemZamanDamgasi'] ?? null);
                case 'plaka':
                    return htmlspecialchars($r['plaka'] ?? '-');
                case 'dokum_net_kg':
                    return number_format((float)($r['dokumNetKg'] ?? 0), 0, ',', '.');
                case 'malzeme':
                    return htmlspecialchars($r['dokumTipi'] ?? '-');
                case 'gidecegi_yer':
                    return htmlspecialchars($r['gidecegiYer'] ?? '-');
                case 'musteri':
                    return htmlspecialchars($r['FirmaAdi'] ?? '-');
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
                    return '<span class="badge ' . ($isTahsilat ? 'bg-success' : 'bg-primary') . '">' . ($isTahsilat ? 'Tahsilat' : 'Satış') . '</span>';
                case 'cikis_firmasi':
                    return htmlspecialchars($r['cikisFirmaAdi'] ?? '-');
                default:
                    return '';
            }
        };

        $renderSubtotalCells = function($title, $sefer, $kg, $tutar, $seciliKolonlar, $tumKolonlar) {
            $html = '';
            $summaryColKeys = ['dokum_net_kg', 'tutar', 'genel_tutar', 'brim_fiyat', 'kdv'];
            $firstSummaryIdx = false;
            foreach ($seciliKolonlar as $idx => $ck) {
                if (in_array($ck, $summaryColKeys, true)) {
                    $firstSummaryIdx = $idx;
                    break;
                }
            }

            if ($firstSummaryIdx === false || $firstSummaryIdx === 0) {
                $firstTextFound = false;
                foreach ($seciliKolonlar as $col) {
                    $align = $tumKolonlar[$col]['hizalama'];
                    if (!$firstTextFound && in_array($align, ['start', 'center'])) {
                        $html .= '<td class="text-start">' . $title . ' <span class="badge bg-secondary ms-1">' . number_format($sefer, 0, ',', '.') . ' Sefer</span></td>';
                        $firstTextFound = true;
                    } elseif ($col === 'dokum_net_kg') {
                        $html .= '<td class="text-end fw-bold text-nowrap">' . number_format($kg, 0, ',', '.') . ' kg</td>';
                    } elseif ($col === 'tutar' || $col === 'genel_tutar') {
                        $html .= '<td class="text-end fw-bold text-nowrap">' . number_format($tutar, 2, ',', '.') . ' ₺</td>';
                    } else {
                        $html .= '<td></td>';
                    }
                }
            } else {
                $html .= '<td colspan="' . (int)$firstSummaryIdx . '" class="text-start">' . $title . ' <span class="badge bg-secondary ms-1">' . number_format($sefer, 0, ',', '.') . ' Sefer</span></td>';
                for ($i = $firstSummaryIdx; $i < count($seciliKolonlar); $i++) {
                    $col = $seciliKolonlar[$i];
                    if ($col === 'dokum_net_kg') {
                        $html .= '<td class="text-end fw-bold text-nowrap">' . number_format($kg, 0, ',', '.') . ' kg</td>';
                    } elseif ($col === 'tutar' || $col === 'genel_tutar') {
                        $html .= '<td class="text-end fw-bold text-nowrap">' . number_format($tutar, 2, ',', '.') . ' ₺</td>';
                    } else {
                        $html .= '<td></td>';
                    }
                }
            }
            return $html;
        };
        ?>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <!-- Yazdırma için özel temiz rapor başlığı (yalnızca print modunda görünür) -->
                <div class="d-none d-print-block mb-3 border-bottom pb-2">
                    <div class="d-flex justify-content-between align-items-end">
                        <div>
                            <h4 class="fw-bold mb-1" style="color:#0f172a;"><?php echo htmlspecialchars($baslik); ?></h4>
                            <div style="font-size: 11px; color: #475569;">
                                <strong>Tarih:</strong> <?php echo date('d.m.Y', strtotime($baslangic)); ?> – <?php echo date('d.m.Y', strtotime($bitis)); ?>
                                <?php if ($cikis_firma && isset($cikisFirmaListesi[$cikis_firma])): ?>
                                    &nbsp;|&nbsp; <strong>Çıkış:</strong> <?php echo htmlspecialchars($cikisFirmaListesi[$cikis_firma]); ?>
                                <?php endif; ?>
                                <?php if ($musteri): ?>
                                    &nbsp;|&nbsp; <strong>Müşteri:</strong> <?php echo htmlspecialchars($musteri); ?>
                                <?php endif; ?>
                                <?php if ($gidecegi_yer): ?>
                                    &nbsp;|&nbsp; <strong>Gideceği Yer:</strong> <?php echo htmlspecialchars($gidecegi_yer); ?>
                                <?php endif; ?>
                                <?php if ($malzeme): ?>
                                    &nbsp;|&nbsp; <strong>Malzeme:</strong> <?php echo htmlspecialchars($malzeme); ?>
                                <?php endif; ?>
                                <?php if ($plaka): ?>
                                    &nbsp;|&nbsp; <strong>Plaka:</strong> <?php echo htmlspecialchars($plaka); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-end" style="font-size: 11px; color: #475569;">
                            <div><strong>Toplam:</strong> <?php echo number_format($esnekGenelToplam['sefer'], 0, ',', '.'); ?> Sefer &nbsp;|&nbsp; <?php echo number_format($esnekGenelToplam['net_kg'], 0, ',', '.'); ?> kg</div>
                            <div class="text-muted" style="font-size: 10px;">Yazdırma Tarihi: <?php echo date('d.m.Y H:i'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Web ekran başlığı ve butonları (print modunda gizlenir) -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 d-print-none">
                    <div>
                        <h5 class="card-title mb-1 text-primary fw-bold">
                            <i class="bi bi-file-earmark-bar-graph"></i> <?php echo htmlspecialchars($baslik); ?>
                        </h5>
                        <div class="text-muted small d-flex flex-wrap align-items-center gap-1">
                            <span class="badge bg-light text-dark border"><i class="bi bi-layers"></i> 1. Grup: <?php echo htmlspecialchars($gecerliGruplar[$grup1]); ?></span>
                            <span class="badge bg-light text-dark border"><i class="bi bi-diagram-2"></i> 2. Grup: <?php echo htmlspecialchars($gecerliGruplar[$grup2]); ?></span>
                            <span class="badge bg-primary"><?php echo number_format($esnekGenelToplam['sefer'], 0, ',', '.'); ?> Sefer</span>
                            <span class="badge bg-success"><?php echo number_format($esnekGenelToplam['net_kg'], 0, ',', '.'); ?> kg</span>
                            <?php if ($esnekGenelToplam['tutar'] > 0): ?>
                            <span class="badge bg-info text-dark"><?php echo number_format($esnekGenelToplam['tutar'], 2, ',', '.'); ?> ₺</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#esnekAyarModal">
                            <i class="bi bi-sliders"></i> Grupları & Kolonları Özelleştir
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-sm table-bordered align-middle mb-0" style="font-size:.85rem">
                        <thead class="table-light">
                            <tr>
                                <?php foreach ($seciliKolonlar as $colKey):
                                    $isExpCol = in_array($colKey, ['malzeme', 'gidecegi_yer', 'musteri'], true);
                                ?>
                                    <th class="text-<?php echo $tumKolonlar[$colKey]['hizalama']; ?> <?php echo $isExpCol ? '' : 'text-nowrap'; ?>" style="<?php echo $isExpCol ? 'min-width:180px;' : 'width:1%;'; ?>">
                                        <?php echo htmlspecialchars($tumKolonlar[$colKey]['baslik']); ?>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $hasGrup1 = ($grup1 !== 'yok');
                            $hasGrup2 = ($grup2 !== 'yok' && $grup2 !== $grup1);

                            if (empty($esnekGruplanmis) || ($hasGrup1 && empty($esnekGruplanmis)) || (!$hasGrup1 && empty($esnekGruplanmis['duz_liste']))):
                            ?>
                                <tr>
                                    <td colspan="<?php echo count($seciliKolonlar); ?>" class="text-center text-muted py-4">
                                        <i class="bi bi-inbox fs-3 d-block mb-1"></i> Bu kriterlere uygun kayıt bulunamadı.
                                    </td>
                                </tr>
                            <?php
                            elseif ($hasGrup1 && $hasGrup2):
                                foreach ($esnekGruplanmis as $k1 => $g1Data):
                                    $g1Sefer = $g1Data['toplam']['sefer'];
                                    $g1Kg    = $g1Data['toplam']['net_kg'];
                                    $g1Tut   = $g1Data['toplam']['tutar'];
                            ?>
                                    <!-- 1. Seviye Grup Başlığı -->
                                    <tr class="table-primary border-top border-dark border-2">
                                        <td colspan="<?php echo count($seciliKolonlar); ?>" class="fw-bold py-2">
                                            <i class="bi bi-geo-alt-fill text-danger me-1"></i>
                                            <?php echo htmlspecialchars($gecerliGruplar[$grup1]); ?>:
                                            <span class="text-dark fs-6 ms-1"><?php echo htmlspecialchars($k1); ?></span>
                                            <span class="badge bg-dark ms-2"><?php echo number_format($g1Sefer, 0, ',', '.'); ?> Sefer</span>
                                            <span class="badge bg-secondary ms-1"><?php echo number_format($g1Kg, 0, ',', '.'); ?> kg</span>
                                            <?php if ($g1Tut > 0): ?>
                                            <span class="badge bg-info text-dark ms-1"><?php echo number_format($g1Tut, 2, ',', '.'); ?> ₺</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php
                                    foreach ($g1Data['alt_gruplar'] as $k2 => $g2Data):
                                        $g2Sefer = $g2Data['toplam']['sefer'];
                                        $g2Kg    = $g2Data['toplam']['net_kg'];
                                        $g2Tut   = $g2Data['toplam']['tutar'];
                                    ?>
                                        <!-- 2. Seviye Grup Başlığı -->
                                        <tr class="table-secondary bg-opacity-50">
                                            <td colspan="<?php echo count($seciliKolonlar); ?>" class="fw-semibold ps-4 py-1">
                                                <i class="bi bi-box-seam text-primary me-1"></i>
                                                <?php echo htmlspecialchars($gecerliGruplar[$grup2]); ?>:
                                                <span class="text-dark ms-1"><?php echo htmlspecialchars($k2); ?></span>
                                                <span class="badge bg-light text-dark border ms-2"><?php echo number_format($g2Sefer, 0, ',', '.'); ?> Sefer</span>
                                                <span class="badge bg-light text-dark border ms-1"><?php echo number_format($g2Kg, 0, ',', '.'); ?> kg</span>
                                            </td>
                                        </tr>
                                        <!-- Veri Satırları -->
                                        <?php foreach ($g2Data['satirlar'] as $r): ?>
                                            <tr>
                                                <?php foreach ($seciliKolonlar as $ck):
                                                    $isExp = in_array($ck, ['malzeme', 'gidecegi_yer', 'musteri'], true);
                                                ?>
                                                    <td class="text-<?php echo $tumKolonlar[$ck]['hizalama']; ?> <?php echo $isExp ? '' : 'text-nowrap'; ?>">
                                                        <?php echo $renderCell($ck, $r); ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                        <!-- 2. Seviye Grup Alt Toplam Satırı -->
                                        <tr class="table-warning bg-opacity-25 fw-semibold" style="font-size:.8rem">
                                            <?php
                                            $subTitle = '<i class="bi bi-calculator me-1"></i> Alt Toplam (' . htmlspecialchars($k2) . '):';
                                            echo $renderSubtotalCells($subTitle, $g2Sefer, $g2Kg, $g2Tut, $seciliKolonlar, $tumKolonlar);
                                            ?>
                                        </tr>
                                    <?php endforeach; ?>
                                    <!-- 1. Seviye Grup Ara Toplam Satırı -->
                                    <tr class="table-info bg-opacity-25 fw-bold" style="font-size:.85rem">
                                        <?php
                                        $subTitle = '<i class="bi bi-arrow-return-right me-1"></i> ' . htmlspecialchars($k1) . ' Toplamı:';
                                        echo $renderSubtotalCells($subTitle, $g1Sefer, $g1Kg, $g1Tut, $seciliKolonlar, $tumKolonlar);
                                        ?>
                                    </tr>
                            <?php
                                endforeach;
                            elseif ($hasGrup1):
                                foreach ($esnekGruplanmis as $k1 => $g1Data):
                                    $g1Sefer = $g1Data['toplam']['sefer'];
                                    $g1Kg    = $g1Data['toplam']['net_kg'];
                                    $g1Tut   = $g1Data['toplam']['tutar'];
                            ?>
                                    <!-- 1. Seviye Grup Başlığı -->
                                    <tr class="table-primary border-top border-dark border-2">
                                        <td colspan="<?php echo count($seciliKolonlar); ?>" class="fw-bold py-2">
                                            <i class="bi bi-tag-fill me-1"></i>
                                            <?php echo htmlspecialchars($gecerliGruplar[$grup1]); ?>:
                                            <span class="text-dark fs-6 ms-1"><?php echo htmlspecialchars($k1); ?></span>
                                            <span class="badge bg-dark ms-2"><?php echo number_format($g1Sefer, 0, ',', '.'); ?> Sefer</span>
                                            <span class="badge bg-secondary ms-1"><?php echo number_format($g1Kg, 0, ',', '.'); ?> kg</span>
                                        </td>
                                    </tr>
                                    <?php foreach ($g1Data['satirlar'] as $r): ?>
                                        <tr>
                                            <?php foreach ($seciliKolonlar as $ck):
                                                $isExp = in_array($ck, ['malzeme', 'gidecegi_yer', 'musteri'], true);
                                            ?>
                                                <td class="text-<?php echo $tumKolonlar[$ck]['hizalama']; ?> <?php echo $isExp ? '' : 'text-nowrap'; ?>">
                                                    <?php echo $renderCell($ck, $r); ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                    <!-- 1. Seviye Grup Alt Toplam Satırı -->
                                    <tr class="table-info bg-opacity-25 fw-bold" style="font-size:.85rem">
                                        <?php
                                        $subTitle = '<i class="bi bi-calculator me-1"></i> ' . htmlspecialchars($k1) . ' Alt Toplamı:';
                                        echo $renderSubtotalCells($subTitle, $g1Sefer, $g1Kg, $g1Tut, $seciliKolonlar, $tumKolonlar);
                                        ?>
                                    </tr>
                            <?php
                                endforeach;
                            else:
                                foreach ($esnekGruplanmis['duz_liste'] ?? [] as $r):
                            ?>
                                    <tr>
                                        <?php foreach ($seciliKolonlar as $ck):
                                            $isExp = in_array($ck, ['malzeme', 'gidecegi_yer', 'musteri'], true);
                                        ?>
                                            <td class="text-<?php echo $tumKolonlar[$ck]['hizalama']; ?> <?php echo $isExp ? '' : 'text-nowrap'; ?>">
                                                <?php echo $renderCell($ck, $r); ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                            <?php
                                endforeach;
                            endif;
                            ?>
                        </tbody>
                        <?php if ($esnekGenelToplam['sefer'] > 0): ?>
                        <tfoot class="table-dark">
                            <tr class="fw-bold fs-6">
                                <?php
                                $genelBaslik = 'GENEL TOPLAM';
                                echo $renderSubtotalCells($genelBaslik, $esnekGenelToplam['sefer'], $esnekGenelToplam['net_kg'], $esnekGenelToplam['tutar'], $seciliKolonlar, $tumKolonlar);
                                ?>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- Rapor Özelleştirme Modalı -->
        <div class="modal fade" id="esnekAyarModal" tabindex="-1" aria-labelledby="esnekAyarModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="get" action="">
                        <input type="hidden" name="rapor" value="esnek">
                        <input type="hidden" name="baslangic" value="<?php echo htmlspecialchars($baslangic); ?>">
                        <input type="hidden" name="bitis" value="<?php echo htmlspecialchars($bitis); ?>">
                        <input type="hidden" name="cikis_firma" value="<?php echo (int)$cikis_firma; ?>">
                        <input type="hidden" name="musteri" value="<?php echo htmlspecialchars($musteri); ?>">
                        <input type="hidden" name="plaka" value="<?php echo htmlspecialchars($plaka); ?>">
                        <input type="hidden" name="gidecegi_yer" value="<?php echo htmlspecialchars($gidecegi_yer); ?>">
                        <input type="hidden" name="malzeme" value="<?php echo htmlspecialchars($malzeme); ?>">
                        <input type="hidden" name="hareket" value="<?php echo htmlspecialchars($hareket); ?>">

                        <div class="modal-header bg-light">
                            <h5 class="modal-title" id="esnekAyarModalLabel">
                                <i class="bi bi-sliders text-primary"></i> Raporu & Kolonları Özelleştir
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                        </div>
                        <div class="modal-body">
                            <!-- Rapor Başlığı ve Kağıt Yönü -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-7">
                                    <label for="modalBaslik" class="form-label fw-bold">
                                        <i class="bi bi-type me-1"></i> Rapor Başlığı
                                    </label>
                                    <input type="text" class="form-control" id="modalBaslik" name="baslik" value="<?php echo htmlspecialchars($baslik); ?>" placeholder="Örn: Gideceği Yer & Malzeme Bazlı Sevkiyat Raporu">
                                    <div class="form-text">Rapor tablosunun üstünde ve çıktıda görünecek başlık.</div>
                                </div>
                                <div class="col-md-5">
                                    <label for="modalYon" class="form-label fw-bold">
                                        <i class="bi bi-file-earmark me-1 text-secondary"></i> Kağıt Yönü (PDF & Yazdır)
                                    </label>
                                    <select class="form-select" id="modalYon" name="yon" onchange="guncelleKagitYonu(this.value)">
                                        <option value="yatay" <?php echo $yon === 'yatay' ? 'selected' : ''; ?>>📄 Yatay (Landscape)</option>
                                        <option value="dikey" <?php echo $yon === 'dikey' ? 'selected' : ''; ?>>📄 Dikey (Portrait)</option>
                                    </select>
                                    <div class="form-text">Çok kolonlu tablolarda Yatay önerilir.</div>
                                </div>
                            </div>

                            <!-- Gruplandırma Alanları -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label for="modalGrup1" class="form-label fw-bold">
                                        <i class="bi bi-layers me-1 text-primary"></i> 1. Seviye Gruplama (Ana Grup)
                                    </label>
                                    <select class="form-select" id="modalGrup1" name="grup1">
                                        <?php foreach ($gecerliGruplar as $gk => $gAd): ?>
                                            <option value="<?php echo htmlspecialchars($gk); ?>" <?php echo $grup1 === $gk ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($gAd); ?><?php echo $gk === 'gidecegiYer' ? ' (Varsayılan)' : ''; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Ana grup başlıkları ve ara toplamları oluşturur.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="modalGrup2" class="form-label fw-bold">
                                        <i class="bi bi-diagram-2 me-1 text-success"></i> 2. Seviye Gruplama (Alt Grup)
                                    </label>
                                    <select class="form-select" id="modalGrup2" name="grup2">
                                        <?php foreach ($gecerliGruplar as $gk => $gAd): ?>
                                            <option value="<?php echo htmlspecialchars($gk); ?>" <?php echo $grup2 === $gk ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($gAd); ?><?php echo $gk === 'dokumTipi' ? ' (Varsayılan)' : ''; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Ana grup içerisindeki alt grupları ve alt toplamları oluşturur.</div>
                                </div>
                            </div>

                            <!-- Gösterilecek Kolonlar -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label fw-bold mb-0">
                                        <i class="bi bi-table me-1"></i> Gösterilecek Kolonlar
                                    </label>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAllKolonlar(true)">Tümünü Seç</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setVarsayilanKolonlar()">Varsayılana Sıfırla</button>
                                    </div>
                                </div>
                                <div class="p-3 bg-light rounded border">
                                    <div class="row g-2">
                                        <?php foreach ($tumKolonlar as $colKey => $colBilgi):
                                            $isChecked = in_array($colKey, $seciliKolonlar, true);
                                            $isDef = in_array($colKey, $varsayilanKolonlar, true);
                                        ?>
                                            <div class="col-md-4 col-sm-6">
                                                <div class="form-check">
                                                    <input class="form-check-input kolon-checkbox" type="checkbox" name="kolonlar[]" value="<?php echo htmlspecialchars($colKey); ?>" id="col_<?php echo htmlspecialchars($colKey); ?>" <?php echo $isChecked ? 'checked' : ''; ?> data-varsayilan="<?php echo $isDef ? '1' : '0'; ?>">
                                                    <label class="form-check-label user-select-none" for="col_<?php echo htmlspecialchars($colKey); ?>">
                                                        <?php echo htmlspecialchars($colBilgi['baslik']); ?>
                                                        <?php if ($isDef): ?>
                                                        <span class="badge bg-light text-muted border" style="font-size:0.65rem">varsayılan</span>
                                                        <?php endif; ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Ayarları Uygula</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        function toggleAllKolonlar(state) {
            document.querySelectorAll('.kolon-checkbox').forEach(function(cb) {
                cb.checked = state;
            });
        }
        function setVarsayilanKolonlar() {
            document.querySelectorAll('.kolon-checkbox').forEach(function(cb) {
                cb.checked = (cb.getAttribute('data-varsayilan') === '1');
            });
            var b = document.getElementById('modalBaslik');
            if (b) b.value = 'Gideceği Yer & Malzeme Bazlı Sevkiyat Raporu';
            var g1 = document.getElementById('modalGrup1');
            if (g1) g1.value = 'gidecegiYer';
            var g2 = document.getElementById('modalGrup2');
            if (g2) g2.value = 'dokumTipi';
            var my = document.getElementById('modalYon');
            if (my) my.value = 'yatay';
        }

        function guncelleKagitYonu(y) {
            var styleEl = document.getElementById('printOrientationDynamic');
            if (styleEl) {
                styleEl.innerHTML = '@media print { @page { size: A4 ' + (y === 'dikey' ? 'portrait' : 'landscape') + ' !important; margin: 6mm 8mm !important; } }';
            }
            var hid = document.getElementById('esnekYonHidden');
            if (hid) hid.value = y;
            var modalY = document.getElementById('modalYon');
            if (modalY && modalY.value !== y) modalY.value = y;
            var selY = document.getElementById('esnekKagitYonuSelect');
            if (selY && selY.value !== y) selY.value = y;

            // PDF linkini de dinamik olarak güncelle
            var pdfBtn = document.getElementById('btnEsnekPdf');
            if (pdfBtn) {
                try {
                    var url = new URL(pdfBtn.href, window.location.origin);
                    url.searchParams.set('yon', y);
                    pdfBtn.href = url.pathname + url.search;
                } catch(e) {}
            }
        }

        function kantarYazdir(yon) {
            if (yon) {
                guncelleKagitYonu(yon);
            } else {
                var sel = document.getElementById('esnekKagitYonuSelect');
                if (sel) guncelleKagitYonu(sel.value);
            }
            setTimeout(function() {
                window.print();
            }, 50);
        }
        </script>
    <?php endif; ?>

<?php endif; ?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style id="printOrientationDynamic">
@media print {
    @page {
        size: A4 <?php echo ($yon === 'dikey') ? 'portrait' : 'landscape'; ?> !important;
        margin: 6mm 8mm !important;
    }
}
</style>
<style>
.select2-container--bootstrap-5 .select2-selection { min-height: 31px; }
.select2-container { min-width: 200px; }

@media print {
    nav, .navbar, footer, .no-print, .d-print-none, form, .btn, .modal, .modal-backdrop, .alert, .select2-container {
        display: none !important;
    }
    html, body {
        background: #fff !important;
        color: #000 !important;
        margin: 0 !important;
        padding: 0 !important;
        font-size: 10px !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .container-fluid, .container {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
        margin: 0 !important;
        padding: 0 !important;
        background: transparent !important;
    }
    .card-body {
        padding: 0 !important;
    }
    .table-responsive {
        overflow: visible !important;
        display: block !important;
    }
    table.table {
        width: 100% !important;
        border-collapse: collapse !important;
        font-size: 9px !important;
        margin-bottom: 0 !important;
    }
    table.table th, table.table td {
        border: 1px solid #94a3b8 !important;
        padding: 2.5px 4px !important;
        line-height: 1.2 !important;
    }
    thead {
        display: table-header-group !important;
    }
    tr {
        page-break-inside: avoid !important;
    }
    tr.table-primary, tr[style*="background-color: #e0f2fe"], td[style*="background-color: #e0f2fe"] {
        background-color: #e0f2fe !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    tr.table-light, tr[style*="background-color: #f1f5f9"], td[style*="background-color: #f1f5f9"] {
        background-color: #f1f5f9 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    tr.table-warning, tr[style*="background-color: #fef9c3"], td[style*="background-color: #fef9c3"] {
        background-color: #fef9c3 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    tr.table-dark, tr[style*="background-color: #1e293b"], td[style*="background-color: #1e293b"] {
        background-color: #1e293b !important;
        color: #fff !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
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
    if (document.getElementById('gidecegiYerSelect')) {
        jQuery('#gidecegiYerSelect').select2({
            theme: 'bootstrap-5',
            placeholder: 'Gideceği yer seçin...',
            allowClear: true,
            width: '180px',
            language: {
                noResults: function() { return 'Sonuç bulunamadı'; },
                searching: function() { return 'Aranıyor…'; }
            }
        });
    }
    if (document.getElementById('malzemeSelect')) {
        jQuery('#malzemeSelect').select2({
            theme: 'bootstrap-5',
            placeholder: 'Malzeme seçin...',
            allowClear: true,
            width: '180px',
            language: {
                noResults: function() { return 'Sonuç bulunamadı'; },
                searching: function() { return 'Aranıyor…'; }
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
