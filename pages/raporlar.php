<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Raporlar';

// Rapor verileri - SQL injection koruması için integer cast ve validasyon
$ay = isset($_GET['ay']) ? (int)$_GET['ay'] : date('n');
$yil = isset($_GET['yil']) ? (int)$_GET['yil'] : date('Y');

// Validasyon
if ($ay < 1 || $ay > 12) {
    $ay = date('n');
}
if ($yil < 2000 || $yil > 2100) {
    $yil = date('Y');
}

try {
    // Aylık bordro toplamı (toplam ödenecek) - negatif olamaz
    $bordroToplam = $pdo->prepare("SELECT SUM(GREATEST(brut_maas + ek_odenek - (COALESCE(izin_kesintisi, 0) + COALESCE(sgk_kesintisi, 0) + COALESCE(diger_kesintiler, 0)), 0)) as toplam FROM bordro WHERE ay = ? AND yil = ?");
    $bordroToplam->execute([$ay, $yil]);
    $bordroToplamSonuc = $bordroToplam->fetch()['toplam'] ?? 0;
    
    // Aylık banka ödemesi toplamı: Kesinti önce nakitten (brüt−banka), kalanı bankadan
    $bankaToplam = $pdo->prepare("SELECT SUM(
        GREATEST(
            sgk_banka - GREATEST((COALESCE(izin_kesintisi,0)+COALESCE(sgk_kesintisi,0)+COALESCE(diger_kesintiler,0)) - (brut_maas - sgk_banka), 0)
        , 0)
    ) as toplam FROM bordro WHERE ay = ? AND yil = ?");
    $bankaToplam->execute([$ay, $yil]);
    $bankaToplamSonuc = $bankaToplam->fetch()['toplam'] ?? 0;
    
    // Aylık nakit ödemesi toplamı: (Nakit Baz - Kesinti)
    $nakitToplam = $pdo->prepare("SELECT SUM(GREATEST((brut_maas - sgk_banka) - (COALESCE(izin_kesintisi,0)+COALESCE(sgk_kesintisi,0)+COALESCE(diger_kesintiler,0)), 0)) as toplam FROM bordro WHERE ay = ? AND yil = ?");
    $nakitToplam->execute([$ay, $yil]);
    $nakitToplamSonuc = $nakitToplam->fetch()['toplam'] ?? 0;

    // Ek ödenek kartı kaldırıldı (ödemeler banka/nakit ayrımıyla listeleniyor)
    
    // Aylık fazla mesai toplamı
    $fmToplam = $pdo->prepare("SELECT SUM(saat) as toplam FROM fazla_mesai WHERE MONTH(tarih) = ? AND YEAR(tarih) = ?");
    $fmToplam->execute([$ay, $yil]);
    $fmToplamSonuc = $fmToplam->fetch()['toplam'] ?? 0;
    
    // Aylık avans toplamı
    $avansToplam = $pdo->prepare("SELECT SUM(avans_tutari) as toplam FROM avans_takip WHERE MONTH(tarih) = ? AND YEAR(tarih) = ?");
    $avansToplam->execute([$ay, $yil]);
    $avansToplamSonuc = $avansToplam->fetch()['toplam'] ?? 0;
    
    // Aylık tazminat toplamı
    $tazminatToplam = $pdo->prepare("SELECT SUM(tutar) as toplam FROM tazminat_takip WHERE MONTH(tarih) = ? AND YEAR(tarih) = ?");
    $tazminatToplam->execute([$ay, $yil]);
    $tazminatToplamSonuc = $tazminatToplam->fetch()['toplam'] ?? 0;
    
    // Personel bazlı bordro listesi - kesinti önce nakit (brüt−banka), kalanı banka
    $personelBordro = $pdo->prepare("SELECT p.ad_soyad,
        GREATEST(b.brut_maas + b.ek_odenek - (COALESCE(b.izin_kesintisi,0)+COALESCE(b.sgk_kesintisi,0)+COALESCE(b.diger_kesintiler,0)), 0) as toplam_odenecek,
        GREATEST((b.brut_maas - b.sgk_banka) - (COALESCE(b.izin_kesintisi,0)+COALESCE(b.sgk_kesintisi,0)+COALESCE(b.diger_kesintiler,0)), 0) as nakit_pay,
        GREATEST(b.sgk_banka - GREATEST((COALESCE(b.izin_kesintisi,0)+COALESCE(b.sgk_kesintisi,0)+COALESCE(b.diger_kesintiler,0)) - (b.brut_maas - b.sgk_banka), 0), 0) as banka_pay
        FROM bordro b
        LEFT JOIN personel_listesi p ON b.personel_id = p.id
        WHERE b.ay = ? AND b.yil = ?
        ORDER BY p.ad_soyad");
    $personelBordro->execute([$ay, $yil]);
    $personelBordroListe = $personelBordro->fetchAll();
    
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

