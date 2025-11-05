<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Toplu Bordro Oluşturma';

$ay = isset($_GET['ay']) ? $_GET['ay'] : date('n');
$yil = isset($_GET['yil']) ? $_GET['yil'] : date('Y');

// Aktif personelleri getir
try {
    $personeller = $pdo->query("SELECT * FROM personel_listesi WHERE aktif = 1 ORDER BY ad_soyad")->fetchAll();
    
    // Seçilen ay için mevcut bordroları kontrol et
    $mevcutBordrolar = [];
    $stmt = $pdo->prepare("SELECT personel_id FROM bordro WHERE ay = ? AND yil = ?");
    $stmt->execute([$ay, $yil]);
    foreach($stmt->fetchAll() as $row) {
        $mevcutBordrolar[] = $row['personel_id'];
    }
} catch(PDOException $e) {
    $personeller = [];
    $mevcutBordrolar = [];
}

include '../includes/header.php';

// Mesaj gösterimi
if (isset($_GET['success'])) {
    echo showMessage('Toplu bordro başarıyla oluşturuldu!', 'success');
}
if (isset($_GET['error'])) {
    echo showMessage('Hata: ' . escape($_GET['error']), 'danger');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-file-earmark-spreadsheet"></i> Toplu Bordro Oluşturma</h1>
    <a href="bordro.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Bordro Listesi
    </a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Ay</label>
                <select class="form-select" name="ay">
                    <?php for($i=1; $i<=12; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo $i == $ay ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0,0,0,$i,1)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Yıl</label>
                <input type="number" class="form-control" name="yil" value="<?php echo $yil; ?>" min="2020" max="<?php echo date('Y')+1; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">Filtrele</button>
            </div>
        </form>
    </div>
</div>

<?php if (empty($personeller)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> Aktif personel bulunmamaktadır.
    </div>
<?php else: ?>
    <form action="toplu_bordro_islem.php" method="POST">
        <input type="hidden" name="ay" value="<?php echo $ay; ?>">
        <input type="hidden" name="yil" value="<?php echo $yil; ?>">
        
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?php echo date('F', mktime(0,0,0,$ay,1)) . ' ' . $yil; ?> - Personel Bordroları</h5>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-save"></i> Tümünü Kaydet
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Personel</th>
                                <th>Maaş</th>
                                <th>Maaş SGK</th>
                                <th>Brüt Maaş</th>
                                <th>SGK/Banka</th>
                                <th>Ek Ödenek</th>
                                <th>Ödeme Tipi</th>
                                <th>İzin Günü</th>
                                <th>İzin Kes.</th>
                                <th>SGK Kes.</th>
                                <th>Diğer Kes.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($personeller as $index => $personel): ?>
                                <?php 
                                $personelId = $personel['id'];
                                $mevcutMu = in_array($personelId, $mevcutBordrolar);
                                ?>
                                <tr class="<?php echo $mevcutMu ? 'table-warning' : ''; ?>">
                                    <td>
                                        <?php echo escape($personel['ad_soyad']); ?>
                                        <?php if($mevcutMu): ?>
                                            <small class="text-muted d-block">(Mevcut)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo formatMoney($personel['maas']); ?></td>
                                    <td><?php echo formatMoney($personel['maas_sgk']); ?></td>
                                    <td>
                                        <input type="hidden" name="personel_id[]" value="<?php echo $personelId; ?>">
                                        <input type="number" step="0.01" class="form-control form-control-sm" 
                                               name="brut_maas[]" value="<?php echo $personel['maas']; ?>" required>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" class="form-control form-control-sm" 
                                               name="sgk_banka[]" value="<?php echo $personel['maas_sgk']; ?>" required>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" class="form-control form-control-sm" 
                                               name="ek_odenek[]" value="0" required>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" name="odeme_tipi[]">
                                            <option value="BANKA" selected>BANKA</option>
                                            <option value="NAKIT">NAKIT</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" step="0.5" class="form-control form-control-sm" 
                                               name="izin_gunu[]" value="0">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" class="form-control form-control-sm" 
                                               name="izin_kesintisi[]" value="0">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" class="form-control form-control-sm" 
                                               name="sgk_kesintisi[]" value="0">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" class="form-control form-control-sm" 
                                               name="diger_kesintiler[]" value="0">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </form>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

