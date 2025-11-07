<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Toplu Puantaj';

// Aktif personelleri çek
try {
    $personeller = $pdo->query("SELECT id, ad_soyad FROM personel_listesi WHERE aktif=1 ORDER BY ad_soyad")->fetchAll();
} catch (Throwable $e) { $personeller = []; }

$bugun = date('Y-m-d');

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1><i class="bi bi-clipboard-plus"></i> Toplu Puantaj</h1>
    <div>
        <a href="puantaj.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Puantaj'a Dön</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="puantaj_islem.php">
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
                        <button type="button" id="hepsiniCalisti" class="btn btn-primary">
                            <i class="bi bi-check-all"></i> Tümünü Çalıştı (8.00)
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
                            <th>
                                Seç
                            </th>
                            <th>Personel</th>
                            <th>Durum</th>
                            <th class="text-end">Saat</th>
                            <th>Açıklama</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($personeller as $p): ?>
                            <tr>
                                <td><input type="checkbox" class="form-check-input dahil" name="items[<?php echo $p['id']; ?>][dahil]" value="1"></td>
                                <td><?php echo escape($p['ad_soyad']); ?></td>
                                <td>
                                    <select class="form-select form-select-sm durum" name="items[<?php echo $p['id']; ?>][durum]">
                                        <option value="Calisti" selected>Çalıştı</option>
                                        <option value="Izin">İzin</option>
                                        <option value="Rapor">Rapor</option>
                                        <option value="Devamsizlik">Devamsızlık</option>
                                        <option value="HTatil">Hafta Tatili</option>
                                        <option value="RTatil">Resmi Tatil</option>
                                    </select>
                                </td>
                                <td class="text-end" style="width:140px;">
                                    <input type="number" step="0.25" min="0" max="24" class="form-control form-control-sm saat text-end" name="items[<?php echo $p['id']; ?>][saat]" value="8.00">
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
    const hepsiniCalisti = document.getElementById('hepsiniCalisti');
    const hepsiniSifirla = document.getElementById('hepsiniSifirla');
    const checkboxes = document.querySelectorAll('input.dahil');
    const saatInputs = document.querySelectorAll('input.saat');
    const durumSelects = document.querySelectorAll('select.durum');
    if (hepsiniIsaretle) hepsiniIsaretle.addEventListener('change', function(){
        checkboxes.forEach(cb => { cb.checked = hepsiniIsaretle.checked; });
    });
    if (hepsiniCalisti) hepsiniCalisti.addEventListener('click', function(){
        checkboxes.forEach(cb => cb.checked = true);
        durumSelects.forEach(s => s.value = 'Calisti');
        saatInputs.forEach(inp => inp.value = '8.00');
    });
    if (hepsiniSifirla) hepsiniSifirla.addEventListener('click', function(){
        checkboxes.forEach(cb => cb.checked = false);
        durumSelects.forEach(s => s.value = 'Calisti');
        saatInputs.forEach(inp => inp.value = '8.00');
        document.querySelectorAll('input.aciklama').forEach(a => a.value='');
    });
});
</script>

<?php include '../includes/footer.php'; ?>


