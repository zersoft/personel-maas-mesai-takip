<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Avans Düzenle';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: avans_takip.php?error=' . urlencode('Geçersiz avans.'));
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT a.*, p.ad_soyad FROM avans_takip a LEFT JOIN personel_listesi p ON a.personel_id = p.id WHERE a.id = ?");
    $stmt->execute([$id]);
    $avans = $stmt->fetch();
    if (!$avans) {
        header('Location: avans_takip.php?error=' . urlencode('Avans bulunamadı.'));
        exit;
    }
    $personeller = $pdo->query("SELECT id, ad_soyad FROM personel_listesi WHERE aktif = 1 ORDER BY ad_soyad")->fetchAll();
} catch (PDOException $e) {
    header('Location: avans_takip.php?error=' . urlencode('Veri okunamadı.'));
    exit;
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-pencil-square"></i> Avans Düzenle</h1>
    <a href="avans_takip.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Geri</a>
    </div>

<div class="card">
    <div class="card-body">
        <form action="avans_islem.php" method="POST" class="row g-3">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <div class="col-md-6">
                <label class="form-label">Personel</label>
                <select class="form-select" name="personel_id" required>
                    <?php foreach($personeller as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo ($p['id']==$avans['personel_id'])?'selected':''; ?>><?php echo escape($p['ad_soyad']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Tarih</label>
                <input type="date" class="form-control" name="tarih" value="<?php echo escape($avans['tarih']); ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Bordro Ayı</label>
                <select class="form-select" name="bordro_ay">
                    <option value="">(Seçiniz)</option>
                    <?php for($i=1;$i<=12;$i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo ((int)$avans['bordro_ay']===$i)?'selected':''; ?>><?php echo getTurkishMonthName($i); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Bordro Yılı</label>
                <input type="number" class="form-control" name="bordro_yil" value="<?php echo escape($avans['bordro_yil'] ?? ''); ?>" min="2020" max="<?php echo date('Y')+1; ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Banka Avansı (₺)</label>
                <div class="input-group">
                    <input type="text" class="form-control money-field" pattern="[0-9.,]+" name="banka_tutari" value="<?php echo number_format((float)($avans['banka_tutari'] ?? 0), 2, ',', '.'); ?>">
                    <span class="input-group-text">₺</span>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Nakit Avansı (₺)</label>
                <div class="input-group">
                    <input type="text" class="form-control money-field" pattern="[0-9.,]+" name="nakit_tutari" value="<?php echo number_format((float)($avans['nakit_tutari'] ?? 0), 2, ',', '.'); ?>">
                    <span class="input-group-text">₺</span>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">Açıklama</label>
                <textarea class="form-control" name="aciklama" rows="3"><?php echo escape($avans['aciklama']); ?></textarea>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Kaydet</button>
                <a href="avans_takip.php" class="btn btn-secondary">İptal</a>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>


