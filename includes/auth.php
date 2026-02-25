<?php
// Oturum başlat (eğer başlatılmamışsa)
if (session_status() === PHP_SESSION_NONE) {
	// Session dizini kontrolü ve ayarı (uygulama içi yol)
	$appSessionPath = __DIR__ . '/../storage/sessions';
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

// Veritabanı bağlantısı (logUserAction için gerekli)
if (!isset($pdo)) {
    require_once __DIR__ . '/../config/db.php';
}

// Giriş kontrolü
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        $basePath = getBasePath();
        if (ob_get_level() > 0) ob_end_clean();
        header('Location: ' . $basePath . 'login.php');
        echo '<script>window.location.href="' . $basePath . 'login.php";</script>';
        exit;
    }
}

// Viewer hiç ekleme/düzenleme/silme yapamaz; sadece user ve admin yapabilir
function canEdit() {
    $rol = $_SESSION['rol'] ?? '';
    return $rol !== 'viewer';
}

// Yetki kontrolü
function requireRole($requiredRole) {
    requireLogin();
    $userRole = $_SESSION['rol'] ?? 'user';
    $roles = ['viewer' => 1, 'user' => 2, 'admin' => 3];
    
    // Debug: Session rol bilgisini kontrol et
    if (!isset($_SESSION['rol'])) {
        error_log("requireRole: Session'da rol bilgisi yok! User ID: " . ($_SESSION['user_id'] ?? 'N/A'));
    }
    
    if (!isset($roles[$userRole]) || !isset($roles[$requiredRole]) || $roles[$userRole] < $roles[$requiredRole]) {
        $errorMsg = "Bu sayfaya erişim yetkiniz yok. (Mevcut rol: " . ($userRole ?? 'yok') . ", Gerekli: " . $requiredRole . ")";
        header('Location: ' . getBasePath() . 'index.php?error=' . urlencode($errorMsg));
        exit;
    }
}

// Audit log kaydet
function logUserAction($tablo, $islem, $kayit_id = null, $aciklama = null) {
    global $pdo;
    if (!isset($_SESSION['user_id'])) return;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO user_logs (user_id, islem_tipi, tablo_adi, kayit_id, aciklama, ip_adresi) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $islem,
            $tablo,
            $kayit_id,
            $aciklama,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch(PDOException $e) {
        // Log hatası sessizce yutulur (ana işlemi etkilemez)
    }
}

// Mevcut kullanıcı ID
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Base path helper
function getBasePath() {
    $scriptPath = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    $isInPages = (strpos($scriptPath, '/pages/') !== false);
    return $isInPages ? '../' : '';
}

// CSRF token oluştur
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF token doğrula
function verifyCsrfToken() {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        // Login sayfasından gelen POST: redirect etme, login sayfası kendi formunu göstersin (session cookie bu yanıtta set edilir)
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $isLoginPost = ($_SERVER['REQUEST_METHOD'] === 'POST' && (strpos($scriptName, 'login.php') !== false));
        if ($isLoginPost) {
            // verifyCsrfToken()'ı atla; login.php'de hata gösterip formu tekrar render edecek (yeni token ile)
            return false;
        }
        die('Geçersiz istek (CSRF doğrulaması başarısız).');
    }
    return true;
}

// Form için CSRF hidden input
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}