<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Bordro Yönetimi';

// Bordro listesi
try {
    $stmt = $pdo->query("SELECT b.*, p.ad_soyad,
                                (b.brut_maas - b.sgk_banka) as nakit,
                                (b.brut_maas + b.ek_odenek - COALESCE(b.izin_kesintisi, 0) - COALESCE(b.sgk_kesintisi, 0) - COALESCE(b.diger_kesintiler, 0)) as toplam_odenecek
                         FROM bordro b 
                         LEFT JOIN personel_listesi p ON b.personel_id = p.id 
                         ORDER BY b.ay DESC, b.yil DESC");
    $bordrolar = $stmt->fetchAll();
} catch (PDOException $e) {
    $bordrolar = [];
}

// Varsayılan dönem: son bordro (yoksa bugünün ay/yılı)
$defaultAy = (int)date('n');
$defaultYil = (int)date('Y');
try {
    $son = $pdo->query("SELECT yil, ay FROM bordro ORDER BY yil DESC, ay DESC LIMIT 1")->fetch();
    if ($son) {
        $defaultAy = (int)$son['ay'];
        $defaultYil = (int)$son['yil'];
    }
} catch (PDOException $e) {
}

// Mini özet toplamları (banka/nakit/toplam) – avans ve ek ödenek kanal mantığıyla
try {
    // Banka toplamı: banka baz − (kesintiden kalan) − banka avans + ek_odenek_banka
    $bankaToplamStmt = $pdo->prepare("SELECT SUM(
        GREATEST(
            b.sgk_banka - GREATEST((COALESCE(b.izin_kesintisi,0)+COALESCE(b.sgk_kesintisi,0)+COALESCE(b.diger_kesintiler,0)) - (b.brut_maas - b.sgk_banka), 0)
            - COALESCE(b.banka_avans, a.banka_avans, 0)
        , 0) + COALESCE(b.ek_odenek_banka,0)
    ) as toplam
    FROM bordro b
    LEFT JOIN (
        SELECT personel_id, SUM(banka_tutari) AS banka_avans, SUM(nakit_tutari) AS nakit_avans
        FROM avans_takip
        WHERE ( (bordro_ay = ? AND bordro_yil = ?) OR (bordro_ay IS NULL AND bordro_yil IS NULL AND MONTH(tarih) = ? AND YEAR(tarih) = ?) )
        GROUP BY personel_id
    ) a ON a.personel_id = b.personel_id
    WHERE b.ay = ? AND b.yil = ?");
    $bankaToplamStmt->execute([$defaultAy, $defaultYil, $defaultAy, $defaultYil, $defaultAy, $defaultYil]);
    $miniBanka = (float)($bankaToplamStmt->fetch()['toplam'] ?? 0);

    // Nakit toplamı: nakit baz − kesinti − nakit avans + ek_odenek_nakit
    $nakitToplamStmt = $pdo->prepare("SELECT SUM(
        GREATEST((b.brut_maas - b.sgk_banka) - (COALESCE(b.izin_kesintisi,0)+COALESCE(b.sgk_kesintisi,0)+COALESCE(b.diger_kesintiler,0))
        - COALESCE(b.nakit_avans, a.nakit_avans, 0), 0) + COALESCE(b.ek_odenek_nakit,0)
    ) as toplam
    FROM bordro b
    LEFT JOIN (
        SELECT personel_id, SUM(banka_tutari) AS banka_avans, SUM(nakit_tutari) AS nakit_avans
        FROM avans_takip
        WHERE ( (bordro_ay = ? AND bordro_yil = ?) OR (bordro_ay IS NULL AND bordro_yil IS NULL AND MONTH(tarih) = ? AND YEAR(tarih) = ?) )
        GROUP BY personel_id
    ) a ON a.personel_id = b.personel_id
    WHERE b.ay = ? AND b.yil = ?");
    $nakitToplamStmt->execute([$defaultAy, $defaultYil, $defaultAy, $defaultYil, $defaultAy, $defaultYil]);
    $miniNakit = (float)($nakitToplamStmt->fetch()['toplam'] ?? 0);

    // Toplam ödenecek: (brüt − kesintiler) + ek_odenek(banka+nakit)
    $toplamStmt = $pdo->prepare("SELECT SUM(
        GREATEST(b.brut_maas - (COALESCE(b.izin_kesintisi,0)+COALESCE(b.sgk_kesintisi,0)+COALESCE(b.diger_kesintiler,0)), 0)
        + COALESCE(b.ek_odenek_banka,0) + COALESCE(b.ek_odenek_nakit,0)
    ) as toplam FROM bordro b WHERE b.ay = ? AND b.yil = ?");
    $toplamStmt->execute([$defaultAy, $defaultYil]);
    $miniToplam = (float)($toplamStmt->fetch()['toplam'] ?? 0);
} catch (PDOException $e) {
    $miniBanka = 0;
    $miniNakit = 0;
    $miniToplam = 0;
}

include '../includes/header.php';

// Mesaj gösterimi
if (isset($_GET['success'])) {
    echo showMessage('Bordro başarıyla kaydedildi!', 'success');
}
if (isset($_GET['error'])) {
    echo showMessage('Hata: ' . escape($_GET['error']), 'danger');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-cash-coin"></i> Bordro Yönetimi</h1>
    <div class="d-flex align-items-center gap-2">
        <a href="toplu_bordro.php" class="btn btn-success me-2">
            <i class="bi bi-file-earmark-spreadsheet"></i> Toplu Bordro Oluştur
        </a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bordroEkleModal">
            <i class="bi bi-plus-circle"></i> Yeni Bordro Oluştur
        </button>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-2">
        <select class="form-select" id="ayFiltre">
            <option value="">Tüm Aylar</option>
            <?php for ($i = 1; $i <= 12; $i++): ?>
                <option value="<?php echo $i; ?>" <?php echo ($i == $defaultAy) ? 'selected' : ''; ?>><?php echo getTurkishMonthName($i); ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select class="form-select" id="yilFiltre">
            <option value="">Tüm Yıllar</option>
            <?php for ($yil = date('Y'); $yil >= date('Y') - 5; $yil--): ?>
                <option value="<?php echo $yil; ?>" <?php echo ($yil == $defaultYil) ? 'selected' : ''; ?>><?php echo $yil; ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div class="col-md-2">  
        <div class="form-control form-control-sm d-flex justify-content-between align-items-center">
            <span class="text-muted small">Ödeme Özeti</span>
            <span class="text-primary fw-semibold" id="miniOzetTxt"><?php echo getTurkishMonthName($defaultAy) . ' ' . $defaultYil; ?></span>
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-control form-control-sm d-flex justify-content-between align-items-center">
            <span class="text-muted small">Banka</span>
            <span class="text-success fw-semibold" id="miniBankaTxt"><?php echo formatMoney($miniBanka); ?></span>
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-control form-control-sm d-flex justify-content-between align-items-center">
            <span class="text-muted small">Nakit</span>
            <span class="text-warning fw-semibold" id="miniNakitTxt"><?php echo formatMoney($miniNakit); ?></span>
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-control form-control-sm d-flex justify-content-between align-items-center">
            <span class="text-muted small">Toplam</span>
            <span class="text-primary fw-semibold" id="miniToplamTxt"><?php echo formatMoney($miniToplam); ?></span>
        </div>
    </div>
</div>

<?php if (empty($bordrolar)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> Henüz bordro kaydı bulunmamaktadır.
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Personel</th>
                            <th>Ay</th>
                            <th>Yıl</th>
                            <th class="money">Brüt Maaş</th>
                            <th class="money">SGK/Banka</th>
                            <th class="money">Nakit</th>
                            <th class="money">Ek Ödenek</th>
                            <th class="money">Kesintiler</th>
                            <th class="money">Toplam Ödenecek</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Toplamları hesapla
                        $toplam_brut_maas = 0;
                        $toplam_sgk_banka = 0;
                        $toplam_nakit = 0;
                        $toplam_ek_odenek = 0;
                        $toplam_kesintiler = 0;
                        $toplam_odenecek = 0;

                        foreach ($bordrolar as $bordro):
                            $toplam_brut_maas += $bordro['brut_maas'];
                            $toplam_sgk_banka += $bordro['sgk_banka'];
                            $nakit_deger = $bordro['nakit'] ?? ($bordro['brut_maas'] - $bordro['sgk_banka']);
                            $toplam_nakit += $nakit_deger;
                            $toplam_ek_odenek += $bordro['ek_odenek'];
                            $toplamKesinti = ($bordro['izin_kesintisi'] ?? 0) + ($bordro['sgk_kesintisi'] ?? 0) + ($bordro['diger_kesintiler'] ?? 0);
                            $toplam_kesintiler += $toplamKesinti;
                            $toplam_odenecek_deger = $bordro['toplam_odenecek'] ?? ($bordro['toplam_odeme'] ?? 0);
                            $toplam_odenecek += $toplam_odenecek_deger;
                        ?>
                            <tr>
                                <td><?php echo escape($bordro['ad_soyad']); ?></td>
                                <td><?php echo getTurkishMonthName($bordro['ay']); ?></td>
                                <td><?php echo escape($bordro['yil']); ?></td>
                                <td class="money"><?php echo formatMoney($bordro['brut_maas']); ?></td>
                                <td class="money"><?php echo formatMoney($bordro['sgk_banka']); ?></td>
                                <td class="money"><?php echo formatMoney($nakit_deger); ?></td>
                                <td class="money"><?php echo formatMoney($bordro['ek_odenek']); ?></td>
                                <td class="money"><?php echo formatMoney($toplamKesinti); ?></td>
                                <td class="money"><?php echo formatMoney($toplam_odenecek_deger); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick="gosterBordro(<?php echo $bordro['id']; ?>)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-warning" onclick="duzenleBordro(<?php echo $bordro['id']; ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="silBordro(<?php echo $bordro['id']; ?>)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-primary">
                            <th colspan="3" class="text-end">TOPLAM:</th>
                            <th class="money"><?php echo formatMoney($toplam_brut_maas); ?></th>
                            <th class="money"><?php echo formatMoney($toplam_sgk_banka); ?></th>
                            <th class="money"><?php echo formatMoney($toplam_nakit); ?></th>
                            <th class="money"><?php echo formatMoney($toplam_ek_odenek); ?></th>
                            <th class="money"><?php echo formatMoney($toplam_kesintiler); ?></th>
                            <th class="money"><?php echo formatMoney($toplam_odenecek); ?></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Bordro Ekle Modal -->
<div class="modal fade" id="bordroEkleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Yeni Bordro Oluştur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="bordro_islem.php" method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Personel</label>
                            <select class="form-select" name="personel_id" id="personelSelect" required>
                                <option value="">Seçiniz...</option>
                                <?php
                                try {
                                    $personeller = $pdo->query("SELECT id, ad_soyad, maas, maas_sgk, mesai_saat_ucreti FROM personel_listesi WHERE aktif = 1 ORDER BY ad_soyad")->fetchAll();
                                    foreach ($personeller as $personel):
                                ?>
                                        <option value="<?php echo $personel['id']; ?>"
                                            data-maas="<?php echo $personel['maas']; ?>"
                                            data-maas-sgk="<?php echo $personel['maas_sgk']; ?>"
                                            data-mesai-ucreti="<?php echo $personel['mesai_saat_ucreti']; ?>">
                                            <?php echo escape($personel['ad_soyad']); ?>
                                        </option>
                                <?php endforeach;
                                } catch (PDOException $e) {
                                } ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Ay</label>
                            <select class="form-select" name="ay" required>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $i == date('n') ? 'selected' : ''; ?>>
                                        <?php echo getTurkishMonthName($i); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Yıl</label>
                            <input type="number" class="form-control" name="yil" value="<?php echo date('Y'); ?>" min="2020" max="<?php echo date('Y') + 1; ?>" required>
                        </div>
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                            <label class="form-label">Brüt Maaş (₺)</label>
                            <div class="input-group">
                                <input type="text" class="form-control money-field" name="brut_maas" id="brutMaas" value="0" pattern="[0-9.,]+" required>
                                <span class="input-group-text">₺</span>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                            <label class="form-label">SGK/Banka (₺)</label>
                            <div class="input-group">
                                <input type="text" class="form-control money-field" name="sgk_banka" id="sgkBanka" value="0" pattern="[0-9.,]+" required>
                                <span class="input-group-text">₺</span>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                            <label class="form-label">Nakit (₺)</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="nakitGoster" value="0,00 ₺" readonly style="background-color: #e9ecef;">
                                <span class="input-group-text">₺</span>
                            </div>
                            <small class="text-muted">Brüt Maaş - SGK/Banka (Otomatik hesaplanır)</small>
                        </div>
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                            <label class="form-label">Bu Dönem Avans (Banka/Nakit)</label>
                            <div class="form-control" id="avansInfo" style="background:#f8f9fa;">-</div>
                        </div>
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                            <label class="form-label">Ek Ödenek (Banka) (₺)</label>
                            <div class="input-group">
                                <input type="text" class="form-control money-field" name="ek_odenek_banka" value="0" pattern="[0-9.,]+">
                                <span class="input-group-text">₺</span>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                            <label class="form-label">Ek Ödenek (Nakit) (₺)</label>
                            <div class="input-group">
                                <input type="text" class="form-control money-field" name="ek_odenek_nakit" value="0" pattern="[0-9.,]+">
                                <span class="input-group-text">₺</span>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">İzin Günü</label>
                            <input type="number" step="0.5" class="form-control" name="izin_gunu" value="0">
                        </div>
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                            <label class="form-label">İzin Kesintisi (₺)</label>
                            <div class="input-group">
                                <input type="text" class="form-control money-field" name="izin_kesintisi" value="0" pattern="[0-9.,]+">
                                <span class="input-group-text">₺</span>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                            <label class="form-label">SGK Kesintisi (₺)</label>
                            <div class="input-group">
                                <input type="text" class="form-control money-field" name="sgk_kesintisi" value="0" pattern="[0-9.,]+">
                                <span class="input-group-text">₺</span>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                            <label class="form-label">Diğer Kesintiler (₺)</label>
                            <div class="input-group">
                                <input type="text" class="form-control money-field" name="diger_kesintiler" value="0" pattern="[0-9.,]+">
                                <span class="input-group-text">₺</span>
                            </div>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Kesinti Açıklaması</label>
                            <textarea class="form-control" name="kesinti_aciklama" rows="2"></textarea>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Açıklama</label>
                            <textarea class="form-control" name="aciklama" rows="3"></textarea>
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

<script>
    // Bordro modalı açıldığında personel seçildiğinde maaş bilgilerini otomatik doldur
    document.addEventListener('DOMContentLoaded', function() {
        // Liste filtresi ile mini özetin senkronu
        const ayFiltre = document.getElementById('ayFiltre');
        const yilFiltre = document.getElementById('yilFiltre');

        function guncelleMiniOzet() {
            if (!ayFiltre || !yilFiltre) return;
            const ay = ayFiltre.value || '<?php echo $defaultAy; ?>';
            const yil = yilFiltre.value || '<?php echo $defaultYil; ?>';
            fetch(`odeme_ozet_api.php?ay=${ay}&yil=${yil}`)
                .then(r => r.json())
                .then(d => {
                    const fmt = v => (v || 0).toLocaleString('tr-TR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }) + ' ₺';
                    const bEl = document.getElementById('miniBankaTxt');
                    const nEl = document.getElementById('miniNakitTxt');
                    const tEl = document.getElementById('miniToplamTxt');
                    if (bEl) bEl.textContent = fmt(d.banka).replace(' ₺', '');
                    if (nEl) nEl.textContent = fmt(d.nakit).replace(' ₺', '');
                    if (tEl) tEl.textContent = fmt(d.toplam).replace(' ₺', '');
                    const donem = document.getElementById('ozetDonem');
                    if (donem) donem.textContent = 'Ödeme Özeti - ' + getAyAdi(parseInt(ay)) + ' ' + yil;
                })
                .catch(() => {});
        }

        function getAyAdi(i) {
            const adlar = ['', 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
            return adlar[i] || i;
        }
        if (ayFiltre) ayFiltre.addEventListener('change', guncelleMiniOzet);
        if (yilFiltre) yilFiltre.addEventListener('change', guncelleMiniOzet);
        // İlk açılışta güncelle
        guncelleMiniOzet();
        const bordroModal = document.getElementById('bordroEkleModal');

        if (bordroModal) {
            bordroModal.addEventListener('shown.bs.modal', function() {
                const personelSelect = document.getElementById('personelSelect');
                const brutMaasInput = document.getElementById('brutMaas');
                const sgkBankaInput = document.getElementById('sgkBanka');

                if (personelSelect && brutMaasInput && sgkBankaInput) {
                    // Para formatlama fonksiyonu
                    function formatMoneyInput(value) {
                        if (!value || value === '0' || value === '') return '0';
                        const parts = value.toString().split('.');
                        const integerPart = parts[0].replace(/\D/g, '');
                        const decimalPart = parts[1] || '';
                        const formattedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                        return decimalPart ? formattedInteger + ',' + decimalPart : formattedInteger;
                    }

                    // Para parse fonksiyonu
                    function parseMoneyValue(value) {
                        if (!value || value === '0' || value === '') return 0;
                        let val = value.toString().trim();
                        val = val.replace(/\./g, '');
                        val = val.replace(',', '.');
                        val = val.replace(/[^0-9.]/g, '');
                        const parts = val.split('.');
                        if (parts.length > 2) {
                            val = parts[0] + '.' + parts.slice(1).join('');
                        }
                        return parseFloat(val) || 0;
                    }

                    // Nakit hesaplama fonksiyonu
                    function hesaplaNakit() {
                        const brutMaas = parseMoneyValue(brutMaasInput.value);
                        const sgkBanka = parseMoneyValue(sgkBankaInput.value);
                        const nakit = brutMaas - sgkBanka;
                        const nakitGoster = document.getElementById('nakitGoster');
                        if (nakitGoster) {
                            nakitGoster.value = formatMoneyInput(nakit.toString()) + ' ₺';
                        }
                    }
                    // Avans bilgisini getir ve göster
                    function guncelleAvansInfo() {
                        const personelId = personelSelect.value;
                        const aySel = document.querySelector('select[name="ay"]').value;
                        const yilSel = document.querySelector('input[name="yil"]').value;
                        const avansInfo = document.getElementById('avansInfo');
                        if (!personelId || !aySel || !yilSel || !avansInfo) return;
                        fetch(`avans_ozet_api.php?personel_id=${personelId}&ay=${aySel}&yil=${yilSel}`)
                            .then(r => r.json())
                            .then(d => {
                                const b = (d && d.banka) ? d.banka : 0;
                                const n = (d && d.nakit) ? d.nakit : 0;
                                avansInfo.textContent = `Banka: ${b.toLocaleString('tr-TR',{minimumFractionDigits:2, maximumFractionDigits:2})} ₺, Nakit: ${n.toLocaleString('tr-TR',{minimumFractionDigits:2, maximumFractionDigits:2})} ₺`;
                            })
                            .catch(() => {
                                if (avansInfo) avansInfo.textContent = '-';
                            });
                    }

                    // İlk yüklemede varsayılan değerleri ayarla
                    const selectedOption = personelSelect.options[personelSelect.selectedIndex];
                    if (selectedOption && selectedOption.value) {
                        const maas = selectedOption.getAttribute('data-maas');
                        const maasSgk = selectedOption.getAttribute('data-maas-sgk');

                        if (maas && maas !== '0') {
                            brutMaasInput.value = formatMoneyInput(maas);
                        }
                        if (maasSgk && maasSgk !== '0') {
                            sgkBankaInput.value = formatMoneyInput(maasSgk);
                        }
                        hesaplaNakit();
                        guncelleAvansInfo();
                    }

                    // Personel değiştiğinde maaş bilgilerini güncelle
                    personelSelect.addEventListener('change', function() {
                        const selectedOption = this.options[this.selectedIndex];
                        if (selectedOption && selectedOption.value) {
                            const maas = selectedOption.getAttribute('data-maas');
                            const maasSgk = selectedOption.getAttribute('data-maas-sgk');

                            if (maas && maas !== '0') {
                                brutMaasInput.value = formatMoneyInput(maas);
                            } else {
                                brutMaasInput.value = '0';
                            }

                            if (maasSgk && maasSgk !== '0') {
                                sgkBankaInput.value = formatMoneyInput(maasSgk);
                            } else {
                                sgkBankaInput.value = '0';
                            }
                        } else {
                            brutMaasInput.value = '0';
                            sgkBankaInput.value = '0';
                        }
                        hesaplaNakit();
                        guncelleAvansInfo();
                    });

                    // Brüt maaş veya SGK/Banka değiştiğinde nakit'i güncelle
                    brutMaasInput.addEventListener('blur', hesaplaNakit);
                    brutMaasInput.addEventListener('input', hesaplaNakit);
                    sgkBankaInput.addEventListener('blur', hesaplaNakit);
                    sgkBankaInput.addEventListener('input', hesaplaNakit);

                    // Ay / Yıl değişince avans bilgisini güncelle
                    const aySelEl = document.querySelector('select[name="ay"]');
                    const yilInputEl = document.querySelector('input[name="yil"]');
                    if (aySelEl) aySelEl.addEventListener('change', guncelleAvansInfo);
                    if (yilInputEl) yilInputEl.addEventListener('input', guncelleAvansInfo);
                }
            });

            // Modal kapandığında formu temizle
            bordroModal.addEventListener('hidden.bs.modal', function() {
                const form = bordroModal.querySelector('form');
                if (form) {
                    form.reset();
                    // Varsayılan değerleri geri yükle
                    const yilInput = form.querySelector('input[name="yil"]');
                    if (yilInput) {
                        yilInput.value = '<?php echo date("Y"); ?>';
                    }
                    const aySelect = form.querySelector('select[name="ay"]');
                    if (aySelect) {
                        aySelect.value = '<?php echo date("n"); ?>';
                    }
                    const avansInfo = document.getElementById('avansInfo');
                    if (avansInfo) avansInfo.textContent = '-';
                }
            });
        }
    });
</script>

<?php include '../includes/footer.php'; ?>