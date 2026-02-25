<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Fazla Mesai Ekstresi';

// Personel listesi
$personeller = [];
try {
    $personeller = $pdo->query("SELECT id, ad_soyad FROM personel_listesi WHERE aktif = 1 ORDER BY ad_soyad")->fetchAll();
} catch (PDOException $e) {}

// Varsayılanlar
$defaultPersonel = !empty($personeller) ? (int)$personeller[0]['id'] : 0;
$seciliPersonel = isset($_GET['personel_id']) ? (int)$_GET['personel_id'] : $defaultPersonel;

// Dönem / tarih aralığı
$mode = isset($_GET['mode']) && $_GET['mode'] === 'tarih' ? 'tarih' : 'donem';
$ay = isset($_GET['ay']) ? (int)$_GET['ay'] : (int)date('n');
$yil = isset($_GET['yil']) ? (int)$_GET['yil'] : (int)date('Y');
$baslangic = isset($_GET['baslangic']) ? $_GET['baslangic'] : date('Y-m-01');
$bitis = isset($_GET['bitis']) ? $_GET['bitis'] : date('Y-m-t');

// Ekstre verileri
$satirlar = [];
$topBedel = 0.0; $topOdeme = 0.0; $bakiye = 0.0;
$personelAdi = '';
try {
    if ($seciliPersonel > 0) {
        // Personel adını çek
        $pStmt = $pdo->prepare("SELECT ad_soyad FROM personel_listesi WHERE id=?");
        $pStmt->execute([$seciliPersonel]);
        $pRow = $pStmt->fetch();
        $personelAdi = $pRow ? $pRow['ad_soyad'] : '';
        
        if ($mode === 'donem') {
            // Ay/Yıl bazlı
            $fmSql = "SELECT tarih AS t, 'FM' AS tur, saat, saat_ucreti, (saat * saat_ucreti) AS tutar, NULL AS aciklama
                      FROM fazla_mesai
                      WHERE personel_id = ? AND MONTH(tarih) = ? AND YEAR(tarih) = ?";
            $odSql = "SELECT odeme_tarihi AS t, 'ODEME' AS tur, NULL AS saat, NULL AS saat_ucreti, (tutar * -1) AS tutar, aciklama
                      FROM fazla_mesai_odeme
                      WHERE personel_id = ? AND MONTH(odeme_tarihi) = ? AND YEAR(odeme_tarihi) = ?";
            $st1 = $pdo->prepare($fmSql);
            $st1->execute([$seciliPersonel, $ay, $yil]);
            $st2 = $pdo->prepare($odSql);
            $st2->execute([$seciliPersonel, $ay, $yil]);
        } else {
            // Tarih aralığı
            $fmSql = "SELECT tarih AS t, 'FM' AS tur, saat, saat_ucreti, (saat * saat_ucreti) AS tutar, NULL AS aciklama
                      FROM fazla_mesai
                      WHERE personel_id = ? AND tarih BETWEEN ? AND ?";
            $odSql = "SELECT odeme_tarihi AS t, 'ODEME' AS tur, NULL AS saat, NULL AS saat_ucreti, (tutar * -1) AS tutar, aciklama
                      FROM fazla_mesai_odeme
                      WHERE personel_id = ? AND odeme_tarihi BETWEEN ? AND ?";
            $st1 = $pdo->prepare($fmSql);
            $st1->execute([$seciliPersonel, $baslangic, $bitis]);
            $st2 = $pdo->prepare($odSql);
            $st2->execute([$seciliPersonel, $baslangic, $bitis]);
        }

        $rows = array_merge($st1->fetchAll(), $st2->fetchAll());
        // Tarihe göre sırala
        usort($rows, function($a, $b){ return strcmp($a['t'], $b['t']); });
        $satirlar = $rows;

        foreach ($satirlar as $s) {
            if ($s['tur'] === 'FM') $topBedel += (float)$s['tutar'];
            if ($s['tur'] === 'ODEME') $topOdeme += (float)abs($s['tutar']);
            $bakiye += (float)$s['tutar'];
        }
    }
} catch (PDOException $e) {
    $satirlar = [];
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1><i class="bi bi-receipt"></i> Fazla Mesai Ekstresi</h1>
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
</div>

<div class="card">
    <div class="card-body">
        <div class="row g-2 align-items-end filter-row">
            <div class="col-md-4">
                <label class="form-label">Personel</label>
                <select id="personel" class="form-select">
                    <?php foreach ($personeller as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo ($p['id']==$seciliPersonel)?'selected':''; ?>><?php echo escape($p['ad_soyad']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-8">
                <div class="d-flex gap-3 align-items-end flex-wrap">
                    <div>
                        <label class="form-label">Mod</label>
                        <select id="mode" class="form-select">
                            <option value="donem" <?php echo $mode==='donem'?'selected':''; ?>>Ay / Yıl</option>
                            <option value="tarih" <?php echo $mode==='tarih'?'selected':''; ?>>Tarih Aralığı</option>
                        </select>
                    </div>
                    <div id="donemInputs" class="d-flex gap-2" style="<?php echo $mode==='donem'?'':'display:none;'; ?>">
                        <div>
                            <label class="form-label">Ay</label>
                            <select id="ay" class="form-select">
                                <?php for ($i=1;$i<=12;$i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $i==$ay?'selected':''; ?>><?php echo getTurkishMonthName($i); ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Yıl</label>
                            <select id="yil" class="form-select">
                                <?php for ($y=date('Y'); $y>=date('Y')-5; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $y==$yil?'selected':''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div id="tarihInputs" class="d-flex gap-2" style="<?php echo $mode==='tarih'?'':'display:none;'; ?>">
                        <div>
                            <label class="form-label">Başlangıç</label>
                            <input type="date" id="baslangic" class="form-control" value="<?php echo escape($baslangic); ?>">
                        </div>
                        <div>
                            <label class="form-label">Bitiş</label>
                            <input type="date" id="bitis" class="form-control" value="<?php echo escape($bitis); ?>">
                        </div>
                    </div>
                    <div>
                        <button id="uygula" class="btn btn-primary">Uygula</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ekstre Başlık Bilgileri (Yazdırma için) -->
        <div class="mt-4 mb-3 p-3 bg-light border rounded">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="mb-2">Ekstre Bilgileri</h5>
                    <p class="mb-1"><strong>Personel:</strong> <?php echo escape($personelAdi); ?></p>
                    <p class="mb-1"><strong>Dönem:</strong> 
                        <?php 
                        if ($mode === 'donem') {
                            echo getTurkishMonthName($ay) . ' ' . $yil;
                        } else {
                            echo date('d.m.Y', strtotime($baslangic)) . ' - ' . date('d.m.Y', strtotime($bitis));
                        }
                        ?>
                    </p>
                    <p class="mb-0"><small class="text-muted">Oluşturma: <?php echo date('d.m.Y H:i'); ?></small></p>
                </div>
                <div class="col-md-6">
                    <div class="row g-2">
                        <div class="col-12">
                            <div class="d-flex justify-content-between p-2 bg-white border rounded">
                                <span class="text-muted">Toplam FM:</span>
                                <span class="fw-semibold"><?php echo formatMoney($topBedel); ?></span>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between p-2 bg-white border rounded">
                                <span class="text-muted">Ödeme:</span>
                                <span class="fw-semibold text-success"><?php echo formatMoney($topOdeme); ?></span>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between p-2 bg-primary text-white border rounded">
                                <span class="fw-bold">Bakiye:</span>
                                <span class="fw-bold"><?php echo formatMoney($bakiye); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (empty($satirlar)): ?>
            <div class="alert alert-info mt-3">Seçilen kritere göre kayıt bulunamadı.</div>
        <?php else: ?>
        <div class="table-responsive mt-3">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Tür</th>
                        <th class="text-end">Saat</th>
                        <th class="text-end">Saat Ücreti</th>
                        <th class="text-end">Tutar</th>
                        <th>Açıklama</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($satirlar as $s): ?>
                        <tr>
                            <td><?php echo date('d.m.Y', strtotime($s['t'])); ?></td>
                            <td><?php echo $s['tur']==='FM' ? 'FM' : 'ÖDEME'; ?></td>
                            <td class="text-end"><?php echo $s['saat'] !== null ? number_format((float)$s['saat'], 2, ',', '.') : '-'; ?></td>
                            <td class="text-end"><?php echo $s['saat_ucreti'] !== null ? number_format((float)$s['saat_ucreti'], 2, ',', '.') : '-'; ?></td>
                            <td class="text-end <?php echo ($s['tur']==='FM') ? '' : 'text-success'; ?>">
                                <?php echo formatMoney($s['tutar']); ?>
                            </td>
                            <td><?php echo escape($s['aciklama'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-primary">
                        <th colspan="4" class="text-end">TOPLAM:</th>
                        <th class="text-end"><?php echo formatMoney($topBedel); ?></th>
                        <th></th>
                    </tr>
                    <tr class="table-success">
                        <th colspan="4" class="text-end">ÖDEME:</th>
                        <th class="text-end"><?php echo formatMoney($topOdeme); ?></th>
                        <th></th>
                    </tr>
                    <tr class="table-info">
                        <th colspan="4" class="text-end">BAKİYE:</th>
                        <th class="text-end"><?php echo formatMoney($bakiye); ?></th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const modeEl = document.getElementById('mode');
    const donemInputs = document.getElementById('donemInputs');
    const tarihInputs = document.getElementById('tarihInputs');
    const uygula = document.getElementById('uygula');
    function toggle() {
        if (modeEl.value === 'donem') {
            donemInputs.style.display = '';
            tarihInputs.style.display = 'none';
        } else {
            donemInputs.style.display = 'none';
            tarihInputs.style.display = '';
        }
    }
    if (modeEl) modeEl.addEventListener('change', toggle);
    toggle();

    function go() {
        const sp = new URLSearchParams();
        const personel = document.getElementById('personel');
        if (personel && personel.value) sp.set('personel_id', personel.value);
        if (modeEl && modeEl.value) sp.set('mode', modeEl.value);
        if (modeEl.value === 'donem') {
            const ay = document.getElementById('ay');
            const yil = document.getElementById('yil');
            if (ay && ay.value) sp.set('ay', ay.value);
            if (yil && yil.value) sp.set('yil', yil.value);
        } else {
            const bas = document.getElementById('baslangic');
            const bit = document.getElementById('bitis');
            if (bas && bas.value) sp.set('baslangic', bas.value);
            if (bit && bit.value) sp.set('bitis', bit.value);
        }
        window.location.href = 'fazla_mesai_ekstre.php' + (sp.toString() ? ('?' + sp.toString()) : '');
    }
    if (uygula) uygula.addEventListener('click', function(e){ e.preventDefault(); go(); });
});
</script>

<?php include '../includes/footer.php'; ?>


