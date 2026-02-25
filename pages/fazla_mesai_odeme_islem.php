<?php
// Session başlat
if (session_status() === PHP_SESSION_NONE) {
	$appSessionPath = __DIR__ . '/../storage/sessions';
	if (!is_dir($appSessionPath)) {
		@mkdir($appSessionPath, 0700, true);
	}
	if (is_dir($appSessionPath) && is_writable($appSessionPath)) {
		ini_set('session.save_path', $appSessionPath);
	}
	ini_set('session.cookie_lifetime', 0);
	ini_set('session.cookie_path', '/');
	ini_set('session.cookie_httponly', 1);
	ini_set('session.use_only_cookies', 1);
	@session_start();
}

// Çıktı tamponu; yönlendirme sorunlarını engelle
if (ob_get_level() === 0) { ob_start(); }

require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Giriş kontrolü
requireRole('user');

// parseMoneyLocal için parseMoney kullan
function parseMoneyLocal($value) {
    return parseMoney($value);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    try {
        $personel_id = isset($_POST['personel_id']) ? (int)$_POST['personel_id'] : null;

        if (!$personel_id || $personel_id <= 0) {
            throw new Exception('Geçersiz personel ID');
        }
        $odeme_tarihi = $_POST['odeme_tarihi'] ?? date('Y-m-d');
        $odeme_tutari = parseMoneyLocal($_POST['odeme_tutari'] ?? 0);
        $tamamini_ode = isset($_POST['tamamini_ode']) ? true : false;
        $aciklama = $_POST['aciklama'] ?? null;

        if (!$personel_id) {
            throw new Exception('Personel seçilmedi');
        }

        if ($odeme_tutari <= 0) {
            throw new Exception('Ödeme tutarı geçersiz');
        }

        $userId = getCurrentUserId();
        
        // created_by kontrolü
        $hasCreatedBy = false;
        try {
            $checkStmt = $pdo->query("SHOW COLUMNS FROM fazla_mesai_odeme LIKE 'created_by'");
            $hasCreatedBy = $checkStmt->rowCount() > 0;
        } catch(PDOException $e) {}

        // Ödeme kaydı oluştur (sadece fazla_mesai_odeme tablosuna)
        if ($hasCreatedBy) {
            $insertPay = $pdo->prepare("INSERT INTO fazla_mesai_odeme (personel_id, odeme_tarihi, tutar, aciklama, created_by) VALUES (?, ?, ?, ?, ?)");
            $insertPay->execute([
                $personel_id,
                $odeme_tarihi,
                $odeme_tutari,
                $aciklama ?: null,
                $userId
            ]);
        } else {
            $insertPay = $pdo->prepare("INSERT INTO fazla_mesai_odeme (personel_id, odeme_tarihi, tutar, aciklama) VALUES (?, ?, ?, ?)");
            $insertPay->execute([
                $personel_id,
                $odeme_tarihi,
                $odeme_tutari,
                $aciklama ?: null
            ]);
        }

        $newId = $pdo->lastInsertId();
        logUserAction('fazla_mesai_odeme', 'INSERT', $newId, "Fazla mesai ödemesi: " . formatMoney($odeme_tutari));
        if (ob_get_level() > 0) { @ob_end_clean(); }
        safeRedirect('fazla_mesai.php?success=1');
    } catch(PDOException $e) {
        error_log("FM ödeme hatası: " . $e->getMessage());
        if (ob_get_level() > 0) { @ob_end_clean(); }
        safeRedirect('fazla_mesai_odeme.php?personel_id=' . ($personel_id ?? '') . '&error=' . urlencode($e->getMessage()));
    } catch(Exception $e) {
        error_log("FM ödeme hatası: " . $e->getMessage());
        if (ob_get_level() > 0) { @ob_end_clean(); }
        safeRedirect('fazla_mesai_odeme.php?personel_id=' . ($personel_id ?? '') . '&error=' . urlencode($e->getMessage()));
    }
} else {
    if (ob_get_level() > 0) { @ob_end_clean(); }
    safeRedirect('fazla_mesai.php');
}
?>

