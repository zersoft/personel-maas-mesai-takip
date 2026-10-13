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
                } else if (maas && maas !== '0') {
                    sgkBankaInput.value = maas;
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
        const trMonths = {
            'ocak': 1, 'şubat': 2, 'subat': 2, 'mart': 3, 'nisan': 4, 'mayıs': 5, 'mayis': 5,
            'haziran': 6, 'temmuz': 7, 'ağustos': 8, 'agustos': 8, 'eylül': 9, 'eylul': 9,
            'ekim': 10, 'kasım': 11, 'kasim': 11, 'aralık': 12, 'aralik': 12
        };

        function cleanNumber(val) {
            if (val === null || val === undefined) return 0;
            let s = val.toString().trim();
            if (!s || s === '-') return 0;
            const isNegative = s.indexOf('-') !== -1 || (s.startsWith('(') && s.endsWith(')'));
            s = s.replace(/[₺\s\u00A0]/g, '');
            const hasComma = s.indexOf(',') !== -1;
            const hasDot = s.indexOf('.') !== -1;
            if (hasComma && hasDot) {
                s = s.replace(/\./g, '').replace(',', '.');
            } else if (hasComma) {
                s = s.replace(',', '.');
            } else if (hasDot) {
                const parts = s.split('.');
                if (parts.length > 1) {
                    const last = parts.pop();
                    if (last.length <= 2) {
                        s = parts.join('') + '.' + last;
                    } else {
                        s = parts.join('') + last;
                    }
                }
            }
            s = s.replace(/[^0-9.]/g, '');
            let num = parseFloat(s) || 0;
            return isNegative ? -num : num;
        }

        function cleanDate(val) {
            if (!val) return 0;
            const s = val.toString().trim();
            if (!s || s === '-') return 0;

            const iso = s.match(/(\d{4})-(\d{1,2})-(\d{1,2})/);
            if (iso) {
                return new Date(parseInt(iso[1], 10), parseInt(iso[2], 10) - 1, parseInt(iso[3], 10)).getTime() || 0;
            }

            const trDate = s.match(/(\d{1,2})[./-](\d{1,2})[./-](\d{4})/);
            if (trDate) {
                return new Date(parseInt(trDate[3], 10), parseInt(trDate[2], 10) - 1, parseInt(trDate[1], 10)).getTime() || 0;
            }

            const monthYear = s.toLowerCase().match(/([a-zçğıöşü]+)\s+(\d{4})/i);
            if (monthYear && trMonths[monthYear[1]]) {
                return new Date(parseInt(monthYear[2], 10), trMonths[monthYear[1]] - 1, 1).getTime() || 0;
            }

            return 0;
        }

        function cleanMonth(val) {
            if (!val) return 0;
            const s = val.toString().trim().toLowerCase();
            return trMonths[s] || parseInt(s, 10) || 0;
        }

        // Tüm sıralanabilir tabloları veya başlıkları bul
        const tables = new Set();
        document.querySelectorAll('table.table-sortable, table:has(th.sortable)').forEach(t => tables.add(t));

        tables.forEach(function(table) {
            const thead = table.querySelector('thead');
            const tbody = table.querySelector('tbody');
            if (!thead || !tbody) return;

            const headers = thead.querySelectorAll('th.sortable');
            headers.forEach(function(th) {
                // Sütun indeksini belirle
                const cellIndex = th.cellIndex;

                // Sıralama simgesi yoksa otomatik ekle
                let iconSpan = th.querySelector('.sort-icon');
                if (!iconSpan) {
                    let existingIcon = th.querySelector('i');
                    if (existingIcon) {
                        iconSpan = document.createElement('span');
                        iconSpan.className = 'sort-icon';
                        existingIcon.parentNode.insertBefore(iconSpan, existingIcon);
                        iconSpan.appendChild(existingIcon);
                    } else {
                        iconSpan = document.createElement('span');
                        iconSpan.className = 'sort-icon';
                        iconSpan.innerHTML = '<i class="bi bi-arrow-down-up small text-muted"></i>';
                        th.appendChild(iconSpan);
                    }
                }

                // Tıklama olayı
                th.style.cursor = 'pointer';
                th.style.userSelect = 'none';

                // Var olan event listener'ları tekrar eklememek için işaretle
                if (th.getAttribute('data-sort-bound') === 'true') return;
                th.setAttribute('data-sort-bound', 'true');

                th.addEventListener('click', function(e) {
                    if (e.target.closest('input, button, select, a')) return;

                    const sortType = th.getAttribute('data-sort') || (th.classList.contains('money') ? 'money' : 'text');
                    const currentDir = th.getAttribute('data-dir');
                    const nextDir = (currentDir === 'asc') ? 'desc' : 'asc';

                    // Tablodaki tüm başlık simgelerini ve durumlarını temizle
                    headers.forEach(h => {
                        h.removeAttribute('data-dir');
                        h.classList.remove('sorted-asc', 'sorted-desc');
                        const ico = h.querySelector('.sort-icon i');
                        if (ico) {
                            ico.className = 'bi bi-arrow-down-up small text-muted';
                        }
                    });

                    // Aktif başlığı güncelle
                    th.setAttribute('data-dir', nextDir);
                    th.classList.add(nextDir === 'asc' ? 'sorted-asc' : 'sorted-desc');
                    const activeIco = th.querySelector('.sort-icon i');
                    if (activeIco) {
                        if (sortType === 'money' || sortType === 'number') {
                            activeIco.className = nextDir === 'asc' ? 'bi bi-sort-numeric-down text-primary' : 'bi bi-sort-numeric-up-alt text-primary';
                        } else if (sortType === 'date' || sortType === 'month') {
                            activeIco.className = nextDir === 'asc' ? 'bi bi-sort-down text-primary' : 'bi bi-sort-up-alt text-primary';
                        } else {
                            activeIco.className = nextDir === 'asc' ? 'bi bi-sort-alpha-down text-primary' : 'bi bi-sort-alpha-up-alt text-primary';
                        }
                    }

                    // Sıralanacak satırları al (colspan olan bilgi satırlarını hariç tut)
                    const rows = Array.from(tbody.querySelectorAll('tr')).filter(tr => {
                        if (tr.classList.contains('no-sort')) return false;
                        const firstCell = tr.querySelector('td');
                        if (firstCell && firstCell.colSpan > 1) return false;
                        return tr.cells.length > cellIndex;
                    });

                    if (rows.length <= 1) return;

                    const getVal = function(cell) {
                        if (!cell) return '';
                        const input = cell.querySelector('input:not([type="hidden"]), select');
                        if (input) return input.value.trim();
                        return (cell.innerText || cell.textContent || '').trim();
                    };

                    rows.sort(function(rowA, rowB) {
                        const cellA = rowA.cells[cellIndex];
                        const cellB = rowB.cells[cellIndex];
                        const valA = getVal(cellA);
                        const valB = getVal(cellB);

                        if (sortType === 'money' || sortType === 'number') {
                            const numA = cleanNumber(valA);
                            const numB = cleanNumber(valB);
                            return nextDir === 'asc' ? (numA - numB) : (numB - numA);
                        } else if (sortType === 'date') {
                            const dateA = cleanDate(valA);
                            const dateB = cleanDate(valB);
                            return nextDir === 'asc' ? (dateA - dateB) : (dateB - dateA);
                        } else if (sortType === 'month') {
                            const mA = cleanMonth(valA);
                            const mB = cleanMonth(valB);
                            return nextDir === 'asc' ? (mA - mB) : (mB - mA);
                        } else {
                            const comp = valA.localeCompare(valB, 'tr', { sensitivity: 'base', numeric: true });
                            return nextDir === 'asc' ? comp : -comp;
                        }
                    });

                    // Sıralanmış satırları tbody içine tekrar yerleştir
                    rows.forEach(r => tbody.appendChild(r));
                });
            });
        });
    }

    window.initSortableTables = initSortableTables;
    initSortableTables();
});

