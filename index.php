<?php
error_reporting(0);
ini_set('display_errors', 0);

// Session başlat (header.php'den önce)
if (session_status() === PHP_SESSION_NONE) {
	$appSessionPath = __DIR__ . '/storage/sessions';
	if (!is_dir($appSessionPath)) {
		@mkdir($appSessionPath, 0700, true);
	}
	if (is_dir($appSessionPath) && is_writable($appSessionPath)) {
		ini_set('session.save_path', $appSessionPath);
	}
	// Cookie ayarları
	ini_set('session.cookie_lifetime', 0);
	ini_set('session.cookie_path', '/');
	ini_set('session.cookie_httponly', 1);
	ini_set('session.use_only_cookies', 1);
	@session_start();
}

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
    // Hata olsa bile devam et
    $personelSayisi = 0;
    $toplamBordro = 0;
    $toplamFazlaMesai = 0;
    $toplamAvans = 0;
    // Hatayı logla ama sayfayı göster
    error_log("Index.php DB Error: " . $e->getMessage());
}

try {
    include 'includes/header.php';
} catch(Exception $e) {
    die("Header yükleme hatası: " . $e->getMessage());
}
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4"><i class="bi bi-speedometer2"></i> Dashboard</h1>
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

<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Hızlı Erişim</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <a href="pages/personel_listesi.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-person-plus"></i> Personel Ekle
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="pages/bordro.php" class="btn btn-outline-success w-100">
                            <i class="bi bi-cash-stack"></i> Bordro Oluştur
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="pages/fazla_mesai.php" class="btn btn-outline-warning w-100">
                            <i class="bi bi-clock"></i> Fazla Mesai Kaydet
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="pages/raporlar.php" class="btn btn-outline-info w-100">
                            <i class="bi bi-file-earmark-bar-graph"></i> Raporlar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

