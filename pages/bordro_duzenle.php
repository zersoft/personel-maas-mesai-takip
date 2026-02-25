<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('user');

$pageTitle = 'Bordro Düzenle';

$id = isset($_GET['id']) ? (int)$_GET['id'] : null; // SQL injection koruması için integer cast

if (!$id || $id <= 0) {
    header('Location: bordro.php?error=Geçersiz bordro ID');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT b.*, p.ad_soyad,
                                   (b.brut_maas - b.sgk_banka) as nakit,
                                   (b.brut_maas + b.ek_odenek - COALESCE(b.izin_kesintisi, 0) - COALESCE(b.sgk_kesintisi, 0) - COALESCE(b.diger_kesintiler, 0)) as toplam_odenecek
                          FROM bordro b 
                          LEFT JOIN personel_listesi p ON b.personel_id = p.id 
                          WHERE b.id = ?");
    $stmt->execute([$id]);
    $bordro = $stmt->fetch();
    
    if (!$bordro) {
        header('Location: bordro.php?error=Bordro bulunamadı');
        exit;
    }
    
    $personeller = $pdo->query("SELECT id, ad_soyad, maas, maas_sgk FROM personel_listesi WHERE aktif = 1 ORDER BY ad_soyad")->fetchAll();
} catch(PDOException $e) {
    header('Location: bordro.php?error=' . urlencode($e->getMessage()));
    exit;
}

include '../includes/header.php';

if (isset($_GET['success'])) {
    echo showMessage('Bordro başarıyla güncellendi!', 'success');
}
if (isset($_GET['error'])) {
    echo showMessage('Hata: ' . escape($_GET['error']), 'danger');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-pencil-square"></i> Bordro Düzenle</h1>
    <a href="bordro.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="bordro_islem.php" method="POST" id="bordroForm">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo $bordro['id']; ?>">
            <!-- Ham sayısal değerler için gizli alanlar (sunucu tarafı bu alanları önceliklendirir) -->
            <input type="hidden" name="brut_maas_raw" id="brut_maas_raw" value="">
            <input type="hidden" name="sgk_banka_raw" id="sgk_banka_raw" value="">
            <input type="hidden" name="ek_odenek_raw" id="ek_odenek_raw" value="">
            <input type="hidden" name="izin_kesintisi_raw" id="izin_kesintisi_raw" value="">
            <input type="hidden" name="sgk_kesintisi_raw" id="sgk_kesintisi_raw" value="">
            <input type="hidden" name="diger_kesintiler_raw" id="diger_kesintiler_raw" value="">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Personel</label>
                    <select class="form-select" name="personel_id" id="personelSelect" required>
                        <option value="">Seçiniz...</option>
                        <?php foreach($personeller as $personel): ?>
                            <option value="<?php echo $personel['id']; ?>" 
                                    data-maas="<?php echo $personel['maas']; ?>"
                                    data-maas-sgk="<?php echo $personel['maas_sgk']; ?>"
                                    <?php echo $personel['id'] == $bordro['personel_id'] ? 'selected' : ''; ?>>
                                <?php echo escape($personel['ad_soyad']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Ay</label>
                    <select class="form-select" name="ay" required>
                        <?php for($i=1; $i<=12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $i == $bordro['ay'] ? 'selected' : ''; ?>>
                                <?php echo getTurkishMonthName($i); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Yıl</label>
                    <input type="number" class="form-control" name="yil" value="<?php echo $bordro['yil']; ?>" required>
                </div>
                <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                    <label class="form-label">Brüt Maaş (₺)</label>
                    <div class="input-group">
                        <input type="text" class="form-control money-field" pattern="[0-9.,]+" name="brut_maas" id="brutMaas" value="<?php echo number_format($bordro['brut_maas'], 2, ',', '.'); ?>" required>
                        <span class="input-group-text">₺</span>
                    </div>
                </div>
                <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                    <label class="form-label">SGK/Banka (₺)</label>
                    <div class="input-group">
                        <input type="text" class="form-control money-field" pattern="[0-9.,]+" name="sgk_banka" id="sgkBanka" value="<?php echo number_format($bordro['sgk_banka'], 2, ',', '.'); ?>" required>
                        <span class="input-group-text">₺</span>
                    </div>
                </div>
                <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                    <label class="form-label">Nakit (₺)</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="nakitGoster" value="<?php echo formatMoney($bordro['nakit'] ?? ($bordro['brut_maas'] - $bordro['sgk_banka'])); ?>" readonly style="background-color: #e9ecef;">
                        <span class="input-group-text">₺</span>
                    </div>
                    <small class="text-muted">Brüt Maaş - SGK/Banka (Otomatik hesaplanır)</small>
                </div>
                <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                    <label class="form-label">Ek Ödenek (Banka) (₺)</label>
                    <div class="input-group">
                        <input type="text" class="form-control money-field" pattern="[0-9.,]+" name="ek_odenek_banka" value="<?php echo number_format($bordro['ek_odenek_banka'] ?? 0, 2, ',', '.'); ?>">
                        <span class="input-group-text">₺</span>
                    </div>
                </div>
                <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                    <label class="form-label">Ek Ödenek (Nakit) (₺)</label>
                    <div class="input-group">
                        <input type="text" class="form-control money-field" pattern="[0-9.,]+" name="ek_odenek_nakit" value="<?php echo number_format(($bordro['ek_odenek_nakit'] ?? ($bordro['ek_odenek'] ?? 0)), 2, ',', '.'); ?>">
                        <span class="input-group-text">₺</span>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Bu Dönem Avans (Banka/Nakit)</label>
                    <div class="form-control" id="avansInfo" style="background:#f8f9fa;">-</div>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">İzin Günü</label>
                    <input type="number" step="0.5" class="form-control" name="izin_gunu" value="<?php echo $bordro['izin_gunu'] ?? 0; ?>">
                </div>
                <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                    <label class="form-label">İzin Kesintisi (₺)</label>
                    <div class="input-group">
                        <input type="text" class="form-control money-field" pattern="[0-9.,]+" name="izin_kesintisi" value="<?php echo number_format($bordro['izin_kesintisi'] ?? 0, 2, ',', '.'); ?>">
                        <span class="input-group-text">₺</span>
                    </div>
                </div>
                <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                    <label class="form-label">SGK Kesintisi (₺)</label>
                    <div class="input-group">
                        <input type="text" class="form-control money-field" pattern="[0-9.,]+" name="sgk_kesintisi" value="<?php echo number_format($bordro['sgk_kesintisi'] ?? 0, 2, ',', '.'); ?>">
                        <span class="input-group-text">₺</span>
                    </div>
                </div>
                <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                    <label class="form-label">Diğer Kesintiler (₺)</label>
                    <div class="input-group">
                        <input type="text" class="form-control money-field" pattern="[0-9.,]+" name="diger_kesintiler" value="<?php echo number_format($bordro['diger_kesintiler'] ?? 0, 2, ',', '.'); ?>">
                        <span class="input-group-text">₺</span>
                    </div>
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">Kesinti Açıklaması</label>
                    <textarea class="form-control" name="kesinti_aciklama" rows="2"><?php echo escape($bordro['kesinti_aciklama'] ?? ''); ?></textarea>
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">Açıklama</label>
                    <textarea class="form-control" name="aciklama" rows="3"><?php echo escape($bordro['aciklama'] ?? ''); ?></textarea>
                </div>
                <div class="col-md-12 mb-3">
                    <div class="alert alert-info">
                        <strong>Toplam Ödenecek:</strong> 
                        <span class="money"><?php echo formatMoney($bordro['toplam_odenecek'] ?? ($bordro['toplam_odeme'] ?? 0)); ?></span>
                        <br>
                        <small>Hesaplama: Brüt Maaş + Ek Ödenek - İzin Kesintisi - SGK Kesintisi - Diğer Kesintiler</small>
                    </div>
                </div>
            </div>
            
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Güncelle
                </button>
                <a href="bordro.php" class="btn btn-secondary">İptal</a>
            </div>
        </form>
    </div>
</div>

<script>
// main.js yüklenmeden önce çalışacak inline script
// PHP'den gelen formatlanmış değerleri koru, sadece formatlanmamış sayıları formatla
(function() {
    document.querySelectorAll('.money-field').forEach(function(input) {
        const value = input.value.trim();
        // Eğer değer zaten formatlanmışsa (22.104,00 gibi) olduğu gibi bırak
        if (value && value.match(/^\d{1,3}(\.\d{3})*,\d{2}$/)) {
            // Format doğru, hiçbir şey yapma
            return;
        }
        // Sadece sayı varsa (formatlanmamış) formatla
        if (value && value.match(/^\d+$/)) {
            const numValue = parseFloat(value);
            if (!isNaN(numValue) && numValue > 0) {
                // Binlik ayracı ekle ve ondalık kısmı ekle
                const parts = numValue.toFixed(2).split('.');
                const integerPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                input.value = integerPart + ',' + parts[1];
            }
        }
    });
})();

document.addEventListener('DOMContentLoaded', function() {
    const bordroPersonelSelect = document.getElementById('personelSelect');
    const brutMaasInput = document.getElementById('brutMaas');
    const sgkBankaInput = document.getElementById('sgkBanka');
    
    // Para parse fonksiyonu (formatlanmış değeri sayıya çevirir)
    // Türk Lira formatı: 57.500,00 -> 57500.00
    function parseMoneyValue(value) {
        if (!value || value === '0' || value === '') return 0;
        let val = value.toString().trim();
        
        // Türk Lira sembolü ve boşlukları kaldır
        val = val.replace(/₺/g, '').trim();
        
        // Eğer zaten sadece sayı formatındaysa (nokta ondalık ayracı olarak) direkt parse et
        // Örnek: 57500.00 veya 57500
        if (val.match(/^\d+\.?\d*$/)) {
            return parseFloat(val) || 0;
        }
        
        // Türk Lira formatı: binlik ayracı nokta (.), ondalık ayracı virgül (,)
        // Örnek: 57.500,00 -> 57500.00
        
        // Virgül varsa -> Türk Lira formatı (ondalık ayracı virgül)
        if (val.includes(',')) {
            // Tüm noktaları kaldır (binlik ayraçları)
            val = val.replace(/\./g, '');
            // Virgülü noktaya çevir (ondalık ayracı)
            val = val.replace(',', '.');
        } else if (val.includes('.')) {
            // Sadece nokta var -> kontrol et
            const parts = val.split('.');
            if (parts.length === 2) {
                // İki parça var, son parça 2-3 haneli ise ondalık kısım olabilir
                const lastPart = parts[parts.length - 1];
                if (lastPart.length <= 3 && lastPart.length >= 1) {
                    // Son kısım muhtemelen ondalık kısım
                    val = parts[0] + '.' + lastPart;
                } else {
                    // Binlik ayraç olarak kullanılmış, kaldır
                    val = parts.join('');
                }
            } else {
                // Birden fazla nokta var -> binlik ayraçları kaldır
                val = parts.join('');
            }
        }
        
        // Sadece rakam ve nokta bırak
        val = val.replace(/[^0-9.]/g, '');
        
        // Birden fazla nokta varsa sadece sonuncusunu bırak
        const parts = val.split('.');
        if (parts.length > 2) {
            val = parts.slice(0, -1).join('') + '.' + parts[parts.length - 1];
        }
        
        const result = parseFloat(val) || 0;
        console.log('parseMoneyValue:', value, '->', result); // Debug
        return result;
    }
    
    // Para formatlama fonksiyonu (sayıyı Türk Lira formatına çevirir)
    function formatMoneyInput(value) {
        if (!value || value === '0' || value === '') return '0';
        // Önce parse et (eğer zaten formatlanmışsa)
        let numValue = parseMoneyValue(value);
        if (numValue === 0) return '0';
        
        // Sayıyı string'e çevir ve ondalık kısmını ayır
        let val = numValue.toFixed(2);
        let parts = val.split('.');
        const integerPart = parts[0].replace(/\D/g, '');
        const decimalPart = parts[1] || '00';
        
        // Binlik ayracı ekle
        const formattedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return formattedInteger + ',' + decimalPart;
    }
    
    // Nakit hesaplama fonksiyonu
    function hesaplaNakit() {
        if (brutMaasInput && sgkBankaInput) {
            const brutMaas = parseMoneyValue(brutMaasInput.value);
            const sgkBanka = parseMoneyValue(sgkBankaInput.value);
            const nakit = brutMaas - sgkBanka;
            const nakitGoster = document.getElementById('nakitGoster');
            if (nakitGoster) {
                nakitGoster.value = formatMoneyInput(nakit.toString()) + ' ₺';
            }
        }
    }
    
    if (bordroPersonelSelect && brutMaasInput && sgkBankaInput) {
        // İlk yüklemede nakit'i hesapla
        hesaplaNakit();
        // Avans bilgisini getir
        (function guncelleAvansInfo(){
            const pid = document.getElementById('personelSelect').value;
            const ay = document.querySelector('select[name="ay"]').value;
            const yil = document.querySelector('input[name="yil"]').value;
            const box = document.getElementById('avansInfo');
            if (!pid || !box) return;
            fetch(`avans_ozet_api.php?personel_id=${pid}&ay=${ay}&yil=${yil}`)
                .then(r=>r.json())
                .then(d=>{
                    const b = (d&&d.banka)?d.banka:0;
                    const n = (d&&d.nakit)?d.nakit:0;
                    box.textContent = `Banka: ${b.toLocaleString('tr-TR',{minimumFractionDigits:2, maximumFractionDigits:2})} ₺, Nakit: ${n.toLocaleString('tr-TR',{minimumFractionDigits:2, maximumFractionDigits:2})} ₺`;
                })
                .catch(()=>{ box.textContent='-'; });
        })();
        
        bordroPersonelSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const maas = selectedOption.getAttribute('data-maas');
                const maasSgk = selectedOption.getAttribute('data-maas-sgk');
                
                if (maas && maas !== '0') {
                    brutMaasInput.value = formatMoneyInput(maas);
                }
                if (maasSgk && maasSgk !== '0') {
                    sgkBankaInput.value = formatMoneyInput(maasSgk);
                }
                hesaplaNakit();
            }
        });
        
        // Brüt maaş veya SGK/Banka değiştiğinde nakit'i güncelle
        brutMaasInput.addEventListener('blur', hesaplaNakit);
        brutMaasInput.addEventListener('input', hesaplaNakit);
        sgkBankaInput.addEventListener('blur', hesaplaNakit);
        sgkBankaInput.addEventListener('input', hesaplaNakit);
        // Ay/Yıl değişince avansı güncelle
        const aySel = document.querySelector('select[name="ay"]');
        const yilIn = document.querySelector('input[name="yil"]');
        const perSel = document.getElementById('personelSelect');
        const updateAvans = function(){
            const pid = perSel.value;
            const ay = aySel.value;
            const yil = yilIn.value;
            const box = document.getElementById('avansInfo');
            if (!pid || !box) return;
            fetch(`avans_ozet_api.php?personel_id=${pid}&ay=${ay}&yil=${yil}`)
                .then(r=>r.json())
                .then(d=>{
                    const b = (d&&d.banka)?d.banka:0;
                    const n = (d&&d.nakit)?d.nakit:0;
                    box.textContent = `Banka: ${b.toLocaleString('tr-TR',{minimumFractionDigits:2, maximumFractionDigits:2})} ₺, Nakit: ${n.toLocaleString('tr-TR',{minimumFractionDigits:2, maximumFractionDigits:2})} ₺`;
                })
                .catch(()=>{ box.textContent='-'; });
        };
        if (aySel) aySel.addEventListener('change', updateAvans);
        if (yilIn) yilIn.addEventListener('input', updateAvans);
        if (perSel) perSel.addEventListener('change', updateAvans);
    }
    
    // main.js'nin otomatik formatlamasını engelle
    // Bu sayfada PHP'den gelen formatlanmış değerleri koruyoruz
    // main.js yüklenmeden önce çalışacak event listener'ları ekle
    const moneyFields = document.querySelectorAll('.money-field');
    moneyFields.forEach(function(input) {
        // Bu sayfada formatlanmış değerleri korumak için özel attribute ekle
        input.setAttribute('data-no-auto-format', 'true');
        
        // Focus olduğunda formatı temizle (sadece sayı göster)
        input.addEventListener('focus', function(e) {
            e.stopImmediatePropagation(); // main.js'in event'ini durdur
            const rawValue = parseMoneyValue(this.value);
            this.value = rawValue === 0 ? '' : rawValue.toFixed(2);
            this.select();
        }, true); // capture phase'de çalıştır
        
        // Blur olduğunda formatla
        input.addEventListener('blur', function(e) {
            e.stopImmediatePropagation(); // main.js'in event'ini durdur
            const rawValue = parseMoneyValue(this.value);
            if (rawValue && rawValue !== 0) {
                this.value = formatMoneyInput(rawValue.toString());
            } else {
                this.value = '0';
            }
            // Nakit'i güncelle
            if (this === brutMaasInput || this === sgkBankaInput) {
                hesaplaNakit();
            }
        }, true); // capture phase'de çalıştır
    });
    
    // Form submit edilmeden önce tüm para alanlarını temizle (binlik ayraçları kaldır)
    const bordroForm = document.getElementById('bordroForm');
    if (bordroForm) {
        bordroForm.addEventListener('submit', function(e) {
            // Form içindeki her para alanı için hem görünür input'ı koru hem de gizli ham alanı doldur
            const map = [
                { vis: 'brutMaas', hid: 'brut_maas_raw' },
                { vis: 'sgkBanka', hid: 'sgk_banka_raw' }
            ];
            // Ek alanları isimlerinden bulacağız
            const ek = [
                { sel: 'input[name="ek_odenek"]', hid: 'ek_odenek_raw' },
                { sel: 'input[name="izin_kesintisi"]', hid: 'izin_kesintisi_raw' },
                { sel: 'input[name="sgk_kesintisi"]', hid: 'sgk_kesintisi_raw' },
                { sel: 'input[name="diger_kesintiler"]', hid: 'diger_kesintiler_raw' }
            ];

            // ID üzerinden
            map.forEach(function(m) {
                const vis = document.getElementById(m.vis);
                const hid = document.getElementById(m.hid);
                if (vis && hid) {
                    hid.value = parseMoneyValue(vis.value).toFixed(2);
                }
            });
            // Seçiciler üzerinden
            ek.forEach(function(m) {
                const vis = bordroForm.querySelector(m.sel);
                const hid = document.getElementById(m.hid);
                if (vis && hid) {
                    hid.value = parseMoneyValue(vis.value).toFixed(2);
                }
            });
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>

