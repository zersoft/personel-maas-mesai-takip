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

$pageTitle = 'Kullanıcı Düzenle';

// Sadece admin
requireRole('admin');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: kullanici_yonetimi.php?error=Geçersiz ID');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header('Location: kullanici_yonetimi.php?error=Kullanıcı bulunamadı');
        exit;
    }
} catch(PDOException $e) {
    header('Location: kullanici_yonetimi.php?error=' . urlencode($e->getMessage()));
    exit;
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-pencil-square"></i> Kullanıcı Düzenle</h3>
    <a href="kullanici_yonetimi.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <div class="card-body">
        <form action="kullanici_islem.php" method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
            <?php echo csrfField(); ?>
            
            <div class="mb-3">
                <label class="form-label">Kullanıcı Adı</label>
                <input type="text" class="form-control" name="username" value="<?php echo escape($user['username']); ?>" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Şifre <small class="text-muted">(Değiştirmek istemiyorsanız boş bırakın)</small></label>
                <input type="password" class="form-control" name="password">
            </div>
            
            <div class="mb-3">
                <label class="form-label">Ad Soyad</label>
                <input type="text" class="form-control" name="ad_soyad" value="<?php echo escape($user['ad_soyad']); ?>" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">E-posta</label>
                <input type="email" class="form-control" name="email" value="<?php echo escape($user['email']); ?>">
            </div>
            
            <div class="mb-3">
                <label class="form-label">Rol</label>
                <select class="form-select" name="rol" required>
                    <option value="viewer" <?php echo $user['rol']==='viewer'?'selected':''; ?>>İzleyici</option>
                    <option value="user" <?php echo $user['rol']==='user'?'selected':''; ?>>Kullanıcı</option>
                    <option value="admin" <?php echo $user['rol']==='admin'?'selected':''; ?>>Yönetici</option>
                </select>
            </div>
            
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="aktif" value="1" <?php echo $user['aktif']?'checked':''; ?>>
                    <label class="form-check-label">Aktif</label>
                </div>
            </div>
            
            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Güncelle
                </button>
                <a href="kullanici_yonetimi.php" class="btn btn-secondary">İptal</a>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

