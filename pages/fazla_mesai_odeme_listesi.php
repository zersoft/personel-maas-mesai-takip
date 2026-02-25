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
    
    // Kümülatif toplamlar (toplu ödeme için)
    $kumulatifToplamlar = [];
    $tumPersoneller = $pdo->query("SELECT id, ad_soyad FROM personel_listesi WHERE aktif = 1 ORDER BY ad_soyad")->fetchAll();
    foreach($tumPersoneller as $p) {
        $kumulatifToplamlar[$p['id']] = ['ad_soyad' => $p['ad_soyad'], 'toplam_tutar' => 0, 'toplam_odeme' => 0, 'bakiye' => 0];
    }
    $fmStmt = $pdo->query("SELECT personel_id, SUM(tutar) as toplam FROM fazla_mesai GROUP BY personel_id");
    foreach($fmStmt->fetchAll() as $r) {
        if (isset($kumulatifToplamlar[$r['personel_id']])) $kumulatifToplamlar[$r['personel_id']]['toplam_tutar'] = (float)$r['toplam'];
    }
    $odStmt = $pdo->query("SELECT personel_id, SUM(tutar) as toplam FROM fazla_mesai_odeme GROUP BY personel_id");
    foreach($odStmt->fetchAll() as $r) {
        if (isset($kumulatifToplamlar[$r['personel_id']])) $kumulatifToplamlar[$r['personel_id']]['toplam_odeme'] = (float)$r['toplam'];
    }
    foreach($kumulatifToplamlar as $pid => &$t) {
        $t['bakiye'] = $t['toplam_tutar'] - $t['toplam_odeme'];
    }
    $kumulatifToplamlar = array_filter($kumulatifToplamlar, function($t) { return $t['bakiye'] > 0; });
} catch(PDOException $e) {
    $odemeler = [];
    $personeller = [];
    $kumulatifToplamlar = [];
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
    <div class="d-flex gap-2">
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#odemeYapModal">
            <i class="bi bi-cash-coin"></i> Ödeme Yap
        </button>
        <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#topluOdemeModal">
            <i class="bi bi-cash-stack"></i> Toplu Ödeme
        </button>
        <a href="fazla_mesai.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Fazla Mesaiye Dön
        </a>
    </div>
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
                                    <?php if (canEdit()): ?>
                                    <a href="fazla_mesai_odeme_duzenle.php?id=<?php echo $o['id']; ?>" class="btn btn-sm btn-warning" title="Düzenle">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button class="btn btn-sm btn-danger" title="Sil" onclick="silOdeme(<?php echo $o['id']; ?>)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <?php endif; ?>
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

<!-- Ödeme Yap Modal -->
<div class="modal fade" id="odemeYapModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Fazla Mesai Ödeme Yap</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="fazla_mesai_odeme_kayit_islem.php" method="POST">
                <input type="hidden" name="action" value="single_payment">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Personel</label>
                        <select class="form-select" name="personel_id" required>
                            <option value="">Seçiniz...</option>
                            <?php foreach($personeller as $personel): ?>
                                <option value="<?php echo $personel['id']; ?>">
                                    <?php echo escape($personel['ad_soyad']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ödeme Tarihi</label>
                        <input type="date" class="form-control" name="odeme_tarihi" value="<?php echo date('Y-m-d'); ?>" required>
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
                        <textarea class="form-control" name="aciklama" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-success">Ödeme Yap</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toplu Ödeme Modal -->
<div class="modal fade" id="topluOdemeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Toplu Fazla Mesai Ödemesi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="fazla_mesai_odeme_kayit_islem.php" method="POST">
                <input type="hidden" name="action" value="bulk_payment">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Ödeme Tarihi</label>
                        <input type="date" class="form-control" name="odeme_tarihi" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="selectAllOdeme"></th>
                                    <th>Personel</th>
                                    <th class="text-end">Bakiye</th>
                                    <th>Tutar (₺)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($kumulatifToplamlar as $pid => $toplam): 
                                    if ($toplam['bakiye'] <= 0) continue;
                                ?>
                                    <tr>
                                        <td><input type="checkbox" class="personel-check-odeme" name="personel[<?php echo $pid; ?>][secili]" value="1"></td>
                                        <td><?php echo escape($toplam['ad_soyad']); ?></td>
                                        <td class="text-end"><?php echo formatMoney($toplam['bakiye']); ?></td>
                                        <td>
                                            <div class="input-group input-group-sm">
                                                <input type="text" class="form-control money-field" name="personel[<?php echo $pid; ?>][tutar]" value="<?php echo $toplam['bakiye']; ?>">
                                                <span class="input-group-text">₺</span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-success">Toplu Ödeme Yap</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('selectAllOdeme')?.addEventListener('change', function() {
    document.querySelectorAll('.personel-check-odeme').forEach(cb => cb.checked = this.checked);
});
</script>

<?php include '../includes/footer.php'; ?>


