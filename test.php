<?php
/**
 * Veritabanı Bağlantı Test Sayfası
 * Bu sayfa veritabanı bağlantısını ve tabloları kontrol eder
 */

require_once 'config/db.php';

$errors = [];
$success = [];

// Veritabanı bağlantı testi
try {
    $pdo->query("SELECT 1");
    $success[] = "✓ Veritabanı bağlantısı başarılı";
} catch(PDOException $e) {
    $errors[] = "✗ Veritabanı bağlantı hatası: " . $e->getMessage();
}

// Tablo kontrolü
$tables = [
    'personel_listesi',
    'bordro',
    'fazla_mesai',
    'avans_takip',
    'tazminat_takip',
    'rapor_ozet'
];

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $success[] = "✓ Tablo '$table' mevcut";
            
            // Tablo kayıt sayısı
            $count = $pdo->query("SELECT COUNT(*) as sayi FROM $table")->fetch()['sayi'];
            $success[] = "  → Toplam kayıt: $count";
        } else {
            $errors[] = "✗ Tablo '$table' bulunamadı";
        }
    } catch(PDOException $e) {
        $errors[] = "✗ Tablo '$table' kontrol hatası: " . $e->getMessage();
    }
}

// Personel listesi kontrolü
try {
    $personelSayisi = $pdo->query("SELECT COUNT(*) as sayi FROM personel_listesi")->fetch()['sayi'];
    if ($personelSayisi > 0) {
        $success[] = "✓ $personelSayisi personel kaydı bulundu";
    } else {
        $success[] = "ℹ Henüz personel kaydı yok (Normal - ilk kullanım)";
    }
} catch(PDOException $e) {
    $errors[] = "✗ Personel kontrolü hatası: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veritabanı Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
            background-color: #f8f9fa;
        }
        .test-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .success-item {
            color: #198754;
            padding: 5px 0;
        }
        .error-item {
            color: #dc3545;
            padding: 5px 0;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">🔍 Veritabanı Bağlantı Testi</h3>
            </div>
            <div class="card-body">
                <h5>Test Sonuçları:</h5>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <h6>Başarılı Kontroller:</h6>
                        <ul class="mb-0">
                            <?php foreach ($success as $msg): ?>
                                <li class="success-item"><?php echo $msg; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <h6>Hatalar:</h6>
                        <ul class="mb-0">
                            <?php foreach ($errors as $msg): ?>
                                <li class="error-item"><?php echo $msg; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($errors)): ?>
                    <div class="alert alert-info">
                        <strong>✅ Tüm kontroller başarılı!</strong><br>
                        Sistem kullanıma hazır. <a href="index.php" class="alert-link">Ana sayfaya git</a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <strong>⚠️ Bazı sorunlar tespit edildi</strong><br>
                        Lütfen veritabanı bağlantı bilgilerini ve tabloları kontrol edin.
                    </div>
                <?php endif; ?>
                
                <hr>
                
                <h6>Sonraki Adımlar:</h6>
                <ol>
                    <li>Veritabanı bağlantısı başarılıysa <a href="index.php">Ana Sayfa</a>'ya gidin</li>
                    <li><a href="pages/personel_listesi.php">Personel Listesi</a> sayfasından personel ekleyin</li>
                    <li>Bordro, fazla mesai ve diğer modülleri test edin</li>
                </ol>
                
                <div class="mt-3">
                    <a href="index.php" class="btn btn-primary">Ana Sayfaya Git</a>
                    <a href="database.sql" class="btn btn-secondary" download>SQL Dosyasını İndir</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

