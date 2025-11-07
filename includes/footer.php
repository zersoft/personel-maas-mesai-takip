    </div>
    <footer class="mt-5 py-4 bg-light">
        <div class="container-fluid text-center">
            <p class="mb-0 text-muted">
                &copy; <?php echo date('Y'); ?> 
                <a class="text-dark" href="https://zersoft.net" target="_blank">ZERSOFT</a> 
                Personel Takip Sistemi. Tüm hakları saklıdır.
                <?php
                // Versiyon bilgisini göster
                $appConfigPath = __DIR__ . '/../config/app.php';
                if (file_exists($appConfigPath)) {
                    require_once $appConfigPath;
                    echo ' <span class="badge bg-secondary">v' . APP_VERSION . '</span>';
                } else {
                    echo ' <span class="badge bg-secondary">v2.0.0</span>';
                }
                ?>
            </p>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php
    // Base path'i header'dan almak için kontrol et
    $scriptPath = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    $isInPages = (strpos($scriptPath, '/pages/') !== false);
    $basePath = $isInPages ? '../' : '';
    ?>
    <?php $assetVersion = date('YmdHis'); ?>
    <script src="<?php echo $basePath; ?>assets/js/main.js?v=<?php echo $assetVersion; ?>"></script>
</body>
</html>

