<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Fazla Mesai Takibi';

// Fazla mesai listesi
try {
    $stmt = $pdo->query("SELECT fm.*, p.ad_soyad 
                         FROM fazla_mesai fm 
                         LEFT JOIN personel_listesi p ON fm.personel_id = p.id 
                         ORDER BY fm.tarih DESC");
    $fazlaMesailer = $stmt->fetchAll();
    
    // Personel bazlı kümülatif toplamlar
    $kumulatifToplamlar = [];
    foreach($fazlaMesailer as $fm) {
        $personelId = $fm['personel_id'];
        if (!isset($kumulatifToplamlar[$personelId])) {
            $kumulatifToplamlar[$personelId] = [
                'ad_soyad' => $fm['ad_soyad'],
                'toplam_saat' => 0,
                'toplam_tutar' => 0,
                'odenmeyen_saat' => 0,
                'odenmeyen_tutar' => 0
            ];
        }
        $kumulatifToplamlar[$personelId]['toplam_saat'] += $fm['saat'];
        $kumulatifToplamlar[$personelId]['toplam_tutar'] += $fm['tutar'];
        if (!$fm['odendi']) {
            $kumulatifToplamlar[$personelId]['odenmeyen_saat'] += $fm['saat'];
            $kumulatifToplamlar[$personelId]['odenmeyen_tutar'] += $fm['tutar'];
        }
    }
} catch(PDOException $e) {
    $fazlaMesailer = [];
    $kumulatifToplamlar = [];
}

include '../includes/header.php';

// Mesaj gösterimi
if (isset($_GET['success'])) {
    echo showMessage('Fazla mesai başarıyla kaydedildi!', 'success');
}
if (isset($_GET['error'])) {
    echo showMessage('Hata: ' . escape($_GET['error']), 'danger');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-clock-history"></i> Fazla Mesai Takibi</h1>
    <div class="d-flex gap-2">
        <a href="fazla_mesai_raporu.php" class="btn btn-outline-secondary">
            <i class="bi bi-list-check"></i> FM Raporu
        </a>
        <a href="fazla_mesai_ekstre.php" class="btn btn-outline-secondary">
            <i class="bi bi-journal-text"></i> FM Ekstresi
        </a>
        <a href="fazla_mesai_odeme_listesi.php" class="btn btn-outline-secondary">
            <i class="bi bi-receipt"></i> Ödeme Listesi
        </a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#fazlaMesaiEkleModal">
            <i class="bi bi-plus-circle"></i> Fazla Mesai Ekle
        </button>
    </div>
    
</div>

<?php if (!empty($kumulatifToplamlar)): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-calculator"></i> Personel Bazlı Kümülatif Toplamlar</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead>
                    <tr>
                        <th>Personel</th>
                        <th>Toplam Saat</th>
                        <th class="money">Toplam Tutar</th>
                        <th>Ödenmeyen Saat</th>
                        <th class="money">Ödenmeyen Tutar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($kumulatifToplamlar as $toplam): ?>
                    <tr>
                        <td><?php echo escape($toplam['ad_soyad']); ?></td>
                        <td><?php echo number_format($toplam['toplam_saat'], 2); ?></td>
                        <td class="money"><?php echo formatMoney($toplam['toplam_tutar']); ?></td>
                        <td class="<?php echo $toplam['odenmeyen_saat'] > 0 ? 'text-warning' : ''; ?>">
                            <?php echo number_format($toplam['odenmeyen_saat'], 2); ?>
                        </td>
                        <td class="money <?php echo $toplam['odenmeyen_tutar'] > 0 ? 'text-warning' : ''; ?>">
                            <?php echo formatMoney($toplam['odenmeyen_tutar']); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (empty($fazlaMesailer)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> Henüz fazla mesai kaydı bulunmamaktadır.
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Personel</th>
                            <th>Tarih</th>
                            <th>Süre (Saat)</th>
                            <th class="money">Saat Ücreti</th>
                            <th class="money">Tutar</th>
                            <th>Ödendi</th>
                            <th>Açıklama</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fazlaMesailer as $fm): ?>
                            <tr>
                                <td><?php echo escape($fm['ad_soyad']); ?></td>
                                <td><?php echo formatDate($fm['tarih']); ?></td>
                                <td><?php echo escape($fm['saat']); ?></td>
                                <td class="money"><?php echo formatMoney($fm['saat_ucreti']); ?></td>
                                <td class="money"><?php echo formatMoney($fm['tutar']); ?></td>
                                <td>
                                    <?php if($fm['odendi']): ?>
                                        <span class="badge bg-success">Ödendi</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Beklemede</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo escape($fm['aciklama']); ?></td>
                                <td>
                                    <?php if(!$fm['odendi']): ?>
                                        <a href="fazla_mesai_odeme.php?personel_id=<?php echo $fm['personel_id']; ?>" class="btn btn-sm btn-success" title="Ödeme Yap">
                                            <i class="bi bi-cash"></i>
                                        </a>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-warning" onclick="duzenleFazlaMesai(<?php echo $fm['id']; ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="silFazlaMesai(<?php echo $fm['id']; ?>)">
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

<!-- Fazla Mesai Ekle Modal -->
<div class="modal fade" id="fazlaMesaiEkleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Fazla Mesai Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="fazla_mesai_islem.php" method="POST">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Personel</label>
                            <select class="form-select" name="personel_id" required>
                                <option value="">Seçiniz...</option>
                                <?php
                                try {
                                    $personeller = $pdo->query("SELECT id, ad_soyad, mesai_saat_ucreti FROM personel_listesi WHERE aktif = 1 ORDER BY ad_soyad")->fetchAll();
                                    foreach($personeller as $personel):
                                ?>
                                    <option value="<?php echo $personel['id']; ?>" data-ucret="<?php echo $personel['mesai_saat_ucreti']; ?>">
                                        <?php echo escape($personel['ad_soyad']); ?>
                                    </option>
                                <?php endforeach; } catch(PDOException $e) {} ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tarih</label>
                            <input type="date" class="form-control" name="tarih" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Süre (Saat)</label>
                            <input type="number" step="0.5" class="form-control" name="saat" value="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Saat Ücreti</label>
                            <div class="input-group">
                                <input type="text" class="form-control money-field" pattern="[0-9.,]+" name="saat_ucreti" id="saat_ucreti" value="0" data-no-auto-format="true" inputmode="decimal" required>
                                <input type="hidden" name="saat_ucreti_raw" id="saat_ucreti_raw" value="0">
                                <span class="input-group-text">₺</span>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="odendi" value="1">
                                <label class="form-check-label">Ödendi</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Açıklama</label>
                            <textarea class="form-control" name="aciklama" rows="2"></textarea>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('#fazlaMesaiEkleModal form');
    const saatUcreti = document.getElementById('saat_ucreti');
    const saatUcretiRaw = document.getElementById('saat_ucreti_raw');
    // TR ve EN formatlarını güvenle parse et
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

