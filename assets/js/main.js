// OYS - Ocak Yönetim Sistemi - Ana JavaScript Dosyası

// CSRF token al
function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

// POST ile silme formu gönder (CSRF korumalı)
function postDeleteForm(action, id) {
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = action;
    form.style.display = 'none';
    form.innerHTML = '<input type="hidden" name="action" value="delete">' +
                     '<input type="hidden" name="id" value="' + id + '">' +
                     '<input type="hidden" name="csrf_token" value="' + getCsrfToken() + '">';
    document.body.appendChild(form);
    form.submit();
}

// POST ile restore formu gönder (CSRF korumalı)
function postRestoreForm(action, id) {
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = action;
    form.style.display = 'none';
    form.innerHTML = '<input type="hidden" name="action" value="restore">' +
                     '<input type="hidden" name="id" value="' + id + '">' +
                     '<input type="hidden" name="csrf_token" value="' + getCsrfToken() + '">';
    document.body.appendChild(form);
    form.submit();
}

// Personel işlemleri
function duzenlePersonel(id) {
    window.location.href = 'personel_duzenle.php?id=' + id;
}

function silPersonel(id) {
    if (confirm('Bu personeli silmek istediğinize emin misiniz?')) {
        postDeleteForm('personel_islem.php', id);
    }
}

function geriAlPersonel(id) {
    if (confirm('Bu personeli geri almak istiyor musunuz?')) {
        postRestoreForm('personel_islem.php', id);
    }
}

// Bordro işlemleri
function gosterBordro(id) {
    window.location.href = 'bordro_detay.php?id=' + id;
}

function duzenleBordro(id) {
    window.location.href = 'bordro_duzenle.php?id=' + id;
}

function silBordro(id) {
    if (confirm('Bu bordroyu silmek istediğinize emin misiniz?')) {
        postDeleteForm('bordro_islem.php', id);
    }
}

// Fazla mesai işlemleri
function duzenleFazlaMesai(id) {
    // Mevcut URL parametrelerini koru
    const currentParams = new URLSearchParams(window.location.search);
    const params = new URLSearchParams();
    params.set('id', id);
    // Filtre parametrelerini taşı
    if (currentParams.has('mode')) params.set('mode', currentParams.get('mode'));
    if (currentParams.has('personel_id')) params.set('personel_id', currentParams.get('personel_id'));
    if (currentParams.has('ay')) params.set('ay', currentParams.get('ay'));
    if (currentParams.has('yil')) params.set('yil', currentParams.get('yil'));
    if (currentParams.has('baslangic')) params.set('baslangic', currentParams.get('baslangic'));
    if (currentParams.has('bitis')) params.set('bitis', currentParams.get('bitis'));
    window.location.href = 'fazla_mesai_duzenle.php?' + params.toString();
}

function silFazlaMesai(id) {
    if (confirm('Bu fazla mesai kaydını silmek istediğinize emin misiniz?')) {
        postDeleteForm('fazla_mesai_islem.php', id);
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
        postDeleteForm('avans_islem.php', id);
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
    console.log('OYS - Ocak Yönetim Sistemi yüklendi');
    
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
        if (!value || value === '0' || value === '') return '0,00';
        const raw = parseMoneyInput(value);
        if (!raw || raw === '0') return '0,00';
        
        let parts = raw.split('.');
        let integerPart = parts[0].replace(/\D/g, '') || '0';
        let decimalPart = parts[1] || '00';
        if (decimalPart.length === 1) decimalPart += '0';
        if (decimalPart.length > 2) decimalPart = decimalPart.substring(0, 2);
        
        const formattedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return formattedInteger + ',' + decimalPart;
    }
    
    function parseMoneyInput(value) {
        if (!value) return '0';
        let val = value.toString().trim().replace('₺','');
        const hasComma = val.indexOf(',') !== -1;
        const hasDot = val.indexOf('.') !== -1;
        if (hasComma && hasDot) {
            // Türkçe format: binlik nokta, ondalık virgül
            val = val.replace(/\./g, '');
            val = val.replace(',', '.');
        } else if (hasComma) {
            // Sadece virgül varsa: virgülü ondalık kabul et
            val = val.replace(/\./g, '');
            val = val.replace(',', '.');
        } else if (hasDot) {
            // Sadece nokta varsa: son noktayı ondalık kabul et, diğerlerini kaldır
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
        // Sadece rakam ve tek nokta bırak
        val = val.replace(/[^0-9.]/g, '');
        return val || '0';
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
                // Bu input sayfa özel formatlama/ham değer gönderimi kullanıyorsa atla
                if (input.getAttribute('data-no-auto-format') === 'true') {
                    return;
                }
                const rawValue = parseMoneyInput(input.value);
                input.value = rawValue || '0';
            });
        });
    });

    // Tablo sütun başlığına tıklayarak sıralama motoru
    function initSortableTables() {
        document.querySelectorAll('table.table-sortable').forEach(function(table) {
            const thead = table.querySelector('thead');
            const tbody = table.querySelector('tbody');
            if (!thead || !tbody) return;

            const headers = thead.querySelectorAll('th.sortable');
            headers.forEach(function(th) {
                const cellIndex = th.cellIndex;
                const sortType = th.getAttribute('data-sort') || 'text';
                let currentDir = null; // 'asc' veya 'desc'

                th.addEventListener('click', function(e) {
                    if (e.target.tagName === 'INPUT' || e.target.tagName === 'BUTTON') return;

                    currentDir = (currentDir === 'asc') ? 'desc' : 'asc';

                    // Tüm başlıklardaki simgeleri sıfırla
                    headers.forEach(h => {
                        const icon = h.querySelector('i');
                        if (icon) {
                            icon.className = 'bi bi-arrow-down-up small text-muted';
                        }
                    });

                    // Aktif başlık simgesi
                    const activeIcon = th.querySelector('i');
                    if (activeIcon) {
                        activeIcon.className = currentDir === 'asc' 
                            ? (sortType === 'money' || sortType === 'number' ? 'bi bi-sort-numeric-down text-primary' : 'bi bi-sort-alpha-down text-primary')
                            : (sortType === 'money' || sortType === 'number' ? 'bi bi-sort-numeric-up-alt text-primary' : 'bi bi-sort-alpha-up-alt text-primary');
                    }

                    const rows = Array.from(tbody.querySelectorAll('tr'));
                    if (rows.length <= 1) return;

                    const getVal = function(cell) {
                        if (!cell) return '';
                        const input = cell.querySelector('input:not([type="hidden"]), select');
                        if (input) return input.value.trim();
                        return cell.textContent.trim();
                    };

                    rows.sort(function(rowA, rowB) {
                        const cellA = rowA.cells[cellIndex];
                        const cellB = rowB.cells[cellIndex];
                        const valA = getVal(cellA);
                        const valB = getVal(cellB);

                        if (sortType === 'money' || sortType === 'number') {
                            const numA = typeof parseMoneyInput === 'function' ? parseFloat(parseMoneyInput(valA)) : (parseFloat(valA.replace(/[^0-9.-]/g, '')) || 0);
                            const numB = typeof parseMoneyInput === 'function' ? parseFloat(parseMoneyInput(valB)) : (parseFloat(valB.replace(/[^0-9.-]/g, '')) || 0);
                            return currentDir === 'asc' ? (numA - numB) : (numB - numA);
                        } else if (sortType === 'date') {
                            const parseDateVal = function(s) {
                                if (!s) return 0;
                                if (s.indexOf('-') !== -1) {
                                    return new Date(s).getTime() || 0;
                                }
                                const p = s.split('.');
                                if (p.length === 3) {
                                    return new Date(p[2], p[1] - 1, p[0]).getTime() || 0;
                                }
                                return 0;
                            };
                            return currentDir === 'asc' ? (parseDateVal(valA) - parseDateVal(valB)) : (parseDateVal(valB) - parseDateVal(valA));
                        } else {
                            return currentDir === 'asc' 
                                ? valA.localeCompare(valB, 'tr', { sensitivity: 'base' })
                                : valB.localeCompare(valA, 'tr', { sensitivity: 'base' });
                        }
                    });

                    rows.forEach(r => tbody.appendChild(r));
                });
            });
        });
    }

    initSortableTables();
});

