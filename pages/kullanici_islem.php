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

ob_start();

// Sadece admin
requireRole('admin');

$action = $_POST['action'] ?? '';

try {
    verifyCsrfToken();
    if ($action === 'insert') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $ad_soyad = $_POST['ad_soyad'] ?? '';
        $email = $_POST['email'] ?? null;
        $rol = $_POST['rol'] ?? 'user';
        $aktif = isset($_POST['aktif']) ? 1 : 0;
        
        if (!$username || !$password || !$ad_soyad) {
            throw new Exception('Gerekli alanlar eksik');
        }
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, ad_soyad, email, rol, aktif) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, $hashedPassword, $ad_soyad, $email, $rol, $aktif]);
        
        logUserAction('users', 'INSERT', $pdo->lastInsertId(), "Yeni kullanıcı: $username");
        safeRedirect('kullanici_yonetimi.php?success=1');
        
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $username = $_POST['username'] ?? '';
        $ad_soyad = $_POST['ad_soyad'] ?? '';
        $email = $_POST['email'] ?? null;
        $rol = $_POST['rol'] ?? 'user';
        $aktif = isset($_POST['aktif']) ? 1 : 0;
        $password = $_POST['password'] ?? '';
        
        if ($id <= 0 || !$username || !$ad_soyad) {
            throw new Exception('Gerekli alanlar eksik');
        }
        
        if ($password) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET username=?, password=?, ad_soyad=?, email=?, rol=?, aktif=? WHERE id=?");
            $stmt->execute([$username, $hashedPassword, $ad_soyad, $email, $rol, $aktif, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET username=?, ad_soyad=?, email=?, rol=?, aktif=? WHERE id=?");
            $stmt->execute([$username, $ad_soyad, $email, $rol, $aktif, $id]);
        }
        
        logUserAction('users', 'UPDATE', $id, "Kullanıcı güncellendi: $username");
        safeRedirect('kullanici_yonetimi.php?success=1');
        
    } elseif ($action === 'delete') {
        verifyCsrfToken();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) throw new Exception('Geçersiz ID');
        if ($id == $_SESSION['user_id']) throw new Exception('Kendi hesabınızı silemezsiniz');

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);

        logUserAction('users', 'DELETE', $id, "Kullanıcı silindi");
        safeRedirect('kullanici_yonetimi.php?success=1');
    } else {
        throw new Exception('Geçersiz işlem');
    }
} catch(PDOException $e) {
    safeRedirect('kullanici_yonetimi.php?error=' . urlencode($e->getMessage()));
} catch(Exception $e) {
    safeRedirect('kullanici_yonetimi.php?error=' . urlencode($e->getMessage()));
}

