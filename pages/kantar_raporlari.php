<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Kantar Raporları';

$rapor = isset($_GET['rapor']) ? $_GET['rapor'] : 'perakende';
if (!in_array($rapor, ['perakende', 'ozet', 'ozet_malzeme', 'cari'], true)) {
    $rapor = 'perakende';
}

// Tarih: pratik seçenek veya özel aralık
$periyot = isset($_GET['periyot']) ? $_GET['periyot'] : '';
$today = date('Y-m-d');
if ($periyot === 'bugun') {
    $baslangic = $bitis = $today;
} elseif ($periyot === 'bu_hafta') {
    $baslangic = date('Y-m-d', strtotime('monday this week'));
    $bitis = $today;
} elseif ($periyot === 'bu_ay') {
    $baslangic = date('Y-m-01');
    $bitis = $today;
} elseif ($periyot === 'bu_yil') {
    $baslangic = date('Y-01-01');
    $bitis = $today;
} else {
    $baslangic = isset($_GET['baslangic']) ? $_GET['baslangic'] : date('Y-m-01');
    $bitis     = isset($_GET['bitis'])     ? $_GET['bitis']     : $today;
}
$musteri   = isset($_GET['musteri'])   ? trim($_GET['musteri']) : '';
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
$ozetListe = [];
$ozetMalzemeListe = [];
$cariListe = [];
$cariDevir = null;
$musteriListesi = [];
$raporDbHata = null;

if ($pdoReport) {
    try {
        // Müşteri listesi (cari ekstre dropdown)
        $musteriStmt = $pdoReport->query("SELECT DISTINCT FirmaAdi FROM SahadanSatis WHERE status = 1 AND FirmaAdi IS NOT NULL AND FirmaAdi != '' ORDER BY FirmaAdi");
        $musteriListesi = $musteriStmt->fetchAll(PDO::FETCH_COLUMN);

        if ($rapor === 'perakende') {
            $sql = "SELECT id, FirmaAdi, plaka, dokumTipi, dokumNetKg, brimFiyat, dokumTutar, kdv, genelTutar,
                           irsaliyeNo, irsaliyeSeri, islemTipi, tarih, islemZamanDamgasi
                    FROM SahadanSatis WHERE status = 1 AND tarih BETWEEN ? AND ?";
            $params = [$tarihBas, $tarihBit];
            if ($musteri !== '') {
                $sql .= " AND (FirmaAdi LIKE ? OR plaka LIKE ?)";
                $params[] = '%' . $musteri . '%';
                $params[] = '%' . $musteri . '%';
            }
            $sql .= " ORDER BY tarih DESC, islemZamanDamgasi DESC";
            $stmt = $pdoReport->prepare($sql);
            $stmt->execute($params);
            $liste = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $toplamSatisPerakende = 0;   // DB'de satış eksi olarak toplanacak
            $toplamTahsilatPerakende = 0; // DB'de tahsilat artı
            foreach ($liste as $r) {
                $gt = (float)($r['genelTutar'] ?? 0);
                if (isset($r['islemTipi']) && $r['islemTipi'] === 'GELİR TAHSİLAT') {
                    $toplamTahsilatPerakende += $gt;
                } else {
                    $toplamSatisPerakende += $gt; // TAHAKKUK = satış, DB'de eksi
                }
                $toplamNetKg += (float)($r['dokumNetKg'] ?? 0);
                $toplamGenel += $gt;
            }
        } elseif ($rapor === 'ozet') {
            // İlk iki sütun: seçili tarih aralığı (baslangic–bitis). Genel: baştan seçili son tarihe (bitis) kadar. Bakiye = genel bakiye (eksi = müşteri borçlu).
            $sql = "SELECT FirmaAdi,
                    SUM(CASE WHEN islemTipi = 'GELİR TAHAKKUK' AND tarih >= ? AND tarih <= ? THEN COALESCE(genelTutar,0) ELSE 0 END) AS toplam_satis,
                    SUM(CASE WHEN islemTipi = 'GELİR TAHSİLAT' AND tarih >= ? AND tarih <= ? THEN COALESCE(genelTutar,0) ELSE 0 END) AS toplam_tahsilat,
                    SUM(CASE WHEN islemTipi = 'GELİR TAHAKKUK' AND tarih <= ? THEN COALESCE(genelTutar,0) ELSE 0 END) AS genel_satis,
                    SUM(CASE WHEN islemTipi = 'GELİR TAHSİLAT' AND tarih <= ? THEN COALESCE(genelTutar,0) ELSE 0 END) AS genel_tahsilat
                    FROM SahadanSatis
                    WHERE status = 1 AND tarih <= ?
                    GROUP BY FirmaAdi ORDER BY FirmaAdi";
            $stmt = $pdoReport->prepare($sql);
            $stmt->execute([$tarihBas, $tarihBit, $tarihBas, $tarihBit, $tarihBit, $tarihBit, $tarihBit]);
            $ozetListe = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($ozetListe as &$o) {
                $o['bakiye'] = (float)($o['genel_satis'] ?? 0) + (float)($o['genel_tahsilat'] ?? 0);
            }
            unset($o);
            if ($ozet_bakiyesiz === 0) {
                // Bakiyesiz = seçili tarih aralığında satış veya tahsilat yok. Genel bakiyeden farklı.
                $ozetListe = array_values(array_filter($ozetListe, function ($o) {
                    return (float)$o['toplam_satis'] != 0 || (float)$o['toplam_tahsilat'] != 0;
                }));
            }
        } elseif ($rapor === 'ozet_malzeme') {
            // Malzeme (dokumTipi) bazında özet: sadece satış (GELİR TAHAKKUK), kg + tutar
            $sql = "SELECT COALESCE(NULLIF(TRIM(dokumTipi),''), '-') AS malzeme,
                    SUM(CASE WHEN islemTipi = 'GELİR TAHAKKUK' AND tarih >= ? AND tarih <= ? THEN COALESCE(dokumNetKg,0) ELSE 0 END) AS donem_net_kg,
                    SUM(CASE WHEN islemTipi = 'GELİR TAHAKKUK' AND tarih >= ? AND tarih <= ? THEN COALESCE(genelTutar,0) ELSE 0 END) AS donem_tutar,
                    SUM(CASE WHEN islemTipi = 'GELİR TAHAKKUK' AND tarih <= ? THEN COALESCE(dokumNetKg,0) ELSE 0 END) AS genel_net_kg,
                    SUM(CASE WHEN islemTipi = 'GELİR TAHAKKUK' AND tarih <= ? THEN COALESCE(genelTutar,0) ELSE 0 END) AS genel_tutar
                    FROM SahadanSatis
                    WHERE status = 1 AND tarih <= ?
                    GROUP BY COALESCE(NULLIF(TRIM(dokumTipi),''), '-') ORDER BY malzeme";
            $stmt = $pdoReport->prepare($sql);
            $stmt->execute([$tarihBas, $tarihBit, $tarihBas, $tarihBit, $tarihBit, $tarihBit, $tarihBit]);
            $ozetMalzemeListe = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($malzeme_bos_gizle === 0) {
                $ozetMalzemeListe = array_values(array_filter($ozetMalzemeListe, function ($m) {
                    return (float)($m['donem_net_kg'] ?? 0) != 0 || (float)($m['donem_tutar'] ?? 0) != 0;
                }));
            }
        } elseif ($rapor === 'cari' && $cari_firma !== '') {
            // Bakiye eksi = müşteri borçlu (alacak). Satış (eksi giriş) bakiyeyi daha eksi yapar, tahsilat (artı) azaltır.
            $devirStmt = $pdoReport->prepare("
                SELECT SUM(COALESCE(genelTutar,0)) AS devir
                FROM SahadanSatis
                WHERE status = 1 AND FirmaAdi = ? AND tarih < ?
            ");
            $devirStmt->execute([$cari_firma, $tarihDevirBit]);
            $cariDevir = (float)$devirStmt->fetchColumn();

            $sql = "SELECT id, FirmaAdi, tarih, islemZamanDamgasi, islemTipi, dokumTipi, irsaliyeSeri, irsaliyeNo, genelTutar, personelAd
                    FROM SahadanSatis
                    WHERE status = 1 AND FirmaAdi = ? AND tarih BETWEEN ? AND ?
                    ORDER BY tarih ASC, islemZamanDamgasi ASC";
            $stmt = $pdoReport->prepare($sql);
            $stmt->execute([$cari_firma, $tarihBas, $tarihBit]);
            $cariListe = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $bakiye = $cariDevir;
            foreach ($cariListe as &$row) {
                $tutar = (float)($row['genelTutar'] ?? 0);
                $bakiye += $tutar; // satış (eksi) → bakiye daha eksi; tahsilat (artı) → bakiye artar (azalır eksi)
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
            <a class="nav-link <?php echo $rapor === 'perakende' ? 'active' : ''; ?>" href="?rapor=perakende&baslangic=<?php echo urlencode($baslangic); ?>&bitis=<?php echo urlencode($bitis); ?>&musteri=<?php echo urlencode($musteri); ?>"><i class="bi bi-cart-check"></i> Perakende Satış</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $rapor === 'ozet' ? 'active' : ''; ?>" href="?rapor=ozet&baslangic=<?php echo urlencode($baslangic); ?>&bitis=<?php echo urlencode($bitis); ?>&ozet_bakiyesiz=<?php echo (int)$ozet_bakiyesiz; ?>"><i class="bi bi-pie-chart"></i> Özet Rapor</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $rapor === 'ozet_malzeme' ? 'active' : ''; ?>" href="?rapor=ozet_malzeme&baslangic=<?php echo urlencode($baslangic); ?>&bitis=<?php echo urlencode($bitis); ?>&malzeme_bos_gizle=<?php echo (int)$malzeme_bos_gizle; ?>"><i class="bi bi-box-seam"></i> Özet Malzeme Satış</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $rapor === 'cari' ? 'active' : ''; ?>" href="?rapor=cari&baslangic=<?php echo urlencode($baslangic); ?>&bitis=<?php echo urlencode($bitis); ?>&cari_firma=<?php echo urlencode($cari_firma); ?>"><i class="bi bi-journal-bookmark"></i> Cari Ekstre</a>
        </li>
    </ul>

    <!-- Tarih / periyot seçimi (ortak) -->
    <div class="card shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="get" class="row g-3 align-items-end">
                <input type="hidden" name="rapor" value="<?php echo htmlspecialchars($rapor); ?>">
                <?php if ($rapor === 'ozet'): ?>
                <input type="hidden" name="ozet_bakiyesiz" value="<?php echo (int)$ozet_bakiyesiz; ?>">
                <?php endif; ?>
                <?php if ($rapor === 'ozet_malzeme'): ?>
                <input type="hidden" name="malzeme_bos_gizle" value="<?php echo (int)$malzeme_bos_gizle; ?>">
                <?php endif; ?>
                <div class="col-auto">
                    <label class="form-label small mb-0">Periyot</label>
                    <div class="btn-group btn-group-sm">
                        <?php
                        $periyotSuffix = ($rapor === 'cari' && $cari_firma !== '') ? '&cari_firma=' . urlencode($cari_firma) : '';
                        if ($rapor === 'ozet') $periyotSuffix .= '&ozet_bakiyesiz=' . (int)$ozet_bakiyesiz;
                        if ($rapor === 'ozet_malzeme') $periyotSuffix .= '&malzeme_bos_gizle=' . (int)$malzeme_bos_gizle;
                        ?>
                        <a href="?rapor=<?php echo $rapor; ?>&periyot=bugun<?php echo $periyotSuffix; ?>" class="btn <?php echo $periyot === 'bugun' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Bugün</a>
                        <a href="?rapor=<?php echo $rapor; ?>&periyot=bu_hafta<?php echo $periyotSuffix; ?>" class="btn <?php echo $periyot === 'bu_hafta' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Bu Hafta</a>
                        <a href="?rapor=<?php echo $rapor; ?>&periyot=bu_ay<?php echo $periyotSuffix; ?>" class="btn <?php echo $periyot === 'bu_ay' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Bu Ay</a>
                        <a href="?rapor=<?php echo $rapor; ?>&periyot=bu_yil<?php echo $periyotSuffix; ?>" class="btn <?php echo $periyot === 'bu_yil' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Bu Yıl</a>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Başlangıç</label>
                    <input type="date" name="baslangic" class="form-control form-control-sm" value="<?php echo htmlspecialchars($baslangic); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Bitiş</label>
                    <input type="date" name="bitis" class="form-control form-control-sm" value="<?php echo htmlspecialchars($bitis); ?>">
                </div>
                <?php if ($rapor === 'perakende'): ?>
                <div class="col-md-2">
                    <label class="form-label small">Müşteri / Plaka</label>
                    <input type="text" name="musteri" class="form-control form-control-sm" value="<?php echo htmlspecialchars($musteri); ?>" placeholder="Müşteri veya plaka ara">
                </div>
                <?php endif; ?>
                <?php if ($rapor === 'cari'): ?>
                <div class="col-md-3">
                    <label class="form-label small">Müşteri (Cari)</label>
                    <select name="cari_firma" id="cariFirmaSelect" class="form-select form-select-sm">
                        <option value="">-- Müşteri ara veya seçin --</option>
                        <?php foreach ($musteriListesi as $f): ?>
                            <option value="<?php echo htmlspecialchars($f); ?>" <?php echo $cari_firma === $f ? 'selected' : ''; ?>><?php echo htmlspecialchars($f); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> Uygula</button>
                    <?php if ($rapor === 'perakende'): ?>
                    <a href="kantar_perakende_pdf.php?baslangic=<?php echo urlencode($baslangic); ?>&bitis=<?php echo urlencode($bitis); ?>&musteri=<?php echo urlencode($musteri); ?>" target="_blank" class="btn btn-sm btn-outline-danger ms-1"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                    <?php elseif ($rapor === 'ozet'): ?>
                    <a href="kantar_ozet_pdf.php?baslangic=<?php echo urlencode($baslangic); ?>&bitis=<?php echo urlencode($bitis); ?>&ozet_bakiyesiz=<?php echo (int)$ozet_bakiyesiz; ?>" target="_blank" class="btn btn-sm btn-outline-danger ms-1"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                    <?php elseif ($rapor === 'ozet_malzeme'): ?>
                    <a href="kantar_ozet_malzeme_pdf.php?baslangic=<?php echo urlencode($baslangic); ?>&bitis=<?php echo urlencode($bitis); ?>&malzeme_bos_gizle=<?php echo (int)$malzeme_bos_gizle; ?>" target="_blank" class="btn btn-sm btn-outline-danger ms-1"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                    <?php elseif ($rapor === 'cari'): ?>
                    <a href="kantar_cari_ekstre_pdf.php?cari_firma=<?php echo urlencode($cari_firma); ?>&baslangic=<?php echo urlencode($baslangic); ?>&bitis=<?php echo urlencode($bitis); ?>" target="_blank" class="btn btn-sm btn-outline-danger ms-1 <?php echo $cari_firma === '' ? 'disabled' : ''; ?>"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
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

    <!-- Özet Rapor -->
    <?php if ($rapor === 'ozet' && !$raporDbHata): ?>
        <?php
        $ozetQuery = ['rapor' => 'ozet', 'baslangic' => $baslangic, 'bitis' => $bitis];
        $ozetUrlGoster = '?' . http_build_query(array_merge($ozetQuery, ['ozet_bakiyesiz' => 1]));
        $ozetUrlGizle  = '?' . http_build_query(array_merge($ozetQuery, ['ozet_bakiyesiz' => 0]));
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
        $malzemeQuery = ['rapor' => 'ozet_malzeme', 'baslangic' => $baslangic, 'bitis' => $bitis];
        $malzemeUrlGoster = '?' . http_build_query(array_merge($malzemeQuery, ['malzeme_bos_gizle' => 1]));
        $malzemeUrlGizle  = '?' . http_build_query(array_merge($malzemeQuery, ['malzeme_bos_gizle' => 0]));
        $sumDonemKg = $sumDonemTutar = $sumGenelKg = $sumGenelTutar = 0;
        foreach ($ozetMalzemeListe as $m) {
            $sumDonemKg += (float)($m['donem_net_kg'] ?? 0);
            $sumDonemTutar += (float)($m['donem_tutar'] ?? 0);
            $sumGenelKg += (float)($m['genel_net_kg'] ?? 0);
            $sumGenelTutar += (float)($m['genel_tutar'] ?? 0);
        }
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
                <p class="text-muted small">Sadece satış (TAHAKKUK) hareketleri. Dönem: <?php echo htmlspecialchars($baslangic); ?> – <?php echo htmlspecialchars($bitis); ?>. Genel: baştan son tarihe kadar. <?php if ($malzeme_bos_gizle === 0): ?>Seçili dönemde satışı olmayan malzemeler gizlenmiştir.<?php endif; ?></p>
                <div class="table-responsive">
                    <table class="table table-hover table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Malzeme (Döküm Tipi)</th>
                                <th class="text-end">Dönem Net (kg)</th>
                                <th class="text-end">Dönem Tutar (₺)</th>
                                <th class="text-end">Genel Net (kg)</th>
                                <th class="text-end">Genel Tutar (₺)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ozetMalzemeListe as $m): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($m['malzeme'] ?? '-'); ?></td>
                                    <td class="text-end"><?php echo number_format((float)($m['donem_net_kg'] ?? 0), 0, ',', '.'); ?></td>
                                    <td class="text-end"><?php echo number_format((float)($m['donem_tutar'] ?? 0), 2, ',', '.'); ?></td>
                                    <td class="text-end"><?php echo number_format((float)($m['genel_net_kg'] ?? 0), 0, ',', '.'); ?></td>
                                    <td class="text-end"><?php echo number_format((float)($m['genel_tutar'] ?? 0), 2, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php if (!empty($ozetMalzemeListe)): ?>
                        <tfoot class="table-light">
                            <tr>
                                <th>Toplam</th>
                                <th class="text-end"><?php echo number_format($sumDonemKg, 0, ',', '.'); ?></th>
                                <th class="text-end"><?php echo number_format($sumDonemTutar, 2, ',', '.'); ?></th>
                                <th class="text-end"><?php echo number_format($sumGenelKg, 0, ',', '.'); ?></th>
                                <th class="text-end"><?php echo number_format($sumGenelTutar, 2, ',', '.'); ?></th>
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

<?php if ($rapor === 'cari'): ?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var el = document.getElementById('cariFirmaSelect');
    if (el && typeof jQuery !== 'undefined' && jQuery.fn.select2) {
        jQuery('#cariFirmaSelect').select2({
            theme: 'bootstrap-5',
            placeholder: 'Müşteri ara veya seçin...',
            allowClear: true,
            width: '100%'
        });
    }
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
