<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Fazla Mesai Ödeme Listesi';

// Filtreler
$personel_id = isset($_GET['personel_id']) ? (int)$_GET['personel_id'] : 0;
$baslangic = isset($_GET['baslangic']) ? $_GET['baslangic'] : '';
$bitis = isset($_GET['bitis']) ? $_GET['bitis'] : '';

try {
    $sql = "SELECT o.*, p.ad_soyad FROM fazla_mesai_odeme o
            LEFT JOIN personel_listesi p ON o.personel_id = p.id
            WHERE 1=1";
    $params = [];
    
    if ($personel_id > 0) {
        $sql .= " AND o.personel_id = ?";
        $params[] = $personel_id;
    }
    if ($baslangic) {
        $sql .= " AND o.odeme_tarihi >= ?";
        $params[] = $baslangic;
    }
    if ($bitis) {
        $sql .= " AND o.odeme_tarihi <= ?";
        $params[] = $bitis;
    }
    
    $sql .= " ORDER BY o.odeme_zamani DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
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
            <div class="col-md-3">
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
            <div class="col-md-3">
                <label class="form-label">Başlangıç Tarihi</label>
                <input type="date" class="form-control" name="baslangic" value="<?php echo escape($baslangic); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Bitiş Tarihi</label>
                <input type="date" class="form-control" name="bitis" value="<?php echo escape($bitis); ?>">
            </div>
            <div class="col-md-3 align-self-end">
                <button type="submit" class="btn btn-primary">Filtrele</button>
                <?php if ($personel_id || $baslangic || $bitis): ?>
                    <a href="fazla_mesai_odeme_listesi.php" class="btn btn-outline-secondary">Temizle</a>
                <?php endif; ?>
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
                        <?php 
                        $toplamTutar = 0;
                        foreach($odemeler as $o): 
                            $toplamTutar += (float)$o['tutar'];
                        ?>
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
                    <tfoot>
                        <tr class="table-primary">
                            <th colspan="2" class="text-end">TOPLAM:</th>
                            <th class="money"><?php echo formatMoney($toplamTutar); ?></th>
                            <th colspan="3"></th>
                        </tr>
                    </tfoot>
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


