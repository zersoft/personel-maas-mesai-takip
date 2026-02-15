<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Sürüm Notları';

$versionNotes = [];
$versionNotesPath = __DIR__ . '/../config/version_notes.php';
if (file_exists($versionNotesPath)) {
    $versionNotes = (array) require $versionNotesPath;
}

$appVersion = '2.0.0';
$appConfigPath = __DIR__ . '/../config/app.php';
if (file_exists($appConfigPath)) {
    require_once $appConfigPath;
    $appVersion = defined('APP_VERSION') ? APP_VERSION : $appVersion;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-journal-text"></i> Sürüm Notları</h4>
    <span class="badge bg-primary fs-6">Mevcut sürüm: v<?php echo htmlspecialchars($appVersion); ?></span>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <p class="text-muted mb-4">
            Uygulama güncellemeleri ve iyileştirmeleri aşağıda listelenmektedir. En yeni sürüm en üstte yer alır.
        </p>

        <?php if (empty($versionNotes)): ?>
            <div class="alert alert-info mb-0">
                <i class="bi bi-info-circle"></i> Henüz sürüm notu eklenmemiş.
            </div>
        <?php else: ?>
            <div class="accordion accordion-flush" id="versionAccordion">
                <?php foreach ($versionNotes as $index => $release): ?>
                    <?php
                    $v = $release['version'];
                    $date = $release['date'];
                    $title = isset($release['title']) ? $release['title'] : 'Sürüm ' . $v;
                    $notes = isset($release['notes']) ? $release['notes'] : [];
                    $collapseId = 'v' . str_replace('.', '-', $v) . '-' . $index;
                    $showFirst = ($index === 0);
                    $dateFormatted = date('d.m.Y', strtotime($date));
                    ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button <?php echo $showFirst ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="<?php echo $showFirst ? 'true' : 'false'; ?>">
                                <span class="badge bg-secondary me-3">v<?php echo htmlspecialchars($v); ?></span>
                                <span class="me-2"><?php echo htmlspecialchars($title); ?></span>
                                <small class="text-muted">(<?php echo $dateFormatted; ?>)</small>
                            </button>
                        </h2>
                        <div id="<?php echo $collapseId; ?>" class="accordion-collapse collapse <?php echo $showFirst ? 'show' : ''; ?>" data-bs-parent="#versionAccordion">
                            <div class="accordion-body">
                                <?php if (empty($notes)): ?>
                                    <p class="text-muted mb-0">Bu sürüm için not eklenmemiş.</p>
                                <?php else: ?>
                                    <ul class="mb-0 ps-3">
                                        <?php foreach ($notes as $note): ?>
                                            <li class="mb-1"><?php echo htmlspecialchars($note); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
