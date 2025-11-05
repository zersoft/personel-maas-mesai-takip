<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Fazla Mesai Ödeme';

$personel_id = isset($_GET['personel_id']) ? $_GET['personel_id'] : null;

if (!$personel_id) {
    header('Location: fazla_mesai.php?error=Personel seçilmedi');
    exit;
}

try {
    $personel = $pdo->prepare("SELECT id, ad_soyad FROM personel_listesi WHERE id = ?");
    $personel->execute([$personel_id]);
    $personelBilgi = $personel->fetch();
    
    if (!$personelBilgi) {
        header('Location: fazla_mesai.php?error=Personel bulunamadı');
        exit;
    }
    
    // Ödenmemiş fazla mesaileri getir
    $stmt = $pdo->prepare("SELECT * FROM fazla_mesai WHERE personel_id = ? AND odendi = 0 ORDER BY tarih ASC");
    $stmt->execute([$personel_id]);
    $fazlaMesailer = $stmt->fetchAll();
    
    $toplamTutar = 0;
    foreach($fazlaMesailer as $fm) {
        $toplamTutar += $fm['tutar'];
    }
} catch(PDOException $e) {
    header('Location: fazla_mesai.php?error=' . urlencode($e->getMessage()));
    exit;
}

include '../includes/header.php';

if (isset($_GET['success'])) {
    echo showMessage('Ödeme başarıyla kaydedildi!', 'success');
}
if (isset($_GET['error'])) {
    echo showMessage('Hata: ' . escape($_GET['error']), 'danger');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-cash-stack"></i> Fazla Mesai Ödeme - <?php echo escape($personelBilgi['ad_soyad']); ?></h1>
    <a href="fazla_mesai.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<?php if (empty($fazlaMesailer)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> Bu personel için ödenmemiş fazla mesai bulunmamaktadır.
    </div>
<?php else: ?>
    <div class="card mb-4">
        <div class="card-body">
            <h5>Ödenmemiş Fazla Mesai Listesi</h5>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>Saat</th>
                            <th>Saat Ücreti</th>
                            <th>Tutar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($fazlaMesailer as $fm): ?>
                            <tr>
                                <td><?php echo formatDate($fm['tarih']); ?></td>
                                <td><?php echo $fm['saat']; ?></td>
                                <td><?php echo formatMoney($fm['saat_ucreti']); ?></td>
                                <td><?php echo formatMoney($fm['tutar']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-primary">
                            <th colspan="3">Toplam</th>
                            <th><?php echo formatMoney($toplamTutar); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Ödeme Yap</h5>
        </div>
        <div class="card-body">
            <form action="fazla_mesai_odeme_islem.php" method="POST">
                <input type="hidden" name="personel_id" value="<?php echo $personel_id; ?>">
                
                <div class="mb-3">
                    <label class="form-label">Ödeme Tarihi</label>
                    <input type="date" class="form-control" name="odeme_tarihi" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Ödeme Tutarı</label>
                    <input type="number" step="0.01" class="form-control" name="odeme_tutari" 
                           value="<?php echo $toplamTutar; ?>" max="<?php echo $toplamTutar; ?>" required>
                    <small class="text-muted">Maksimum: <?php echo formatMoney($toplamTutar); ?></small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Açıklama</label>
                    <textarea class="form-control" name="aciklama" rows="3"></textarea>
                </div>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tamamini_ode" value="1" id="tamaminiOde" checked>
                        <label class="form-check-label" for="tamaminiOde">
                            Tüm ödenmemiş fazla mesaileri öde
                        </label>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-circle"></i> Ödemeyi Kaydet
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

