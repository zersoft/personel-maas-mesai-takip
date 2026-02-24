<?php
// Auth kontrolü (debug modda atlanabilir)
if (!defined('SKIP_AUTH')) {
    try {
        require_once __DIR__ . '/auth.php';
        requireLogin();
    } catch(Exception $e) {
        die("Auth hatası: " . $e->getMessage());
    }
}

// Mevcut dosyanın konumunu belirle (sadece basePath için)
$scriptPath = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
$isInPages = (strpos($scriptPath, '/pages/') !== false);

// Base path: CSS, JS, index.php gibi dosyalar için
$basePath = $isInPages ? '../' : '';

// Menü linkleri için: HER ZAMAN pages/ klasörüne işaret et
// Sayfa konumundan bağımsız olarak aynı yolu kullan
$menuPagesPath = $isInPages ? '' : 'pages/';

// Aktif sayfa belirleme (vurgulama için)
$currentPage = basename($scriptPath);
if (!defined('APP_SHORT_NAME')) {
    $appConfigPath = __DIR__ . '/../config/app.php';
    if (file_exists($appConfigPath)) require_once $appConfigPath;
    if (!defined('APP_SHORT_NAME')) { define('APP_SHORT_NAME', 'OYS'); define('APP_NAME', 'Ocak Yönetim Sistemi'); }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . APP_SHORT_NAME : APP_SHORT_NAME . ' - ' . APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/style.css">
</head>
<body>
    <!-- Üst Bar: Uygulama Adı ve Kullanıcı Bilgileri -->
    <nav class="navbar navbar-dark bg-primary py-2" style="min-height: 50px;">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="<?php echo $basePath; ?>index.php" style="font-size: 1.25rem;" title="<?php echo htmlspecialchars(APP_NAME); ?>">
                <i class="bi bi-layers"></i> <?php echo htmlspecialchars(APP_SHORT_NAME); ?>
            </a>
            <div class="d-flex align-items-center">
                <a class="text-white text-decoration-none me-3" href="#" title="Sürüm Notları" data-bs-toggle="modal" data-bs-target="#versionNotesModal">
                    <i class="bi bi-journal-text"></i>
                </a>
                <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                <a class="text-white text-decoration-none me-3" href="<?php echo $menuPagesPath; ?>kullanici_yonetimi.php" title="Kullanıcı Yönetimi">
                    <i class="bi bi-gear"></i>
                </a>
                <?php endif; ?>
                <div class="dropdown">
                    <a class="text-white text-decoration-none dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown" style="cursor: pointer;">
                        <i class="bi bi-person-circle me-2" style="font-size: 1.25rem;"></i>
                        <span><?php echo htmlspecialchars($_SESSION['ad_soyad'] ?? 'Kullanıcı'); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item disabled"><small class="text-muted">@<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></small></a></li>
                        <li><a class="dropdown-item disabled"><small class="text-muted"><?php echo ucfirst($_SESSION['rol'] ?? 'user'); ?></small></a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#versionNotesModal"><i class="bi bi-journal-text"></i> Sürüm Notları</a></li>
                        <li><a class="dropdown-item" href="<?php echo $basePath; ?>logout.php"><i class="bi bi-box-arrow-right"></i> Çıkış Yap</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Alt Bar: Modül Menüleri -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #0a58ca;">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#moduleNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="moduleNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentPage === 'index.php') ? 'active' : ''; ?>" href="<?php echo $basePath; ?>index.php">
                            <i class="bi bi-house-door"></i> Ana Sayfa
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentPage === 'personel_listesi.php') ? 'active' : ''; ?>" href="<?php echo $menuPagesPath; ?>personel_listesi.php">
                            <i class="bi bi-people"></i> Personel Listesi
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentPage === 'bordro.php') ? 'active' : ''; ?>" href="<?php echo $menuPagesPath; ?>bordro.php">
                            <i class="bi bi-cash-coin"></i> Bordro
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentPage === 'puantaj.php') ? 'active' : ''; ?>" href="<?php echo $menuPagesPath; ?>puantaj.php">
                            <i class="bi bi-clipboard-data"></i> Puantaj
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentPage === 'fazla_mesai.php') ? 'active' : ''; ?>" href="<?php echo $menuPagesPath; ?>fazla_mesai.php">
                            <i class="bi bi-clock-history"></i> Fazla Mesai
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentPage === 'avans_takip.php') ? 'active' : ''; ?>" href="<?php echo $menuPagesPath; ?>avans_takip.php">
                            <i class="bi bi-wallet2"></i> Avans Takip
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentPage === 'tazminat_takip.php') ? 'active' : ''; ?>" href="<?php echo $menuPagesPath; ?>tazminat_takip.php">
                            <i class="bi bi-file-earmark-text"></i> Tazminat Takip
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentPage === 'raporlar.php') ? 'active' : ''; ?>" href="<?php echo $menuPagesPath; ?>raporlar.php">
                            <i class="bi bi-graph-up"></i> Raporlar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentPage === 'kantar_raporlari.php') ? 'active' : ''; ?>" href="<?php echo $menuPagesPath; ?>kantar_raporlari.php">
                            <i class="bi bi-truck"></i> Kantar Raporları
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid mt-4">

