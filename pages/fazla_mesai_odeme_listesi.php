<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Fazla Mesai Ödeme Listesi';

// Filtreler (opsiyonel)
$personel_id = isset($_GET['personel_id']) ? (int)$_GET['personel_id'] : 0;

try {
    if ($personel_id > 0) {
        $stmt = $pdo->prepare("SELECT o.*, p.ad_soyad FROM fazla_mesai_odeme o
                               LEFT JOIN personel_listesi p ON o.personel_id = p.id
                               WHERE o.personel_id = ?
                               ORDER BY o.odeme_zamani DESC");
        $stmt->execute([$personel_id]);
    } else {
        $stmt = $pdo->query("SELECT o.*, p.ad_soyad FROM fazla_mesai_odeme o
                              LEFT JOIN personel_listesi p ON o.personel_id = p.id
                              ORDER BY o.odeme_zamani DESC");
    }
    $odemeler = $stmt->fetchAll();

    $personeller = $pdo->query("SELECT id, ad_soyad FROM personel_listesi WHERE aktif = 1 ORDER BY ad_soyad")->fetchAll();
} catch(PDOException $e) {
    $odemeler = [];
    $personeller = [];
}

include '../includes/header.php';

if (isset($_GET['success'])) {
    echo showMessage('İşlem başarıyla tamamlandı', 'success');
}
if (isset($_GET['error'])) {
    echo showMessage('Hata: ' . escape($_GET['error']), 'danger');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-receipt"></i> Fazla Mesai Ödeme Listesi</h1>
    <a href="fazla_mesai.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Fazla Mesaiye Dön
    </a>
    </div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-4">
                <label class="form-label">Personel</label>
                <select class="form-select" name="personel_id">
                    <option value="0">Tümü</option>
                    <?php foreach($personeller as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo $personel_id == $p['id'] ? 'selected' : ''; ?>>
                            <?php echo escape($p['ad_soyad']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 align-self-end">
                <button type="submit" class="btn btn-outline-primary">Filtrele</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($odemeler)): ?>
            <div class="alert alert-info mb-0">
                <i class="bi bi-info-circle"></i> Kayıt bulunamadı.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Personel</th>
                            <th>Ödeme Tarihi</th>
                            <th class="money">Tutar</th>
                            <th>Not</th>
                            <th>Kaydedilme Zamanı</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($odemeler as $o): ?>
                            <tr>
                                <td><?php echo escape($o['ad_soyad'] ?? ''); ?></td>
                                <td><?php echo formatDate($o['odeme_tarihi']); ?></td>
                                <td class="money"><?php echo formatMoney((float)$o['tutar']); ?></td>
                                <td><?php echo escape($o['aciklama'] ?? ''); ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($o['odeme_zamani'])); ?></td>
                                <td>
                                    <a href="fazla_mesai_odeme_duzenle.php?id=<?php echo $o['id']; ?>" class="btn btn-sm btn-warning" title="Düzenle">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button class="btn btn-sm btn-danger" title="Sil" onclick="silOdeme(<?php echo $o['id']; ?>)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function silOdeme(id) {
    if (confirm('Bu ödeme kaydını silmek istediğinize emin misiniz?')) {
        window.location.href = 'fazla_mesai_odeme_kayit_islem.php?action=delete&id=' + id;
    }
}
</script>

<?php include '../includes/footer.php'; ?>


