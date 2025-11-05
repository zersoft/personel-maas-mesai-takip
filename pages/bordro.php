<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Bordro Yönetimi';

// Bordro listesi
try {
    $stmt = $pdo->query("SELECT b.*, p.ad_soyad 
                         FROM bordro b 
                         LEFT JOIN personel_listesi p ON b.personel_id = p.id 
                         ORDER BY b.ay DESC, b.yil DESC");
    $bordrolar = $stmt->fetchAll();
} catch(PDOException $e) {
    $bordrolar = [];
}

include '../includes/header.php';

// Mesaj gösterimi
if (isset($_GET['success'])) {
    echo showMessage('Bordro başarıyla kaydedildi!', 'success');
}
if (isset($_GET['error'])) {
    echo showMessage('Hata: ' . escape($_GET['error']), 'danger');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-cash-coin"></i> Bordro Yönetimi</h1>
    <div>
        <a href="toplu_bordro.php" class="btn btn-success me-2">
            <i class="bi bi-file-earmark-spreadsheet"></i> Toplu Bordro Oluştur
        </a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bordroEkleModal">
            <i class="bi bi-plus-circle"></i> Yeni Bordro Oluştur
        </button>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-4">
        <select class="form-select" id="ayFiltre">
            <option value="">Tüm Aylar</option>
            <?php for($i=1; $i<=12; $i++): ?>
                <option value="<?php echo $i; ?>"><?php echo date('F', mktime(0,0,0,$i,1)); ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div class="col-md-4">
        <select class="form-select" id="yilFiltre">
            <option value="">Tüm Yıllar</option>
            <?php for($yil = date('Y'); $yil >= date('Y')-5; $yil--): ?>
                <option value="<?php echo $yil; ?>"><?php echo $yil; ?></option>
            <?php endfor; ?>
        </select>
    </div>
</div>

<?php if (empty($bordrolar)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> Henüz bordro kaydı bulunmamaktadır.
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Personel</th>
                            <th>Ay</th>
                            <th>Yıl</th>
                            <th>Brüt Maaş</th>
                            <th>SGK/Banka</th>
                            <th>Ek Ödenek</th>
                            <th>Kesintiler</th>
                            <th>Ödeme Tipi</th>
                            <th>Toplam Ödeme</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bordrolar as $bordro): ?>
                            <tr>
                                <td><?php echo escape($bordro['ad_soyad']); ?></td>
                                <td><?php echo date('F', mktime(0,0,0,$bordro['ay'],1)); ?></td>
                                <td><?php echo escape($bordro['yil']); ?></td>
                                <td><?php echo formatMoney($bordro['brut_maas']); ?></td>
                                <td><?php echo formatMoney($bordro['sgk_banka']); ?></td>
                                <td><?php echo formatMoney($bordro['ek_odenek']); ?></td>
                                <td>
                                    <?php 
                                    $toplamKesinti = ($bordro['izin_kesintisi'] ?? 0) + ($bordro['sgk_kesintisi'] ?? 0) + ($bordro['diger_kesintiler'] ?? 0);
                                    echo formatMoney($toplamKesinti);
                                    ?>
                                </td>
                                <td>
                                    <?php if(isset($bordro['odeme_tipi'])): ?>
                                        <span class="badge bg-<?php echo $bordro['odeme_tipi'] == 'BANKA' ? 'primary' : 'success'; ?>">
                                            <?php echo escape($bordro['odeme_tipi']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">BANKA</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatMoney($bordro['toplam_odeme']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick="gosterBordro(<?php echo $bordro['id']; ?>)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-warning" onclick="duzenleBordro(<?php echo $bordro['id']; ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="silBordro(<?php echo $bordro['id']; ?>)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Bordro Ekle Modal -->
<div class="modal fade" id="bordroEkleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Yeni Bordro Oluştur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="bordro_islem.php" method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Personel</label>
                            <select class="form-select" name="personel_id" id="personelSelect" required>
                                <option value="">Seçiniz...</option>
                                <?php
                                try {
                                    $personeller = $pdo->query("SELECT id, ad_soyad, maas, maas_sgk, mesai_saat_ucreti FROM personel_listesi WHERE aktif = 1 ORDER BY ad_soyad")->fetchAll();
                                    foreach($personeller as $personel):
                                ?>
                                    <option value="<?php echo $personel['id']; ?>" 
                                            data-maas="<?php echo $personel['maas']; ?>"
                                            data-maas-sgk="<?php echo $personel['maas_sgk']; ?>"
                                            data-mesai-ucreti="<?php echo $personel['mesai_saat_ucreti']; ?>">
                                        <?php echo escape($personel['ad_soyad']); ?>
                                    </option>
                                <?php endforeach; } catch(PDOException $e) {} ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Ay</label>
                            <select class="form-select" name="ay" required>
                                <?php for($i=1; $i<=12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $i == date('n') ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0,0,0,$i,1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Yıl</label>
                            <input type="number" class="form-control" name="yil" value="<?php echo date('Y'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Brüt Maaş</label>
                            <input type="number" step="0.01" class="form-control" name="brut_maas" id="brutMaas" value="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SGK/Banka</label>
                            <input type="number" step="0.01" class="form-control" name="sgk_banka" id="sgkBanka" value="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ek Ödenek</label>
                            <input type="number" step="0.01" class="form-control" name="ek_odenek" value="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ödeme Tipi</label>
                            <select class="form-select" name="odeme_tipi" required>
                                <option value="BANKA" selected>BANKA</option>
                                <option value="NAKIT">NAKIT</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">İzin Günü</label>
                            <input type="number" step="0.5" class="form-control" name="izin_gunu" value="0">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">İzin Kesintisi</label>
                            <input type="number" step="0.01" class="form-control" name="izin_kesintisi" value="0">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">SGK Kesintisi</label>
                            <input type="number" step="0.01" class="form-control" name="sgk_kesintisi" value="0">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Diğer Kesintiler</label>
                            <input type="number" step="0.01" class="form-control" name="diger_kesintiler" value="0">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Kesinti Açıklaması</label>
                            <textarea class="form-control" name="kesinti_aciklama" rows="2"></textarea>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Açıklama</label>
                            <textarea class="form-control" name="aciklama" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

