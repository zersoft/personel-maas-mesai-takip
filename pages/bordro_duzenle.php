<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Bordro Düzenle';

$id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$id) {
    header('Location: bordro.php?error=Bordro ID bulunamadı');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT b.*, p.ad_soyad FROM bordro b 
                          LEFT JOIN personel_listesi p ON b.personel_id = p.id 
                          WHERE b.id = ?");
    $stmt->execute([$id]);
    $bordro = $stmt->fetch();
    
    if (!$bordro) {
        header('Location: bordro.php?error=Bordro bulunamadı');
        exit;
    }
    
    $personeller = $pdo->query("SELECT id, ad_soyad, maas, maas_sgk FROM personel_listesi WHERE aktif = 1 ORDER BY ad_soyad")->fetchAll();
} catch(PDOException $e) {
    header('Location: bordro.php?error=' . urlencode($e->getMessage()));
    exit;
}

include '../includes/header.php';

if (isset($_GET['success'])) {
    echo showMessage('Bordro başarıyla güncellendi!', 'success');
}
if (isset($_GET['error'])) {
    echo showMessage('Hata: ' . escape($_GET['error']), 'danger');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-pencil-square"></i> Bordro Düzenle</h1>
    <a href="bordro.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="bordro_islem.php" method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo $bordro['id']; ?>">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Personel</label>
                    <select class="form-select" name="personel_id" id="personelSelect" required>
                        <option value="">Seçiniz...</option>
                        <?php foreach($personeller as $personel): ?>
                            <option value="<?php echo $personel['id']; ?>" 
                                    data-maas="<?php echo $personel['maas']; ?>"
                                    data-maas-sgk="<?php echo $personel['maas_sgk']; ?>"
                                    <?php echo $personel['id'] == $bordro['personel_id'] ? 'selected' : ''; ?>>
                                <?php echo escape($personel['ad_soyad']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Ay</label>
                    <select class="form-select" name="ay" required>
                        <?php for($i=1; $i<=12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $i == $bordro['ay'] ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0,0,0,$i,1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Yıl</label>
                    <input type="number" class="form-control" name="yil" value="<?php echo $bordro['yil']; ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Brüt Maaş</label>
                    <input type="number" step="0.01" class="form-control" name="brut_maas" id="brutMaas" value="<?php echo $bordro['brut_maas']; ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">SGK/Banka</label>
                    <input type="number" step="0.01" class="form-control" name="sgk_banka" id="sgkBanka" value="<?php echo $bordro['sgk_banka']; ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Ek Ödenek</label>
                    <input type="number" step="0.01" class="form-control" name="ek_odenek" value="<?php echo $bordro['ek_odenek']; ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Ödeme Tipi</label>
                    <select class="form-select" name="odeme_tipi" required>
                        <option value="BANKA" <?php echo (!isset($bordro['odeme_tipi']) || $bordro['odeme_tipi'] == 'BANKA') ? 'selected' : ''; ?>>BANKA</option>
                        <option value="NAKIT" <?php echo (isset($bordro['odeme_tipi']) && $bordro['odeme_tipi'] == 'NAKIT') ? 'selected' : ''; ?>>NAKIT</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">İzin Günü</label>
                    <input type="number" step="0.5" class="form-control" name="izin_gunu" value="<?php echo $bordro['izin_gunu'] ?? 0; ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">İzin Kesintisi</label>
                    <input type="number" step="0.01" class="form-control" name="izin_kesintisi" value="<?php echo $bordro['izin_kesintisi'] ?? 0; ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">SGK Kesintisi</label>
                    <input type="number" step="0.01" class="form-control" name="sgk_kesintisi" value="<?php echo $bordro['sgk_kesintisi'] ?? 0; ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Diğer Kesintiler</label>
                    <input type="number" step="0.01" class="form-control" name="diger_kesintiler" value="<?php echo $bordro['diger_kesintiler'] ?? 0; ?>">
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">Kesinti Açıklaması</label>
                    <textarea class="form-control" name="kesinti_aciklama" rows="2"><?php echo escape($bordro['kesinti_aciklama'] ?? ''); ?></textarea>
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">Açıklama</label>
                    <textarea class="form-control" name="aciklama" rows="3"><?php echo escape($bordro['aciklama'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Güncelle
                </button>
                <a href="bordro.php" class="btn btn-secondary">İptal</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const bordroPersonelSelect = document.getElementById('personelSelect');
    const brutMaasInput = document.getElementById('brutMaas');
    const sgkBankaInput = document.getElementById('sgkBanka');
    
    if (bordroPersonelSelect && brutMaasInput && sgkBankaInput) {
        bordroPersonelSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const maas = selectedOption.getAttribute('data-maas');
                const maasSgk = selectedOption.getAttribute('data-maas-sgk');
                
                if (maas && maas !== '0') {
                    brutMaasInput.value = maas;
                }
                if (maasSgk && maasSgk !== '0') {
                    sgkBankaInput.value = maasSgk;
                }
            }
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>

