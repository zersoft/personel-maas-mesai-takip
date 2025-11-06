<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Raporlar';

// Varsayılan dönem: bordro tablosundaki en son (yil, ay); yoksa mevcut tarih
try {
    $sonDonem = $pdo->query("SELECT ay, yil FROM bordro ORDER BY yil DESC, ay DESC LIMIT 1")->fetch();
} catch(PDOException $e) {
    $sonDonem = false;
}
$varsayilanAy = $sonDonem ? (int)$sonDonem['ay'] : (int)date('n');
$varsayilanYil = $sonDonem ? (int)$sonDonem['yil'] : (int)date('Y');

// Rapor verileri - SQL injection koruması için integer cast ve validasyon
$ay = isset($_GET['ay']) ? (int)$_GET['ay'] : $varsayilanAy;
$yil = isset($_GET['yil']) ? (int)$_GET['yil'] : $varsayilanYil;

// Kart toplamları ve liste başlangıçta boş; dolarsa tekrar yazmayacağız
$bordroToplamSonuc = null;
$bankaToplamSonuc = null;
$nakitToplamSonuc = null;
$personelBordroListe = [];

// Validasyon
if ($ay < 1 || $ay > 12) {
    $ay = $varsayilanAy;
}
if ($yil < 2000 || $yil > 2100) {
    $yil = $varsayilanYil;
}

// Şema yeteneklerini tespit et (migrasyon durumuna göre esnek sorgular)
function columnExists(PDO $pdo, $table, $column) {
    try {
        $q = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
        $q->execute([$table, $column]);
        return (bool)$q->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}

$hasEkSplit = columnExists($pdo, 'bordro', 'ek_odenek_banka') && columnExists($pdo, 'bordro', 'ek_odenek_nakit');
$hasEkOdenek = $hasEkSplit || columnExists($pdo, 'bordro', 'ek_odenek');
$hasBankaAvansCol = columnExists($pdo, 'bordro', 'banka_avans');
$hasNakitAvansCol = columnExists($pdo, 'bordro', 'nakit_avans');
try {
    // 1) Ödeme özeti ile aynı çekirdek sorgu: satır bazlı banka/nakit/toplam
    $coreStmt = $pdo->prepare("SELECT p.ad_soyad,
        (GREATEST((b.brut_maas - b.sgk_banka) - (COALESCE(b.izin_kesintisi,0)+COALESCE(b.sgk_kesintisi,0)+COALESCE(b.diger_kesintiler,0)), 0) - COALESCE(b.nakit_avans, a.nakit_avans, 0) + COALESCE(b.ek_odenek_nakit,0)) AS nakit_pay,
        (GREATEST(b.sgk_banka - GREATEST((COALESCE(b.izin_kesintisi,0)+COALESCE(b.sgk_kesintisi,0)+COALESCE(b.diger_kesintiler,0)) - (b.brut_maas - b.sgk_banka), 0), 0) - COALESCE(b.banka_avans, a.banka_avans, 0) + COALESCE(b.ek_odenek_banka,0)) AS banka_pay,
        GREATEST((GREATEST((b.brut_maas - b.sgk_banka) - (COALESCE(b.izin_kesintisi,0)+COALESCE(b.sgk_kesintisi,0)+COALESCE(b.diger_kesintiler,0)), 0) - COALESCE(b.nakit_avans, a.nakit_avans, 0) + COALESCE(b.ek_odenek_nakit,0))
               + (GREATEST(b.sgk_banka - GREATEST((COALESCE(b.izin_kesintisi,0)+COALESCE(b.sgk_kesintisi,0)+COALESCE(b.diger_kesintiler,0)) - (b.brut_maas - b.sgk_banka), 0), 0) - COALESCE(b.banka_avans, a.banka_avans, 0) + COALESCE(b.ek_odenek_banka,0)), 0) AS toplam_odenecek
        FROM bordro b
        LEFT JOIN personel_listesi p ON b.personel_id = p.id
        LEFT JOIN (
            SELECT personel_id, SUM(banka_tutari) AS banka_avans, SUM(nakit_tutari) AS nakit_avans
            FROM avans_takip
            WHERE ( (bordro_ay = ? AND bordro_yil = ?) OR (bordro_ay IS NULL AND bordro_yil IS NULL AND MONTH(tarih) = ? AND YEAR(tarih) = ?) )
            GROUP BY personel_id
        ) a ON a.personel_id = b.personel_id
        WHERE b.ay = ? AND b.yil = ?
        ORDER BY p.ad_soyad");
    $coreStmt->execute([$ay, $yil, $ay, $yil, $ay, $yil]);
    $coreRows = $coreStmt->fetchAll();
    if (!empty($coreRows)) {
        // Satırlardan kart toplamlarını türet
        $bankaToplamSonuc = array_sum(array_map(function($r){ return (float)$r['banka_pay']; }, $coreRows));
        $nakitToplamSonuc = array_sum(array_map(function($r){ return (float)$r['nakit_pay']; }, $coreRows));
        $bordroToplamSonuc = array_sum(array_map(function($r){ return (float)$r['toplam_odenecek']; }, $coreRows));
        $personelBordroListe = $coreRows;
    }

    // Aylık bordro toplamı: (Brüt − Kesintiler) + Ek Ödenek(kanal bazlı varsa topla, yoksa ek_odenek)
    if ($bordroToplamSonuc === null) {
        if ($hasEkSplit) {
            $bordroToplam = $pdo->prepare("SELECT SUM(GREATEST(brut_maas - (COALESCE(izin_kesintisi, 0) + COALESCE(sgk_kesintisi, 0) + COALESCE(diger_kesintiler, 0)), 0) + COALESCE(ek_odenek_banka,0) + COALESCE(ek_odenek_nakit,0)) as toplam FROM bordro WHERE ay = ? AND yil = ?");
        } elseif ($hasEkOdenek) {
            $bordroToplam = $pdo->prepare("SELECT SUM(GREATEST(brut_maas - (COALESCE(izin_kesintisi, 0) + COALESCE(sgk_kesintisi, 0) + COALESCE(diger_kesintiler, 0)), 0) + COALESCE(ek_odenek,0)) as toplam FROM bordro WHERE ay = ? AND yil = ?");
        } else {
            $bordroToplam = $pdo->prepare("SELECT SUM(GREATEST(brut_maas - (COALESCE(izin_kesintisi, 0) + COALESCE(sgk_kesintisi, 0) + COALESCE(diger_kesintiler, 0)), 0)) as toplam FROM bordro WHERE ay = ? AND yil = ?");
        }
        $bordroToplam->execute([$ay, $yil]);
        $bordroToplamSonuc = $bordroToplam->fetch()['toplam'] ?? 0;
    }
    
    // Aylık banka ödemesi toplamı (avans banka düşülmüş): Kesinti önce nakitten, kalanı bankadan
    $ekBankaExpr = $hasEkSplit ? "COALESCE(b.ek_odenek_banka,0)" : ($hasEkOdenek ? "COALESCE(b.ek_odenek,0)" : "0");
    $bankaToplamSql = "SELECT SUM(
        GREATEST(
            b.sgk_banka - GREATEST((COALESCE(b.izin_kesintisi,0)+COALESCE(b.sgk_kesintisi,0)+COALESCE(b.diger_kesintiler,0)) - (b.brut_maas - b.sgk_banka), 0) - " . ($hasBankaAvansCol ? "COALESCE(b.banka_avans, a.banka_avans, 0)" : "COALESCE(a.banka_avans,0)") . "
        , 0)
    ) + {$ekBankaExpr} as toplam
    FROM bordro b
    LEFT JOIN (
        SELECT personel_id, SUM(banka_tutari) AS banka_avans, SUM(nakit_tutari) AS nakit_avans
        FROM avans_takip
        WHERE ( (bordro_ay = ? AND bordro_yil = ?) OR (bordro_ay IS NULL AND bordro_yil IS NULL AND MONTH(tarih) = ? AND YEAR(tarih) = ?) )
        GROUP BY personel_id
    ) a ON a.personel_id = b.personel_id
    WHERE b.ay = ? AND b.yil = ?";
    if ($bankaToplamSonuc === null) {
        $bankaToplam = $pdo->prepare($bankaToplamSql);
        $bankaToplam->execute([$ay, $yil, $ay, $yil, $ay, $yil]);
        $bankaToplamSonuc = $bankaToplam->fetch()['toplam'] ?? 0;
    }
    
    // Aylık nakit ödemesi toplamı (avans nakit düşülmüş): (Nakit Baz - Kesinti) - avans_nakit
    $ekNakitExpr = $hasEkSplit ? "COALESCE(b.ek_odenek_nakit,0)" : ($hasEkOdenek ? "COALESCE(b.ek_odenek,0)" : "0");
    $nakitToplamSql = "SELECT SUM(
        GREATEST((b.brut_maas - b.sgk_banka) - (COALESCE(b.izin_kesintisi,0)+COALESCE(b.sgk_kesintisi,0)+COALESCE(b.diger_kesintiler,0)) - " . ($hasNakitAvansCol ? "COALESCE(b.nakit_avans, a.nakit_avans, 0)" : "COALESCE(a.nakit_avans,0)") . ", 0) + {$ekNakitExpr}
    ) as toplam
    FROM bordro b
    LEFT JOIN (
        SELECT personel_id, SUM(banka_tutari) AS banka_avans, SUM(nakit_tutari) AS nakit_avans
        FROM avans_takip
        WHERE ( (bordro_ay = ? AND bordro_yil = ?) OR (bordro_ay IS NULL AND bordro_yil IS NULL AND MONTH(tarih) = ? AND YEAR(tarih) = ?) )
        GROUP BY personel_id
    ) a ON a.personel_id = b.personel_id
    WHERE b.ay = ? AND b.yil = ?";
    if ($nakitToplamSonuc === null) {
        $nakitToplam = $pdo->prepare($nakitToplamSql);
        $nakitToplam->execute([$ay, $yil, $ay, $yil, $ay, $yil]);
        $nakitToplamSonuc = $nakitToplam->fetch()['toplam'] ?? 0;
    }

    // Ek ödenek kartı kaldırıldı (ödemeler banka/nakit ayrımıyla listeleniyor)
    
    // Aylık fazla mesai toplamı
    $fmToplam = $pdo->prepare("SELECT SUM(saat) as toplam FROM fazla_mesai WHERE MONTH(tarih) = ? AND YEAR(tarih) = ?");
    $fmToplam->execute([$ay, $yil]);
    $fmToplamSonuc = $fmToplam->fetch()['toplam'] ?? 0;
    
    // Aylık avans toplamı (banka+nakit) - öncelik bordro_ay/yıl; yoksa tarih ay/yıl
    $avansToplam = $pdo->prepare("SELECT SUM(COALESCE(banka_tutari,0) + COALESCE(nakit_tutari,0)) as toplam
        FROM avans_takip
        WHERE ( (bordro_ay = ? AND bordro_yil = ?) OR (bordro_ay IS NULL AND bordro_yil IS NULL AND MONTH(tarih) = ? AND YEAR(tarih) = ?) )");
    $avansToplam->execute([$ay, $yil, $ay, $yil]);
    $avansToplamSonuc = $avansToplam->fetch()['toplam'] ?? 0;
    
    // Aylık tazminat toplamı
    $tazminatToplam = $pdo->prepare("SELECT SUM(tutar) as toplam FROM tazminat_takip WHERE MONTH(tarih) = ? AND YEAR(tarih) = ?");
    $tazminatToplam->execute([$ay, $yil]);
    $tazminatToplamSonuc = $tazminatToplam->fetch()['toplam'] ?? 0;
    
    // Personel bazlı bordro listesi - avans kanal bazında düşülmüş (bordro kolonları öncelikli)
    $nakitPayExpr = "GREATEST((b.brut_maas - b.sgk_banka) - (COALESCE(b.izin_kesintisi,0)+COALESCE(b.sgk_kesintisi,0)+COALESCE(b.diger_kesintiler,0)), 0) - " . ($hasNakitAvansCol ? "COALESCE(b.nakit_avans, a.nakit_avans, 0)" : "COALESCE(a.nakit_avans,0)") . " + " . $ekNakitExpr;
    $bankaPayExpr = "GREATEST(b.sgk_banka - GREATEST((COALESCE(b.izin_kesintisi,0)+COALESCE(b.sgk_kesintisi,0)+COALESCE(b.diger_kesintiler,0)) - (b.brut_maas - b.sgk_banka), 0), 0) - " . ($hasBankaAvansCol ? "COALESCE(b.banka_avans, a.banka_avans, 0)" : "COALESCE(a.banka_avans,0)") . " + " . $ekBankaExpr;
    $toplamPayExpr = "GREATEST((" . $nakitPayExpr . ") + (" . $bankaPayExpr . "), 0)";
    $personelBordroSql = "SELECT p.ad_soyad,
        ({$nakitPayExpr}) AS nakit_pay,
        ({$bankaPayExpr}) AS banka_pay,
        ({$toplamPayExpr}) AS toplam_odenecek
        FROM bordro b
        LEFT JOIN personel_listesi p ON b.personel_id = p.id
        LEFT JOIN (
            SELECT personel_id, SUM(banka_tutari) AS banka_avans, SUM(nakit_tutari) AS nakit_avans
            FROM avans_takip
            WHERE ( (bordro_ay = ? AND bordro_yil = ?) OR (bordro_ay IS NULL AND bordro_yil IS NULL AND MONTH(tarih) = ? AND YEAR(tarih) = ?) )
            GROUP BY personel_id
        ) a ON a.personel_id = b.personel_id
        WHERE b.ay = ? AND b.yil = ?
        ORDER BY p.ad_soyad";
    if (empty($personelBordroListe)) {
        $personelBordro = $pdo->prepare($personelBordroSql);
        $personelBordro->execute([$ay, $yil, $ay, $yil, $ay, $yil]);
        $personelBordroListe = $personelBordro->fetchAll();
    }

    // Fallback: Eğer yukarıdaki sorgu 0 dönerse, en azından satırları gösterip PHP tarafında hesaplayalım
    if (empty($personelBordroListe)) {
        $selectCols = [
            'b.personel_id', 'p.ad_soyad',
            'b.brut_maas', 'b.sgk_banka',
            'b.izin_kesintisi', 'b.sgk_kesintisi', 'b.diger_kesintiler'
        ];
        if ($hasBankaAvansCol) $selectCols[] = 'b.banka_avans';
        if ($hasNakitAvansCol) $selectCols[] = 'b.nakit_avans';
        if ($hasEkSplit) {
            $selectCols[] = 'b.ek_odenek_banka';
            $selectCols[] = 'b.ek_odenek_nakit';
        } elseif ($hasEkOdenek) {
            $selectCols[] = 'b.ek_odenek';
        }
        $sqlBasic = "SELECT " . implode(', ', $selectCols) . "
            FROM bordro b
            LEFT JOIN personel_listesi p ON b.personel_id = p.id
            WHERE b.ay = ? AND b.yil = ?
            ORDER BY p.ad_soyad";
        $stmtBasic = $pdo->prepare($sqlBasic);
        $stmtBasic->execute([$ay, $yil]);
        $rows = $stmtBasic->fetchAll();

        $computed = [];
        foreach ($rows as $r) {
            $brut = (float)($r['brut_maas'] ?? 0);
            $banka = (float)($r['sgk_banka'] ?? 0);
            $izin = (float)($r['izin_kesintisi'] ?? 0);
            $sgkK = (float)($r['sgk_kesintisi'] ?? 0);
            $diger = (float)($r['diger_kesintiler'] ?? 0);
            $kesintiler = $izin + $sgkK + $diger;
            $cashBase = $brut - $banka;
            if ($cashBase < 0) $cashBase = 0;
            $nakitNet = max($cashBase - $kesintiler, 0);
            $bankaNet = max($banka - max($kesintiler - $cashBase, 0), 0);
            $bankaAvans = (float)($r['banka_avans'] ?? 0);
            $nakitAvans = (float)($r['nakit_avans'] ?? 0);
            if ($hasBankaAvansCol || $hasNakitAvansCol) {
                $bankaNet = max($bankaNet - $bankaAvans, 0);
                $nakitNet = max($nakitNet - $nakitAvans, 0);
            }
            if ($hasEkSplit) {
                $bankaNet += (float)($r['ek_odenek_banka'] ?? 0);
                $nakitNet += (float)($r['ek_odenek_nakit'] ?? 0);
            } elseif ($hasEkOdenek) {
                $nakitNet += (float)($r['ek_odenek'] ?? 0);
            }
            $toplam = max($bankaNet + $nakitNet, 0);
            $computed[] = [
                'ad_soyad' => $r['ad_soyad'] ?? '',
                'banka_pay' => $bankaNet,
                'nakit_pay' => $nakitNet,
                'toplam_odenecek' => $toplam
            ];
        }
        if (!empty($computed)) {
            $personelBordroListe = $computed;
            // Toplam kartlarını da dolduralım
            $bankaToplamSonuc = array_sum(array_map(function($x){ return $x['banka_pay']; }, $computed));
            $nakitToplamSonuc = array_sum(array_map(function($x){ return $x['nakit_pay']; }, $computed));
            $bordroToplamSonuc = array_sum(array_map(function($x){ return $x['toplam_odenecek']; }, $computed));
        }
    }
    
} catch(PDOException $e) {
    $bordroToplamSonuc = 0;
    $bankaToplamSonuc = 0;
    $nakitToplamSonuc = 0;
    $fmToplamSonuc = 0;
    $avansToplamSonuc = 0;
    $tazminatToplamSonuc = 0;
    $personelBordroListe = [];
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-graph-up"></i> Aylık Raporlar</h1>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <select class="form-select" name="ay">
                    <?php for($i=1; $i<=12; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo $i == $ay ? 'selected' : ''; ?>>
                            <?php echo getTurkishMonthName($i); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-4">
                <input type="number" class="form-control" name="yil" value="<?php echo $yil; ?>" min="2020" max="<?php echo date('Y')+1; ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filtrele</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card border-primary">
            <div class="card-body text-center">
                <h6 class="card-title text-muted">Toplam Bordro</h6>
                <h3 class="text-primary"><?php echo formatMoney($bordroToplamSonuc); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-success">
            <div class="card-body text-center">
                <h6 class="card-title text-muted">Banka Ödemesi</h6>
                <h3 class="text-success"><?php echo formatMoney($bankaToplamSonuc); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-warning">
            <div class="card-body text-center">
                <h6 class="card-title text-muted">Nakit Ödemesi</h6>
                <h3 class="text-warning"><?php echo formatMoney($nakitToplamSonuc); ?></h3>
            </div>
        </div>
    </div>
    
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card border-info">
            <div class="card-body text-center">
                <h6 class="card-title text-muted">Toplam Avans</h6>
                <h3 class="text-info"><?php echo formatMoney($avansToplamSonuc); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-danger">
            <div class="card-body text-center">
                <h6 class="card-title text-muted">Toplam Tazminat</h6>
                <h3 class="text-danger"><?php echo formatMoney($tazminatToplamSonuc); ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Personel Bazlı Bordro Dağılımı - <?php echo getTurkishMonthName($ay) . ' ' . $yil; ?></h5>
            </div>
            <div class="card-body">
                <?php if (empty($personelBordroListe)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Bu ay için bordro kaydı bulunmamaktadır.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Personel</th>
                                    <th class="money">Toplam Ödenecek</th>
                                    <th class="money">Banka</th>
                                    <th class="money">Nakit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($personelBordroListe as $pb): ?>
                                    <tr>
                                        <td><?php echo escape($pb['ad_soyad']); ?></td>
                                        <td class="money"><?php echo formatMoney($pb['toplam_odenecek']); ?></td>
                                        <td class="money"><?php echo formatMoney($pb['banka_pay'] ?? 0); ?></td>
                                        <td class="money"><?php echo formatMoney($pb['nakit_pay'] ?? 0); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-primary">
                                    <th>Toplam</th>
                                    <th class="money"><?php echo formatMoney($bordroToplamSonuc); ?></th>
                                    <th class="money"><?php echo formatMoney($bankaToplamSonuc); ?></th>
                                    <th class="money"><?php echo formatMoney($nakitToplamSonuc); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

