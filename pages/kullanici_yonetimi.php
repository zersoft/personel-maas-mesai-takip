<?php
// Session başlat
if (session_status() === PHP_SESSION_NONE) {
	$appSessionPath = __DIR__ . '/../storage/sessions';
	if (!is_dir($appSessionPath)) {
		@mkdir($appSessionPath, 0777, true);
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

$pageTitle = 'Kullanıcı Yönetimi';

// Sadece admin erişebilir
requireRole('admin');

// Kullanıcı listesi
try {
    $users = $pdo->query("SELECT * FROM users ORDER BY ad_soyad")->fetchAll();
} catch(PDOException $e) {
    $users = [];
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
    <h3 class="mb-0"><i class="bi bi-people"></i> Kullanıcı Yönetimi</h3>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#kullaniciEkleModal">
        <i class="bi bi-person-plus"></i> Yeni Kullanıcı
    </button>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Kullanıcı Adı</th>
                        <th>Ad Soyad</th>
                        <th>E-posta</th>
                        <th>Rol</th>
                        <th>Durum</th>
                        <th>Son Giriş</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $u): ?>
                        <tr>
                            <td><?php echo escape($u['username']); ?></td>
                            <td><?php echo escape($u['ad_soyad']); ?></td>
                            <td><?php echo escape($u['email']); ?></td>
                            <td>
                                <?php 
                                $rolBadge = ['admin' => 'danger', 'user' => 'primary', 'viewer' => 'secondary'];
                                $rolAd = ['admin' => 'Yönetici', 'user' => 'Kullanıcı', 'viewer' => 'İzleyici'];
                                ?>
                                <span class="badge bg-<?php echo $rolBadge[$u['rol']] ?? 'secondary'; ?>">
                                    <?php echo $rolAd[$u['rol']] ?? $u['rol']; ?>
                                </span>
                            </td>
                            <td>
                                <?php if($u['aktif']): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Pasif</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $u['son_giris'] ? date('d.m.Y H:i', strtotime($u['son_giris'])) : '-'; ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="duzenleKullanici(<?php echo $u['id']; ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php if($u['id'] != $_SESSION['user_id']): ?>
                                    <button class="btn btn-sm btn-danger" onclick="silKullanici(<?php echo $u['id']; ?>)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Kullanıcı Ekle Modal -->
<div class="modal fade" id="kullaniciEkleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Yeni Kullanıcı Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="kullanici_islem.php" method="POST">
                <input type="hidden" name="action" value="insert">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Kullanıcı Adı</label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Şifre</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ad Soyad</label>
                        <input type="text" class="form-control" name="ad_soyad" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">E-posta</label>
                        <input type="email" class="form-control" name="email">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rol</label>
                        <select class="form-select" name="rol" required>
                            <option value="viewer">İzleyici (Sadece Görüntüleme)</option>
                            <option value="user" selected>Kullanıcı (Ekleme/Düzenleme)</option>
                            <option value="admin">Yönetici (Tam Yetki)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="aktif" value="1" checked>
                            <label class="form-check-label">Aktif</label>
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
function duzenleKullanici(id) {
    window.location.href = 'kullanici_duzenle.php?id=' + id;
}

function silKullanici(id) {
    if (confirm('Bu kullanıcıyı silmek istediğinize emin misiniz?')) {
        window.location.href = 'kullanici_islem.php?action=delete&id=' + id;
    }
}
</script>

<?php include '../includes/footer.php'; ?>

