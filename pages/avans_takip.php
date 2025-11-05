<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Avans Takibi';

// Avans listesi
try {
    $stmt = $pdo->query("SELECT a.*, p.ad_soyad 
                         FROM avans_takip a 
                         LEFT JOIN personel_listesi p ON a.personel_id = p.id 
                         ORDER BY a.tarih DESC");
    $avanslar = $stmt->fetchAll();
} catch(PDOException $e) {
    $avanslar = [];
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-wallet2"></i> Avans Takibi</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#avansEkleModal">
        <i class="bi bi-plus-circle"></i> Avans Ekle
    </button>
</div>

<?php if (empty($avanslar)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> Henüz avans kaydı bulunmamaktadır.
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
                            <th>Avans Tutarı</th>
                            <th>Açıklama</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($avanslar as $avans): ?>
                            <tr>
                                <td><?php echo escape($avans['ad_soyad']); ?></td>
                                <td><?php echo formatDate($avans['tarih']); ?></td>
                                <td><?php echo formatMoney($avans['avans_tutari']); ?></td>
                                <td><?php echo escape($avans['aciklama']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="duzenleAvans(<?php echo $avans['id']; ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="silAvans(<?php echo $avans['id']; ?>)">
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

<!-- Avans Ekle Modal -->
<div class="modal fade" id="avansEkleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Avans Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="avans_islem.php" method="POST">
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
                        <input type="date" class="form-control" name="tarih" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Avans Tutarı</label>
                        <input type="number" step="0.01" class="form-control" name="avans_tutari" value="0" required>
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

<?php include '../includes/footer.php'; ?>

