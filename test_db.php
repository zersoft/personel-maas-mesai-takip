<?php
require_once 'config/db.php';

echo "<h3>Veritabanı Bağlantı Testi</h3>";

// Users tablosu var mı?
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color:green;'>✓ users tablosu mevcut</p>";
        
        // Admin kullanıcısı var mı?
        $stmt = $pdo->query("SELECT * FROM users WHERE username = 'admin'");
        $user = $stmt->fetch();
        if ($user) {
            echo "<p style='color:green;'>✓ Admin kullanıcısı mevcut</p>";
            echo "<pre>";
            echo "ID: " . $user['id'] . "\n";
            echo "Username: " . $user['username'] . "\n";
            echo "Ad Soyad: " . $user['ad_soyad'] . "\n";
            echo "Rol: " . $user['rol'] . "\n";
            echo "Aktif: " . $user['aktif'] . "\n";
            echo "Password hash: " . substr($user['password'], 0, 30) . "...\n";
            echo "</pre>";
            
            // Şifre testi
            if (password_verify('admin123', $user['password'])) {
                echo "<p style='color:green;'>✓ Şifre doğru (admin123)</p>";
            } else {
                echo "<p style='color:red;'>✗ Şifre yanlış!</p>";
                echo "<p>Yeni hash: <code>" . password_hash('admin123', PASSWORD_DEFAULT) . "</code></p>";
            }
        } else {
            echo "<p style='color:red;'>✗ Admin kullanıcısı bulunamadı!</p>";
            echo "<p>Lütfen migration SQL'i çalıştırın.</p>";
        }
    } else {
        echo "<p style='color:red;'>✗ users tablosu bulunamadı!</p>";
        echo "<p>Lütfen migration SQL'i çalıştırın: <code>migration_user_system.sql</code></p>";
    }
} catch(PDOException $e) {
    echo "<p style='color:red;'>Hata: " . $e->getMessage() . "</p>";
}
?>

