<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "1. Index test başladı<br>";

session_start();
echo "2. Session başlatıldı<br>";
echo "Session ID: " . session_id() . "<br>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'YOK') . "<br>";

require_once 'config/db.php';
echo "3. DB bağlantısı OK<br>";

require_once 'includes/functions.php';
echo "4. Functions yüklendi<br>";

echo "<h3>Session İçeriği:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<p><a href='index.php'>Normal Index'e Git</a></p>";
echo "<p><a href='login.php'>Login'e Git</a></p>";
?>

