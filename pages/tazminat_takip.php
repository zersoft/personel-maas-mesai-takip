<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Tazminat Takibi';

// Tazminat listesi
try {
    $stmt = $pdo->query("SELECT t.*, p.ad_soyad 
                         FROM tazminat_takip t 
                         LEFT JOIN personel_listesi p ON t.personel_id = p.id 
                         ORDER BY t.tarih DESC");
    $tazminatlar = $stmt->fetchAll();
} catch(PDOException $e) {
    $tazminatlar = [];
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-file-earmark-text"></i> Tazminat Takibi</h1>
    <?php if (canEdit()): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tazminatEkleModal">
        <i class="bi bi-plus-circle"></i> Tazminat Ekle
    </button>
    <?php endif; ?>
</div>

<?php if (empty($tazminatlar)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> Henüz tazminat kaydı bulunmamaktadır.
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
                            <th>Plan</th>
                            <th>İşlem</th>
                            <th class="money">Tutar</th>
                            <th>Açıklama</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tazminatlar as $tazminat): ?>
                            <tr>
                                <td><?php echo escape($tazminat['ad_soyad']); ?></td>
                                <td><?php echo $tazminat['tarih'] ? formatDate($tazminat['tarih']) : '-'; ?></td>
                                <td><?php echo escape($tazminat['plan']); ?></td>
                                <td><?php echo escape($tazminat['islem']); ?></td>
                                <td class="money"><?php echo formatMoney($tazminat['tutar']); ?></td>
                                <td><?php echo escape($tazminat['aciklama']); ?></td>
                                <td>
                                    <?php if (canEdit()): ?>
                                    <button class="btn btn-sm btn-warning" onclick="duzenleTazminat(<?php echo $tazminat['id']; ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="silTazminat(<?php echo $tazminat['id']; ?>)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (canEdit()): ?>
<!-- Tazminat Ekle Modal -->
<div class="modal fade" id="tazminatEkleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tazminat Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="tazminat_islem.php" method="POST">
                <?php echo csrfField(); ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Personel</label>
                        <select class="form-select" name="personel_id" required>
                            <option value="">Seçiniz...</option>
                            <?php
                            try {
                                $personeller = $pdo->query("SELECT id, ad_soyad FROM personel_listesi WHERE aktif = 1 ORDER BY ad_soyad")->fetchAll();
                                foreach($personeller as $personel):
                            ?>
                                <option value="<?php echo $personel['id']; ?>"><?php echo escape($personel['ad_soyad']); ?></option>
                            <?php endforeach; } catch(PDOException $e) {} ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tarih</label>
                        <input type="date" class="form-control" name="tarih" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Plan</label>
                        <input type="text" class="form-control" name="plan">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">İşlem</label>
                        <input type="text" class="form-control" name="islem">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tutar (₺)</label>
                        <div class="input-group">
                            <input type="text" class="form-control money-field" pattern="[0-9.,]+" name="tutar" value="0" required>
                            <span class="input-group-text">₺</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Açıklama</label>
                        <textarea class="form-control" name="aciklama" rows="3"></textarea>
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
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

