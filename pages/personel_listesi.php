<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Personel Listesi';

// Personel listesi
try {
    $stmt = $pdo->query("SELECT * FROM personel_listesi ORDER BY ad_soyad ASC");
    $personeller = $stmt->fetchAll();
} catch(PDOException $e) {
    $personeller = [];
}

include '../includes/header.php';

// Mesaj gösterimi
if (isset($_GET['success'])) {
    echo showMessage('Personel başarıyla kaydedildi!', 'success');
}
if (isset($_GET['error'])) {
    echo showMessage('Hata: ' . escape($_GET['error']), 'danger');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-people"></i> Personel Listesi</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#personelEkleModal">
        <i class="bi bi-person-plus"></i> Yeni Personel Ekle
    </button>
</div>

<?php if (empty($personeller)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> Henüz personel kaydı bulunmamaktadır.
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ad Soyad</th>
                            <th>TC No</th>
                            <th>Pozisyon</th>
                            <th>Maaş</th>
                            <th>Maaş SGK</th>
                            <th>İşe Giriş Tarihi</th>
                            <th>Banka</th>
                            <th>Mesai Saat Ücreti</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($personeller as $personel): ?>
                            <tr>
                                <td><?php echo escape($personel['id']); ?></td>
                                <td><?php echo escape($personel['ad_soyad']); ?></td>
                                <td><?php echo escape($personel['tc_no']); ?></td>
                                <td><?php echo escape($personel['pozisyon']); ?></td>
                                <td><?php echo formatMoney($personel['maas']); ?></td>
                                <td><?php echo formatMoney($personel['maas_sgk']); ?></td>
                                <td><?php echo formatDate($personel['ise_giris_tarihi']); ?></td>
                                <td><?php echo escape($personel['banka_adi']); ?></td>
                                <td><?php echo formatMoney($personel['mesai_saat_ucreti']); ?></td>
                                <td>
                                    <?php if($personel['aktif']): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Pasif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="duzenlePersonel(<?php echo $personel['id']; ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="silPersonel(<?php echo $personel['id']; ?>)">
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

<!-- Personel Ekle Modal -->
<div class="modal fade" id="personelEkleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Yeni Personel Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="personel_islem.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Ad Soyad</label>
                        <input type="text" class="form-control" name="ad_soyad" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">TC No</label>
                        <input type="text" class="form-control" name="tc_no" maxlength="11">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Pozisyon</label>
                        <input type="text" class="form-control" name="pozisyon">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Maaş</label>
                        <input type="number" step="0.01" class="form-control" name="maas" value="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Maaş SGK</label>
                        <input type="number" step="0.01" class="form-control" name="maas_sgk" value="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">İşe Giriş Tarihi</label>
                        <input type="date" class="form-control" name="ise_giris_tarihi">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Banka Adı</label>
                        <input type="text" class="form-control" name="banka_adi">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">IBAN</label>
                        <input type="text" class="form-control" name="iban" maxlength="26">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mesai Saat Ücreti</label>
                        <input type="number" step="0.01" class="form-control" name="mesai_saat_ucreti" value="0">
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="aktif" value="1" checked>
                            <label class="form-check-label">Aktif</label>
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

