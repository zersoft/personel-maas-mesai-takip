<?php
// Session başlat
if (session_status() === PHP_SESSION_NONE) {
	$appSessionPath = __DIR__ . '/../storage/sessions';
	if (!is_dir($appSessionPath)) {
		@mkdir($appSessionPath, 0700, true);
	}
	if (is_dir($appSessionPath) && is_writable($appSessionPath)) {
		ini_set('session.save_path', $appSessionPath);
	}
	ini_set('session.cookie_lifetime', 0);
	ini_set('session.cookie_path', '/');
	ini_set('session.cookie_httponly', 1);
	ini_set('session.use_only_cookies', 1);
	@session_start();
}

require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

$pageTitle = 'Kullanıcı Log Kayıtları';

// Sadece admin erişebilir
requireRole('admin');

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($user_id <= 0) {
    safeRedirect('kullanici_yonetimi.php?error=' . urlencode('Geçersiz kullanıcı ID'));
}

// Kullanıcı bilgilerini al
try {
    $userStmt = $pdo->prepare("SELECT id, username, ad_soyad FROM users WHERE id = ?");
    $userStmt->execute([$user_id]);
    $user = $userStmt->fetch();
    
    if (!$user) {
        safeRedirect('kullanici_yonetimi.php?error=' . urlencode('Kullanıcı bulunamadı'));
    }
} catch(PDOException $e) {
    safeRedirect('kullanici_yonetimi.php?error=' . urlencode('Veritabanı hatası'));
}

// Filtreler
$tablo_filtre = $_GET['tablo'] ?? '';
$islem_filtre = $_GET['islem'] ?? '';
$baslangic = $_GET['baslangic'] ?? '';
$bitis = $_GET['bitis'] ?? '';

// Log kayıtlarını al
try {
    $sql = "SELECT ul.*, u.username, u.ad_soyad 
            FROM user_logs ul
            LEFT JOIN users u ON ul.user_id = u.id
            WHERE ul.user_id = ?";
    $params = [$user_id];
    
    if ($tablo_filtre) {
        $sql .= " AND ul.tablo_adi = ?";
        $params[] = $tablo_filtre;
    }
    
    if ($islem_filtre) {
        $sql .= " AND ul.islem_tipi = ?";
        $params[] = $islem_filtre;
    }
    
    if ($baslangic) {
        $sql .= " AND DATE(ul.created_at) >= ?";
        $params[] = $baslangic;
    }
    
    if ($bitis) {
        $sql .= " AND DATE(ul.created_at) <= ?";
        $params[] = $bitis;
    }
    
    $sql .= " ORDER BY ul.created_at DESC LIMIT 500";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    // Tablo listesi (filtre için)
    $tabloStmt = $pdo->prepare("SELECT DISTINCT tablo_adi FROM user_logs WHERE user_id = ? ORDER BY tablo_adi");
    $tabloStmt->execute([$user_id]);
    $tabloListesi = $tabloStmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch(PDOException $e) {
    $logs = [];
    $tabloListesi = [];
}

include '../includes/header.php';

if (isset($_GET['success'])) {
    echo showMessage('İşlem başarıyla tamamlandı', 'success');
}
if (isset($_GET['error'])) {
    echo showMessage('Hata: ' . escape($_GET['error']), 'danger');
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">
        <i class="bi bi-journal-text"></i> Kullanıcı Log Kayıtları
    </h3>
    <a href="kullanici_yonetimi.php" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<!-- Kullanıcı Bilgisi -->
<div class="card mb-3">
    <div class="card-body">
        <h5 class="card-title mb-0">
            <i class="bi bi-person-circle"></i> <?php echo escape($user['ad_soyad']); ?>
            <small class="text-muted">(@<?php echo escape($user['username']); ?>)</small>
        </h5>
    </div>
</div>

<!-- Filtreler -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
            
            <div class="col-md-3">
                <label class="form-label">Tablo</label>
                <select class="form-select form-select-sm" name="tablo">
                    <option value="">Tümü</option>
                    <?php foreach($tabloListesi as $tablo): ?>
                        <option value="<?php echo escape($tablo); ?>" <?php echo $tablo_filtre === $tablo ? 'selected' : ''; ?>>
                            <?php echo escape($tablo); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">İşlem Tipi</label>
                <select class="form-select form-select-sm" name="islem">
                    <option value="">Tümü</option>
                    <option value="INSERT" <?php echo $islem_filtre === 'INSERT' ? 'selected' : ''; ?>>INSERT</option>
                    <option value="UPDATE" <?php echo $islem_filtre === 'UPDATE' ? 'selected' : ''; ?>>UPDATE</option>
                    <option value="DELETE" <?php echo $islem_filtre === 'DELETE' ? 'selected' : ''; ?>>DELETE</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Başlangıç Tarihi</label>
                <input type="date" class="form-control form-control-sm" name="baslangic" value="<?php echo escape($baslangic); ?>">
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Bitiş Tarihi</label>
                <input type="date" class="form-control form-control-sm" name="bitis" value="<?php echo escape($bitis); ?>">
            </div>
            
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-funnel"></i> Filtrele
                </button>
            </div>
        </form>
        
        <?php if ($tablo_filtre || $islem_filtre || $baslangic || $bitis): ?>
            <div class="mt-2">
                <a href="kullanici_loglari.php?user_id=<?php echo $user_id; ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-circle"></i> Filtreleri Temizle
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Log Listesi -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-sm">
                <thead>
                    <tr>
                        <th>Tarih/Saat</th>
                        <th>İşlem</th>
                        <th>Tablo</th>
                        <th>Kayıt ID</th>
                        <th>Açıklama</th>
                        <th>IP Adresi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="bi bi-inbox"></i> Log kaydı bulunamadı.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($logs as $log): ?>
                            <tr>
                                <td>
                                    <small><?php echo date('d.m.Y H:i:s', strtotime($log['created_at'])); ?></small>
                                </td>
                                <td>
                                    <?php
                                    $islemBadge = [
                                        'INSERT' => 'success',
                                        'UPDATE' => 'warning',
                                        'DELETE' => 'danger'
                                    ];
                                    $badgeClass = $islemBadge[$log['islem_tipi']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $badgeClass; ?>">
                                        <?php echo escape($log['islem_tipi']); ?>
                                    </span>
                                </td>
                                <td>
                                    <code><?php echo escape($log['tablo_adi']); ?></code>
                                </td>
                                <td>
                                    <?php echo $log['kayit_id'] ? escape($log['kayit_id']) : '<span class="text-muted">-</span>'; ?>
                                </td>
                                <td>
                                    <?php echo $log['aciklama'] ? escape($log['aciklama']) : '<span class="text-muted">-</span>'; ?>
                                </td>
                                <td>
                                    <small class="text-muted"><?php echo escape($log['ip_adresi'] ?? '-'); ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($logs)): ?>
                <tfoot>
                    <tr>
                        <td colspan="6" class="text-muted text-end">
                            <small>Toplam <?php echo count($logs); ?> kayıt gösteriliyor</small>
                        </td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

