    </div>

    <?php
    $scriptPath = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    $isInPages = (strpos($scriptPath, '/pages/') !== false);
    $menuPagesPath = $isInPages ? '' : 'pages/';
    $appConfigPath = __DIR__ . '/../config/app.php';
    $footerVersion = '2.0.0';
    if (file_exists($appConfigPath)) {
        require_once $appConfigPath;
        $footerVersion = defined('APP_VERSION') ? APP_VERSION : $footerVersion;
    }
    $footerVersionNotes = [];
    $versionNotesPath = __DIR__ . '/../config/version_notes.php';
    if (file_exists($versionNotesPath)) {
        $footerVersionNotes = (array) require $versionNotesPath;
    }
    $footerAppName = defined('APP_NAME') ? APP_NAME : 'OYS - Ocak Yönetim Sistemi';
    ?>

    <!-- Sürüm Notları Modal -->
    <div class="modal fade" id="versionNotesModal" tabindex="-1" aria-labelledby="versionNotesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="versionNotesModalLabel">
                        <i class="bi bi-journal-text"></i> Sürüm Notları
                        <span class="badge bg-primary ms-2">v<?php echo htmlspecialchars($footerVersion); ?></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($footerVersionNotes)): ?>
                        <p class="text-muted mb-0">Henüz sürüm notu eklenmemiş.</p>
                    <?php else: ?>
                        <?php foreach ($footerVersionNotes as $release): ?>
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
                    <a href="<?php echo htmlspecialchars($menuPagesPath . 'versiyon_notlari.php'); ?>" class="btn btn-outline-primary btn-sm">Tümünü sayfada aç</a>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>

    <footer class="mt-5 py-4 bg-light">
        <div class="container-fluid text-center">
            <p class="mb-0 text-muted">
                &copy; <?php echo date('Y'); ?> 
                <a class="text-dark" href="https://zersoft.net" target="_blank">ZERSOFT</a> 
                <?php echo htmlspecialchars($footerAppName); ?>. Tüm hakları saklıdır.
                <a href="#" class="text-decoration-none" title="Sürüm notlarını görüntüle" data-bs-toggle="modal" data-bs-target="#versionNotesModal">
                    <span class="badge bg-secondary">v<?php echo htmlspecialchars($footerVersion); ?></span>
                </a>
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

