<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Fazla Mesai Takibi';

// Filtreler
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'bugun';
$baslangic = isset($_GET['baslangic']) ? $_GET['baslangic'] : date('Y-m-d');
$bitis = isset($_GET['bitis']) ? $_GET['bitis'] : date('Y-m-d');
$personel_filtre = isset($_GET['personel_id']) ? (int)$_GET['personel_id'] : 0;
$ay = isset($_GET['ay']) ? (int)$_GET['ay'] : (int)date('n');
$yil = isset($_GET['yil']) ? (int)$_GET['yil'] : (int)date('Y');

// Filtrelenmiş FM listesi ve kümülatif toplamlar
try {
    // Filtre parametrelerini hazırla
    $sqlFiltre = "SELECT fm.*, p.ad_soyad 
                  FROM fazla_mesai fm 
                  LEFT JOIN personel_listesi p ON fm.personel_id = p.id 
                  WHERE 1=1";
    $paramsFiltre = [];
    
    // Personel filtresi
    if ($personel_filtre > 0) {
        $sqlFiltre .= " AND fm.personel_id = ?";
        $paramsFiltre[] = $personel_filtre;
    }
    
    if ($mode === 'bugun') {
        $sqlFiltre .= " AND fm.tarih = ?";
        $paramsFiltre[] = date('Y-m-d');
    } elseif ($mode === 'bu_hafta') {
        $pazartesi = date('Y-m-d', strtotime('monday this week'));
        $sqlFiltre .= " AND fm.tarih BETWEEN ? AND ?";
        $paramsFiltre[] = $pazartesi;
        $paramsFiltre[] = date('Y-m-d');
    } elseif ($mode === 'bu_ay') {
        $sqlFiltre .= " AND MONTH(fm.tarih) = ? AND YEAR(fm.tarih) = ?";
        $paramsFiltre[] = (int)date('n');
        $paramsFiltre[] = (int)date('Y');
    } elseif ($mode === 'donem') {
        $sqlFiltre .= " AND MONTH(fm.tarih) = ? AND YEAR(fm.tarih) = ?";
        $paramsFiltre[] = $ay;
        $paramsFiltre[] = $yil;
    } elseif ($mode === 'tarih') {
        $sqlFiltre .= " AND fm.tarih BETWEEN ? AND ?";
        $paramsFiltre[] = $baslangic;
        $paramsFiltre[] = $bitis;
    }
    
    $sqlFiltre .= " ORDER BY fm.tarih DESC";
    $stmtFiltre = $pdo->prepare($sqlFiltre);
    $stmtFiltre->execute($paramsFiltre);
    $fazlaMesailer = $stmtFiltre->fetchAll();
    
    // Kümülatif toplamlar (filtrelenmiş kayıtlardan)
    $kumulatifToplamlar = [];
    
    // Tüm aktif personelleri al
    $personelStmt = $pdo->query("SELECT id, ad_soyad FROM personel_listesi WHERE aktif = 1 ORDER BY ad_soyad");
    $tumPersoneller = $personelStmt->fetchAll();
    
    // Her personel için başlangıç değerleri
    foreach($tumPersoneller as $p) {
        $kumulatifToplamlar[$p['id']] = [
            'ad_soyad' => $p['ad_soyad'],
            'toplam_saat' => 0,
            'toplam_tutar' => 0,
            'toplam_odeme' => 0,
            'bakiye' => 0
        ];
    }
    
    // FM toplamlarını hesapla (filtrelenmiş kayıtlardan)
    foreach($fazlaMesailer as $fm) {
        $personelId = $fm['personel_id'];
        if (isset($kumulatifToplamlar[$personelId])) {
            $kumulatifToplamlar[$personelId]['toplam_saat'] += $fm['saat'];
            $kumulatifToplamlar[$personelId]['toplam_tutar'] += $fm['tutar'];
        }
    }
    
    // Ödemeleri çek (filtre ile aynı dönemden)
    $sqlOdeme = "SELECT personel_id, SUM(tutar) as toplam_odeme FROM fazla_mesai_odeme WHERE 1=1";
    $paramsOdeme = [];
    
    // Personel filtresi
    if ($personel_filtre > 0) {
        $sqlOdeme .= " AND personel_id = ?";
        $paramsOdeme[] = $personel_filtre;
    }
    
    if ($mode === 'bugun') {
        $sqlOdeme .= " AND odeme_tarihi = ?";
        $paramsOdeme[] = date('Y-m-d');
    } elseif ($mode === 'bu_hafta') {
        $pazartesi = date('Y-m-d', strtotime('monday this week'));
        $sqlOdeme .= " AND odeme_tarihi BETWEEN ? AND ?";
        $paramsOdeme[] = $pazartesi;
        $paramsOdeme[] = date('Y-m-d');
    } elseif ($mode === 'bu_ay') {
        $sqlOdeme .= " AND MONTH(odeme_tarihi) = ? AND YEAR(odeme_tarihi) = ?";
        $paramsOdeme[] = (int)date('n');
        $paramsOdeme[] = (int)date('Y');
    } elseif ($mode === 'donem') {
        $sqlOdeme .= " AND MONTH(odeme_tarihi) = ? AND YEAR(odeme_tarihi) = ?";
        $paramsOdeme[] = $ay;
        $paramsOdeme[] = $yil;
    } elseif ($mode === 'tarih') {
        $sqlOdeme .= " AND odeme_tarihi BETWEEN ? AND ?";
        $paramsOdeme[] = $baslangic;
        $paramsOdeme[] = $bitis;
    }
    
    $sqlOdeme .= " GROUP BY personel_id";
    $odemeStmt = $pdo->prepare($sqlOdeme);
    $odemeStmt->execute($paramsOdeme);
    $odemeler = $odemeStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    foreach($kumulatifToplamlar as $pid => &$toplam) {
        $toplam['toplam_odeme'] = isset($odemeler[$pid]) ? (float)$odemeler[$pid] : 0;
        $toplam['bakiye'] = $toplam['toplam_tutar'] - $toplam['toplam_odeme'];
    }
    
    // Sadece FM'si veya ödemesi olan personelleri göster
    $kumulatifToplamlar = array_filter($kumulatifToplamlar, function($t) {
        return $t['toplam_tutar'] > 0 || $t['toplam_odeme'] > 0;
    });
} catch(PDOException $e) {
    $fazlaMesailer = [];
    $kumulatifToplamlar = [];
}

include '../includes/header.php';

// Mesaj gösterimi
if (isset($_GET['success'])) {
    echo showMessage('Fazla mesai başarıyla kaydedildi!', 'success');
}
if (isset($_GET['error'])) {
    echo showMessage('Hata: ' . escape($_GET['error']), 'danger');
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="bi bi-clock-history"></i> Fazla Mesai Takibi</h3>
    <div class="d-flex gap-1">
        <a href="fazla_mesai_raporu.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-list-check"></i> FM Raporu
        </a>
        <a href="fazla_mesai_ekstre.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-journal-text"></i> FM Ekstresi
        </a>
        <a href="fazla_mesai_odeme_listesi.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-receipt"></i> Ödeme Listesi
        </a>
        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#odemeYapModal">
            <i class="bi bi-cash-coin"></i> Ödeme Yap
        </button>
        <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#topluOdemeModal">
            <i class="bi bi-cash-stack"></i> Toplu Ödeme
        </button>
        <a href="toplu_fazla_mesai.php" class="btn btn-sm btn-warning">
            <i class="bi bi-file-earmark-spreadsheet"></i> Toplu FM
        </a>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#fazlaMesaiEkleModal">
            <i class="bi bi-plus-circle"></i> FM Ekle
        </button>
    </div>
</div>

<!-- Filtre -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
            <div style="min-width: 200px;">
                <select class="form-select form-select-sm" name="personel_id" id="personelFiltre">
                    <option value="0">Tüm Personel</option>
                    <?php
                    try {
                        $personelListesi = $pdo->query("SELECT id, ad_soyad FROM personel_listesi WHERE aktif = 1 ORDER BY ad_soyad")->fetchAll();
                        foreach($personelListesi as $p):
                    ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo $personel_filtre == $p['id'] ? 'selected' : ''; ?>>
                            <?php echo escape($p['ad_soyad']); ?>
                        </option>
                    <?php endforeach; } catch(PDOException $e) {} ?>
                </select>
            </div>
            <div style="min-width: 150px;">
                <select class="form-select form-select-sm" name="mode" id="modeSelect">
                    <option value="bugun" <?php echo $mode==='bugun'?'selected':''; ?>>Bugün</option>
                    <option value="bu_hafta" <?php echo $mode==='bu_hafta'?'selected':''; ?>>Bu Hafta</option>
                    <option value="bu_ay" <?php echo $mode==='bu_ay'?'selected':''; ?>>Bu Ay</option>
                    <option value="donem" <?php echo $mode==='donem'?'selected':''; ?>>Dönem</option>
                    <option value="tarih" <?php echo $mode==='tarih'?'selected':''; ?>>Tarih Aralığı</option>
                </select>
            </div>
            <div id="donemInputs" class="d-flex gap-2" style="<?php echo $mode==='donem'?'':'display:none;'; ?>">
                <select class="form-select form-select-sm" name="ay" style="width: 110px;">
                    <?php for($i=1;$i<=12;$i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo $i==$ay?'selected':''; ?>><?php echo getTurkishMonthName($i); ?></option>
                    <?php endfor; ?>
                </select>
                <select class="form-select form-select-sm" name="yil" style="width: 90px;">
                    <?php for($y=date('Y'); $y>=date('Y')-5; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y==$yil?'selected':''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div id="tarihInputs" class="d-flex gap-2" style="<?php echo $mode==='tarih'?'':'display:none;'; ?>">
                <input type="date" class="form-control form-control-sm" name="baslangic" value="<?php echo $baslangic; ?>" style="width: 150px;">
                <input type="date" class="form-control form-control-sm" name="bitis" value="<?php echo $bitis; ?>" style="width: 150px;">
            </div>
            <div class="ms-auto d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Filtrele</button>
                <?php if ($mode !== 'bugun' || $personel_filtre > 0): ?>
                    <a href="fazla_mesai.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle"></i> Temizle</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select2 başlat
    $('#personelFiltre').select2({
        theme: 'bootstrap-5',
        placeholder: 'Personel seçin...',
        allowClear: true,
        width: '100%'
    });
    
    // Mod değişiminde ilgili alanları göster/gizle
    const modeSelect = document.getElementById('modeSelect');
    const donemInputs = document.getElementById('donemInputs');
    const tarihInputs = document.getElementById('tarihInputs');
    
    function toggleInputs() {
        if (!modeSelect) return;
        donemInputs.style.display = 'none';
        tarihInputs.style.display = 'none';
        if (modeSelect.value === 'donem') {
            donemInputs.style.display = '';
        } else if (modeSelect.value === 'tarih') {
            tarihInputs.style.display = '';
        }
    }
    
    if (modeSelect) {
        modeSelect.addEventListener('change', toggleInputs);
        toggleInputs();
    }
});
</script>

<div class="card">
    <div class="card-body">
        <ul class="nav nav-tabs" id="fmTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="kayitlar-tab" data-bs-toggle="tab" data-bs-target="#kayitlar" type="button" role="tab">
                    <i class="bi bi-list-ul"></i> Fazla Mesai Kayıtları
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="ozet-tab" data-bs-toggle="tab" data-bs-target="#ozet" type="button" role="tab">
                    <i class="bi bi-calculator"></i> Kümülatif Toplamlar
                </button>
            </li>
        </ul>
        
        <div class="tab-content pt-3" id="fmTabContent">
            <!-- Tab 1: Fazla Mesai Kayıtları -->
            <div class="tab-pane fade show active" id="kayitlar" role="tabpanel">
                <?php if (empty($fazlaMesailer)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Henüz fazla mesai kaydı bulunmamaktadır.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Personel</th>
                                    <th>Tarih</th>
                                    <th>Süre (Saat)</th>
                                    <th class="money">Saat Ücreti</th>
                                    <th class="money">Tutar</th>
                                    <th>Açıklama</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $toplamSaat = 0;
                                $toplamTutar = 0;
                                foreach ($fazlaMesailer as $fm): 
                                    $toplamSaat += (float)$fm['saat'];
                                    $toplamTutar += (float)$fm['tutar'];
                                ?>
                                    <tr>
                                        <td><?php echo escape($fm['ad_soyad']); ?></td>
                                        <td><?php echo formatDateWithDay($fm['tarih']); ?></td>
                                        <td><?php echo escape($fm['saat']); ?></td>
                                        <td class="money"><?php echo formatMoney($fm['saat_ucreti']); ?></td>
                                        <td class="money"><?php echo formatMoney($fm['tutar']); ?></td>
                                        <td><?php echo escape($fm['aciklama']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" onclick="duzenleFazlaMesai(<?php echo $fm['id']; ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="silFazlaMesai(<?php echo $fm['id']; ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-primary">
                                    <th colspan="2" class="text-end">TOPLAM:</th>
                                    <th><?php echo number_format($toplamSaat, 2, ',', '.'); ?></th>
                                    <th></th>
                                    <th class="money"><?php echo formatMoney($toplamTutar); ?></th>
                                    <th colspan="2"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab 2: Kümülatif Toplamlar -->
            <div class="tab-pane fade" id="ozet" role="tabpanel">
                <?php if (empty($kumulatifToplamlar)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Seçilen filtrede kümülatif veri bulunamadı.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Personel</th>
                                    <th>Toplam Saat</th>
                                    <th class="money">Toplam FM</th>
                                    <th class="money">Toplam Ödeme</th>
                                    <th class="money">Bakiye</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $kumTopSaat = 0;
                                $kumTopFM = 0;
                                $kumTopOdeme = 0;
                                $kumTopBakiye = 0;
                                foreach($kumulatifToplamlar as $toplam): 
                                    $kumTopSaat += (float)$toplam['toplam_saat'];
                                    $kumTopFM += (float)$toplam['toplam_tutar'];
                                    $kumTopOdeme += (float)$toplam['toplam_odeme'];
                                    $kumTopBakiye += (float)$toplam['bakiye'];
                                ?>
                                <tr>
                                    <td><?php echo escape($toplam['ad_soyad']); ?></td>
                                    <td><?php echo number_format($toplam['toplam_saat'], 2); ?></td>
                                    <td class="money"><?php echo formatMoney($toplam['toplam_tutar']); ?></td>
                                    <td class="money text-success"><?php echo formatMoney($toplam['toplam_odeme']); ?></td>
                                    <td class="money <?php echo $toplam['bakiye'] > 0 ? 'text-warning' : ''; ?>">
                                        <?php echo formatMoney($toplam['bakiye']); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-primary">
                                    <th>TOPLAM</th>
                                    <th><?php echo number_format($kumTopSaat, 2, ',', '.'); ?></th>
                                    <th class="money"><?php echo formatMoney($kumTopFM); ?></th>
                                    <th class="money"><?php echo formatMoney($kumTopOdeme); ?></th>
                                    <th class="money"><?php echo formatMoney($kumTopBakiye); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Fazla Mesai Ekle Modal -->
<div class="modal fade" id="fazlaMesaiEkleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Fazla Mesai Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="fazla_mesai_islem.php" method="POST">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Personel</label>
                            <select class="form-select" name="personel_id" required>
                                <option value="">Seçiniz...</option>
                                <?php
                                try {
                                    $personeller = $pdo->query("SELECT id, ad_soyad, mesai_saat_ucreti FROM personel_listesi WHERE aktif = 1 ORDER BY ad_soyad")->fetchAll();
                                    foreach($personeller as $personel):
                                ?>
                                    <option value="<?php echo $personel['id']; ?>" data-ucret="<?php echo $personel['mesai_saat_ucreti']; ?>">
                                        <?php echo escape($personel['ad_soyad']); ?>
                                    </option>
                                <?php endforeach; } catch(PDOException $e) {} ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tarih</label>
                            <input type="date" class="form-control" name="tarih" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Süre (Saat)</label>
                            <input type="number" step="0.01" min="0" max="999.99" class="form-control" name="saat" value="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Saat Ücreti</label>
                            <div class="input-group">
                                <input type="text" class="form-control money-field" pattern="[0-9.,]+" name="saat_ucreti" id="saat_ucreti" value="0" data-no-auto-format="true" inputmode="decimal" required>
                                <input type="hidden" name="saat_ucreti_raw" id="saat_ucreti_raw" value="0">
                                <span class="input-group-text">₺</span>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Açıklama</label>
                            <textarea class="form-control" name="aciklama" rows="2"></textarea>
                        </div>
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
                            <?php
                            try {
                                $personeller = $pdo->query("SELECT id, ad_soyad FROM personel_listesi WHERE aktif = 1 ORDER BY ad_soyad")->fetchAll();
                                foreach($personeller as $personel):
                            ?>
                                <option value="<?php echo $personel['id']; ?>">
                                    <?php echo escape($personel['ad_soyad']); ?>
                                </option>
                            <?php endforeach; } catch(PDOException $e) {} ?>
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
                                    <th><input type="checkbox" id="selectAll"></th>
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
                                        <td><input type="checkbox" class="personel-check" name="personel[<?php echo $pid; ?>][secili]" value="1"></td>
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
document.getElementById('selectAll')?.addEventListener('change', function() {
    document.querySelectorAll('.personel-check').forEach(cb => cb.checked = this.checked);
});
</script>

<?php include '../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('#fazlaMesaiEkleModal form');
    const saatUcreti = document.getElementById('saat_ucreti');
    const saatUcretiRaw = document.getElementById('saat_ucreti_raw');
    // TR ve EN formatlarını güvenle parse et
    function parseMoneyLocalJS(val) {
        if (!val) return '0';
        val = val.toString().trim().replace('₺','');
        if (val.indexOf(',') !== -1 && val.indexOf('.') !== -1) {
            // TR: binlik nokta, ondalık virgül
            val = val.replace(/\./g, '').replace(',', '.');
        } else if (val.indexOf(',') !== -1) {
            // Sadece virgül varsa: virgülü ondalık kabul et
            val = val.replace(/\./g, '');
            val = val.replace(',', '.');
        } else {
            // Nokta varsa: son noktayı ondalık kabul et, diğerlerini kaldır
            const parts = val.split('.');
            if (parts.length > 1) {
                const last = parts.pop();
                if (last.length <= 2) {
                    val = parts.join('') + '.' + last;
                } else {
                    val = parts.join('') + last;
                }
            }
        }
        val = val.replace(/[^0-9.]/g, '');
        return val || '0';
    }
    if (form && saatUcreti && saatUcretiRaw) {
        form.addEventListener('submit', function() {
            saatUcretiRaw.value = parseMoneyLocalJS(saatUcreti.value);
        });
    }
});
</script>

