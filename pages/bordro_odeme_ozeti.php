<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Bordro Ödeme Özeti';

$ay = isset($_GET['ay']) ? (int)$_GET['ay'] : date('n');
$yil = isset($_GET['yil']) ? (int)$_GET['yil'] : date('Y');
if ($ay < 1 || $ay > 12) $ay = date('n');
if ($yil < 2000 || $yil > 2100) $yil = date('Y');

try {
    $stmt = $pdo->prepare("SELECT p.ad_soyad,
        GREATEST(b.brut_maas + b.ek_odenek - (COALESCE(b.izin_kesintisi,0)+COALESCE(b.sgk_kesintisi,0)+COALESCE(b.diger_kesintiler,0)), 0) as toplam_odenecek,
        GREATEST((b.brut_maas - b.sgk_banka) - (COALESCE(b.izin_kesintisi,0)+COALESCE(b.sgk_kesintisi,0)+COALESCE(b.diger_kesintiler,0)), 0) as nakit_pay,
        GREATEST(b.sgk_banka - GREATEST((COALESCE(b.izin_kesintisi,0)+COALESCE(b.sgk_kesintisi,0)+COALESCE(b.diger_kesintiler,0)) - (b.brut_maas - b.sgk_banka), 0), 0) as banka_pay
        FROM bordro b
        LEFT JOIN personel_listesi p ON b.personel_id = p.id
        WHERE b.ay = ? AND b.yil = ?
        ORDER BY p.ad_soyad");
    $stmt->execute([$ay, $yil]);
    $rows = $stmt->fetchAll();
} catch(PDOException $e) {
    $rows = [];
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-receipt"></i> Bordro Ödeme Özeti</h1>
</div>

<form class="row g-3 mb-4" method="GET">
    <div class="col-md-4">
        <label class="form-label">Ay</label>
        <select class="form-select" name="ay">
            <?php for($i=1; $i<=12; $i++): ?>
                <option value="<?php echo $i; ?>" <?php echo $i == $ay ? 'selected' : ''; ?>>
                    <?php echo getTurkishMonthName($i); ?>
                </option>
            <?php endfor; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Yıl</label>
        <input type="number" class="form-control" name="yil" value="<?php echo $yil; ?>" min="2020" max="<?php echo date('Y')+1; ?>">
    </div>
    <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-primary w-100">Filtrele</button>
    </div>
</form>

<?php if (empty($rows)): ?>
    <div class="alert alert-info"><i class="bi bi-info-circle"></i> Bu ay için bordro verisi bulunamadı.</div>
<?php else: ?>
    <?php
        $sumBanka = 0; $sumNakit = 0; $sumToplam = 0;
        foreach($rows as $r) {
            $sumBanka += $r['banka_pay'];
            $sumNakit += $r['nakit_pay'];
            $sumToplam += $r['toplam_odenecek'];
        }
    ?>
    <div class="card mb-3">
        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-4"><h6>Toplam Banka</h6><h4 class="text-success"><?php echo formatMoney($sumBanka); ?></h4></div>
                <div class="col-md-4"><h6>Toplam Nakit</h6><h4 class="text-warning"><?php echo formatMoney($sumNakit); ?></h4></div>
                <div class="col-md-4"><h6>Toplam Ödenecek</h6><h4 class="text-primary"><?php echo formatMoney($sumToplam); ?></h4></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Personel</th>
                            <th class="money">Banka</th>
                            <th class="money">Nakit</th>
                            <th class="money">Toplam</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($rows as $r): ?>
                            <tr>
                                <td><?php echo escape($r['ad_soyad']); ?></td>
                                <td class="money"><?php echo formatMoney($r['banka_pay']); ?></td>
                                <td class="money"><?php echo formatMoney($r['nakit_pay']); ?></td>
                                <td class="money"><?php echo formatMoney($r['toplam_odenecek']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-primary">
                            <th>Toplam</th>
                            <th class="money"><?php echo formatMoney($sumBanka); ?></th>
                            <th class="money"><?php echo formatMoney($sumNakit); ?></th>
                            <th class="money"><?php echo formatMoney($sumToplam); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>


