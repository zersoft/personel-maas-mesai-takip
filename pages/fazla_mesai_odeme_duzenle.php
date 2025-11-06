<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Fazla Mesai Ödeme Düzenle';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: fazla_mesai_odeme_listesi.php?error=Geçersiz ödeme ID');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM fazla_mesai_odeme WHERE id = ?");
    $stmt->execute([$id]);
    $odeme = $stmt->fetch();
    if (!$odeme) {
        header('Location: fazla_mesai_odeme_listesi.php?error=Ödeme kaydı bulunamadı');
        exit;
    }
    $personeller = $pdo->query("SELECT id, ad_soyad FROM personel_listesi WHERE aktif = 1 ORDER BY ad_soyad")->fetchAll();
} catch(PDOException $e) {
    header('Location: fazla_mesai_odeme_listesi.php?error=' . urlencode($e->getMessage()));
    exit;
}

include '../includes/header.php';

if (isset($_GET['success'])) {
    echo showMessage('Kayıt güncellendi', 'success');
}
if (isset($_GET['error'])) {
    echo showMessage('Hata: ' . escape($_GET['error']), 'danger');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-pencil-square"></i> Fazla Mesai Ödeme Düzenle</h1>
    <a href="fazla_mesai_odeme_listesi.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Listeye Dön
    </a>
    </div>

<div class="card">
    <div class="card-body">
        <form action="fazla_mesai_odeme_kayit_islem.php" method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo $odeme['id']; ?>">

            <div class="mb-3">
                <label class="form-label">Personel</label>
                <select class="form-select" name="personel_id" required>
                    <?php foreach($personeller as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo $p['id'] == $odeme['personel_id'] ? 'selected' : ''; ?>>
                            <?php echo escape($p['ad_soyad']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Ödeme Tarihi</label>
                <input type="date" class="form-control" name="odeme_tarihi" value="<?php echo $odeme['odeme_tarihi']; ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Tutar (₺)</label>
                <div class="input-group">
                    <input type="text" class="form-control money-field" pattern="[0-9.,]+" name="tutar" id="tutar" value="<?php echo number_format((float)$odeme['tutar'], 2, ',', '.'); ?>" data-no-auto-format="true" inputmode="decimal" required>
                    <input type="hidden" name="tutar_raw" id="tutar_raw" value="<?php echo $odeme['tutar']; ?>">
                    <span class="input-group-text">₺</span>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Açıklama</label>
                <textarea class="form-control" name="aciklama" rows="3"><?php echo escape($odeme['aciklama'] ?? ''); ?></textarea>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Güncelle
                </button>
                <a href="fazla_mesai_odeme_listesi.php" class="btn btn-secondary">İptal</a>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form[action="fazla_mesai_odeme_kayit_islem.php"]');
    const tutar = document.getElementById('tutar');
    const tutarRaw = document.getElementById('tutar_raw');
    function parseMoneyLocalJS(val) {
        if (!val) return '0';
        val = val.toString().trim().replace('₺','');
        if (val.indexOf(',') !== -1 && val.indexOf('.') !== -1) {
            val = val.replace(/\./g, '').replace(',', '.');
        } else if (val.indexOf(',') !== -1) {
            val = val.replace(/\./g, '');
            val = val.replace(',', '.');
        } else {
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
    if (form && tutar && tutarRaw) {
        form.addEventListener('submit', function() {
            tutarRaw.value = parseMoneyLocalJS(tutar.value);
        });
    }
});
</script>


