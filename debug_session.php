<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session başlat
$appSessionPath = __DIR__ . '/storage/sessions';
if (!is_dir($appSessionPath)) {
	@mkdir($appSessionPath, 0777, true);
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

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Session Debug</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .box { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #007bff; }
        .success { border-left-color: #28a745; }
        .error { border-left-color: #dc3545; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 Session Debug Sayfası</h1>
    
    <div class="box">
        <h3>1. Session Durumu</h3>
        <p><strong>Session Status:</strong> <?php echo session_status() === PHP_SESSION_ACTIVE ? '✅ Aktif' : '❌ Pasif'; ?></p>
        <p><strong>Session ID:</strong> <?php echo session_id(); ?></p>
        <p><strong>Session Save Path:</strong> <?php echo session_save_path(); ?></p>
        <p><strong>Session Cookie Name:</strong> <?php echo ini_get('session.name'); ?></p>
        <p><strong>Cookie Lifetime:</strong> <?php echo ini_get('session.cookie_lifetime'); ?> (0 = tarayıcı kapanana kadar)</p>
        <p><strong>Cookie Path:</strong> <?php echo ini_get('session.cookie_path'); ?></p>
        <p><strong>Cookie HttpOnly:</strong> <?php echo ini_get('session.cookie_httponly') ? '✅ Evet' : '❌ Hayır'; ?></p>
    </div>
    
    <div class="box">
        <h3>2. Session Cookie Bilgileri</h3>
        <pre><?php print_r($_COOKIE); ?></pre>
    </div>
    
    <div class="box">
        <h3>3. Session İçeriği ($_SESSION)</h3>
        <?php if (empty($_SESSION)): ?>
            <p class="error">❌ Session boş!</p>
        <?php else: ?>
            <pre><?php print_r($_SESSION); ?></pre>
        <?php endif; ?>
    </div>
    
    <div class="box">
        <h3>4. Session Dosyası Kontrolü</h3>
        <?php
        $sessionFile = session_save_path() . '/sess_' . session_id();
        if (file_exists($sessionFile)) {
            echo "<p class='success'>✅ Session dosyası bulundu: <code>$sessionFile</code></p>";
            echo "<p><strong>Dosya içeriği:</strong></p>";
            echo "<pre>" . htmlspecialchars(file_get_contents($sessionFile)) . "</pre>";
        } else {
            echo "<p class='error'>❌ Session dosyası bulunamadı: <code>$sessionFile</code></p>";
        }
        ?>
    </div>
    
    <div class="box">
        <h3>5. Login Kontrolü</h3>
        <?php if (isset($_SESSION['user_id'])): ?>
            <p class="success">✅ Kullanıcı giriş yapmış!</p>
            <p><strong>User ID:</strong> <?php echo $_SESSION['user_id']; ?></p>
            <p><strong>Username:</strong> <?php echo htmlspecialchars($_SESSION['username'] ?? 'N/A'); ?></p>
            <p><strong>Ad Soyad:</strong> <?php echo htmlspecialchars($_SESSION['ad_soyad'] ?? 'N/A'); ?></p>
            <p><strong>Rol:</strong> <?php echo htmlspecialchars($_SESSION['rol'] ?? 'N/A'); ?></p>
            <p><a href="index.php">→ Index.php'ye Git</a></p>
        <?php else: ?>
            <p class="error">❌ Kullanıcı giriş yapmamış!</p>
            <p><a href="login.php">→ Login Sayfasına Git</a></p>
        <?php endif; ?>
    </div>
    
    <div class="box">
        <h3>6. Test: Session'a Veri Yaz</h3>
        <form method="POST">
            <input type="hidden" name="test_write" value="1">
            <button type="submit">Session'a Test Verisi Yaz</button>
        </form>
        <?php
        if (isset($_POST['test_write'])) {
            $_SESSION['test_data'] = 'Test değeri: ' . date('Y-m-d H:i:s');
            echo "<p class='success'>✅ Test verisi yazıldı! Sayfayı yenileyin.</p>";
        }
        ?>
    </div>
    
    <div class="box">
        <h3>7. Storage Dizini Kontrolü</h3>
        <?php
        $storagePath = __DIR__ . '/storage/sessions';
        echo "<p><strong>Dizin:</strong> <code>$storagePath</code></p>";
        echo "<p><strong>Var mı:</strong> " . (is_dir($storagePath) ? '✅ Evet' : '❌ Hayır') . "</p>";
        echo "<p><strong>Yazılabilir mi:</strong> " . (is_writable($storagePath) ? '✅ Evet' : '❌ Hayır') . "</p>";
        if (is_dir($storagePath)) {
            $files = glob($storagePath . '/sess_*');
            echo "<p><strong>Session dosyaları:</strong> " . count($files) . " adet</p>";
            if (count($files) > 0) {
                echo "<pre>";
                foreach (array_slice($files, 0, 5) as $file) {
                    echo basename($file) . " (" . filesize($file) . " bytes)\n";
                }
                echo "</pre>";
            }
        }
        ?>
    </div>
</body>
</html>

