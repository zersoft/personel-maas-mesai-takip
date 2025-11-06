// Personel Takip Sistemi - Ana JavaScript Dosyası

// Personel işlemleri
function duzenlePersonel(id) {
    window.location.href = 'personel_duzenle.php?id=' + id;
}

function silPersonel(id) {
    if (confirm('Bu personeli silmek istediğinize emin misiniz?')) {
        window.location.href = 'personel_islem.php?action=delete&id=' + id;
    }
}

// Bordro işlemleri
function gosterBordro(id) {
    alert('Bordro detay görüntüleme özelliği yakında eklenecek. ID: ' + id);
}

function duzenleBordro(id) {
    window.location.href = 'bordro_duzenle.php?id=' + id;
}

function silBordro(id) {
    if (confirm('Bu bordroyu silmek istediğinize emin misiniz?')) {
        window.location.href = 'bordro_islem.php?action=delete&id=' + id;
    }
}

// Fazla mesai işlemleri
function duzenleFazlaMesai(id) {
    window.location.href = 'fazla_mesai_duzenle.php?id=' + id;
}

function silFazlaMesai(id) {
    if (confirm('Bu fazla mesai kaydını silmek istediğinize emin misiniz?')) {
        window.location.href = 'fazla_mesai_islem.php?action=delete&id=' + id;
    }
}

// Avans işlemleri
function odemeYap(id) {
    if (confirm('Bu avansın ödemesini yapmak istediğinize emin misiniz?')) {
        alert('Avans ödeme özelliği yakında eklenecek. ID: ' + id);
    }
}

function duzenleAvans(id) {
    window.location.href = 'avans_duzenle.php?id=' + id;
}

function silAvans(id) {
    if (confirm('Bu avans kaydını silmek istediğinize emin misiniz?')) {
        window.location.href = 'avans_islem.php?action=delete&id=' + id;
    }
}

// Tazminat işlemleri
function odemeYapTazminat(id) {
    if (confirm('Bu tazminatın ödemesini yapmak istediğinize emin misiniz?')) {
        alert('Tazminat ödeme özelliği yakında eklenecek. ID: ' + id);
    }
}

function duzenleTazminat(id) {
    alert('Tazminat düzenleme özelliği yakında eklenecek. ID: ' + id);
}

function silTazminat(id) {
    if (confirm('Bu tazminat kaydını silmek istediğinize emin misiniz?')) {
        alert('Tazminat silme özelliği yakında eklenecek. ID: ' + id);
    }
}

// Fazla mesai sayfasında personel seçildiğinde saat ücretini otomatik doldur
document.addEventListener('DOMContentLoaded', function() {
    console.log('Personel Takip Sistemi yüklendi');
    
    // Fazla mesai modalında personel seçimi
    const personelSelect = document.querySelector('select[name="personel_id"]');
    const saatUcretiInput = document.getElementById('saat_ucreti');
    
    if (personelSelect && saatUcretiInput) {
        personelSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const ucret = selectedOption.getAttribute('data-ucret');
            if (ucret && ucret !== '0') {
                saatUcretiInput.value = ucret;
            }
        });
    }
    
    // Bordro modalında personel seçildiğinde maaş bilgilerini otomatik doldur
    const bordroPersonelSelect = document.getElementById('personelSelect');
    const brutMaasInput = document.getElementById('brutMaas');
    const sgkBankaInput = document.getElementById('sgkBanka');
    
    if (bordroPersonelSelect && brutMaasInput && sgkBankaInput) {
        bordroPersonelSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const maas = selectedOption.getAttribute('data-maas');
                const maasSgk = selectedOption.getAttribute('data-maas-sgk');
                
                if (maas && maas !== '0') {
                    brutMaasInput.value = maas;
                }
                if (maasSgk && maasSgk !== '0') {
                    sgkBankaInput.value = maasSgk;
                }
            } else {
                brutMaasInput.value = '0';
                sgkBankaInput.value = '0';
            }
        });
    }
    
    // Para alanları için binlik ayracı formatlaması
    function formatMoneyInput(value) {
        if (!value || value === '0' || value === '') return '0';
        // Ondalık kısmı ayır (virgül veya nokta)
        let parts = value.toString().split(/[,.]/);
        let integerPart = parts[0].replace(/\D/g, ''); // Sadece rakamları al
        let decimalPart = parts[1] || '';
        
        // Binlik ayracı ekle
        const formattedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        
        // Ondalık kısmı varsa virgül ile ekle
        return decimalPart ? formattedInteger + ',' + decimalPart : formattedInteger;
    }
    
    function parseMoneyInput(value) {
        if (!value) return '0';
        // Binlik ayraçlarını ve virgülü kaldır, noktayı virgüle çevir
        let parsed = value.toString()
            .replace(/\./g, '') // Binlik ayraçlarını kaldır
            .replace(',', '.'); // Virgülü noktaya çevir (ondalık için)
        return parsed;
    }
    
    // Tüm para alanlarına formatlama ekle
    document.querySelectorAll('.money-field').forEach(function(input) {
        // Eğer bu input için otomatik formatlama devre dışı bırakılmışsa atla
        if (input.getAttribute('data-no-auto-format') === 'true') {
            return;
        }
        
        // Sayfa yüklendiğinde formatla
        if (input.value && input.value !== '0' && input.value !== '') {
            input.value = formatMoneyInput(input.value);
        }
        
        // Focus olduğunda (yazmaya başladığında) formatı temizle
        input.addEventListener('focus', function() {
            const rawValue = parseMoneyInput(this.value);
            this.value = rawValue === '0' ? '' : rawValue;
            this.select(); // Tüm metni seç
        });
        
        // Blur olduğunda (odaktan çıktığında) formatla
        input.addEventListener('blur', function() {
            const rawValue = parseMoneyInput(this.value);
            if (rawValue && rawValue !== '0') {
                this.value = formatMoneyInput(rawValue);
            } else {
                this.value = '0';
            }
        });
        
        // Kullanıcı yazarken sadece rakam ve virgül/nokta kabul et
        input.addEventListener('input', function(e) {
            let value = this.value;
            // Virgülü noktaya çevir (Türkçe klavye için)
            value = value.replace(',', '.');
            // Sadece rakam ve nokta bırak
            value = value.replace(/[^\d.]/g, '');
            // Birden fazla nokta varsa sadece ilkini bırak
            const parts = value.split('.');
            if (parts.length > 2) {
                value = parts[0] + '.' + parts.slice(1).join('');
            }
            // Ondalık kısımda maksimum 2 rakam
            if (parts.length === 2 && parts[1].length > 2) {
                value = parts[0] + '.' + parts[1].substring(0, 2);
            }
            this.value = value;
        });
    });
    
    // Form submit edilmeden önce tüm para alanlarını temizle (binlik ayraçları kaldır)
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const moneyInputs = form.querySelectorAll('.money-field');
            moneyInputs.forEach(function(input) {
                const rawValue = parseMoneyInput(input.value);
                input.value = rawValue || '0';
            });
        });
    });
});

