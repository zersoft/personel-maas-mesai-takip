<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Personel Listesi';

// Filtreleme parametreleri
$filtre_ad_soyad = $_GET['filtre_ad_soyad'] ?? '';
$filtre_pozisyon = $_GET['filtre_pozisyon'] ?? '';

// Durum filtresi: aktif (varsayılan) | silinmis | tum
$durum = $_GET['durum'] ?? 'aktif';
if (!in_array($durum, ['aktif','silinmis','tum'], true)) { $durum = 'aktif'; }

// Personel listesi (filtreleme ile)
try {
    $sql = "SELECT * FROM personel_listesi WHERE 1=1";
    $params = [];
    
    if (!empty($filtre_ad_soyad)) {
        $sql .= " AND ad_soyad LIKE ?";
        $params[] = '%' . $filtre_ad_soyad . '%';
    }
    
    if (!empty($filtre_pozisyon)) {
        $sql .= " AND pozisyon = ?";
        $params[] = $filtre_pozisyon;
    }
    
    // Durum koşulu uygula
    if ($durum === 'aktif') {
        $sql .= " AND aktif = 1";
    } elseif ($durum === 'silinmis') {
        $sql .= " AND aktif = 0";
    }

    $sql .= " ORDER BY ad_soyad ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $personeller = $stmt->fetchAll();
} catch(PDOException $e) {
    $personeller = [];
}

include '../includes/header.php';

// Mesaj gösterimi
if (isset($_GET['success'])) {
    echo showMessage('Personel başarıyla kaydedildi!', 'success');
}
if (isset($_GET['error'])) {
    echo showMessage('Hata: ' . escape($_GET['error']), 'danger');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-people"></i> Personel Listesi</h1>
    <div class="d-flex gap-2">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#personelEkleModal">
        <i class="bi bi-person-plus"></i> Yeni Personel Ekle
    </button>
    </div>
</div>

<!-- Filtreleme Formu -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Ad Soyad Ara</label>
                <input type="text" class="form-control" name="filtre_ad_soyad" 
                       value="<?php echo escape($filtre_ad_soyad); ?>" 
                       placeholder="Ad soyad ile ara...">
            </div>
            <div class="col-md-2">
                <label class="form-label">Pozisyon</label>
                <select class="form-select" name="filtre_pozisyon">
                    <option value="">Tüm Pozisyonlar</option>
                    <option value="_AİLE" <?php echo $filtre_pozisyon === '_AİLE' ? 'selected' : ''; ?>>_AİLE</option>
                    <option value="_BEKÇİ" <?php echo $filtre_pozisyon === '_BEKÇİ' ? 'selected' : ''; ?>>_BEKÇİ</option>
                    <option value="_D.ELEMAN" <?php echo $filtre_pozisyon === '_D.ELEMAN' ? 'selected' : ''; ?>>_D.ELEMAN</option>
                    <option value="_KANTAR" <?php echo $filtre_pozisyon === '_KANTAR' ? 'selected' : ''; ?>>_KANTAR</option>
                    <option value="_MUHASEBE" <?php echo $filtre_pozisyon === '_MUHASEBE' ? 'selected' : ''; ?>>_MUHASEBE</option>
                    <option value="_MÜHNEDİS" <?php echo $filtre_pozisyon === '_MÜHNEDİS' ? 'selected' : ''; ?>>_MÜHNEDİS</option>
                    <option value="_MUTFAK" <?php echo $filtre_pozisyon === '_MUTFAK' ? 'selected' : ''; ?>>_MUTFAK</option>
                    <option value="_OPERATÖR" <?php echo $filtre_pozisyon === '_OPERATÖR' ? 'selected' : ''; ?>>_OPERATÖR</option>
                    <option value="_ŞOFÖR" <?php echo $filtre_pozisyon === '_ŞOFÖR' ? 'selected' : ''; ?>>_ŞOFÖR</option>
                    <option value="_TAMİR" <?php echo $filtre_pozisyon === '_TAMİR' ? 'selected' : ''; ?>>_TAMİR</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Durum</label>
                <select class="form-select" name="durum">
                    <option value="aktif" <?php echo $durum==='aktif'?'selected':''; ?>>Aktif</option>
                    <option value="silinmis" <?php echo $durum==='silinmis'?'selected':''; ?>>Silinmiş</option>
                    <option value="tum" <?php echo $durum==='tum'?'selected':''; ?>>Tümü</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Ara
                </button>
            </div>
            <?php if (!empty($filtre_ad_soyad) || !empty($filtre_pozisyon)): ?>
            <div class="col-md-2">
                <a href="personel_listesi.php" class="btn btn-sm btn-secondary">
                    <i class="bi bi-x-circle"></i> Filtreleri Temizle
                </a>
                <small class="text-muted ms-2">
                    <?php echo count($personeller); ?> sonuç bulundu
                </small>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if (empty($personeller)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> 
        <?php if (!empty($filtre_ad_soyad) || !empty($filtre_pozisyon)): ?>
            Arama kriterlerinize uygun personel bulunamadı.
        <?php else: ?>
            Henüz personel kaydı bulunmamaktadır.
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <style>
                #personel-table.table-sm th, #personel-table.table-sm td { vertical-align: middle; }
                #personel-table .col-name { width: 26%; }
                #personel-table .col-actions { width: 110px; white-space: nowrap; }
                </style>
                <table id="personel-table" class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th class="col-name">Ad Soyad</th>
                            <th>TC No</th>
                            <th>Pozisyon</th>
                            <th class="money">Maaş</th>
                            <th class="money">Maaş SGK</th>
                            <th>İşe Giriş</th>
                            <th class="money">Mesai Saat Ücreti</th>
                            <th>Durum</th>
                            <th class="col-actions">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Toplamları hesapla
                        $toplam_maas = 0;
                        $toplam_maas_sgk = 0;
                        $toplam_mesai_ucreti = 0;
                        
                        foreach ($personeller as $personel): 
                            $toplam_maas += $personel['maas'];
                            $toplam_maas_sgk += $personel['maas_sgk'];
                            $toplam_mesai_ucreti += $personel['mesai_saat_ucreti'];
                        ?>
                            <tr>
                                <td><?php echo escape($personel['id']); ?></td>
                                <td><?php echo escape($personel['ad_soyad']); ?></td>
                                <td><?php echo escape($personel['tc_no']); ?></td>
                                <td><?php echo escape($personel['pozisyon']); ?></td>
                                <td class="money"><?php echo formatMoney($personel['maas']); ?></td>
                                <td class="money"><?php echo formatMoney($personel['maas_sgk']); ?></td>
                                <td><?php $d=$personel['ise_giris_tarihi']??null; echo ($d && $d!=='0000-00-00') ? formatDate($d) : '-'; ?></td>
                                <td class="money"><?php echo formatMoney($personel['mesai_saat_ucreti']); ?></td>
                                <td>
                                    <?php if($personel['aktif']): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Pasif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if($personel['aktif']): ?>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-warning" title="Düzenle" onclick="duzenlePersonel(<?php echo $personel['id']; ?>)"><i class="bi bi-pencil"></i></button>
                                            <button class="btn btn-danger" title="Sil" onclick="silPersonel(<?php echo $personel['id']; ?>)"><i class="bi bi-trash"></i></button>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button type="button" class="btn btn-info dropdown-toggle" data-bs-toggle="dropdown" title="Kayıtlar">
                                                    <i class="bi bi-three-dots"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item" href="bordro.php?personel_id=<?php echo $personel['id']; ?>"><i class="bi bi-cash-coin"></i> Bordro Kayıtları</a></li>
                                                    <li><a class="dropdown-item" href="puantaj_ekstre.php?personel_id=<?php echo $personel['id']; ?>&mode=donem"><i class="bi bi-clipboard-data"></i> Puantaj Kayıtları</a></li>
                                                    <li><a class="dropdown-item" href="fazla_mesai.php?personel_id=<?php echo $personel['id']; ?>&mode=bu_ay"><i class="bi bi-clock-history"></i> Fazla Mesai Kayıtları</a></li>
                                                    <li><a class="dropdown-item" href="avans_takip.php?personel_id=<?php echo $personel['id']; ?>"><i class="bi bi-wallet2"></i> Avans Kayıtları</a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item" href="fazla_mesai_ekstre.php?personel_id=<?php echo $personel['id']; ?>&mode=donem"><i class="bi bi-receipt"></i> FM Ekstresi</a></li>
                                                </ul>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-success" title="Geri Al" onclick="geriAlPersonel(<?php echo $personel['id']; ?>)"><i class="bi bi-arrow-counterclockwise"></i></button>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-primary">
                            <th colspan="4" class="text-end">TOPLAM:</th>
                            <th class="money"><?php echo formatMoney($toplam_maas); ?></th>
                            <th class="money"><?php echo formatMoney($toplam_maas_sgk); ?></th>
                            <th colspan="3"></th>
                            <th class="money"></th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Personel Ekle Modal -->
<div class="modal fade" id="personelEkleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Yeni Personel Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="personel_islem.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Ad Soyad</label>
                        <input type="text" class="form-control" name="ad_soyad" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">TC No</label>
                        <input type="text" class="form-control" name="tc_no" maxlength="11">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Pozisyon</label>
                        <input type="text" class="form-control" name="pozisyon">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Maaş (₺)</label>
                        <div class="input-group">
                            <input type="text" class="form-control money-field" pattern="[0-9.,]+" name="maas" value="0">
                            <span class="input-group-text">₺</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Maaş SGK (₺)</label>
                        <div class="input-group">
                            <input type="text" class="form-control money-field" pattern="[0-9.,]+" name="maas_sgk" value="0">
                            <span class="input-group-text">₺</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">İşe Giriş Tarihi</label>
                        <input type="date" class="form-control" name="ise_giris_tarihi">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Banka Adı</label>
                        <input type="text" class="form-control" name="banka_adi">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">IBAN</label>
                        <input type="text" class="form-control" name="iban" maxlength="26">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mesai Saat Ücreti (₺)</label>
                        <div class="input-group">
                            <input type="text" class="form-control money-field" pattern="[0-9.,]+" name="mesai_saat_ucreti" value="0">
                            <span class="input-group-text">₺</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="aktif" value="1" checked>
                            <label class="form-check-label">Aktif</label>
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

<?php include '../includes/footer.php'; ?>

