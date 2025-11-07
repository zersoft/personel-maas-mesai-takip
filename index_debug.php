<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== INDEX DEBUG ===<br><br>";

// Session test
$sessionPath = sys_get_temp_dir() . '/php_sessions';
if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0777, true);
}
if (is_dir($sessionPath) && is_writable($sessionPath)) {
    session_save_path($sessionPath);
}
@session_start();

echo "1. Session başlatıldı<br>";
echo "Session ID: " . session_id() . "<br>";
echo "Session Save Path: " . session_save_path() . "<br>";
echo "Session içeriği:<br><pre>";
print_r($_SESSION);
echo "</pre>";

if (isset($_SESSION['user_id'])) {
    echo "<p style='color:green;'>✓ Session çalışıyor, User ID: " . $_SESSION['user_id'] . "</p>";
} else {
    echo "<p style='color:red;'>✗ Session'da user_id yok!</p>";
    echo "<p>Login sayfasından giriş yaptıktan sonra buraya gelin.</p>";
    echo "<p><a href='login.php'>Login'e Git</a></p>";
    exit;
}

echo "<br>2. DB bağlantısı test ediliyor...<br>";
require_once 'config/db.php';
echo "✓ DB bağlantısı OK<br>";

echo "<br>3. Functions yükleniyor...<br>";
require_once 'includes/functions.php';
echo "✓ Functions yüklendi<br>";

echo "<br>4. İstatistikler çekiliyor...<br>";
try {
    $personelSayisi = $pdo->query("SELECT COUNT(*) as sayi FROM personel_listesi WHERE aktif = 1")->fetch()['sayi'];
    echo "✓ Personel sayısı: $personelSayisi<br>";
    
    $toplamBordro = $pdo->query("SELECT COUNT(*) as sayi FROM bordro")->fetch()['sayi'];
    echo "✓ Bordro sayısı: $toplamBordro<br>";
} catch(PDOException $e) {
    echo "<p style='color:red;'>DB Hatası: " . $e->getMessage() . "</p>";
}

echo "<br>5. Header yükleniyor...<br>";
try {
    // Auth kontrolünü atla, sadece header HTML'ini yükle
    include 'includes/header.php';
    echo "✓ Header yüklendi<br>";
} catch(Exception $e) {
    echo "<p style='color:red;'>Header Hatası: " . $e->getMessage() . "</p>";
}

echo "<br><h3>Tüm testler başarılı!</h3>";
echo "<p><a href='index.php'>Normal Index'e Git</a></p>";
?>

