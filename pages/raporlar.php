<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Raporlar';

// Rapor verileri
$ay = isset($_GET['ay']) ? $_GET['ay'] : date('n');
$yil = isset($_GET['yil']) ? $_GET['yil'] : date('Y');

try {
    // Aylık bordro toplamı
    $bordroToplam = $pdo->prepare("SELECT SUM(toplam_odeme) as toplam FROM bordro WHERE ay = ? AND yil = ?");
    $bordroToplam->execute([$ay, $yil]);
    $bordroToplamSonuc = $bordroToplam->fetch()['toplam'] ?? 0;
    
    // Aylık banka ödemesi toplamı
    $bankaToplam = $pdo->prepare("SELECT SUM(toplam_odeme) as toplam FROM bordro WHERE ay = ? AND yil = ? AND (odeme_tipi = 'BANKA' OR odeme_tipi IS NULL)");
    $bankaToplam->execute([$ay, $yil]);
    $bankaToplamSonuc = $bankaToplam->fetch()['toplam'] ?? 0;
    
    // Aylık nakit ödemesi toplamı
    $nakitToplam = $pdo->prepare("SELECT SUM(toplam_odeme) as toplam FROM bordro WHERE ay = ? AND yil = ? AND odeme_tipi = 'NAKIT'");
    $nakitToplam->execute([$ay, $yil]);
    $nakitToplamSonuc = $nakitToplam->fetch()['toplam'] ?? 0;
    
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
    
    // Personel bazlı bordro listesi
    $personelBordro = $pdo->prepare("SELECT p.ad_soyad, b.toplam_odeme, b.odeme_tipi 
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
                            <?php echo date('F', mktime(0,0,0,$i,1)); ?>
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
    <div class="col-md-3">
        <div class="card border-info">
            <div class="card-body text-center">
                <h6 class="card-title text-muted">Fazla Mesai (Saat)</h6>
                <h3 class="text-info"><?php echo number_format($fmToplamSonuc, 1); ?></h3>
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
                <h5 class="mb-0">Personel Bazlı Bordro Dağılımı - <?php echo date('F', mktime(0,0,0,$ay,1)) . ' ' . $yil; ?></h5>
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
                                    <th>Toplam Ödeme</th>
                                    <th>Ödeme Tipi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($personelBordroListe as $pb): ?>
                                    <tr>
                                        <td><?php echo escape($pb['ad_soyad']); ?></td>
                                        <td><?php echo formatMoney($pb['toplam_odeme']); ?></td>
                                        <td>
                                            <?php if(isset($pb['odeme_tipi']) && $pb['odeme_tipi'] == 'NAKIT'): ?>
                                                <span class="badge bg-success">NAKIT</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary">BANKA</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-primary">
                                    <th>Toplam</th>
                                    <th><?php echo formatMoney($bordroToplamSonuc); ?></th>
                                    <th>
                                        <small>Banka: <?php echo formatMoney($bankaToplamSonuc); ?> | 
                                        Nakit: <?php echo formatMoney($nakitToplamSonuc); ?></small>
                                    </th>
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

