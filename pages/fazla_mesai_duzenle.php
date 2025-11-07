<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Fazla Mesai Düzenle';

$id = isset($_GET['id']) ? (int)$_GET['id'] : null; // SQL injection koruması için integer cast
// Geri dönüş için filtre parametrelerini sakla
$returnParams = [];
if (isset($_GET['mode'])) $returnParams['mode'] = $_GET['mode'];
if (isset($_GET['personel_id'])) $returnParams['personel_id'] = $_GET['personel_id'];
if (isset($_GET['ay'])) $returnParams['ay'] = $_GET['ay'];
if (isset($_GET['yil'])) $returnParams['yil'] = $_GET['yil'];
if (isset($_GET['baslangic'])) $returnParams['baslangic'] = $_GET['baslangic'];
if (isset($_GET['bitis'])) $returnParams['bitis'] = $_GET['bitis'];
$returnQuery = !empty($returnParams) ? '&' . http_build_query($returnParams) : '';

if (!$id || $id <= 0) {
    header('Location: fazla_mesai.php?error=Geçersiz fazla mesai ID');
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
    <a href="fazla_mesai.php?<?php echo http_build_query($returnParams); ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-body">
        <form action="fazla_mesai_islem.php" method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo $fazlaMesai['id']; ?>">
            <input type="hidden" name="return_params" value="<?php echo htmlspecialchars(http_build_query($returnParams)); ?>">
            
            <div class="row g-3">
                <div class="col-md-6">
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
                
                <div class="col-md-6">
                    <label class="form-label">Tarih</label>
                    <input type="date" class="form-control" name="tarih" value="<?php echo $fazlaMesai['tarih']; ?>" required>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Süre (Saat)</label>
                    <input type="number" step="0.01" min="0" max="999.99" class="form-control" name="saat" value="<?php echo $fazlaMesai['saat']; ?>" required>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Saat Ücreti (₺)</label>
                    <div class="input-group">
                        <input type="text" class="form-control money-field" pattern="[0-9.,]+" name="saat_ucreti" id="saat_ucreti" value="<?php echo $fazlaMesai['saat_ucreti']; ?>" data-no-auto-format="true" inputmode="decimal" required>
                        <input type="hidden" name="saat_ucreti_raw" id="saat_ucreti_raw" value="<?php echo $fazlaMesai['saat_ucreti']; ?>">
                        <span class="input-group-text">₺</span>
                    </div>
                </div>
                
                <div class="col-12">
                    <label class="form-label">Açıklama</label>
                    <textarea class="form-control" name="aciklama" rows="2"><?php echo escape($fazlaMesai['aciklama'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Güncelle
                </button>
                <a href="fazla_mesai.php?<?php echo http_build_query($returnParams); ?>" class="btn btn-secondary">İptal</a>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form[action=\"fazla_mesai_islem.php\"]');
    const saatUcreti = document.getElementById('saat_ucreti');
    const saatUcretiRaw = document.getElementById('saat_ucreti_raw');
    function parseMoneyLocalJS(val) {
        if (!val) return '0';
        val = val.toString().trim().replace('₺','');
        if (val.indexOf(',') !== -1 && val.indexOf('.') !== -1) {
            // TR: binlik nokta, ondalık virgül
            val = val.replace(/\./g, '').replace(',', '.');
        } else if (val.indexOf(',') !== -1) {
            // Sadece virgül varsa: virgülü ondalık kabul et
            val = val.replace(/\./g, '');
            val = val.replace(',', '.');
        } else {
            // Nokta varsa: son noktayı ondalık kabul et, diğerlerini kaldır
            const parts = val.split('.');
            if (parts.length > 1) {
                const last = parts.pop();
                if (last.length <= 2) {
                    val = parts.join('') + '.' + last;
                } else {
                    val = parts.join('') + last;
                }
            }
        }
        val = val.replace(/[^0-9.]/g, '');
        return val || '0';
    }
    if (form && saatUcreti && saatUcretiRaw) {
        form.addEventListener('submit', function() {
            saatUcretiRaw.value = parseMoneyLocalJS(saatUcreti.value);
        });
    }
});
</script>

