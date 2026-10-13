<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('user');

$pageTitle = 'Toplu Avans Girişi ve Düzenleme';

// Bordro dönemi (Ay/Yıl)
$ay = isset($_GET['ay']) ? (int)$_GET['ay'] : (int)date('n');
$yil = isset($_GET['yil']) ? (int)$_GET['yil'] : (int)date('Y');
if ($ay < 1 || $ay > 12) { $ay = (int)date('n'); }
if ($yil < 2000 || $yil > 2100) { $yil = (int)date('Y'); }

// Varsayılan işlem tarihi (seçili ay şu anki aysa bugün, değilse ayın 15'i)
if ($ay === (int)date('n') && $yil === (int)date('Y')) {
    $defaultTarih = date('Y-m-d');
} else {
    $defaultTarih = sprintf('%04d-%02d-15', $yil, $ay);
}

// Aktif personelleri çek
try {
    $personeller = $pdo->query("SELECT id, ad_soyad FROM personel_listesi WHERE aktif = 1 ORDER BY ad_soyad")->fetchAll();
} catch (Throwable $e) {
    $personeller = [];
}

// Seçilen döneme ait mevcut avansları çek
$avanslar = [];
try {
    $stmt = $pdo->prepare("SELECT a.*, p.ad_soyad 
                           FROM avans_takip a 
                           JOIN personel_listesi p ON a.personel_id = p.id 
                           WHERE ( (a.bordro_ay = ? AND a.bordro_yil = ?) OR (a.bordro_ay IS NULL AND a.bordro_yil IS NULL AND MONTH(a.tarih) = ? AND YEAR(a.tarih) = ?) )
                           ORDER BY a.tarih ASC, a.id ASC");
    $stmt->execute([$ay, $yil, $ay, $yil]);
    $avanslar = $stmt->fetchAll();
} catch (Throwable $e) {
    $avanslar = [];
}

// Personel bazında mevcut avansları grupla
$personelAvanslari = [];
foreach ($avanslar as $av) {
    $pid = (int)$av['personel_id'];
    if (!isset($personelAvanslari[$pid])) {
        $personelAvanslari[$pid] = [];
    }
    $personelAvanslari[$pid][] = $av;
}

include '../includes/header.php';

// Flash mesajlar
if (isset($_GET['success'])) {
    $eklenen = isset($_GET['eklenen']) ? (int)$_GET['eklenen'] : 0;
    $guncellenen = isset($_GET['guncellenen']) ? (int)$_GET['guncellenen'] : 0;
    $silinen = isset($_GET['silinen']) ? (int)$_GET['silinen'] : 0;
    
    $mesajDetay = [];
    if ($eklenen > 0) $mesajDetay[] = "$eklenen yeni avans eklendi";
    if ($guncellenen > 0) $mesajDetay[] = "$guncellenen avans güncellendi";
    if ($silinen > 0) $mesajDetay[] = "$silinen avans silindi";
    
    $tamMesaj = !empty($mesajDetay) ? implode(', ', $mesajDetay) . '.' : 'Toplu avans işlemleri başarıyla kaydedildi.';
    echo showMessage($tamMesaj, 'success');
}
if (isset($_GET['error'])) {
    echo showMessage($_GET['error'], 'danger');
}
?>

<!-- Üst Başlık ve Aksiyonlar -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0"><i class="bi bi-wallet2"></i> Toplu Avans Girişi ve Düzenleme</h3>
        <small class="text-muted">
            Dönem: <strong><?php echo getTurkishMonthName($ay) . ' ' . $yil; ?></strong> | Aktif Personel: <?php echo count($personeller); ?> kişi
        </small>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="avans_takip.php?ay=<?php echo $ay; ?>&yil=<?php echo $yil; ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Avans Listesine Dön
        </a>
    </div>
</div>

<!-- Dönem Seçim Kartı -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-auto">
                <label class="form-label mb-0 fw-semibold text-secondary"><i class="bi bi-calendar-event"></i> Dönem Değiştir:</label>
            </div>
            <div class="col-auto">
                <select class="form-select form-select-sm" name="ay" style="width: 140px;">
                    <?php for($i=1; $i<=12; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo ($i == $ay) ? 'selected' : ''; ?>>
                            <?php echo getTurkishMonthName($i); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto">
                <input type="number" class="form-control form-control-sm" name="yil" value="<?php echo $yil; ?>" min="2020" max="<?php echo date('Y')+1; ?>" style="width: 100px;">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-funnel"></i> Dönemi Getir
                </button>
            </div>
            <div class="col-auto ms-auto text-muted small">
                <i class="bi bi-info-circle"></i> Bu sayfada hem seçili döneme ait yeni toplu avans girebilir hem de mevcut avansları topluca düzenleyebilirsiniz.
            </div>
        </form>
    </div>
</div>

<!-- Hızlı Toplu İşlem Araçları Kartı -->
<div class="card mb-3 border-primary-subtle bg-light-subtle">
    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
        <strong class="text-primary"><i class="bi bi-magic"></i> Hızlı Toplu Doldurma Araçları</strong>
        <div class="d-flex gap-1">
            <button type="button" id="btnSecTumu" class="btn btn-xs btn-outline-secondary py-1 px-2">Tümünü Seç</button>
            <button type="button" id="btnSecTemizle" class="btn btn-xs btn-outline-secondary py-1 px-2">Seçimi Kaldır</button>
            <button type="button" id="btnSecMevcut" class="btn btn-xs btn-outline-info py-1 px-2">Sadece Mevcutları Seç</button>
            <button type="button" id="btnSecYeni" class="btn btn-xs btn-outline-info py-1 px-2">Sadece Boş Olanları Seç</button>
        </div>
    </div>
    <div class="card-body py-2">
        <div class="row g-2 align-items-center">
            <!-- Toplu Tarih -->
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted">Tarih</span>
                    <input type="date" id="topluTarih" class="form-control" value="<?php echo $defaultTarih; ?>">
                    <button type="button" id="btnUygulaTarih" class="btn btn-outline-primary" title="Tarihi seçilen satırlara uygula">
                        Uygula
                    </button>
                </div>
            </div>
            <!-- Toplu Banka -->
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted">Banka</span>
                    <input type="text" id="topluBanka" class="form-control money-field text-end" placeholder="0,00" value="">
                    <button type="button" id="btnUygulaBanka" class="btn btn-outline-primary" title="Banka tutarını seçilen satırlara uygula">
                        Uygula
                    </button>
                </div>
            </div>
            <!-- Toplu Nakit -->
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted">Nakit</span>
                    <input type="text" id="topluNakit" class="form-control money-field text-end" placeholder="0,00" value="">
                    <button type="button" id="btnUygulaNakit" class="btn btn-outline-primary" title="Nakit tutarını seçilen satırlara uygula">
                        Uygula
                    </button>
                </div>
            </div>
            <!-- Toplu Açıklama -->
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted">Açıklama</span>
                    <input type="text" id="topluAciklama" class="form-control" placeholder="<?php echo getTurkishMonthName($ay) . ' ' . $yil . ' Avansı'; ?>">
                    <button type="button" id="btnUygulaAciklama" class="btn btn-outline-primary" title="Açıklamayı seçilen satırlara uygula">
                        Uygula
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Canlı İstatistikler & Özet Kartları -->
<div class="row g-2 mb-3">
    <div class="col-md-2 col-6">
        <div class="card bg-light border-0 shadow-sm text-center py-2">
            <span class="text-muted small">Aktif Personel</span>
            <strong class="fs-6"><?php echo count($personeller); ?></strong>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="card bg-light border-0 shadow-sm text-center py-2">
            <span class="text-muted small">Seçili Kayıt</span>
            <strong class="fs-6 text-primary" id="ozetSeciliSayisi">0</strong>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="card bg-light border-0 shadow-sm text-center py-2">
            <span class="text-muted small">Mevcut Avans Kaydı</span>
            <strong class="fs-6 text-info"><?php echo count($avanslar); ?></strong>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="card bg-light border-0 shadow-sm text-center py-2">
            <span class="text-muted small">Toplam Banka</span>
            <strong class="fs-6 text-primary" id="ozetToplamBanka">0,00 ₺</strong>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="card bg-light border-0 shadow-sm text-center py-2">
            <span class="text-muted small">Toplam Nakit</span>
            <strong class="fs-6 text-success" id="ozetToplamNakit">0,00 ₺</strong>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="card bg-primary-subtle border-0 shadow-sm text-center py-2">
            <span class="text-primary small fw-semibold">Genel Toplam</span>
            <strong class="fs-6 text-primary" id="ozetGenelToplam">0,00 ₺</strong>
        </div>
    </div>
</div>

<!-- Ana Form -->
<form id="topluAvansForm" action="toplu_avans_islem.php" method="POST">
    <?php echo csrfField(); ?>
    <input type="hidden" name="bordro_ay" value="<?php echo $ay; ?>">
    <input type="hidden" name="bordro_yil" value="<?php echo $yil; ?>">

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <strong><i class="bi bi-list-check"></i> Avans Listesi</strong>
                <span class="badge bg-secondary-subtle text-secondary border">Dönem: <?php echo getTurkishMonthName($ay) . ' ' . $yil; ?></span>
            </div>
            <div style="width: 250px;">
                <input type="text" id="personelArama" class="form-control form-control-sm" placeholder="🔍 Personel ara...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0" id="avansTablosu">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 45px;">
                                <input type="checkbox" id="hepsiniSecCheckbox" class="form-check-input" title="Tümünü Seç / Kaldır">
                            </th>
                            <th style="min-width: 180px;">Personel</th>
                            <th style="width: 100px;">Durum</th>
                            <th style="width: 140px;">Tarih</th>
                            <th style="width: 150px;" class="text-end">Avans (Banka)</th>
                            <th style="width: 150px;" class="text-end">Avans (Nakit)</th>
                            <th style="width: 140px;" class="text-end">Toplam</th>
                            <th style="min-width: 180px;">Açıklama</th>
                            <th style="width: 70px;" class="text-center">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rowIndex = 0;
                        if (empty($personeller)):
                        ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">Aktif personel bulunamadı.</td>
                            </tr>
                        <?php
                        else:
                            foreach ($personeller as $p):
                                $pid = (int)$p['id'];
                                $mevcutList = $personelAvanslari[$pid] ?? [];

                                if (!empty($mevcutList)):
                                    // Personelin bu dönemde bir veya daha fazla kaydı var
                                    foreach ($mevcutList as $subIndex => $av):
                                        $rowIndex++;
                                        $avId = (int)$av['id'];
                                        $bankaTutari = (float)($av['banka_tutari'] ?? 0);
                                        $nakitTutari = (float)($av['nakit_tutari'] ?? 0);
                                        $toplamTutar = ($bankaTutari + $nakitTutari) > 0 ? ($bankaTutari + $nakitTutari) : (float)($av['avans_tutari'] ?? 0);
                                        if ($bankaTutari == 0 && $nakitTutari == 0 && $toplamTutar > 0) {
                                            $bankaTutari = $toplamTutar;
                                        }
                                        $tarihVal = $av['tarih'] ?? $defaultTarih;
                                        $aciklamaVal = $av['aciklama'] ?? '';
                        ?>
                                        <tr class="avans-row row-mevcut table-light" data-personel="<?php echo escape(mb_strtolower($p['ad_soyad'], 'UTF-8')); ?>" data-row-id="<?php echo $rowIndex; ?>">
                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input row-check dahil" name="items[<?php echo $rowIndex; ?>][dahil]" value="1" checked>
                                                <input type="hidden" name="items[<?php echo $rowIndex; ?>][existing_id]" value="<?php echo $avId; ?>">
                                                <input type="hidden" name="items[<?php echo $rowIndex; ?>][personel_id]" value="<?php echo $pid; ?>">
                                            </td>
                                            <td>
                                                <strong class="personel-ad"><?php echo escape($p['ad_soyad']); ?></strong>
                                                <?php if (count($mevcutList) > 1): ?>
                                                    <span class="badge bg-secondary-subtle text-secondary small ms-1">#<?php echo ($subIndex + 1); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-success-subtle text-success border border-success">
                                                    <i class="bi bi-check2"></i> Kayıtlı #<?php echo $avId; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <input type="date" class="form-control form-control-sm row-tarih" name="items[<?php echo $rowIndex; ?>][tarih]" value="<?php echo escape($tarihVal); ?>" required>
                                            </td>
                                            <td class="text-end">
                                                <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control form-control-sm text-end money-field row-banka" name="items[<?php echo $rowIndex; ?>][banka_tutari]" value="<?php echo number_format($bankaTutari, 2, ',', '.'); ?>">
                                                    <span class="input-group-text">₺</span>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control form-control-sm text-end money-field row-nakit" name="items[<?php echo $rowIndex; ?>][nakit_tutari]" value="<?php echo number_format($nakitTutari, 2, ',', '.'); ?>">
                                                    <span class="input-group-text">₺</span>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <span class="fw-semibold row-toplam-badge text-primary"><?php echo formatMoney($toplamTutar); ?></span>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm row-aciklama" name="items[<?php echo $rowIndex; ?>][aciklama]" value="<?php echo escape($aciklamaVal); ?>" placeholder="Opsiyonel">
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" title="Bu kaydı sil" onclick="silTekAvans(<?php echo $avId; ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1 btn-ek-satir" title="Bu personele ek avans satırı ekle" data-personel-id="<?php echo $pid; ?>" data-personel-ad="<?php echo escape($p['ad_soyad']); ?>">
                                                    <i class="bi bi-plus-lg"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php
                                else:
                                    // Henüz bu döneme ait avansı yok
                                    $rowIndex++;
                                ?>
                                    <tr class="avans-row row-yeni" data-personel="<?php echo escape(mb_strtolower($p['ad_soyad'], 'UTF-8')); ?>" data-row-id="<?php echo $rowIndex; ?>">
                                        <td class="text-center">
                                            <input type="checkbox" class="form-check-input row-check dahil" name="items[<?php echo $rowIndex; ?>][dahil]" value="1">
                                            <input type="hidden" name="items[<?php echo $rowIndex; ?>][existing_id]" value="0">
                                            <input type="hidden" name="items[<?php echo $rowIndex; ?>][personel_id]" value="<?php echo $pid; ?>">
                                        </td>
                                        <td>
                                            <strong class="personel-ad"><?php echo escape($p['ad_soyad']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-muted border">Yeni</span>
                                        </td>
                                        <td>
                                            <input type="date" class="form-control form-control-sm row-tarih" name="items[<?php echo $rowIndex; ?>][tarih]" value="<?php echo escape($defaultTarih); ?>" required>
                                        </td>
                                        <td class="text-end">
                                            <div class="input-group input-group-sm">
                                                <input type="text" class="form-control form-control-sm text-end money-field row-banka" name="items[<?php echo $rowIndex; ?>][banka_tutari]" value="0,00">
                                                <span class="input-group-text">₺</span>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <div class="input-group input-group-sm">
                                                <input type="text" class="form-control form-control-sm text-end money-field row-nakit" name="items[<?php echo $rowIndex; ?>][nakit_tutari]" value="0,00">
                                                <span class="input-group-text">₺</span>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <span class="fw-semibold row-toplam-badge text-muted">0,00 ₺</span>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm row-aciklama" name="items[<?php echo $rowIndex; ?>][aciklama]" value="" placeholder="Opsiyonel">
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1 btn-ek-satir" title="Bu personele ek avans satırı ekle" data-personel-id="<?php echo $pid; ?>" data-personel-ad="<?php echo escape($p['ad_soyad']); ?>">
                                                <i class="bi bi-plus-lg"></i>
                                            </button>
                                        </td>
                                    </tr>
                        <?php
                                endif;
                            endforeach;
                        endif;
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center">
            <div class="text-muted small">
                <i class="bi bi-shield-check text-success"></i> Sadece seçili (işaretli) olan satırlar kaydedilir veya güncellenir.
            </div>
            <div class="d-flex gap-2">
                <a href="avans_takip.php?ay=<?php echo $ay; ?>&yil=<?php echo $yil; ?>" class="btn btn-secondary">
                    İptal
                </a>
                <button type="submit" class="btn btn-primary btn-lg px-4" id="btnKaydet">
                    <i class="bi bi-save"></i> Değişiklikleri ve Avansları Kaydet
                </button>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let nextRowIndex = <?php echo $rowIndex + 1; ?>;
    const defaultTarihStr = <?php echo json_encode($defaultTarih); ?>;
    
    // Para ayrıştırma yardımcısı (JS tarafı)
    function parseMoneyLocal(val) {
        if (!val) return 0;
        let s = val.toString().trim().replace('₺', '').trim();
        if (s.indexOf(',') !== -1 && s.indexOf('.') !== -1) {
            s = s.replace(/\./g, '').replace(',', '.');
        } else if (s.indexOf(',') !== -1) {
            s = s.replace(/\./g, '').replace(',', '.');
        } else {
            // Sadece nokta veya düz sayı
            const parts = s.split('.');
            if (parts.length > 1) {
                const last = parts.pop();
                s = (last.length <= 2) ? (parts.join('') + '.' + last) : (parts.join('') + last);
            }
        }
        s = s.replace(/[^0-9.]/g, '');
        const n = parseFloat(s);
        return isNaN(n) ? 0 : n;
    }

    // Para formatlama yardımcısı (JS tarafı)
    function formatMoneyLocal(amount) {
        return (amount || 0).toLocaleString('tr-TR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' ₺';
    }

    // Tek satır toplamını hesapla ve rozeti güncelle
    function updateRowTotal(tr) {
        if (!tr) return;
        const bankaInp = tr.querySelector('.row-banka');
        const nakitInp = tr.querySelector('.row-nakit');
        const badge = tr.querySelector('.row-toplam-badge');
        const check = tr.querySelector('.row-check');

        const banka = bankaInp ? parseMoneyLocal(bankaInp.value) : 0;
        const nakit = nakitInp ? parseMoneyLocal(nakitInp.value) : 0;
        const toplam = banka + nakit;

        if (badge) {
            badge.textContent = formatMoneyLocal(toplam);
            if (toplam > 0) {
                badge.className = 'fw-semibold row-toplam-badge text-primary';
            } else {
                badge.className = 'fw-semibold row-toplam-badge text-muted';
            }
        }
        return toplam;
    }

    // Tüm özet kutularını güncelle
    function recalculateSummary() {
        const rows = document.querySelectorAll('#avansTablosu tbody tr.avans-row');
        let seciliSayisi = 0;
        let toplamBanka = 0;
        let toplamNakit = 0;

        rows.forEach(tr => {
            const check = tr.querySelector('.row-check');
            if (check && check.checked) {
                seciliSayisi++;
                const bankaInp = tr.querySelector('.row-banka');
                const nakitInp = tr.querySelector('.row-nakit');
                const b = bankaInp ? parseMoneyLocal(bankaInp.value) : 0;
                const n = nakitInp ? parseMoneyLocal(nakitInp.value) : 0;
                toplamBanka += b;
                toplamNakit += n;
            }
        });

        const genelToplam = toplamBanka + toplamNakit;
        const elSecili = document.getElementById('ozetSeciliSayisi');
        const elBanka = document.getElementById('ozetToplamBanka');
        const elNakit = document.getElementById('ozetToplamNakit');
        const elGenel = document.getElementById('ozetGenelToplam');

        if (elSecili) elSecili.textContent = seciliSayisi;
        if (elBanka) elBanka.textContent = formatMoneyLocal(toplamBanka);
        if (elNakit) elNakit.textContent = formatMoneyLocal(toplamNakit);
        if (elGenel) elGenel.textContent = formatMoneyLocal(genelToplam);
    }

    // Satır içi girdi olaylarını bağla
    function bindRowEvents(tr) {
        const bankaInp = tr.querySelector('.row-banka');
        const nakitInp = tr.querySelector('.row-nakit');
        const check = tr.querySelector('.row-check');

        [bankaInp, nakitInp].forEach(inp => {
            if (!inp) return;
            inp.addEventListener('input', function() {
                const rowTot = updateRowTotal(tr);
                // Kullanıcı tutar girdiğinde otomatik olarak seçili yap
                if (rowTot > 0 && check && !check.checked) {
                    check.checked = true;
                }
                recalculateSummary();
            });
            inp.addEventListener('blur', function() {
                updateRowTotal(tr);
                recalculateSummary();
            });
        });

        if (check) {
            check.addEventListener('change', function() {
                recalculateSummary();
            });
        }
    }

    // Var olan tüm satırları bağla
    document.querySelectorAll('#avansTablosu tbody tr.avans-row').forEach(tr => {
        bindRowEvents(tr);
        updateRowTotal(tr);
    });
    recalculateSummary();

    // Hepsini Seç / Kaldır
    const masterCheck = document.getElementById('hepsiniSecCheckbox');
    if (masterCheck) {
        masterCheck.addEventListener('change', function() {
            const isChecked = this.checked;
            document.querySelectorAll('#avansTablosu tbody tr.avans-row').forEach(tr => {
                // Görünür olanları seç (filtre durumunu gözet)
                if (tr.style.display !== 'none') {
                    const c = tr.querySelector('.row-check');
                    if (c) c.checked = isChecked;
                }
            });
            recalculateSummary();
        });
    }

    // Hızlı seçim butonları
    const btnSecTumu = document.getElementById('btnSecTumu');
    if (btnSecTumu) {
        btnSecTumu.addEventListener('click', function() {
            document.querySelectorAll('#avansTablosu tbody tr.avans-row').forEach(tr => {
                if (tr.style.display !== 'none') {
                    const c = tr.querySelector('.row-check');
                    if (c) c.checked = true;
                }
            });
            if (masterCheck) masterCheck.checked = true;
            recalculateSummary();
        });
    }

    const btnSecTemizle = document.getElementById('btnSecTemizle');
    if (btnSecTemizle) {
        btnSecTemizle.addEventListener('click', function() {
            document.querySelectorAll('#avansTablosu tbody tr.avans-row').forEach(tr => {
                const c = tr.querySelector('.row-check');
                if (c) c.checked = false;
            });
            if (masterCheck) masterCheck.checked = false;
            recalculateSummary();
        });
    }

    const btnSecMevcut = document.getElementById('btnSecMevcut');
    if (btnSecMevcut) {
        btnSecMevcut.addEventListener('click', function() {
            document.querySelectorAll('#avansTablosu tbody tr.avans-row').forEach(tr => {
                const isMevcut = tr.classList.contains('row-mevcut');
                const c = tr.querySelector('.row-check');
                if (c) c.checked = isMevcut;
            });
            recalculateSummary();
        });
    }

    const btnSecYeni = document.getElementById('btnSecYeni');
    if (btnSecYeni) {
        btnSecYeni.addEventListener('click', function() {
            document.querySelectorAll('#avansTablosu tbody tr.avans-row').forEach(tr => {
                const isYeni = tr.classList.contains('row-yeni');
                const c = tr.querySelector('.row-check');
                if (c) c.checked = isYeni;
            });
            recalculateSummary();
        });
    }

    // Toplu Tarih Uygula
    const btnUygulaTarih = document.getElementById('btnUygulaTarih');
    if (btnUygulaTarih) {
        btnUygulaTarih.addEventListener('click', function() {
            const val = document.getElementById('topluTarih').value;
            if (!val) {
                alert('Lütfen geçerli bir tarih seçin.');
                return;
            }
            let count = 0;
            document.querySelectorAll('#avansTablosu tbody tr.avans-row').forEach(tr => {
                const c = tr.querySelector('.row-check');
                if (c && c.checked) {
                    const inp = tr.querySelector('.row-tarih');
                    if (inp) inp.value = val;
                    count++;
                }
            });
            if (count === 0) {
                alert('Tarih uygulamak için lütfen en az bir personel satırı seçin.');
            }
        });
    }

    // Toplu Banka Uygula
    const btnUygulaBanka = document.getElementById('btnUygulaBanka');
    if (btnUygulaBanka) {
        btnUygulaBanka.addEventListener('click', function() {
            const rawVal = document.getElementById('topluBanka').value;
            const parsed = parseMoneyLocal(rawVal);
            const formatted = (parsed > 0) ? (parsed.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })) : '0,00';
            
            let count = 0;
            document.querySelectorAll('#avansTablosu tbody tr.avans-row').forEach(tr => {
                const c = tr.querySelector('.row-check');
                if (c && c.checked) {
                    const inp = tr.querySelector('.row-banka');
                    if (inp) {
                        inp.value = formatted;
                        updateRowTotal(tr);
                        count++;
                    }
                }
            });
            recalculateSummary();
            if (count === 0) {
                alert('Banka tutarını uygulamak için lütfen en az bir personel satırı seçin.');
            }
        });
    }

    // Toplu Nakit Uygula
    const btnUygulaNakit = document.getElementById('btnUygulaNakit');
    if (btnUygulaNakit) {
        btnUygulaNakit.addEventListener('click', function() {
            const rawVal = document.getElementById('topluNakit').value;
            const parsed = parseMoneyLocal(rawVal);
            const formatted = (parsed > 0) ? (parsed.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })) : '0,00';
            
            let count = 0;
            document.querySelectorAll('#avansTablosu tbody tr.avans-row').forEach(tr => {
                const c = tr.querySelector('.row-check');
                if (c && c.checked) {
                    const inp = tr.querySelector('.row-nakit');
                    if (inp) {
                        inp.value = formatted;
                        updateRowTotal(tr);
                        count++;
                    }
                }
            });
            recalculateSummary();
            if (count === 0) {
                alert('Nakit tutarını uygulamak için lütfen en az bir personel satırı seçin.');
            }
        });
    }

    // Toplu Açıklama Uygula
    const btnUygulaAciklama = document.getElementById('btnUygulaAciklama');
    if (btnUygulaAciklama) {
        btnUygulaAciklama.addEventListener('click', function() {
            const val = document.getElementById('topluAciklama').value.trim();
            let count = 0;
            document.querySelectorAll('#avansTablosu tbody tr.avans-row').forEach(tr => {
                const c = tr.querySelector('.row-check');
                if (c && c.checked) {
                    const inp = tr.querySelector('.row-aciklama');
                    if (inp) inp.value = val;
                    count++;
                }
            });
            if (count === 0) {
                alert('Açıklama uygulamak için lütfen en az bir personel satırı seçin.');
            }
        });
    }

    // Personel Arama Filtresi (Canlı tablo filtresi)
    const aramaInp = document.getElementById('personelArama');
    if (aramaInp) {
        aramaInp.addEventListener('input', function() {
            const q = this.value.trim().toLocaleLowerCase('tr');
            document.querySelectorAll('#avansTablosu tbody tr.avans-row').forEach(tr => {
                const text = tr.getAttribute('data-personel') || '';
                if (!q || text.indexOf(q) !== -1) {
                    tr.style.display = '';
                } else {
                    tr.style.display = 'none';
                }
            });
        });
    }

    // Ek Satır Ekleme Butonu (Aynı personele ikinci bir avans satırı açmak için)
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-ek-satir');
        if (!btn) return;

        const pid = btn.getAttribute('data-personel-id');
        const pAd = btn.getAttribute('data-personel-ad');
        const currentRow = btn.closest('tr');
        if (!currentRow || !pid) return;

        const newRow = document.createElement('tr');
        newRow.className = 'avans-row row-yeni table-warning';
        newRow.setAttribute('data-personel', pAd.toLocaleLowerCase('tr'));
        newRow.setAttribute('data-row-id', nextRowIndex);

        newRow.innerHTML = `
            <td class="text-center">
                <input type="checkbox" class="form-check-input row-check dahil" name="items[${nextRowIndex}][dahil]" value="1" checked>
                <input type="hidden" name="items[${nextRowIndex}][existing_id]" value="0">
                <input type="hidden" name="items[${nextRowIndex}][personel_id]" value="${pid}">
            </td>
            <td>
                <strong class="personel-ad">${pAd}</strong>
                <span class="badge bg-warning-subtle text-warning-emphasis small ms-1">+Ek Avans</span>
            </td>
            <td>
                <span class="badge bg-warning-subtle text-dark border">Yeni Ek</span>
            </td>
            <td>
                <input type="date" class="form-control form-control-sm row-tarih" name="items[${nextRowIndex}][tarih]" value="${defaultTarihStr}" required>
            </td>
            <td class="text-end">
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control form-control-sm text-end money-field row-banka" name="items[${nextRowIndex}][banka_tutari]" value="0,00">
                    <span class="input-group-text">₺</span>
                </div>
            </td>
            <td class="text-end">
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control form-control-sm text-end money-field row-nakit" name="items[${nextRowIndex}][nakit_tutari]" value="0,00">
                    <span class="input-group-text">₺</span>
                </div>
            </td>
            <td class="text-end">
                <span class="fw-semibold row-toplam-badge text-muted">0,00 ₺</span>
            </td>
            <td>
                <input type="text" class="form-control form-control-sm row-aciklama" name="items[${nextRowIndex}][aciklama]" value="" placeholder="Opsiyonel">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" title="Satırı kaldır" onclick="this.closest('tr').remove(); recalculateSummary();">
                    <i class="bi bi-x-lg"></i>
                </button>
            </td>
        `;

        currentRow.parentNode.insertBefore(newRow, currentRow.nextSibling);
        bindRowEvents(newRow);
        nextRowIndex++;
        recalculateSummary();
    });

    // Form submit doğrulaması
    const form = document.getElementById('topluAvansForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            let checkedCount = 0;
            document.querySelectorAll('#avansTablosu tbody tr.avans-row').forEach(tr => {
                const c = tr.querySelector('.row-check');
                if (c && c.checked) {
                    checkedCount++;
                }
            });

            if (checkedCount === 0) {
                e.preventDefault();
                alert('Lütfen kaydedilecek en az bir avans satırı seçin.');
                return false;
            }
        });
    }
});

// Tek avans silme
function silTekAvans(id) {
    if (confirm('Bu avans kaydını silmek istediğinize emin misiniz?')) {
        postDeleteForm('avans_islem.php', id);
    }
}
</script>

<?php include '../includes/footer.php'; ?>
