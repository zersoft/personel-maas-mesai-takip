<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Avans Takibi';

// Bordro dönemi filtresi (Ay/Yıl)
$ay = isset($_GET['ay']) ? (int)$_GET['ay'] : (int)date('n');
$yil = isset($_GET['yil']) ? (int)$_GET['yil'] : (int)date('Y');
$personel_filtre = isset($_GET['personel_id']) ? (int)$_GET['personel_id'] : 0;
if ($ay < 1 || $ay > 12) { $ay = (int)date('n'); }
if ($yil < 2000 || $yil > 2100) { $yil = (int)date('Y'); }

// Avans listesi (öncelik bordro_ay/yıl; yoksa tarih ay/yıl)
try {
    $sql = "SELECT a.*, p.ad_soyad
            FROM avans_takip a
            LEFT JOIN personel_listesi p ON a.personel_id = p.id
            WHERE ( (a.bordro_ay = ? AND a.bordro_yil = ?) OR (a.bordro_ay IS NULL AND a.bordro_yil IS NULL AND MONTH(a.tarih) = ? AND YEAR(a.tarih) = ?) )";
    $params = [$ay, $yil, $ay, $yil];
    
    if ($personel_filtre > 0) {
        $sql .= " AND a.personel_id = ?";
        $params[] = $personel_filtre;
    }
    
    $sql .= " ORDER BY a.tarih DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $avanslar = $stmt->fetchAll();
} catch(PDOException $e) {
    $avanslar = [];
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="bi bi-wallet2"></i> Avans Takibi</h3>
    <div class="d-flex align-items-center gap-2">
        <form method="GET" class="d-flex align-items-center gap-2">
            <input type="hidden" name="personel_id" value="<?php echo $personel_filtre; ?>">
            <select class="form-select form-select-sm" name="ay" style="width: 120px;">
                <?php for($i=1; $i<=12; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo ($i==$ay)?'selected':''; ?>><?php echo getTurkishMonthName($i); ?></option>
                <?php endfor; ?>
            </select>
            <input type="number" class="form-control form-control-sm" name="yil" value="<?php echo $yil; ?>" min="2020" max="<?php echo date('Y')+1; ?>" style="width: 90px;">
            <button class="btn btn-sm btn-outline-primary" type="submit">
                <i class="bi bi-funnel"></i> Filtrele
            </button>
            <?php if ($personel_filtre > 0): ?>
                <a href="avans_takip.php?ay=<?php echo $ay; ?>&yil=<?php echo $yil; ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-circle"></i> Temizle
                </a>
            <?php endif; ?>
        </form>
        <?php if (!empty($avanslar)): ?>
            <a href="avans_takip_pdf.php?ay=<?php echo $ay; ?>&yil=<?php echo $yil; ?><?php echo $personel_filtre > 0 ? '&personel_id=' . $personel_filtre : ''; ?>" class="btn btn-sm btn-danger" target="_blank">
                <i class="bi bi-file-pdf"></i> PDF Rapor
            </a>
        <?php endif; ?>
        <?php if (canEdit()): ?>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#avansEkleModal">
            <i class="bi bi-plus-circle"></i> Avans Ekle
        </button>
        <?php endif; ?>
    </div>
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
                            <th>Bordro Dönemi</th>
                            <th class="money">Avans (Banka)</th>
                            <th class="money">Avans (Nakit)</th>
                            <th class="money">Toplam</th>
                            <th>Açıklama</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($avanslar as $avans): ?>
                            <tr>
                                <td><?php echo escape($avans['ad_soyad']); ?></td>
                                <td><?php echo formatDate($avans['tarih']); ?></td>
                                <td><?php echo ($avans['bordro_ay'] && $avans['bordro_yil']) ? (getTurkishMonthName($avans['bordro_ay']).' '.$avans['bordro_yil']) : '-'; ?></td>
                                <td class="money"><?php echo formatMoney((float)($avans['banka_tutari'] ?? 0)); ?></td>
                                <td class="money"><?php echo formatMoney((float)($avans['nakit_tutari'] ?? 0)); ?></td>
                                <td class="money"><?php echo formatMoney(((float)($avans['banka_tutari'] ?? 0) + (float)($avans['nakit_tutari'] ?? 0)) > 0 ? ((float)($avans['banka_tutari'] ?? 0) + (float)($avans['nakit_tutari'] ?? 0)) : (float)($avans['avans_tutari'] ?? 0)); ?></td>
                                <td><?php echo escape($avans['aciklama']); ?></td>
                                <td>
                                    <?php if (canEdit()): ?>
                                    <button class="btn btn-sm btn-warning" onclick="duzenleAvans(<?php echo $avans['id']; ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="silAvans(<?php echo $avans['id']; ?>)">
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
<!-- Avans Ekle Modal -->
<div class="modal fade" id="avansEkleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Avans Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="avans_islem.php" method="POST">
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
                        <input type="date" class="form-control" name="tarih" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Bordro Ayı</label>
                            <select class="form-select" name="bordro_ay">
                                <option value="">(Seçiniz)</option>
                                <?php for($i=1;$i<=12;$i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($i==(int)date('n'))?'selected':''; ?>><?php echo getTurkishMonthName($i); ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Bordro Yılı</label>
                            <input type="number" class="form-control" name="bordro_yil" value="<?php echo date('Y'); ?>" min="2020" max="<?php echo date('Y')+1; ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nakit Avansı (₺)</label>
                        <div class="input-group">
                            <input type="text" class="form-control money-field" pattern="[0-9.,]+" name="nakit_tutari" value="0,00">
                            <span class="input-group-text">₺</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Banka Avansı (₺)</label>
                        <div class="input-group">
                            <input type="text" class="form-control money-field" pattern="[0-9.,]+" name="banka_tutari" value="0,00">
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

