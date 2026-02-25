<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('user');

$pageTitle = 'Personel Düzenle';

$id = isset($_GET['id']) ? (int)$_GET['id'] : null; // SQL injection koruması için integer cast

if (!$id || $id <= 0) {
    header('Location: personel_listesi.php?error=Geçersiz personel ID');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM personel_listesi WHERE id = ?");
    $stmt->execute([$id]);
    $personel = $stmt->fetch();
    
    if (!$personel) {
        header('Location: personel_listesi.php?error=Personel bulunamadı');
        exit;
    }
} catch(PDOException $e) {
    header('Location: personel_listesi.php?error=' . urlencode($e->getMessage()));
    exit;
}

include '../includes/header.php';

if (isset($_GET['success'])) {
    echo showMessage('Personel başarıyla güncellendi!', 'success');
}
if (isset($_GET['error'])) {
    echo showMessage('Hata: ' . escape($_GET['error']), 'danger');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-pencil-square"></i> Personel Düzenle</h1>
    <a href="personel_listesi.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="personel_islem.php" method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo $personel['id']; ?>">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Ad Soyad</label>
                    <input type="text" class="form-control" name="ad_soyad" value="<?php echo escape($personel['ad_soyad']); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">TC No</label>
                    <input type="text" class="form-control" name="tc_no" value="<?php echo escape($personel['tc_no'] ?? ''); ?>" maxlength="11">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Pozisyon</label>
                    <input type="text" class="form-control" name="pozisyon" value="<?php echo escape($personel['pozisyon'] ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">İşe Giriş Tarihi</label>
                    <input type="date" class="form-control" name="ise_giris_tarihi" value="<?php echo $personel['ise_giris_tarihi'] ?? ''; ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Maaş (₺)</label>
                    <div class="input-group">
                        <input type="text" class="form-control money-field" pattern="[0-9.,]+" name="maas" value="<?php echo $personel['maas']; ?>" required>
                        <span class="input-group-text">₺</span>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Maaş SGK (₺)</label>
                    <div class="input-group">
                        <input type="text" class="form-control money-field" pattern="[0-9.,]+" name="maas_sgk" value="<?php echo $personel['maas_sgk']; ?>" required>
                        <span class="input-group-text">₺</span>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Banka Adı</label>
                    <input type="text" class="form-control" name="banka_adi" value="<?php echo escape($personel['banka_adi'] ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">IBAN</label>
                    <input type="text" class="form-control" name="iban" value="<?php echo escape($personel['iban'] ?? ''); ?>" maxlength="26">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Mesai Saat Ücreti (₺)</label>
                    <div class="input-group">
                        <input type="text" class="form-control money-field" pattern="[0-9.,]+" name="mesai_saat_ucreti" value="<?php echo $personel['mesai_saat_ucreti']; ?>" required>
                        <span class="input-group-text">₺</span>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="aktif" value="1" id="aktif" <?php echo $personel['aktif'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="aktif">Aktif</label>
                    </div>
                </div>
            </div>
            
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Güncelle
                </button>
                <a href="personel_listesi.php" class="btn btn-secondary">İptal</a>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

