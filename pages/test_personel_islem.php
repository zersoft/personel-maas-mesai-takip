<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== PERSONEL ISLEM DEBUG ===<br><br>";

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

echo "1. Session başlatıldı<br>";
echo "Session ID: " . session_id() . "<br>";
echo "Session içeriği:<br><pre>";
print_r($_SESSION);
echo "</pre>";

echo "<br>2. DB bağlantısı test ediliyor...<br>";
try {
    require_once __DIR__ . '/../config/db.php';
    echo "✓ DB bağlantısı OK<br>";
} catch(Exception $e) {
    die("DB Hatası: " . $e->getMessage());
}

echo "<br>3. Functions yükleniyor...<br>";
try {
    require_once __DIR__ . '/../includes/functions.php';
    echo "✓ Functions yüklendi<br>";
    
    // parseMoney test
    echo "parseMoney test: " . parseMoney("1.234,56") . "<br>";
} catch(Exception $e) {
    die("Functions Hatası: " . $e->getMessage());
}

echo "<br>4. Auth yükleniyor...<br>";
try {
    require_once __DIR__ . '/../includes/auth.php';
    echo "✓ Auth yüklendi<br>";
} catch(Exception $e) {
    die("Auth Hatası: " . $e->getMessage());
}

echo "<br>5. requireLogin test ediliyor...<br>";
try {
    requireLogin();
    echo "✓ requireLogin OK<br>";
} catch(Exception $e) {
    die("requireLogin Hatası: " . $e->getMessage());
}

echo "<br>6. getCurrentUserId test ediliyor...<br>";
try {
    $userId = getCurrentUserId();
    echo "✓ getCurrentUserId OK: " . ($userId ?? 'NULL') . "<br>";
} catch(Exception $e) {
    die("getCurrentUserId Hatası: " . $e->getMessage());
}

echo "<br>7. logUserAction test ediliyor...<br>";
try {
    logUserAction('test', 'TEST', 1, "Test log");
    echo "✓ logUserAction OK<br>";
} catch(Exception $e) {
    die("logUserAction Hatası: " . $e->getMessage());
}

echo "<br><h3>Tüm testler başarılı!</h3>";
echo "<p>Şimdi personel_islem.php'yi açmayı deneyin.</p>";
?>

