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

// Kantar özeti: sevk sayısı/tonaj, perakende satış, tahsilat (tarih filtresi)
$kantarPeriyot = isset($_GET['kantar_periyot']) ? $_GET['kantar_periyot'] : 'bugun';
$allowedPeriyot = ['dun', 'bugun', 'gecen_hafta', 'bu_hafta', 'gecen_ay', 'bu_ay', 'gecen_yil', 'bu_yil'];
if (!in_array($kantarPeriyot, $allowedPeriyot, true)) {
    $kantarPeriyot = 'bugun';
}
$today = date('Y-m-d');
switch ($kantarPeriyot) {
    case 'dun':
        $kantarBas = $kantarBit = date('Y-m-d', strtotime('-1 day'));
        break;
    case 'bugun':
        $kantarBas = $kantarBit = $today;
        break;
    case 'gecen_hafta':
        $kantarBas = date('Y-m-d', strtotime('monday last week'));
        $kantarBit = date('Y-m-d', strtotime('sunday last week'));
        break;
    case 'bu_hafta':
        $kantarBas = date('Y-m-d', strtotime('monday this week'));
        $kantarBit = $today;
        break;
    case 'gecen_ay':
        $kantarBas = date('Y-m-01', strtotime('first day of last month'));
        $kantarBit = date('Y-m-t', strtotime('last day of last month'));
        break;
    case 'bu_ay':
        $kantarBas = date('Y-m-01');
        $kantarBit = $today;
        break;
    case 'gecen_yil':
        $kantarBas = date('Y-01-01', strtotime('first day of last year'));
        $kantarBit = date('Y-12-31', strtotime('last day of last year'));
        break;
    case 'bu_yil':
        $kantarBas = date('Y-01-01');
        $kantarBit = $today;
        break;
    default:
        $kantarBas = $kantarBit = $today;
}
// tarih alanı 8 (YYYYMMDD) veya 14 (YYYYMMDDhhmmss) haneli; sadece tarih kısmına göre filtrele ki günler kesişmesin
$tarihBas = str_replace('-', '', $kantarBas);
$tarihBit = str_replace('-', '', $kantarBit);

$sevkSayisi = 0;
$sevkTonaj = 0;
$perakendeSatisTutar = 0;
$tahsilatTutar = 0;
$kantarHata = null;
if (isset($pdoReport) && $pdoReport) {
    try {
        $stmt = $pdoReport->prepare("
            SELECT
                COUNT(CASE WHEN islemTipi = 'GELİR TAHAKKUK' THEN 1 END) AS sevk_sayisi,
                COALESCE(SUM(CASE WHEN islemTipi = 'GELİR TAHAKKUK' THEN COALESCE(dokumNetKg,0) ELSE 0 END), 0) AS tonaj,
                COALESCE(SUM(CASE WHEN islemTipi = 'GELİR TAHAKKUK' THEN COALESCE(genelTutar,0) ELSE 0 END), 0) AS satis_tutar,
                COALESCE(SUM(CASE WHEN islemTipi = 'GELİR TAHSİLAT' THEN COALESCE(genelTutar,0) ELSE 0 END), 0) AS tahsilat_tutar
            FROM SahadanSatis
            WHERE status = 1 AND LEFT(CAST(tarih AS CHAR), 8) BETWEEN ? AND ?
        ");
        $stmt->execute([$tarihBas, $tarihBit]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $sevkSayisi = (int)($row['sevk_sayisi'] ?? 0);
        $sevkTonaj = (float)($row['tonaj'] ?? 0);
        $perakendeSatisTutar = (float)($row['satis_tutar'] ?? 0);
        $tahsilatTutar = (float)($row['tahsilat_tutar'] ?? 0);
    } catch (PDOException $e) {
        $kantarHata = $e->getMessage();
        error_log("Index kantar stats: " . $kantarHata);
    }
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

<!-- Kantar özeti: sevk, perakende satış, tahsilat -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <h5 class="mb-0"><i class="bi bi-graph-up"></i> Kantar Özeti</h5>
                <form method="get" action="" class="d-inline" id="kantar-periyot-form">
                    <div class="btn-group btn-group-sm" role="group">
                        <?php
                        $periyotEtiket = [
                            'dun' => 'Dün',
                            'bugun' => 'Bugün',
                            'gecen_hafta' => 'Geçen Hafta',
                            'bu_hafta' => 'Bu Hafta',
                            'gecen_ay' => 'Geçen Ay',
                            'bu_ay' => 'Bu Ay',
                            'gecen_yil' => 'Geçen Yıl',
                            'bu_yil' => 'Bu Yıl',
                        ];
                        foreach ($periyotEtiket as $p => $label):
                            $active = ($kantarPeriyot === $p) ? ' active' : '';
                        ?>
                        <button type="submit" name="kantar_periyot" value="<?php echo htmlspecialchars($p); ?>" class="btn btn-outline-secondary<?php echo $active; ?>"><?php echo htmlspecialchars($label); ?></button>
                        <?php endforeach; ?>
                    </div>
                </form>
            </div>
            <div class="card-body">
                <?php if ($kantarHata): ?>
                <p class="text-danger mb-0"><i class="bi bi-exclamation-triangle"></i> Kantar verisi yüklenemedi.</p>
                <?php elseif (!isset($pdoReport) || !$pdoReport): ?>
                <p class="text-muted mb-0">Raporlama veritabanı bağlı değil; kantardan özet gösterilemiyor.</p>
                <?php else: ?>
                <p class="text-muted small mb-3">Dönem: <strong><?php echo htmlspecialchars($kantarBas); ?></strong> – <strong><?php echo htmlspecialchars($kantarBit); ?></strong></p>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="card bg-light border-0 h-100">
                            <div class="card-body">
                                <h6 class="card-title text-secondary"><i class="bi bi-truck"></i> Toplam Sevk (Sayı / Tonaj)</h6>
                                <div class="d-flex justify-content-between align-items-baseline flex-wrap gap-1">
                                    <span class="fs-4 fw-bold"><?php echo number_format($sevkSayisi, 0, ',', '.'); ?></span>
                                    <span class="text-muted small">adet</span>
                                </div>
                                <div class="mt-1 text-muted">
                                    <span class="fw-semibold"><?php echo number_format($sevkTonaj, 0, ',', '.'); ?></span> kg (tonaj)
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light border-0 h-100">
                            <div class="card-body">
                                <h6 class="card-title text-secondary"><i class="bi bi-cart-check"></i> Toplam Perakende Satış</h6>
                                <h4 class="mb-0"><?php echo formatMoney(abs($perakendeSatisTutar)); ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light border-0 h-100">
                            <div class="card-body">
                                <h6 class="card-title text-secondary"><i class="bi bi-cash-stack"></i> Toplam Tahsilat</h6>
                                <h4 class="mb-0"><?php echo formatMoney($tahsilatTutar); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
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

