<?php
// HTTP güvenlik header'ları
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Session ayarları (ob_start'tan ÖNCE)
$appSessionPath = __DIR__ . '/storage/sessions';
if (!is_dir($appSessionPath)) {
	@mkdir($appSessionPath, 0700, true);
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
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Versiyon bilgisini yükle
$appConfigPath = __DIR__ . '/config/app.php';
$appVersion = '2.0.0';
if (file_exists($appConfigPath)) {
    require_once $appConfigPath;
    $appVersion = defined('APP_VERSION') ? APP_VERSION : '2.0.0';
}

// Sürüm notları (modal için)
$loginVersionNotes = [];
$versionNotesPath = __DIR__ . '/config/version_notes.php';
if (file_exists($versionNotesPath)) {
    $loginVersionNotes = (array) require $versionNotesPath;
}

// Zaten giriş yapmışsa ana sayfaya yönlendir
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

// Brute force koruması
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_first_attempt'] = time();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    // 15 dakika içinde 5'ten fazla başarısız deneme varsa engelle
    if ($_SESSION['login_attempts'] >= 5) {
        $elapsed = time() - $_SESSION['login_first_attempt'];
        if ($elapsed < 900) { // 15 dakika
            $remaining = ceil((900 - $elapsed) / 60);
            $error = "Çok fazla hatalı giriş denemesi. Lütfen {$remaining} dakika sonra tekrar deneyin.";
        } else {
            // Süre doldu, sayacı sıfırla
            $_SESSION['login_attempts'] = 0;
            $_SESSION['login_first_attempt'] = time();
        }
    }

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!$error && $username && $password) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND aktif = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Başarılı giriş, sayacı sıfırla
                $_SESSION['login_attempts'] = 0;
                // Session fixation koruması
                session_regenerate_id(true);
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
                $_SESSION['login_attempts']++;
                $error = 'Kullanıcı adı veya şifre hatalı!';
            }
        } catch(PDOException $e) {
            error_log('Login hatası: ' . $e->getMessage());
            $error = 'Bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
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
    <title>Giriş - OYS</title>
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
                    <i class="bi bi-layers text-primary" style="font-size: 3rem;"></i>
                    <h3 class="mt-3">OYS</h3>
                    <p class="text-muted small mb-0">Ocak Yönetim Sistemi</p>
                    <p class="text-muted">Giriş Yapın</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <?php echo csrfField(); ?>
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
                    <small class="text-muted">
                        © <?php echo date('Y'); ?> <a href="https://zersoft.net" target="_blank" class="text-decoration-none">ZERSOFT</a> OYS - Ocak Yönetim Sistemi
                        <a href="#" class="text-decoration-none ms-2" title="Sürüm notlarını görüntüle" data-bs-toggle="modal" data-bs-target="#versionNotesModal"><span class="badge bg-secondary">v<?php echo htmlspecialchars($appVersion); ?></span></a>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Sürüm Notları Modal (login sayfası) -->
    <div class="modal fade" id="versionNotesModal" tabindex="-1" aria-labelledby="versionNotesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="versionNotesModalLabel">
                        <i class="bi bi-journal-text"></i> Sürüm Notları
                        <span class="badge bg-primary ms-2">v<?php echo htmlspecialchars($appVersion); ?></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($loginVersionNotes)): ?>
                        <p class="text-muted mb-0">Henüz sürüm notu eklenmemiş.</p>
                    <?php else: ?>
                        <?php foreach ($loginVersionNotes as $release): ?>
                            <?php
                            $v = $release['version'];
                            $title = isset($release['title']) ? $release['title'] : 'Sürüm ' . $v;
                            $notes = isset($release['notes']) ? $release['notes'] : [];
                            $dateFormatted = date('d.m.Y', strtotime($release['date']));
                            ?>
                            <div class="mb-4">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="badge bg-secondary">v<?php echo htmlspecialchars($v); ?></span>
                                    <strong><?php echo htmlspecialchars($title); ?></strong>
                                    <small class="text-muted"><?php echo $dateFormatted; ?></small>
                                </div>
                                <?php if (!empty($notes)): ?>
                                    <ul class="mb-0 ps-3 small">
                                        <?php foreach ($notes as $note): ?>
                                            <li class="mb-1"><?php echo htmlspecialchars($note); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

