<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Fazla Mesai Takibi';

// Filtreler
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'bugun';
$baslangic = isset($_GET['baslangic']) ? $_GET['baslangic'] : date('Y-m-d');
$bitis = isset($_GET['bitis']) ? $_GET['bitis'] : date('Y-m-d');

// Personel bazlı kümülatif toplamlar (filtreden bağımsız, tüm kayıtlar)
try {
    // Tüm FM kayıtlarını çek (filtre olmadan)
    $tumFMStmt = $pdo->query("SELECT fm.*, p.ad_soyad 
                              FROM fazla_mesai fm 
                              LEFT JOIN personel_listesi p ON fm.personel_id = p.id");
    $tumFM = $tumFMStmt->fetchAll();
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
    
    // FM toplamlarını hesapla (tüm kayıtlardan)
    foreach($tumFM as $fm) {
        $personelId = $fm['personel_id'];
        if (isset($kumulatifToplamlar[$personelId])) {
            $kumulatifToplamlar[$personelId]['toplam_saat'] += $fm['saat'];
            $kumulatifToplamlar[$personelId]['toplam_tutar'] += $fm['tutar'];
        }
    }
    
    // Ödemeleri çek ve toplamlardan düş
    $odemeStmt = $pdo->query("SELECT personel_id, SUM(tutar) as toplam_odeme FROM fazla_mesai_odeme GROUP BY personel_id");
    $odemeler = $odemeStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    foreach($kumulatifToplamlar as $pid => &$toplam) {
        $toplam['toplam_odeme'] = isset($odemeler[$pid]) ? (float)$odemeler[$pid] : 0;
        $toplam['bakiye'] = $toplam['toplam_tutar'] - $toplam['toplam_odeme'];
    }
    
    // Sadece FM'si veya ödemesi olan personelleri göster
    $kumulatifToplamlar = array_filter($kumulatifToplamlar, function($t) {
        return $t['toplam_tutar'] > 0 || $t['toplam_odeme'] > 0;
    });
    
    // Filtrelenmiş FM listesi (gösterim için)
    $sqlFiltre = "SELECT fm.*, p.ad_soyad 
                  FROM fazla_mesai fm 
                  LEFT JOIN personel_listesi p ON fm.personel_id = p.id 
                  WHERE 1=1";
    $paramsFiltre = [];
    
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
    } elseif ($mode === 'tarih') {
        $sqlFiltre .= " AND fm.tarih BETWEEN ? AND ?";
        $paramsFiltre[] = $baslangic;
        $paramsFiltre[] = $bitis;
    }
    
    $sqlFiltre .= " ORDER BY fm.tarih DESC";
    $stmtFiltre = $pdo->prepare($sqlFiltre);
    $stmtFiltre->execute($paramsFiltre);
    $fazlaMesailer = $stmtFiltre->fetchAll();
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

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-clock-history"></i> Fazla Mesai Takibi</h1>
    <div class="d-flex gap-2">
        <a href="fazla_mesai_raporu.php" class="btn btn-outline-secondary">
            <i class="bi bi-list-check"></i> FM Raporu
        </a>
        <a href="fazla_mesai_ekstre.php" class="btn btn-outline-secondary">
            <i class="bi bi-journal-text"></i> FM Ekstresi
        </a>
        <a href="fazla_mesai_odeme_listesi.php" class="btn btn-outline-secondary">
            <i class="bi bi-receipt"></i> Ödeme Listesi
        </a>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#odemeYapModal">
            <i class="bi bi-cash-coin"></i> Ödeme Yap
        </button>
        <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#topluOdemeModal">
            <i class="bi bi-cash-stack"></i> Toplu Ödeme
        </button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#fazlaMesaiEkleModal">
            <i class="bi bi-plus-circle"></i> Fazla Mesai Ekle
        </button>
    </div>
    
</div>

<?php if (!empty($kumulatifToplamlar)): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-calculator"></i> Personel Bazlı Kümülatif Toplamlar</h5>
    </div>
    <div class="card-body">
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
                    <?php foreach($kumulatifToplamlar as $toplam): ?>
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
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filtre -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Filtre Tipi</label>
                <select class="form-select" name="mode" id="modeSelect">
                    <option value="bugun" <?php echo $mode==='bugun'?'selected':''; ?>>Bugün</option>
                    <option value="bu_hafta" <?php echo $mode==='bu_hafta'?'selected':''; ?>>Bu Hafta</option>
                    <option value="bu_ay" <?php echo $mode==='bu_ay'?'selected':''; ?>>Bu Ay</option>
                    <option value="tarih" <?php echo $mode==='tarih'?'selected':''; ?>>Tarih Aralığı</option>
                </select>
            </div>
            <div id="tarihInputs" class="col-md-6 d-flex gap-2" style="<?php echo $mode==='tarih'?'':'display:none;'; ?>">
                <div class="flex-fill">
                    <label class="form-label">Başlangıç</label>
                    <input type="date" class="form-control" name="baslangic" value="<?php echo $baslangic; ?>">
                </div>
                <div class="flex-fill">
                    <label class="form-label">Bitiş</label>
                    <input type="date" class="form-control" name="bitis" value="<?php echo $bitis; ?>">
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary">Filtrele</button>
                <?php if ($mode !== 'bugun'): ?>
                    <a href="fazla_mesai.php" class="btn btn-outline-secondary">Temizle</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('modeSelect')?.addEventListener('change', function() {
    const tarihInputs = document.getElementById('tarihInputs');
    if (this.value === 'tarih') {
        tarihInputs.style.display = '';
    } else {
        tarihInputs.style.display = 'none';
    }
});
</script>

<?php if (empty($fazlaMesailer)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> Henüz fazla mesai kaydı bulunmamaktadır.
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
                            <th>Süre (Saat)</th>
                            <th class="money">Saat Ücreti</th>
                            <th class="money">Tutar</th>
                            <th>Açıklama</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fazlaMesailer as $fm): ?>
                            <tr>
                                <td><?php echo escape($fm['ad_soyad']); ?></td>
                                <td><?php echo formatDate($fm['tarih']); ?></td>
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
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

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
                            <input type="number" step="0.5" class="form-control" name="saat" value="0" required>
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

