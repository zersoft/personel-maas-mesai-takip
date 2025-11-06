<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Toplu Bordro Oluşturma';

// SQL injection koruması için integer cast ve validasyon
$ay = isset($_GET['ay']) ? (int)$_GET['ay'] : date('n');
$yil = isset($_GET['yil']) ? (int)$_GET['yil'] : date('Y');

// Validasyon
if ($ay < 1 || $ay > 12) {
    $ay = date('n');
}
if ($yil < 2000 || $yil > 2100) {
    $yil = date('Y');
}

// Aktif personelleri getir
try {
    $personeller = $pdo->query("SELECT * FROM personel_listesi WHERE aktif = 1 ORDER BY ad_soyad")->fetchAll();
    
    // Seçilen ay için mevcut bordroları kontrol et
    $mevcutBordrolar = [];
    $stmt = $pdo->prepare("SELECT personel_id FROM bordro WHERE ay = ? AND yil = ?");
    $stmt->execute([$ay, $yil]);
    foreach($stmt->fetchAll() as $row) {
        $mevcutBordrolar[] = $row['personel_id'];
    }

    // Bu ayın avansları (kanal bazında) - tabloya bilgi amaçlı yansıt
    $avansHarita = [];
    $stmt = $pdo->prepare("SELECT personel_id, SUM(banka_tutari) AS banka, SUM(nakit_tutari) AS nakit
                           FROM avans_takip
                           WHERE ( (bordro_ay = ? AND bordro_yil = ?) OR (bordro_ay IS NULL AND bordro_yil IS NULL AND MONTH(tarih) = ? AND YEAR(tarih) = ?) )
                           GROUP BY personel_id");
    $stmt->execute([$ay, $yil, $ay, $yil]);
    foreach($stmt->fetchAll() as $row) {
        $avansHarita[(int)$row['personel_id']] = [
            'banka' => (float)($row['banka'] ?? 0),
            'nakit' => (float)($row['nakit'] ?? 0)
        ];
    }
} catch(PDOException $e) {
    $personeller = [];
    $mevcutBordrolar = [];
}

include '../includes/header.php';

// Mesaj gösterimi
if (isset($_GET['success'])) {
    $eklenenSayisi = isset($_GET['eklenen']) ? (int)$_GET['eklenen'] : 0;
    echo showMessage($eklenenSayisi . ' personel için bordro başarıyla oluşturuldu!', 'success');
}
if (isset($_GET['info'])) {
    $mesaj = isset($_GET['mesaj']) ? $_GET['mesaj'] : 'Bilgi';
    echo showMessage(urldecode($mesaj), 'info');
}
if (isset($_GET['error'])) {
    echo showMessage('Hata: ' . escape($_GET['error']), 'danger');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-file-earmark-spreadsheet"></i> Toplu Bordro Oluşturma</h1>
    <a href="bordro.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Bordro Listesi
    </a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Ay</label>
                <select class="form-select" name="ay">
                    <?php for($i=1; $i<=12; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo $i == $ay ? 'selected' : ''; ?>>
                            <?php echo getTurkishMonthName($i); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Yıl</label>
                <input type="number" class="form-control" name="yil" value="<?php echo $yil; ?>" min="2020" max="<?php echo date('Y')+1; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">Filtrele</button>
            </div>
        </form>
    </div>
</div>

<?php if (empty($personeller)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> Aktif personel bulunmamaktadır.
    </div>
<?php else: ?>
    <form action="toplu_bordro_islem.php" method="POST">
        <input type="hidden" name="ay" value="<?php echo $ay; ?>">
        <input type="hidden" name="yil" value="<?php echo $yil; ?>">
        
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?php echo getTurkishMonthName($ay) . ' ' . $yil; ?> - Personel Bordroları</h5>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-primary me-2" onclick="selectAll()">
                        <i class="bi bi-check-all"></i> Tümünü Seç
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary me-2" onclick="deselectAll()">
                        <i class="bi bi-x-square"></i> Tümünü Kaldır
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-save"></i> Seçili Olanları Kaydet
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th width="40">
                                    <input type="checkbox" id="selectAllCheckbox" checked onchange="toggleAll(this)">
                                </th>
                                <th>Personel</th>
                                <th class="money">Maaş</th>
                                <th class="money">Maaş SGK</th>
                                <th>Brüt Maaş</th>
                                <th>SGK/Banka</th>
                                <th>Ek Ödenek (Banka)</th>
                                <th>Ek Ödenek (Nakit)</th>
                                <th>İzin Günü</th>
                                <th>İzin Kes.</th>
                                <th>SGK Kes.</th>
                                <th>Diğer Kes.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($personeller as $index => $personel): ?>
                                <?php 
                                $personelId = $personel['id'];
                                $mevcutMu = in_array($personelId, $mevcutBordrolar);
                                ?>
                                <tr class="<?php echo $mevcutMu ? 'table-warning' : ''; ?>" data-personel-id="<?php echo $personelId; ?>">
                                    <td>
                                        <input type="checkbox" class="form-check-input personel-checkbox" 
                                               name="selected_personel[]" value="<?php echo $personelId; ?>" 
                                               checked>
                                    </td>
                                    <td>
                                        <?php echo escape($personel['ad_soyad']); ?>
                                        <?php if($mevcutMu): ?>
                                            <small class="text-muted d-block">(Mevcut)</small>
                                        <?php endif; ?>
                                        <?php
                                            $aid = (int)$personelId;
                                            $ab = $avansHarita[$aid]['banka'] ?? 0;
                                            $an = $avansHarita[$aid]['nakit'] ?? 0;
                                            if ($ab > 0 || $an > 0): ?>
                                                <small class="text-muted d-block">Avans B/N: <?php echo formatMoney($ab); ?> / <?php echo formatMoney($an); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="money"><?php echo formatMoney($personel['maas']); ?></td>
                                    <td class="money"><?php echo formatMoney($personel['maas_sgk']); ?></td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm money-field" data-no-auto-format="true" pattern="[0-9.,]+" 
                                               name="brut_maas[<?php echo $personelId; ?>]" 
                                               value="<?php echo number_format((float)$personel['maas'], 2, ',', '.'); ?>" 
                                               data-personel-id="<?php echo $personelId; ?>" 
                                               data-required="true">
                                        <input type="hidden" name="brut_maas_raw[<?php echo $personelId; ?>]" id="brut_maas_raw_<?php echo $personelId; ?>" value="">
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm money-field" data-no-auto-format="true" pattern="[0-9.,]+" 
                                               name="sgk_banka[<?php echo $personelId; ?>]" 
                                               value="<?php echo number_format((float)$personel['maas_sgk'], 2, ',', '.'); ?>" 
                                               data-personel-id="<?php echo $personelId; ?>" 
                                               data-required="true">
                                        <input type="hidden" name="sgk_banka_raw[<?php echo $personelId; ?>]" id="sgk_banka_raw_<?php echo $personelId; ?>" value="">
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm money-field" data-no-auto-format="true" pattern="[0-9.,]+" 
                                               name="ek_odenek_banka[<?php echo $personelId; ?>]" value="0,00" 
                                               data-personel-id="<?php echo $personelId; ?>" 
                                               data-required="true">
                                        <input type="hidden" name="ek_odenek_banka_raw[<?php echo $personelId; ?>]" id="ek_odenek_banka_raw_<?php echo $personelId; ?>" value="">
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm money-field" data-no-auto-format="true" pattern="[0-9.,]+" 
                                               name="ek_odenek_nakit[<?php echo $personelId; ?>]" value="0,00" 
                                               data-personel-id="<?php echo $personelId; ?>" 
                                               data-required="true">
                                        <input type="hidden" name="ek_odenek_nakit_raw[<?php echo $personelId; ?>]" id="ek_odenek_nakit_raw_<?php echo $personelId; ?>" value="">
                                    </td>
                                    <td>
                                        <input type="number" step="0.5" class="form-control form-control-sm" 
                                               name="izin_gunu[<?php echo $personelId; ?>]" value="0" 
                                               data-personel-id="<?php echo $personelId; ?>">
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm money-field" data-no-auto-format="true" pattern="[0-9.,]+" 
                                               name="izin_kesintisi[<?php echo $personelId; ?>]" value="0,00" 
                                               data-personel-id="<?php echo $personelId; ?>">
                                        <input type="hidden" name="izin_kesintisi_raw[<?php echo $personelId; ?>]" id="izin_kesintisi_raw_<?php echo $personelId; ?>" value="">
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm money-field" data-no-auto-format="true" pattern="[0-9.,]+" 
                                               name="sgk_kesintisi[<?php echo $personelId; ?>]" value="0,00" 
                                               data-personel-id="<?php echo $personelId; ?>">
                                        <input type="hidden" name="sgk_kesintisi_raw[<?php echo $personelId; ?>]" id="sgk_kesintisi_raw_<?php echo $personelId; ?>" value="">
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm money-field" data-no-auto-format="true" pattern="[0-9.,]+" 
                                               name="diger_kesintiler[<?php echo $personelId; ?>]" value="0,00" 
                                               data-personel-id="<?php echo $personelId; ?>">
                                        <input type="hidden" name="diger_kesintiler_raw[<?php echo $personelId; ?>]" id="diger_kesintiler_raw_<?php echo $personelId; ?>" value="">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <script>
        // Para formatlama fonksiyonu
        function formatMoneyInput(value) {
            if (!value || value === '0' || value === '') return '0';
            
            // Önce parse et (binlik ayraçları kaldır)
            let val = value.toString().trim();
            val = val.replace(/\./g, ''); // Binlik ayraçları kaldır
            val = val.replace(',', '.'); // Virgülü noktaya çevir
            val = val.replace(/[^0-9.]/g, ''); // Sadece rakam ve nokta
            
            // Birden fazla nokta varsa sadece ilkini bırak
            let parts = val.split('.');
            if (parts.length > 2) {
                val = parts[0] + '.' + parts.slice(1).join('');
                parts = val.split('.');
            }
            
            // Şimdi formatla
            const integerPart = parts[0].replace(/\D/g, '');
            const decimalPart = parts[1] || '';
            const formattedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            return decimalPart ? formattedInteger + ',' + decimalPart : formattedInteger;
        }
        
        // Para parse fonksiyonu (binlik ayraçları kaldır)
        function parseMoneyValue(value) {
            if (!value || value === '0' || value === '') return '0';
            let val = value.toString().trim();
            // Binlik ayraçları kaldır (nokta)
            val = val.replace(/\./g, '');
            // Virgülü noktaya çevir
            val = val.replace(',', '.');
            // Sadece rakam ve nokta bırak
            val = val.replace(/[^0-9.]/g, '');
            // Birden fazla nokta varsa sadece ilkini bırak
            const parts = val.split('.');
            if (parts.length > 2) {
                val = parts[0] + '.' + parts.slice(1).join('');
            }
            return val;
        }
        
        function toggleAll(checkbox) {
            const checkboxes = document.querySelectorAll('.personel-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
                toggleRowInputs(cb);
            });
        }
        
        function selectAll() {
            const checkboxes = document.querySelectorAll('.personel-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = true;
                toggleRowInputs(cb);
            });
            document.getElementById('selectAllCheckbox').checked = true;
        }
        
        function deselectAll() {
            const checkboxes = document.querySelectorAll('.personel-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = false;
                toggleRowInputs(cb);
            });
            document.getElementById('selectAllCheckbox').checked = false;
        }
        
        function toggleRowInputs(checkbox) {
            const row = checkbox.closest('tr');
            const inputs = row.querySelectorAll('input, select');
            inputs.forEach(input => {
                if (input !== checkbox) {
                    input.disabled = !checkbox.checked;
                    if (!checkbox.checked) {
                        input.setAttribute('data-disabled', 'true');
                        input.removeAttribute('required'); // Seçili değilse required kaldır
                        row.classList.add('row-disabled'); // Satırı gri yap
                    } else {
                        input.removeAttribute('data-disabled');
                        row.classList.remove('row-disabled'); // Satırı normal yap
                        // Eğer data-required varsa required ekle
                        if (input.hasAttribute('data-required')) {
                            input.setAttribute('required', 'required');
                        }
                    }
                }
            });
        }
        
        // Bireysel checkbox değiştiğinde ana checkbox'ı güncelle ve input'ları aktif/pasif yap
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.personel-checkbox');
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            
            // İlk yüklemede tüm input'ları aktif yap (hepsi seçili)
            checkboxes.forEach(cb => {
                // İlk yüklemede required attribute'ları ekle
                const row = cb.closest('tr');
                const requiredInputs = row.querySelectorAll('[data-required="true"]');
                requiredInputs.forEach(input => {
                    input.setAttribute('required', 'required');
                });
                
            // İlk yüklemede para alanlarını formatla (sadece otomatik format dışı alanlar)
            const moneyInputs = row.querySelectorAll('.money-field[data-no-auto-format="true"]');
                moneyInputs.forEach(input => {
                    if (input.value && input.value !== '0' && input.value !== '') {
                        input.value = formatMoneyInput(input.value);
                    }
                });
                
                toggleRowInputs(cb);
            });
            
            // Para alanlarına odak/blur event'leri ekle
            document.querySelectorAll('.money-field[data-no-auto-format="true"]').forEach(input => {
                // Blur: ekrandaki değeri formatla
                input.addEventListener('blur', function(e) {
                    e.stopImmediatePropagation();
                    if (!this.disabled && this.value) {
                        this.value = formatMoneyInput(this.value);
                    }
                }, true);

                // Focus: değeri değiştirme, sadece dış dinleyicileri engelle
                input.addEventListener('focus', function(e) {
                    e.stopImmediatePropagation();
                }, true);
            });
            
            checkboxes.forEach(cb => {
                cb.addEventListener('change', function() {
                    toggleRowInputs(this);
                    const allChecked = Array.from(checkboxes).every(c => c.checked);
                    const noneChecked = Array.from(checkboxes).every(c => !c.checked);
                    selectAllCheckbox.checked = allChecked;
                    selectAllCheckbox.indeterminate = !allChecked && !noneChecked;
                });
            });
        });
        
        // Form submit edilmeden önce kontrol et
        const form = document.querySelector('form[action="toplu_bordro_islem.php"]');
        if (form) {
            form.addEventListener('submit', function(e) {
                const checkedBoxes = document.querySelectorAll('.personel-checkbox:checked');
                if (checkedBoxes.length === 0) {
                    e.preventDefault();
                    alert('Lütfen en az bir personel seçin!');
                    return false;
                }
                
                // Seçili olmayan satırların input'larını formdan çıkar
                const disabledInputs = this.querySelectorAll('[data-disabled="true"]');
                disabledInputs.forEach(input => {
                    input.name = ''; // Form gönderiminden çıkar
                });
                
                // Her satır için gizli ham alanları doldur
                checkedBoxes.forEach(cb => {
                    const personelId = cb.value;
                    const row = cb.closest('tr');
                    const getVal = sel => {
                        const el = row.querySelector(sel);
                        return el && el.value ? parseMoneyValue(el.value) : '0';
                    };
                    const setHidden = (id, val) => {
                        const hidden = document.getElementById(id + '_' + personelId);
                        if (hidden) hidden.value = val;
                    };
                    setHidden('brut_maas_raw', getVal('input[name="brut_maas['+personelId+']"]'));
                    setHidden('sgk_banka_raw', getVal('input[name="sgk_banka['+personelId+']"]'));
                    setHidden('ek_odenek_banka_raw', getVal('input[name="ek_odenek_banka['+personelId+']"]'));
                    setHidden('ek_odenek_nakit_raw', getVal('input[name="ek_odenek_nakit['+personelId+']"]'));
                    setHidden('izin_kesintisi_raw', getVal('input[name="izin_kesintisi['+personelId+']"]'));
                    setHidden('sgk_kesintisi_raw', getVal('input[name="sgk_kesintisi['+personelId+']"]'));
                    setHidden('diger_kesintiler_raw', getVal('input[name="diger_kesintiler['+personelId+']"]'));
                });
            });
        }
        </script>
    </form>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

