<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Bordro Ödeme Özeti';

$ay = isset($_GET['ay']) ? (int)$_GET['ay'] : date('n');
$yil = isset($_GET['yil']) ? (int)$_GET['yil'] : date('Y');
if ($ay < 1 || $ay > 12) $ay = date('n');
if ($yil < 2000 || $yil > 2100) $yil = date('Y');

try {
    $stmt = $pdo->prepare("SELECT b.*, p.ad_soyad,
        COALESCE(b.aciklama, '') AS aciklama,
        COALESCE(b.kesinti_aciklama, '') AS kesinti_aciklama,
        a.banka_avans AS a_banka_avans,
        a.nakit_avans AS a_nakit_avans
        FROM bordro b
        LEFT JOIN personel_listesi p ON b.personel_id = p.id
        LEFT JOIN (
            SELECT personel_id, SUM(banka_tutari) AS banka_avans, SUM(nakit_tutari) AS nakit_avans
            FROM avans_takip
            WHERE ( (bordro_ay = ? AND bordro_yil = ?) OR (bordro_ay IS NULL AND bordro_yil IS NULL AND MONTH(tarih) = ? AND YEAR(tarih) = ?) )
            GROUP BY personel_id
        ) a ON a.personel_id = b.personel_id
        WHERE b.ay = ? AND b.yil = ?
        ORDER BY p.ad_soyad ASC");
    $stmt->execute([$ay, $yil, $ay, $yil, $ay, $yil]);
    $rawRows = $stmt->fetchAll();

    $rows = [];
    foreach ($rawRows as $bRow) {
        $avB = (float)($bRow['banka_avans'] ?? 0);
        $avN = (float)($bRow['nakit_avans'] ?? 0);
        if ($avB == 0 && $avN == 0) {
            $avB = (float)($bRow['a_banka_avans'] ?? 0);
            $avN = (float)($bRow['a_nakit_avans'] ?? 0);
        }

        $ekoB = (float)($bRow['ek_odenek_banka'] ?? 0);
        $ekoN = (float)($bRow['ek_odenek_nakit'] ?? ($bRow['ek_odenek'] ?? 0));
        $izinK = (float)($bRow['izin_kesintisi'] ?? 0);
        $sgkK = (float)($bRow['sgk_kesintisi'] ?? 0);
        $digerK = (float)($bRow['diger_kesintiler'] ?? 0);

        $calc = calculateBordroPay(
            $bRow['brut_maas'],
            $bRow['sgk_banka'] ?? 0,
            $ekoB,
            $ekoN,
            $izinK,
            $sgkK,
            $digerK,
            $avB,
            $avN
        );

        $bRow['kesinti_toplam'] = $izinK + $sgkK + $digerK;
        $bRow['avans_toplam'] = $avB + $avN;
        $bRow['ilave_odeme_toplam'] = $calc['toplam_ek_odenek'];
        $bRow['banka_pay'] = $calc['banka_net'];
        $bRow['nakit_pay'] = $calc['nakit_net'];
        $bRow['toplam_odenecek'] = $calc['toplam_odenecek'];
        $rows[] = $bRow;
    }
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

.notes-cell {
    min-width: 120px;
}

.notes-wrap {
    max-height: 30px;
    overflow: hidden;
    white-space: nowrap;
    font-size: 8px;
    line-height: 1.15;
}

.note-item {
    display: inline-block;
    border-radius: 999px;
    padding: 1px 7px;
    margin-right: 4px;
    border: 1px solid transparent;
    max-width: 105px;
    overflow: hidden;
    text-overflow: ellipsis;
    vertical-align: middle;
}

.note-item-general {
    background: #e0f2fe;
    color: #075985;
    border-color: #bae6fd;
}

.note-item-kesinti {
    background: #fef2f2;
    color: #991b1b;
    border-color: #fecaca;
}

table#bordro-ozet-table td.personel-col,
table#bordro-ozet-table th.personel-col {
    white-space: nowrap;
}

@media print {
    @page { size: A4 landscape; margin: 6mm; }
    .no-print, .navbar, .btn-close, footer { display: none !important; }
    html, body { margin: 0 !important; padding: 0 !important; }
    .card { border: none !important; box-shadow: none !important; }
    .table { border-collapse: collapse !important; }
    .table th, .table td { border: 1px solid #000 !important; }
    a[href]:after { content: "" !important; }
    body { color: #000 !important; }
    .text-orange { color: #b45309 !important; }
    /* Açıklama: çıktıda sadece metin rengi, ayrı container yok */
    .note-item, .note-item-general, .note-item-kesinti {
        background: none !important; border: none !important; border-radius: 0 !important;
        padding: 0 !important; margin-right: 0.25em !important;
    }
    .note-item-general { color: #075985 !important; }
    .note-item-kesinti { color: #991b1b !important; }
    .report-title { font-size: 1.3rem !important; }
    .period-pill {
        font-size: 0.68rem !important;
        padding: 0.12rem 0.35rem !important;
    }
    .report-header { margin-bottom: 0.2rem !important; }
    .card { margin-bottom: 0.22rem !important; }
    .summary-card .card-body { padding: 0.28rem 0.45rem !important; }
    .summary-card h6 { font-size: 0.7rem !important; margin-bottom: 0 !important; }
    .summary-card h4 { font-size: 0.92rem !important; margin-bottom: 0 !important; line-height: 1.05 !important; }
    .summary-card .text-center.mt-3 { margin-top: 0.15rem !important; }
    .summary-card .text-center.mt-3 small { font-size: 8.5px !important; }
    body, .table { font-size: 8.8px !important; }
    .notes-cell { min-width: 0 !important; }
    .notes-wrap {
        font-size: 6.5px !important;
        max-height: 16px !important;
    }
    .note-item {
        max-width: 58px !important;
        padding: 0 3px !important;
        margin-right: 2px !important;
    }
    #bordro-ozet-table { width: 100% !important; table-layout: fixed !important; }
    #bordro-ozet-table th, #bordro-ozet-table td {
        padding: 1px 2px !important;
        line-height: 1.05 !important;
        vertical-align: middle !important;
    }
    #bordro-ozet-table th.personel-col, #bordro-ozet-table td.personel-col {
        width: 20% !important;
        white-space: nowrap !important;
        font-size: 8.8px !important;
    }
    #bordro-ozet-table th.col-brut, #bordro-ozet-table td.col-brut { width: 12% !important; }
    #bordro-ozet-table th.col-ilave, #bordro-ozet-table td.col-ilave { width: 8% !important; }
    #bordro-ozet-table th.col-kesinti, #bordro-ozet-table td.col-kesinti { width: 8% !important; }
    #bordro-ozet-table th.col-avans, #bordro-ozet-table td.col-avans { width: 8% !important; }
    #bordro-ozet-table th.col-banka, #bordro-ozet-table td.col-banka { width: 9% !important; }
    #bordro-ozet-table th.col-nakit, #bordro-ozet-table td.col-nakit { width: 9% !important; }
    #bordro-ozet-table th.col-net, #bordro-ozet-table td.col-net { width: 12% !important; }
    #bordro-ozet-table th.col-notes, #bordro-ozet-table td.col-notes { width: 10% !important; }
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
                <table id="bordro-ozet-table" class="table table-hover table-sortable">
                    <thead>
                        <tr>
                            <th class="personel-col sortable" data-sort="text">Personel <i class="bi bi-arrow-down-up small text-muted"></i></th>
                            <th class="money col-brut sortable" data-sort="money">Brüt <i class="bi bi-arrow-down-up small text-muted"></i></th>
                            <th class="money text-success col-ilave sortable" data-sort="money">İlave Ödeme <i class="bi bi-arrow-down-up small text-muted"></i></th>
                            <th class="col-notes notes-cell">Açıklamalar</th>
                            <th class="money text-danger col-kesinti sortable" data-sort="money">Kesinti <i class="bi bi-arrow-down-up small text-muted"></i></th>
                            <th class="money text-orange col-avans sortable" data-sort="money">Avans <i class="bi bi-arrow-down-up small text-muted"></i></th>
                            <th class="money col-banka sortable" data-sort="money">Banka <i class="bi bi-arrow-down-up small text-muted"></i></th>
                            <th class="money col-nakit sortable" data-sort="money">Nakit <i class="bi bi-arrow-down-up small text-muted"></i></th>
                            <th class="money col-net sortable" data-sort="money">Net Ödenecek <i class="bi bi-arrow-down-up small text-muted"></i></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($rows as $r): ?>
                            <tr>
                                <td class="personel-col"><?php echo escape($r['ad_soyad']); ?></td>
                                <td class="money col-brut"><?php echo formatMoney($r['brut_maas']); ?></td>
                                <td class="money text-success col-ilave"><?php echo formatMoney($r['ilave_odeme_toplam']); ?></td>
                                <td class="col-notes notes-cell">
                                    <div class="notes-wrap">
                                        <?php
                                        $aciklama_t = trim((string)($r['aciklama'] ?? ''));
                                        $kesinti_t = trim((string)($r['kesinti_aciklama'] ?? ''));
                                        $maxLen = 50;
                                        if ($aciklama_t !== ''): $show = mb_strlen($aciklama_t) > $maxLen ? mb_strimwidth($aciklama_t, 0, $maxLen, '..') : $aciklama_t; ?>
                                            <span class="note-item note-item-general" title="Genel: <?php echo escape($r['aciklama']); ?>">Genel: <?php echo escape($show); ?></span>
                                        <?php endif;
                                        if ($kesinti_t !== ''): $show = mb_strlen($kesinti_t) > $maxLen ? mb_strimwidth($kesinti_t, 0, $maxLen, '..') : $kesinti_t; ?>
                                            <span class="note-item note-item-kesinti" title="Kesinti: <?php echo escape($r['kesinti_aciklama']); ?>">Kesinti: <?php echo escape($show); ?></span>
                                        <?php endif;
                                        if ($aciklama_t === '' && $kesinti_t === ''): ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
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
                            <th class="col-notes"></th>
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


