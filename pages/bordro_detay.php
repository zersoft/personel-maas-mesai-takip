<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$pageTitle = 'Bordro Detay';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: bordro.php?error=' . urlencode('Geçersiz bordro.'));
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT b.*, p.ad_soyad
        FROM bordro b LEFT JOIN personel_listesi p ON b.personel_id=p.id WHERE b.id=?");
    $stmt->execute([$id]);
    $b = $stmt->fetch();
    if (!$b) { header('Location: bordro.php?error=' . urlencode('Bordro bulunamadı.')); exit; }

    // Bazlar
    $brut = (float)($b['brut_maas'] ?? 0);
    $banka_baz = (float)($b['sgk_banka'] ?? 0);
    $nakit_baz = $brut - $banka_baz;
    $ekoB = (float)($b['ek_odenek_banka'] ?? 0);
    $ekoN = (float)($b['ek_odenek_nakit'] ?? 0);
    $izinK = (float)($b['izin_kesintisi'] ?? 0);
    $sgkK = (float)($b['sgk_kesintisi'] ?? 0);
    $digerK = (float)($b['diger_kesintiler'] ?? 0);
    $avB = (float)($b['banka_avans'] ?? 0);
    $avN = (float)($b['nakit_avans'] ?? 0);

    $kesintiTop = $izinK + $sgkK + $digerK; // avans hariç
    $nakit_after_kesinti = max($nakit_baz - $kesintiTop, 0);
    $banka_after_kesinti = max($banka_baz - max($kesintiTop - $nakit_baz, 0), 0);
    $nakit_net = max($nakit_after_kesinti - $avN, 0) + $ekoN;
    $banka_net = max($banka_after_kesinti - $avB, 0) + $ekoB;
    $toplam_odenecek = $nakit_net + $banka_net;
} catch (PDOException $e) {
    header('Location: bordro.php?error=' . urlencode('Veri okunamadı.'));
    exit;
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-receipt"></i> Bordro Detay</h1>
    <a href="bordro.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Geri</a>
    </div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="form-control"><strong>Personel:</strong> <?php echo escape($b['ad_soyad']); ?></div>
            </div>
            <div class="col-md-2"><div class="form-control"><strong>Ay:</strong> <?php echo getTurkishMonthName((int)$b['ay']); ?></div></div>
            <div class="col-md-2"><div class="form-control"><strong>Yıl:</strong> <?php echo (int)$b['yil']; ?></div></div>
            <div class="col-md-4"><div class="form-control"><strong>Brüt Maaş:</strong> <span class="money"><?php echo formatMoney($brut); ?></span></div></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Dağılım</div>
            <div class="card-body">
                <ul class="list-group">
                    <li class="list-group-item d-flex justify-content-between align-items-center">Banka (Net) <span class="money"><?php echo formatMoney($banka_net); ?></span></li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">Nakit (Net) <span class="money"><?php echo formatMoney($nakit_net); ?></span></li>
                    <li class="list-group-item d-flex justify-content-between align-items-center"><strong>Toplam Ödenecek</strong> <strong class="money"><?php echo formatMoney($toplam_odenecek); ?></strong></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Ayrıntılar</div>
            <div class="card-body">
                <ul class="list-group">
                    <li class="list-group-item d-flex justify-content-between align-items-center">Banka Baz <span class="money"><?php echo formatMoney($banka_baz); ?></span></li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">Nakit Baz <span class="money"><?php echo formatMoney($nakit_baz); ?></span></li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">Ek Ödenek (Banka) <span class="money"><?php echo formatMoney($ekoB); ?></span></li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">Ek Ödenek (Nakit) <span class="money"><?php echo formatMoney($ekoN); ?></span></li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">İzin Kesintisi <span class="money"><?php echo formatMoney($izinK); ?></span></li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">SGK Kesintisi <span class="money"><?php echo formatMoney($sgkK); ?></span></li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">Diğer Kesintiler <span class="money"><?php echo formatMoney($digerK); ?></span></li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">Avans (Banka) <span class="money"><?php echo formatMoney($avB); ?></span></li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">Avans (Nakit) <span class="money"><?php echo formatMoney($avN); ?></span></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>


