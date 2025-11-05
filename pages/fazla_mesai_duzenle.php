<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Fazla Mesai Düzenle';

$id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$id) {
    header('Location: fazla_mesai.php?error=Fazla mesai ID bulunamadı');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT fm.*, p.ad_soyad FROM fazla_mesai fm 
                          LEFT JOIN personel_listesi p ON fm.personel_id = p.id 
                          WHERE fm.id = ?");
    $stmt->execute([$id]);
    $fazlaMesai = $stmt->fetch();
    
    if (!$fazlaMesai) {
        header('Location: fazla_mesai.php?error=Fazla mesai bulunamadı');
        exit;
    }
    
    $personeller = $pdo->query("SELECT id, ad_soyad, mesai_saat_ucreti FROM personel_listesi WHERE aktif = 1 ORDER BY ad_soyad")->fetchAll();
} catch(PDOException $e) {
    header('Location: fazla_mesai.php?error=' . urlencode($e->getMessage()));
    exit;
}

include '../includes/header.php';

if (isset($_GET['success'])) {
    echo showMessage('Fazla mesai başarıyla güncellendi!', 'success');
}
if (isset($_GET['error'])) {
    echo showMessage('Hata: ' . escape($_GET['error']), 'danger');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-pencil-square"></i> Fazla Mesai Düzenle</h1>
    <a href="fazla_mesai.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="fazla_mesai_islem.php" method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo $fazlaMesai['id']; ?>">
            
            <div class="mb-3">
                <label class="form-label">Personel</label>
                <select class="form-select" name="personel_id" id="personelSelect" required>
                    <option value="">Seçiniz...</option>
                    <?php foreach($personeller as $personel): ?>
                        <option value="<?php echo $personel['id']; ?>" 
                                data-ucret="<?php echo $personel['mesai_saat_ucreti']; ?>"
                                <?php echo $personel['id'] == $fazlaMesai['personel_id'] ? 'selected' : ''; ?>>
                            <?php echo escape($personel['ad_soyad']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Tarih</label>
                <input type="date" class="form-control" name="tarih" value="<?php echo $fazlaMesai['tarih']; ?>" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Süre (Saat)</label>
                <input type="number" step="0.5" class="form-control" name="saat" value="<?php echo $fazlaMesai['saat']; ?>" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Saat Ücreti</label>
                <input type="number" step="0.01" class="form-control" name="saat_ucreti" id="saat_ucreti" value="<?php echo $fazlaMesai['saat_ucreti']; ?>" required>
            </div>
            
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="odendi" value="1" id="odendi" <?php echo $fazlaMesai['odendi'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="odendi">Ödendi</label>
                </div>
            </div>
            
            <?php if($fazlaMesai['odendi'] && isset($fazlaMesai['odeme_tarihi'])): ?>
            <div class="mb-3">
                <label class="form-label">Ödeme Tarihi</label>
                <input type="date" class="form-control" value="<?php echo $fazlaMesai['odeme_tarihi']; ?>" disabled>
            </div>
            <?php endif; ?>
            
            <div class="mb-3">
                <label class="form-label">Açıklama</label>
                <textarea class="form-control" name="aciklama" rows="3"><?php echo escape($fazlaMesai['aciklama'] ?? ''); ?></textarea>
            </div>
            
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Güncelle
                </button>
                <a href="fazla_mesai.php" class="btn btn-secondary">İptal</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const personelSelect = document.getElementById('personelSelect');
    const saatUcretiInput = document.getElementById('saat_ucreti');
    
    if (personelSelect && saatUcretiInput) {
        personelSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const ucret = selectedOption.getAttribute('data-ucret');
            if (ucret && ucret !== '0') {
                saatUcretiInput.value = ucret;
            }
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>

