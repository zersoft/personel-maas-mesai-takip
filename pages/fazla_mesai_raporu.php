<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Fazla Mesai Raporu';

// Varsayılan dönem: fazla mesai tablosundaki son kayıt (yoksa bugünün ay/yılı)
$defaultAy = (int)date('n');
$defaultYil = (int)date('Y');
try {
    $son = $pdo->query("SELECT YEAR(tarih) AS yil, MONTH(tarih) AS ay FROM fazla_mesai ORDER BY tarih DESC LIMIT 1")->fetch();
    if ($son) {
        $defaultAy = (int)$son['ay'];
        $defaultYil = (int)$son['yil'];
    }
} catch (PDOException $e) {}

$ay = isset($_GET['ay']) && (int)$_GET['ay'] > 0 ? (int)$_GET['ay'] : $defaultAy;
$yil = isset($_GET['yil']) && (int)$_GET['yil'] > 0 ? (int)$_GET['yil'] : $defaultYil;

// Kişi bazlı özet: toplam saat, ağırlıklı ortalama saat ücreti, toplam FM bedeli, toplam ödeme ve alacak
$raporSatirlari = [];
$topSaat = 0.0; $topBedel = 0.0; $topOdeme = 0.0; $topAlacak = 0.0;
try {
    $sql = "
        SELECT p.id AS personel_id, p.ad_soyad,
               COALESCE(SUM(fm.saat), 0) AS toplam_saat,
               CASE WHEN COALESCE(SUM(fm.saat),0) > 0
                    THEN SUM(fm.saat * fm.saat_ucreti) / SUM(fm.saat)
                    ELSE 0 END AS ort_saat_ucreti,
               COALESCE(SUM(fm.saat * fm.saat_ucreti), 0) AS toplam_bedel,
               COALESCE(odm.toplam_odeme, 0) AS toplam_odeme,
               (COALESCE(SUM(fm.saat * fm.saat_ucreti), 0) - COALESCE(odm.toplam_odeme, 0)) AS alacak
        FROM personel_listesi p
        LEFT JOIN fazla_mesai fm ON fm.personel_id = p.id AND MONTH(fm.tarih) = ? AND YEAR(fm.tarih) = ?
        LEFT JOIN (
            SELECT personel_id, SUM(tutar) AS toplam_odeme
            FROM fazla_mesai_odeme
            WHERE MONTH(odeme_tarihi) = ? AND YEAR(odeme_tarihi) = ?
            GROUP BY personel_id
        ) odm ON odm.personel_id = p.id
        WHERE p.aktif = 1
        GROUP BY p.id, p.ad_soyad, odm.toplam_odeme
        HAVING COALESCE(SUM(fm.saat), 0) > 0 OR COALESCE(odm.toplam_odeme, 0) > 0
        ORDER BY p.ad_soyad ASC";
    $st = $pdo->prepare($sql);
    $st->execute([$ay, $yil, $ay, $yil]);
    $raporSatirlari = $st->fetchAll();

    foreach ($raporSatirlari as $r) {
        $topSaat += (float)$r['toplam_saat'];
        $topBedel += (float)$r['toplam_bedel'];
        $topOdeme += (float)$r['toplam_odeme'];
        $topAlacak += (float)$r['alacak'];
    }
} catch (PDOException $e) {
    $raporSatirlari = [];
}

include '../includes/header.php';

if (isset($_GET['error'])) {
    echo showMessage('Hata: ' . escape($_GET['error']), 'danger');
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1><i class="bi bi-clock-history"></i> Fazla Mesai Raporu</h1>
    <div class="d-flex gap-2">
        <a href="fazla_mesai.php" class="btn btn-outline-primary">
            <i class="bi bi-arrow-left"></i> Fazla Mesai'ye Dön
        </a>
        <button class="btn btn-outline-secondary" onclick="window.print()">
            <i class="bi bi-printer"></i> PDF / Yazdır
        </button>
    </div>
    <style>
        @media print {
            nav, .navbar, .btn, .form-select, .form-control, .filter-row { display: none !important; }
            .card { border: none; }
            .table { font-size: 12px; }
        }
    </style>
<?php // wrapper kapanışı aşağıda ?>
</div>

<div class="card">
    <div class="card-body">
        <div class="row g-2 align-items-end filter-row">
            <div class="col-md-2">
                <label class="form-label">Ay</label>
                <select class="form-select" id="ayFiltre">
                    <?php for ($i=1; $i<=12; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo $i==$ay?'selected':''; ?>><?php echo getTurkishMonthName($i); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Yıl</label>
                <select class="form-select" id="yilFiltre">
                    <?php for ($y=date('Y'); $y>=date('Y')-5; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y==$yil?'selected':''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2 ms-auto">
                <div class="form-control form-control-sm d-flex justify-content-between align-items-center">
                    <span class="text-muted small">Dönem</span>
                    <span class="fw-semibold"><?php echo getTurkishMonthName($ay) . ' ' . $yil; ?></span>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-control form-control-sm d-flex justify-content-between align-items-center">
                    <span class="text-muted small">Toplam FM</span>
                    <span class="fw-semibold"><?php echo formatMoney($topBedel); ?></span>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-control form-control-sm d-flex justify-content-between align-items-center">
                    <span class="text-muted small">Ödeme</span>
                    <span class="fw-semibold text-success"><?php echo formatMoney($topOdeme); ?></span>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-control form-control-sm d-flex justify-content-between align-items-center">
                    <span class="text-muted small">Alacak</span>
                    <span class="fw-semibold text-primary"><?php echo formatMoney($topAlacak); ?></span>
                </div>
            </div>
        </div>

        <?php if (empty($raporSatirlari)): ?>
            <div class="alert alert-info mt-3">Seçilen dönem için fazla mesai kaydı veya ödeme bulunamadı.</div>
        <?php else: ?>
        <div class="table-responsive mt-3">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Personel</th>
                        <th class="text-end">Toplam Saat</th>
                        <th class="text-end">Saat Ücreti (Ort.)</th>
                        <th class="text-end">Toplam FM Bedeli</th>
                        <th class="text-end">Ödeme</th>
                        <th class="text-end">FM Alacağı</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($raporSatirlari as $r): ?>
                        <tr>
                            <td><?php echo escape($r['ad_soyad']); ?></td>
                            <td class="text-end"><?php echo number_format((float)$r['toplam_saat'], 2, ',', '.'); ?></td>
                            <td class="text-end"><?php echo number_format((float)$r['ort_saat_ucreti'], 2, ',', '.'); ?></td>
                            <td class="text-end"><?php echo formatMoney($r['toplam_bedel']); ?></td>
                            <td class="text-end text-success"><?php echo formatMoney($r['toplam_odeme']); ?></td>
                            <td class="text-end text-primary"><?php echo formatMoney($r['alacak']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-primary">
                        <th>TOPLAM</th>
                        <th class="text-end"><?php echo number_format($topSaat, 2, ',', '.'); ?></th>
                        <th></th>
                        <th class="text-end"><?php echo formatMoney($topBedel); ?></th>
                        <th class="text-end"><?php echo formatMoney($topOdeme); ?></th>
                        <th class="text-end"><?php echo formatMoney($topAlacak); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function(){
            const ay = document.getElementById('ayFiltre');
            const yil = document.getElementById('yilFiltre');
            function go(){
                const sp = new URLSearchParams();
                if (ay && ay.value) sp.set('ay', ay.value);
                if (yil && yil.value) sp.set('yil', yil.value);
                window.location.href = 'fazla_mesai_raporu.php' + (sp.toString() ? ('?' + sp.toString()) : '');
            }
            if (ay) ay.addEventListener('change', go);
            if (yil) yil.addEventListener('change', go);
        });
    </script>
</div>

<?php include '../includes/footer.php'; ?>


