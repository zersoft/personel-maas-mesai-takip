<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Bordro Ödeme Özeti';

$ay = isset($_GET['ay']) ? (int)$_GET['ay'] : date('n');
$yil = isset($_GET['yil']) ? (int)$_GET['yil'] : date('Y');
if ($ay < 1 || $ay > 12) $ay = date('n');
if ($yil < 2000 || $yil > 2100) $yil = date('Y');

try {
    $stmt = $pdo->prepare("SELECT p.ad_soyad,
        COALESCE(b.brut_maas, 0) AS brut_maas,
        (COALESCE(b.izin_kesintisi,0)+COALESCE(b.sgk_kesintisi,0)+COALESCE(b.diger_kesintiler,0)) AS kesinti_toplam,
        (COALESCE(b.banka_avans, a.banka_avans, 0) + COALESCE(b.nakit_avans, a.nakit_avans, 0)) AS avans_toplam,
        (COALESCE(b.ek_odenek_banka,0) + COALESCE(b.ek_odenek_nakit,0)) AS ilave_odeme_toplam,
        (GREATEST((b.brut_maas - b.sgk_banka) - (COALESCE(b.izin_kesintisi,0)+COALESCE(b.sgk_kesintisi,0)+COALESCE(b.diger_kesintiler,0)), 0) - COALESCE(b.nakit_avans, a.nakit_avans, 0) + COALESCE(b.ek_odenek_nakit,0)) as nakit_pay,
        (GREATEST(b.sgk_banka - GREATEST((COALESCE(b.izin_kesintisi,0)+COALESCE(b.sgk_kesintisi,0)+COALESCE(b.diger_kesintiler,0)) - (b.brut_maas - b.sgk_banka), 0), 0) - COALESCE(b.banka_avans, a.banka_avans, 0) + COALESCE(b.ek_odenek_banka,0)) as banka_pay,
        GREATEST((GREATEST((b.brut_maas - b.sgk_banka) - (COALESCE(b.izin_kesintisi,0)+COALESCE(b.sgk_kesintisi,0)+COALESCE(b.diger_kesintiler,0)), 0) - COALESCE(b.nakit_avans, a.nakit_avans, 0) + COALESCE(b.ek_odenek_nakit,0))
               + (GREATEST(b.sgk_banka - GREATEST((COALESCE(b.izin_kesintisi,0)+COALESCE(b.sgk_kesintisi,0)+COALESCE(b.diger_kesintiler,0)) - (b.brut_maas - b.sgk_banka), 0), 0) - COALESCE(b.banka_avans, a.banka_avans, 0) + COALESCE(b.ek_odenek_banka,0)), 0) as toplam_odenecek
        FROM bordro b
        LEFT JOIN personel_listesi p ON b.personel_id = p.id
        LEFT JOIN (
            SELECT personel_id, SUM(banka_tutari) AS banka_avans, SUM(nakit_tutari) AS nakit_avans
            FROM avans_takip
            WHERE ( (bordro_ay = ? AND bordro_yil = ?) OR (bordro_ay IS NULL AND bordro_yil IS NULL AND MONTH(tarih) = ? AND YEAR(tarih) = ?) )
            GROUP BY personel_id
        ) a ON a.personel_id = b.personel_id
        WHERE b.ay = ? AND b.yil = ?
        ORDER BY p.ad_soyad");
    $stmt->execute([$ay, $yil, $ay, $yil, $ay, $yil]);
    $rows = $stmt->fetchAll();
} catch(PDOException $e) {
    $rows = [];
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 report-header">
    <h1 class="report-title"><i class="bi bi-receipt"></i> Bordro Ödeme Özeti</h1>
    <span class="badge rounded-pill text-bg-dark period-pill">Dönem: <?php echo getTurkishMonthName($ay) . ' ' . $yil; ?></span>
</div>
<div class="no-print d-flex gap-2 justify-content-end mb-2">
        <a href="bordro.php?ay=<?php echo $ay; ?>&yil=<?php echo $yil; ?>" class="btn btn-outline-primary">
            <i class="bi bi-arrow-left"></i> Bordroya Dön
        </a>
        <a href="bordro_odeme_ozeti_pdf.php?ay=<?php echo $ay; ?>&yil=<?php echo $yil; ?>" target="_blank" class="btn btn-outline-secondary">
            <i class="bi bi-file-earmark-pdf"></i> PDF
        </a>
        <button type="button" class="btn btn-outline-dark" onclick="window.print()">
            <i class="bi bi-printer"></i> Yazdır
        </button>
</div>

<style>
.text-orange {
    color: #d97706 !important;
}

.report-title {
    font-size: 2rem;
    margin: 0;
}

.period-pill {
    font-size: 0.9rem;
    letter-spacing: 0.2px;
    padding: 0.45rem 0.7rem;
}

.report-header {
    margin-bottom: 0.75rem !important;
}

.summary-card .card-body {
    padding: 0.75rem 1rem;
}

.summary-card h6 {
    margin-bottom: 0.25rem;
    font-size: 0.95rem;
}

.summary-card h4 {
    margin-bottom: 0;
}

table#bordro-ozet-table td.personel-col,
table#bordro-ozet-table th.personel-col {
    white-space: nowrap;
}

@media print {
    @page { size: A4 landscape; margin: 8mm; }
    .no-print, .navbar, .btn-close { display: none !important; }
    .card { border: none !important; box-shadow: none !important; }
    .table { border-collapse: collapse !important; }
    .table th, .table td { border: 1px solid #000 !important; }
    a[href]:after { content: "" !important; }
    body { color: #000 !important; }
    .text-orange { color: #b45309 !important; }
    .report-title { font-size: 1.6rem !important; }
    .period-pill {
        font-size: 0.72rem !important;
        padding: 0.18rem 0.45rem !important;
    }
    .report-header { margin-bottom: 0.35rem !important; }
    .card { margin-bottom: 0.4rem !important; }
    .summary-card .card-body { padding: 0.45rem 0.6rem !important; }
    .summary-card h6 { font-size: 0.8rem !important; margin-bottom: 0.1rem !important; }
    .summary-card h4 { font-size: 1.1rem !important; margin-bottom: 0 !important; line-height: 1.1 !important; }
    .summary-card .text-center.mt-3 { margin-top: 0.25rem !important; }
    .summary-card .text-center.mt-3 small { font-size: 10px !important; }
    body, .table { font-size: 10px !important; }
    #bordro-ozet-table { width: 100% !important; table-layout: fixed !important; }
    #bordro-ozet-table th, #bordro-ozet-table td {
        padding: 2px 4px !important;
        line-height: 1.15 !important;
        vertical-align: middle !important;
    }
    #bordro-ozet-table th.personel-col, #bordro-ozet-table td.personel-col {
        width: 22% !important;
        white-space: nowrap !important;
        font-size: 10.5px !important;
    }
    #bordro-ozet-table th.col-brut, #bordro-ozet-table td.col-brut { width: 13% !important; }
    #bordro-ozet-table th.col-ilave, #bordro-ozet-table td.col-ilave { width: 10% !important; }
    #bordro-ozet-table th.col-kesinti, #bordro-ozet-table td.col-kesinti { width: 10% !important; }
    #bordro-ozet-table th.col-avans, #bordro-ozet-table td.col-avans { width: 10% !important; }
    #bordro-ozet-table th.col-banka, #bordro-ozet-table td.col-banka { width: 11% !important; }
    #bordro-ozet-table th.col-nakit, #bordro-ozet-table td.col-nakit { width: 11% !important; }
    #bordro-ozet-table th.col-net, #bordro-ozet-table td.col-net { width: 13% !important; }
    /* Toplam özetini tek satır yap */
    .summary-inline { display: block !important; text-align: center !important; }
    .summary-inline .col-md-4 { display: inline-block !important; width: 32% !important; padding: 0 !important; margin: 0 !important; vertical-align: top !important; }
}
</style>

<form class="row g-3 mb-4 no-print" method="GET">
    <div class="col-md-4">
        <label class="form-label">Ay</label>
        <select class="form-select" name="ay">
            <?php for($i=1; $i<=12; $i++): ?>
                <option value="<?php echo $i; ?>" <?php echo $i == $ay ? 'selected' : ''; ?>>
                    <?php echo getTurkishMonthName($i); ?>
                </option>
            <?php endfor; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Yıl</label>
        <input type="number" class="form-control" name="yil" value="<?php echo $yil; ?>" min="2020" max="<?php echo date('Y')+1; ?>">
    </div>
    <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-primary w-100">Filtrele</button>
    </div>
</form>

<?php if (empty($rows)): ?>
    <div class="alert alert-info"><i class="bi bi-info-circle"></i> Bu ay için bordro verisi bulunamadı.</div>
<?php else: ?>
    <?php
        $sumBrut = 0; $sumIlave = 0; $sumKesinti = 0; $sumAvans = 0;
        $sumBanka = 0; $sumNakit = 0; $sumToplam = 0;
        foreach($rows as $r) {
            $sumBrut += (float)$r['brut_maas'];
            $sumIlave += (float)$r['ilave_odeme_toplam'];
            $sumKesinti += (float)$r['kesinti_toplam'];
            $sumAvans += (float)$r['avans_toplam'];
            $sumBanka += $r['banka_pay'];
            $sumNakit += $r['nakit_pay'];
            $sumToplam += $r['toplam_odenecek'];
        }
    ?>
    <div class="card mb-2 summary-card">
        <div class="card-body">
            <div class="row text-center summary-inline">
                <div class="col-md-4"><h6>Toplam Banka</h6><h4 class="text-success"><?php echo formatMoney($sumBanka); ?></h4></div>
                <div class="col-md-4"><h6>Toplam Nakit</h6><h4 class="text-orange"><?php echo formatMoney($sumNakit); ?></h4></div>
                <div class="col-md-4"><h6>Toplam Ödenecek</h6><h4 class="text-primary"><?php echo formatMoney($sumToplam); ?></h4></div>
            </div>
            <div class="text-center mt-3">
                <small class="text-muted">Dönem: <?php echo getTurkishMonthName($ay) . ' ' . $yil; ?> · Oluşturma: <?php echo date('d.m.Y H:i'); ?></small>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="bordro-ozet-table" class="table table-hover">
                    <thead>
                        <tr>
                            <th class="personel-col">Personel</th>
                            <th class="money col-brut">Brüt</th>
                            <th class="money text-success col-ilave">İlave Ödeme</th>
                            <th class="money text-danger col-kesinti">Kesinti</th>
                            <th class="money text-orange col-avans">Avans</th>
                            <th class="money col-banka">Banka</th>
                            <th class="money col-nakit">Nakit</th>
                            <th class="money col-net">Net Ödenecek</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($rows as $r): ?>
                            <tr>
                                <td class="personel-col"><?php echo escape($r['ad_soyad']); ?></td>
                                <td class="money col-brut"><?php echo formatMoney($r['brut_maas']); ?></td>
                                <td class="money text-success col-ilave"><?php echo formatMoney($r['ilave_odeme_toplam']); ?></td>
                                <td class="money text-danger col-kesinti"><?php echo formatMoney($r['kesinti_toplam']); ?></td>
                                <td class="money text-orange col-avans"><?php echo formatMoney($r['avans_toplam']); ?></td>
                                <td class="money col-banka"><?php echo formatMoney($r['banka_pay']); ?></td>
                                <td class="money col-nakit"><?php echo formatMoney($r['nakit_pay']); ?></td>
                                <td class="money col-net"><?php echo formatMoney($r['toplam_odenecek']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-primary">
                            <th class="personel-col">Toplam</th>
                            <th class="money col-brut"><?php echo formatMoney($sumBrut); ?></th>
                            <th class="money text-success col-ilave"><?php echo formatMoney($sumIlave); ?></th>
                            <th class="money text-danger col-kesinti"><?php echo formatMoney($sumKesinti); ?></th>
                            <th class="money text-orange col-avans"><?php echo formatMoney($sumAvans); ?></th>
                            <th class="money col-banka"><?php echo formatMoney($sumBanka); ?></th>
                            <th class="money col-nakit"><?php echo formatMoney($sumNakit); ?></th>
                            <th class="money col-net"><?php echo formatMoney($sumToplam); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>


