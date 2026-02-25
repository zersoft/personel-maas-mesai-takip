<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Puantaj Yönetimi';

// Varsayılan dönem: son puantaj kaydı varsa ondan, yoksa bugünün ay/yılı
$defaultAy = (int)date('n');
$defaultYil = (int)date('Y');
try {
    $son = $pdo->query("SELECT YEAR(tarih) AS yil, MONTH(tarih) AS ay FROM puantaj ORDER BY tarih DESC LIMIT 1")->fetch();
    if ($son) { $defaultAy = (int)$son['ay']; $defaultYil = (int)$son['yil']; }
} catch (Throwable $e) {}

$seciliAy = isset($_GET['ay']) && (int)$_GET['ay']>0 ? (int)$_GET['ay'] : $defaultAy;
$seciliYil = isset($_GET['yil']) && (int)$_GET['yil']>0 ? (int)$_GET['yil'] : $defaultYil;

// Özet: kişi bazlı sayım ve saat toplamları
$satirlar = [];
$topSaat = 0; $topCalisilanGun = 0; $topIzin = 0; $topRapor = 0; $topDevamsizlik = 0; $topHT = 0; $topRT = 0;
try {
    $sql = "SELECT p.id AS personel_id, p.ad_soyad,
                   SUM(CASE WHEN pt.durum='Calisti' THEN 1 ELSE 0 END) AS calisilan_gun,
                   SUM(CASE WHEN pt.durum='Calisti' THEN pt.saat ELSE 0 END) AS calisilan_saat,
                   SUM(CASE WHEN pt.durum='Izin' THEN 1 ELSE 0 END) AS izin,
                   SUM(CASE WHEN pt.durum='Rapor' THEN 1 ELSE 0 END) AS rapor,
                   SUM(CASE WHEN pt.durum='Devamsizlik' THEN 1 ELSE 0 END) AS devamsizlik,
                   SUM(CASE WHEN pt.durum='HTatil' THEN 1 ELSE 0 END) AS h_tatil,
                   SUM(CASE WHEN pt.durum='RTatil' THEN 1 ELSE 0 END) AS r_tatil
            FROM personel_listesi p
            LEFT JOIN puantaj pt ON pt.personel_id = p.id AND MONTH(pt.tarih) = ? AND YEAR(pt.tarih) = ?
            WHERE p.aktif = 1
            GROUP BY p.id, p.ad_soyad
            HAVING calisilan_gun > 0 OR izin > 0 OR rapor > 0 OR devamsizlik > 0 OR h_tatil > 0 OR r_tatil > 0
            ORDER BY p.ad_soyad";
    $st = $pdo->prepare($sql);
    $st->execute([$seciliAy, $seciliYil]);
    $satirlar = $st->fetchAll();
    foreach ($satirlar as $r) {
        $topCalisilanGun += (int)$r['calisilan_gun'];
        $topSaat += (float)$r['calisilan_saat'];
        $topIzin += (int)$r['izin'];
        $topRapor += (int)$r['rapor'];
        $topDevamsizlik += (int)$r['devamsizlik'];
        $topHT += (int)$r['h_tatil'];
        $topRT += (int)$r['r_tatil'];
    }
} catch (Throwable $e) {}

// Personel listesi (ekleme için)
$personeller = [];
try { $personeller = $pdo->query("SELECT id, ad_soyad FROM personel_listesi WHERE aktif=1 ORDER BY ad_soyad")->fetchAll(); } catch (Throwable $e) {}

include '../includes/header.php';

if (isset($_GET['success'])) echo showMessage('Puantaj işlemi kaydedildi.', 'success');
if (isset($_GET['error'])) echo showMessage('Hata: ' . escape($_GET['error']), 'danger');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-clipboard-data"></i> Puantaj Yönetimi</h1>
    <div class="d-flex align-items-center gap-2">
        <a href="puantaj_ekstre.php" class="btn btn-outline-secondary"><i class="bi bi-journal-text"></i> Puantaj Ekstresi</a>
        <?php if (canEdit()): ?>
        <a href="toplu_puantaj.php" class="btn btn-success"><i class="bi bi-clipboard-plus"></i> Toplu Puantaj Oluştur</a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#puantajEkleModal"><i class="bi bi-plus-circle"></i> Yeni Puantaj Ekle</button>
        <?php endif; ?>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-2">
        <select class="form-select" id="ayFiltre">
            <?php for ($i=1;$i<=12;$i++): ?>
                <option value="<?php echo $i; ?>" <?php echo $i==$seciliAy?'selected':''; ?>><?php echo getTurkishMonthName($i); ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select class="form-select" id="yilFiltre">
            <?php for ($y=date('Y'); $y>=date('Y')-5; $y--): ?>
                <option value="<?php echo $y; ?>" <?php echo $y==$seciliYil?'selected':''; ?>><?php echo $y; ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div class="col-md-3 ms-auto">
        <div class="form-control form-control-sm d-flex justify-content-between align-items-center">
            <span class="text-muted small">Dönem</span>
            <span class="fw-semibold"><?php echo getTurkishMonthName($seciliAy) . ' ' . $seciliYil; ?></span>
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-control form-control-sm d-flex justify-content-between align-items-center">
            <span class="text-muted small">Çalışılan Saat</span>
            <span class="fw-semibold"><?php echo number_format($topSaat, 2, ',', '.'); ?></span>
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-control form-control-sm d-flex justify-content-between align-items-center">
            <span class="text-muted small">Çalışılan Gün</span>
            <span class="fw-semibold"><?php echo number_format($topCalisilanGun, 0, ',', '.'); ?></span>
        </div>
    </div>
</div>

<?php if (empty($satirlar)): ?>
    <div class="alert alert-info">Seçilen dönem için puantaj kaydı bulunamadı.</div>
<?php else: ?>
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Personel</th>
                        <th class="text-end">Çalışılan Gün</th>
                        <th class="text-end">Çalışılan Saat</th>
                        <th class="text-end">İzin</th>
                        <th class="text-end">Rapor</th>
                        <th class="text-end">Devamsızlık</th>
                        <th class="text-end">Hafta Tatili</th>
                        <th class="text-end">Resmi Tatil</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($satirlar as $r): ?>
                        <tr>
                            <td><?php echo escape($r['ad_soyad']); ?></td>
                            <td class="text-end"><?php echo (int)$r['calisilan_gun']; ?></td>
                            <td class="text-end"><?php echo number_format((float)$r['calisilan_saat'], 2, ',', '.'); ?></td>
                            <td class="text-end"><?php echo (int)$r['izin']; ?></td>
                            <td class="text-end"><?php echo (int)$r['rapor']; ?></td>
                            <td class="text-end"><?php echo (int)$r['devamsizlik']; ?></td>
                            <td class="text-end"><?php echo (int)$r['h_tatil']; ?></td>
                            <td class="text-end"><?php echo (int)$r['r_tatil']; ?></td>
                            <td>
                                <a class="btn btn-sm btn-info" href="puantaj_ekstre.php?personel_id=<?php echo $r['personel_id']; ?>&mode=donem&ay=<?php echo $seciliAy; ?>&yil=<?php echo $seciliYil; ?>">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-primary">
                        <th>TOPLAM</th>
                        <th class="text-end"><?php echo number_format($topCalisilanGun, 0, ',', '.'); ?></th>
                        <th class="text-end"><?php echo number_format($topSaat, 2, ',', '.'); ?></th>
                        <th class="text-end"><?php echo number_format($topIzin, 0, ',', '.'); ?></th>
                        <th class="text-end"><?php echo number_format($topRapor, 0, ',', '.'); ?></th>
                        <th class="text-end"><?php echo number_format($topDevamsizlik, 0, ',', '.'); ?></th>
                        <th class="text-end"><?php echo number_format($topHT, 0, ',', '.'); ?></th>
                        <th class="text-end"><?php echo number_format($topRT, 0, ',', '.'); ?></th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (canEdit()): ?>
<!-- Puantaj Ekle Modal -->
<div class="modal fade" id="puantajEkleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Yeni Puantaj Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="puantaj_islem.php" method="POST">
                <input type="hidden" name="action" value="insert">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Personel</label>
                        <select class="form-select" name="personel_id" required>
                            <option value="">Seçiniz...</option>
                            <?php foreach ($personeller as $p): ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo escape($p['ad_soyad']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tarih</label>
                        <input type="date" class="form-control" name="tarih" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Durum</label>
                        <select class="form-select" name="durum" required>
                            <option value="Calisti">Çalıştı</option>
                            <option value="Izin">İzin</option>
                            <option value="Rapor">Rapor</option>
                            <option value="Devamsizlik">Devamsızlık</option>
                            <option value="HTatil">Hafta Tatili</option>
                            <option value="RTatil">Resmi Tatil</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Saat</label>
                        <input type="number" step="0.25" min="0" max="24" class="form-control" name="saat" value="8.00" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Açıklama</label>
                        <textarea class="form-control" name="aciklama" rows="2"></textarea>
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

<script>
document.addEventListener('DOMContentLoaded', function(){
    const ay = document.getElementById('ayFiltre');
    const yil = document.getElementById('yilFiltre');
    function go(){
        const sp = new URLSearchParams();
        if (ay && ay.value) sp.set('ay', ay.value);
        if (yil && yil.value) sp.set('yil', yil.value);
        window.location.href = 'puantaj.php' + (sp.toString() ? ('?' + sp.toString()) : '');
    }
    if (ay) ay.addEventListener('change', go);
    if (yil) yil.addEventListener('change', go);
});
</script>

<?php include '../includes/footer.php'; ?>


