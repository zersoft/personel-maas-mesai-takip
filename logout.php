<?php
// Output buffer'ı temizle (eğer varsa)
if (ob_get_level() > 0) {
    ob_end_clean();
}

// Session başlat (aynı path ile)
$appSessionPath = __DIR__ . '/storage/sessions';
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

// Session cookie'sini temizle
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Session'ı yok et
session_destroy();

// Redirect
header('Location: login.php');
exit;

