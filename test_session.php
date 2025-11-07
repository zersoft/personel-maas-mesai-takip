<?php
session_start();

echo "<h3>Session Test</h3>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "Session Status: " . session_status() . " (2=active)\n\n";
echo "Session Data:\n";
print_r($_SESSION);
echo "</pre>";

if (isset($_SESSION['user_id'])) {
    echo "<p style='color:green;'>✓ Kullanıcı giriş yapmış (ID: " . $_SESSION['user_id'] . ")</p>";
    echo "<p><a href='index.php'>Ana Sayfaya Git</a></p>";
} else {
    echo "<p style='color:red;'>✗ Kullanıcı giriş yapmamış</p>";
    echo "<p><a href='login.php'>Login Sayfasına Git</a></p>";
}
?>

