<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Auth'u atla
define('SKIP_AUTH', true);

// Session başlat
$sessionPath = sys_get_temp_dir() . '/php_sessions';
if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0777, true);
}
if (is_dir($sessionPath) && is_writable($sessionPath)) {
    session_save_path($sessionPath);
}
@session_start();

// Fake session (test için)
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['ad_soyad'] = 'Test User';
$_SESSION['rol'] = 'admin';

require_once 'config/db.php';
require_once 'includes/functions.php';

$pageTitle = 'Ana Sayfa - Dashboard';

// İstatistikler için sorgular
try {
    $personelSayisi = $pdo->query("SELECT COUNT(*) as sayi FROM personel_listesi WHERE aktif = 1")->fetch()['sayi'];
    $toplamBordro = $pdo->query("SELECT COUNT(*) as sayi FROM bordro")->fetch()['sayi'];
    $toplamFazlaMesai = $pdo->query("SELECT SUM(saat) as toplam FROM fazla_mesai")->fetch()['toplam'] ?? 0;
    $toplamAvans = $pdo->query("SELECT SUM(avans_tutari) as toplam FROM avans_takip")->fetch()['toplam'] ?? 0;
} catch(PDOException $e) {
    $personelSayisi = 0;
    $toplamBordro = 0;
    $toplamFazlaMesai = 0;
    $toplamAvans = 0;
    error_log("Index.php DB Error: " . $e->getMessage());
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4"><i class="bi bi-speedometer2"></i> Dashboard (No Auth Test)</h1>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Toplam Personel</h6>
                        <h2 class="mb-0"><?php echo $personelSayisi; ?></h2>
                    </div>
                    <i class="bi bi-people-fill fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Toplam Bordro</h6>
                        <h2 class="mb-0"><?php echo $toplamBordro; ?></h2>
                    </div>
                    <i class="bi bi-cash-coin fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Fazla Mesai Saati</h6>
                        <h2 class="mb-0"><?php echo number_format($toplamFazlaMesai, 1); ?></h2>
                    </div>
                    <i class="bi bi-clock-history fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Toplam Avans</h6>
                        <h2 class="mb-0"><?php echo formatMoney($toplamAvans); ?></h2>
                    </div>
                    <i class="bi bi-wallet2 fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-warning mt-4">
    <strong>Not:</strong> Bu auth kontrolsüz test sayfasıdır. Normal index: <a href="index.php">index.php</a>
</div>

<?php include 'includes/footer.php'; ?>

