<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Toplu Fazla Mesai';

// Aktif personelleri çek
try {
    $personeller = $pdo->query("SELECT id, ad_soyad, mesai_saat_ucreti FROM personel_listesi WHERE aktif=1 ORDER BY ad_soyad")->fetchAll();
} catch (Throwable $e) { $personeller = []; }

$bugun = date('Y-m-d');

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1><i class="bi bi-clock-history"></i> Toplu Fazla Mesai</h1>
    <div>
        <a href="fazla_mesai.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Fazla Mesai'ye Dön</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="fazla_mesai_islem.php">
            <input type="hidden" name="action" value="bulk_insert">
            <div class="row g-3 align-items-end mb-3">
                <div class="col-md-2">
                    <label class="form-label">Tarih</label>
                    <input type="date" class="form-control" name="tarih" value="<?php echo $bugun; ?>" required>
                </div>
                <div class="col-md-10 d-flex gap-2 align-items-end">
                    <div class="form-check" style="padding-top: 32px;">
                        <input type="checkbox" id="hepsiniIsaretle" class="form-check-input" style="margin-top: 0.4rem;">
                        <label for="hepsiniIsaretle" class="form-check-label">Tümünü Seç</label>
                    </div>
                    <div class="ms-auto d-flex gap-2">
                        <button type="button" id="varsayilanDoldur" class="btn btn-primary">
                            <i class="bi bi-check-all"></i> Varsayılan Doldur
                        </button>
                        <button type="button" id="hepsiniSifirla" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-counterclockwise"></i> Sıfırla
                        </button>
                    </div>
                </div>
            </div>

            <div class="table-responsive mt-3">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th>Seç</th>
                            <th>Personel</th>
                            <th class="text-end">Saat</th>
                            <th class="text-end">Saat Ücreti (₺)</th>
                            <th>Açıklama</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($personeller as $p): ?>
                            <tr>
                                <td><input type="checkbox" class="form-check-input dahil" name="items[<?php echo $p['id']; ?>][dahil]" value="1"></td>
                                <td><?php echo escape($p['ad_soyad']); ?></td>
                                <td class="text-end" style="width:120px;">
                                    <input type="number" step="0.01" min="0" max="999.99" class="form-control form-control-sm saat text-end" name="items[<?php echo $p['id']; ?>][saat]" value="0">
                                </td>
                                <td class="text-end" style="width:140px;">
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control text-end saat-ucreti money-field" name="items[<?php echo $p['id']; ?>][saat_ucreti]" value="<?php echo $p['mesai_saat_ucreti']; ?>" data-no-auto-format="true" inputmode="decimal">
                                        <input type="hidden" class="saat-ucreti-raw" name="items[<?php echo $p['id']; ?>][saat_ucreti_raw]" value="<?php echo $p['mesai_saat_ucreti']; ?>">
                                        <span class="input-group-text">₺</span>
                                    </div>
                                </td>
                                <td><input type="text" class="form-control form-control-sm aciklama" name="items[<?php echo $p['id']; ?>][aciklama]" placeholder="Opsiyonel"></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-3 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Kaydet</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const hepsiniIsaretle = document.getElementById('hepsiniIsaretle');
    const varsayilanDoldur = document.getElementById('varsayilanDoldur');
    const hepsiniSifirla = document.getElementById('hepsiniSifirla');
    const checkboxes = document.querySelectorAll('input.dahil');
    const saatInputs = document.querySelectorAll('input.saat');
    const saatUcretiInputs = document.querySelectorAll('input.saat-ucreti');
    const saatUcretiRaws = document.querySelectorAll('input.saat-ucreti-raw');
    
    if (hepsiniIsaretle) hepsiniIsaretle.addEventListener('change', function(){
        checkboxes.forEach(cb => { cb.checked = hepsiniIsaretle.checked; });
    });
    
    if (varsayilanDoldur) varsayilanDoldur.addEventListener('click', function(){
        checkboxes.forEach(cb => cb.checked = true);
        saatInputs.forEach(inp => inp.value = '8.00');
    });
    
    if (hepsiniSifirla) hepsiniSifirla.addEventListener('click', function(){
        checkboxes.forEach(cb => cb.checked = false);
        saatInputs.forEach(inp => inp.value = '0');
        document.querySelectorAll('input.aciklama').forEach(a => a.value='');
    });
    
    // Para alanları için raw değer güncelleme
    const form = document.querySelector('form[action="fazla_mesai_islem.php"]');
    if (form) {
        form.addEventListener('submit', function() {
            saatUcretiInputs.forEach((inp, idx) => {
                const raw = saatUcretiRaws[idx];
                if (raw) {
                    raw.value = parseMoneyLocalJS(inp.value);
                }
            });
        });
    }
    
    function parseMoneyLocalJS(val) {
        if (!val) return '0';
        val = val.toString().trim().replace('₺','');
        if (val.indexOf(',') !== -1 && val.indexOf('.') !== -1) {
            val = val.replace(/\./g, '').replace(',', '.');
        } else if (val.indexOf(',') !== -1) {
            val = val.replace(/\./g, '').replace(',', '.');
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
});
</script>

<?php include '../includes/footer.php'; ?>

