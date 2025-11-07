<?php
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
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Personel Takip Sistemi'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo $basePath; ?>index.php">
                <i class="bi bi-people-fill"></i> Personel Takip
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
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
                </ul>
            </div>
        </div>
    </nav>
    <div class="container-fluid mt-4">

