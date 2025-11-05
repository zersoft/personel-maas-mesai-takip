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
    alert('Avans düzenleme özelliği yakında eklenecek. ID: ' + id);
}

function silAvans(id) {
    if (confirm('Bu avans kaydını silmek istediğinize emin misiniz?')) {
        alert('Avans silme özelliği yakında eklenecek. ID: ' + id);
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
});

