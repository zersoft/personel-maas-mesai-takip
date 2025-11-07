<?php
// Session ayarları (ob_start'tan ÖNCE)
$appSessionPath = __DIR__ . '/storage/sessions';
if (!is_dir($appSessionPath)) {
	@mkdir($appSessionPath, 0777, true);
}
if (is_dir($appSessionPath) && is_writable($appSessionPath)) {
	ini_set('session.save_path', $appSessionPath);
}
// Cookie ayarları
ini_set('session.cookie_lifetime', 0); // Tarayıcı kapanana kadar
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);

// Session başlat
@session_start();

// Output buffering kullanmıyoruz çünkü cookie gönderilmesini engelleyebilir
// ob_start();

require_once 'config/db.php';

// Zaten giriş yapmışsa ana sayfaya yönlendir
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND aktif = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Giriş başarılı
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['ad_soyad'] = $user['ad_soyad'];
                $_SESSION['rol'] = $user['rol'];
                
                // Son giriş zamanını güncelle
                $updateStmt = $pdo->prepare("UPDATE users SET son_giris = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);
                
                // Session cookie'sini manuel olarak gönder (güvenlik için)
                $sessionName = session_name();
                $sessionId = session_id();
                $cookieParams = session_get_cookie_params();
                
                // Cookie'yi manuel olarak ayarla
                setcookie(
                    $sessionName,
                    $sessionId,
                    $cookieParams['lifetime'] ? time() + $cookieParams['lifetime'] : 0,
                    $cookieParams['path'],
                    $cookieParams['domain'],
                    $cookieParams['secure'],
                    $cookieParams['httponly']
                );
                
                // Redirect yap
                header('Location: index.php');
                exit;
            } else {
                $error = 'Kullanıcı adı veya şifre hatalı!';
            }
        } catch(PDOException $e) {
            $error = 'Bir hata oluştu: ' . $e->getMessage();
        }
    } else {
        $error = 'Lütfen tüm alanları doldurun!';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş - Personel Takip Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            max-width: 400px;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="card shadow-lg">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <i class="bi bi-people-fill text-primary" style="font-size: 3rem;"></i>
                    <h3 class="mt-3">Personel Takip Sistemi</h3>
                    <p class="text-muted">Giriş Yapın</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Kullanıcı Adı</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" name="username" required autofocus>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Şifre</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-box-arrow-in-right"></i> Giriş Yap
                    </button>
                </form>
                
                <div class="text-center mt-4">
                    <small class="text-muted">© 2025 ZERSOFT Personel Takip Sistemi</small>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

