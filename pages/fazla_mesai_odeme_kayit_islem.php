<?php
// Session başlat
if (session_status() === PHP_SESSION_NONE) {
	$appSessionPath = __DIR__ . '/../storage/sessions';
	if (!is_dir($appSessionPath)) {
		@mkdir($appSessionPath, 0777, true);
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

if (ob_get_level() === 0) { ob_start(); }

require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Giriş kontrolü
requireLogin();

// parseMoneyLocal için parseMoney kullan
function parseMoneyLocal($value) {
    return parseMoney($value);
}

try {
    if (isset($_POST['action']) && $_POST['action'] === 'single_payment') {
        // Tek ödeme
        $personel_id = (int)($_POST['personel_id'] ?? 0);
        $odeme_tarihi = $_POST['odeme_tarihi'] ?? date('Y-m-d');
        $tutar = isset($_POST['tutar']) ? parseMoneyLocal($_POST['tutar']) : 0;
        $aciklama = $_POST['aciklama'] ?? null;
        
        if ($personel_id <= 0 || $tutar <= 0) {
            throw new Exception('Geçersiz veri');
        }
        
        $userId = getCurrentUserId();
        
        // created_by kontrolü
        $hasCreatedBy = false;
        try {
            $checkStmt = $pdo->query("SHOW COLUMNS FROM fazla_mesai_odeme LIKE 'created_by'");
            $hasCreatedBy = $checkStmt->rowCount() > 0;
        } catch(PDOException $e) {}
        
        if ($hasCreatedBy) {
            $stmt = $pdo->prepare('INSERT INTO fazla_mesai_odeme (personel_id, odeme_tarihi, tutar, aciklama, created_by) VALUES (?,?,?,?,?)');
            $stmt->execute([$personel_id, $odeme_tarihi, $tutar, $aciklama, $userId]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO fazla_mesai_odeme (personel_id, odeme_tarihi, tutar, aciklama) VALUES (?,?,?,?)');
            $stmt->execute([$personel_id, $odeme_tarihi, $tutar, $aciklama]);
        }
        
        $newId = $pdo->lastInsertId();
        logUserAction('fazla_mesai_odeme', 'INSERT', $newId, "Tek ödeme: " . formatMoney($tutar));
        if (ob_get_level() > 0) { @ob_end_clean(); }
        safeRedirect('fazla_mesai.php?success=1');
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'bulk_payment') {
        // Toplu ödeme
        $odeme_tarihi = $_POST['odeme_tarihi'] ?? date('Y-m-d');
        $personel = $_POST['personel'] ?? [];
        
        if (empty($personel)) {
            throw new Exception('Personel seçilmedi');
        }
        
        $userId = getCurrentUserId();
        
        // created_by kontrolü
        $hasCreatedBy = false;
        try {
            $checkStmt = $pdo->query("SHOW COLUMNS FROM fazla_mesai_odeme LIKE 'created_by'");
            $hasCreatedBy = $checkStmt->rowCount() > 0;
        } catch(PDOException $e) {}
        
        if ($hasCreatedBy) {
            $stmt = $pdo->prepare('INSERT INTO fazla_mesai_odeme (personel_id, odeme_tarihi, tutar, aciklama, created_by) VALUES (?,?,?,?,?)');
        } else {
            $stmt = $pdo->prepare('INSERT INTO fazla_mesai_odeme (personel_id, odeme_tarihi, tutar, aciklama) VALUES (?,?,?,?)');
        }
        
        $pdo->beginTransaction();
        foreach ($personel as $pid => $data) {
            if (!isset($data['secili'])) continue;
            $tutar = isset($data['tutar']) ? parseMoneyLocal($data['tutar']) : 0;
            if ($tutar <= 0) continue;
            
            if ($hasCreatedBy) {
                $stmt->execute([$pid, $odeme_tarihi, $tutar, 'Toplu ödeme', $userId]);
            } else {
                $stmt->execute([$pid, $odeme_tarihi, $tutar, 'Toplu ödeme']);
            }
            
            $newId = $pdo->lastInsertId();
            logUserAction('fazla_mesai_odeme', 'INSERT', $newId, "Toplu ödeme: " . formatMoney($tutar));
        }
        
        $pdo->commit();
        if (ob_get_level() > 0) { @ob_end_clean(); }
        safeRedirect('fazla_mesai.php?success=1');
    }
    
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        if ($id <= 0) {
            throw new Exception('Geçersiz ödeme ID');
        }
        $del = $pdo->prepare("DELETE FROM fazla_mesai_odeme WHERE id = ?");
        $del->execute([$id]);
        logUserAction('fazla_mesai_odeme', 'DELETE', $id, "Ödeme kaydı silindi");
        if (ob_get_level() > 0) { @ob_end_clean(); }
        safeRedirect('fazla_mesai_odeme_listesi.php?success=1');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $personel_id = isset($_POST['personel_id']) ? (int)$_POST['personel_id'] : 0;
        $odeme_tarihi = $_POST['odeme_tarihi'] ?? date('Y-m-d');
        $tutar = isset($_POST['tutar_raw']) ? parseMoneyLocal($_POST['tutar_raw']) : parseMoneyLocal($_POST['tutar'] ?? 0);
        $aciklama = $_POST['aciklama'] ?? null;
        if ($id <= 0 || $personel_id <= 0) {
            throw new Exception('Geçersiz parametreler');
        }
        $userId = getCurrentUserId();
        
        // updated_by kontrolü
        $hasUpdatedBy = false;
        try {
            $checkStmt = $pdo->query("SHOW COLUMNS FROM fazla_mesai_odeme LIKE 'updated_by'");
            $hasUpdatedBy = $checkStmt->rowCount() > 0;
        } catch(PDOException $e) {}
        
        if ($hasUpdatedBy) {
            $upd = $pdo->prepare("UPDATE fazla_mesai_odeme SET personel_id = ?, odeme_tarihi = ?, tutar = ?, aciklama = ?, updated_by = ? WHERE id = ?");
            $upd->execute([$personel_id, $odeme_tarihi, $tutar, $aciklama ?: null, $userId, $id]);
        } else {
            $upd = $pdo->prepare("UPDATE fazla_mesai_odeme SET personel_id = ?, odeme_tarihi = ?, tutar = ?, aciklama = ? WHERE id = ?");
            $upd->execute([$personel_id, $odeme_tarihi, $tutar, $aciklama ?: null, $id]);
        }
        
        logUserAction('fazla_mesai_odeme', 'UPDATE', $id, "Ödeme kaydı güncellendi: " . formatMoney($tutar));
        if (ob_get_level() > 0) { @ob_end_clean(); }
        safeRedirect('fazla_mesai_odeme_listesi.php?success=1');
    }

    // Unknown route
    if (ob_get_level() > 0) { @ob_end_clean(); }
    safeRedirect('fazla_mesai_odeme_listesi.php');
} catch(PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("FM ödeme kayıt hatası: " . $e->getMessage());
    if (ob_get_level() > 0) { @ob_end_clean(); }
    safeRedirect('fazla_mesai_odeme_listesi.php?error=' . urlencode($e->getMessage()));
} catch(Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("FM ödeme kayıt hatası: " . $e->getMessage());
    if (ob_get_level() > 0) { @ob_end_clean(); }
    safeRedirect('fazla_mesai_odeme_listesi.php?error=' . urlencode($e->getMessage()));
}

?>


