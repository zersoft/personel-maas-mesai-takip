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

ob_start();

require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Giriş kontrolü
requireLogin();

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

try {
    if ($action === 'insert') {
        $personel_id = (int)($_POST['personel_id'] ?? 0);
        $tarih = $_POST['tarih'] ?? null;
        $durum = $_POST['durum'] ?? 'Calisti';
        $saat = isset($_POST['saat']) ? floatval($_POST['saat']) : 0;
        $aciklama = $_POST['aciklama'] ?? null;
        if ($personel_id <= 0 || !$tarih) {
            safeRedirect('puantaj.php?error=' . urlencode('Geçersiz veri'));
        }
        
        $userId = getCurrentUserId();
        
        // created_by kontrolü
        $hasCreatedBy = false;
        try {
            $checkStmt = $pdo->query("SHOW COLUMNS FROM puantaj LIKE 'created_by'");
            $hasCreatedBy = $checkStmt->rowCount() > 0;
        } catch(PDOException $e) {}
        
        if ($hasCreatedBy) {
            $stmt = $pdo->prepare("INSERT INTO puantaj (personel_id, tarih, durum, saat, aciklama, created_by) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$personel_id, $tarih, $durum, $saat, $aciklama, $userId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO puantaj (personel_id, tarih, durum, saat, aciklama) VALUES (?,?,?,?,?)");
            $stmt->execute([$personel_id, $tarih, $durum, $saat, $aciklama]);
        }
        
        $newId = $pdo->lastInsertId();
        logUserAction('puantaj', 'INSERT', $newId, "Yeni puantaj eklendi: $tarih");
        $ay = (int)date('n', strtotime($tarih));
        $yil = (int)date('Y', strtotime($tarih));
        safeRedirect('puantaj.php?ay=' . $ay . '&yil=' . $yil . '&success=1');
    } elseif ($action === 'delete') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) safeRedirect('puantaj.php?error=' . urlencode('Geçersiz ID'));
        $row = $pdo->prepare('SELECT tarih FROM puantaj WHERE id=?');
        $row->execute([$id]);
        $r = $row->fetch();
        $pdo->prepare('DELETE FROM puantaj WHERE id=?')->execute([$id]);
        logUserAction('puantaj', 'DELETE', $id, "Puantaj silindi");
        $ay = $r ? (int)date('n', strtotime($r['tarih'])) : (int)date('n');
        $yil = $r ? (int)date('Y', strtotime($r['tarih'])) : (int)date('Y');
        safeRedirect('puantaj_ekstre.php?mode=donem&ay=' . $ay . '&yil=' . $yil . '&success=1');
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $tarih = $_POST['tarih'] ?? null;
        $durum = $_POST['durum'] ?? 'Calisti';
        $saat = isset($_POST['saat']) ? floatval($_POST['saat']) : 0;
        $aciklama = $_POST['aciklama'] ?? null;
        if ($id <= 0 || !$tarih) safeRedirect('puantaj.php?error=' . urlencode('Geçersiz veri'));
        
        $userId = getCurrentUserId();
        
        // updated_by kontrolü
        $hasUpdatedBy = false;
        try {
            $checkStmt = $pdo->query("SHOW COLUMNS FROM puantaj LIKE 'updated_by'");
            $hasUpdatedBy = $checkStmt->rowCount() > 0;
        } catch(PDOException $e) {}
        
        if ($hasUpdatedBy) {
            $stmt = $pdo->prepare('UPDATE puantaj SET tarih=?, durum=?, saat=?, aciklama=?, updated_by=? WHERE id=?');
            $stmt->execute([$tarih, $durum, $saat, $aciklama, $userId, $id]);
        } else {
            $stmt = $pdo->prepare('UPDATE puantaj SET tarih=?, durum=?, saat=?, aciklama=? WHERE id=?');
            $stmt->execute([$tarih, $durum, $saat, $aciklama, $id]);
        }
        
        logUserAction('puantaj', 'UPDATE', $id, "Puantaj güncellendi: $tarih");
        $ay = (int)date('n', strtotime($tarih));
        $yil = (int)date('Y', strtotime($tarih));
        safeRedirect('puantaj_ekstre.php?mode=donem&ay=' . $ay . '&yil=' . $yil . '&success=1');
    } elseif ($action === 'bulk_insert') {
        $tarih = $_POST['tarih'] ?? date('Y-m-d');
        $items = $_POST['items'] ?? [];
        if (empty($items)) safeRedirect('toplu_puantaj.php?error=' . urlencode('Kayıt seçilmedi'));
        
        $userId = getCurrentUserId();
        
        // created_by kontrolü
        $hasCreatedBy = false;
        try {
            $checkStmt = $pdo->query("SHOW COLUMNS FROM puantaj LIKE 'created_by'");
            $hasCreatedBy = $checkStmt->rowCount() > 0;
        } catch(PDOException $e) {}
        
        if ($hasCreatedBy) {
            $stmt = $pdo->prepare('INSERT INTO puantaj (personel_id, tarih, durum, saat, aciklama, created_by) VALUES (?,?,?,?,?,?)');
        } else {
            $stmt = $pdo->prepare('INSERT INTO puantaj (personel_id, tarih, durum, saat, aciklama) VALUES (?,?,?,?,?)');
        }
        
        $pdo->beginTransaction();
        foreach ($items as $pid => $it) {
            if (!isset($it['dahil'])) continue;
            $personel_id = (int)$pid;
            $durum = $it['durum'] ?? 'Calisti';
            $saat = isset($it['saat']) ? floatval($it['saat']) : 8.00;
            $aciklama = $it['aciklama'] ?? null;
            if ($personel_id > 0) {
                if ($hasCreatedBy) {
                    $stmt->execute([$personel_id, $tarih, $durum, $saat, $aciklama, $userId]);
                } else {
                    $stmt->execute([$personel_id, $tarih, $durum, $saat, $aciklama]);
                }
                $newId = $pdo->lastInsertId();
                logUserAction('puantaj', 'INSERT', $newId, "Toplu puantaj eklendi: Personel ID $personel_id, $tarih");
            }
        }
        $pdo->commit();
        $ay = (int)date('n', strtotime($tarih));
        $yil = (int)date('Y', strtotime($tarih));
        safeRedirect('puantaj.php?ay=' . $ay . '&yil=' . $yil . '&success=1');
    } else {
        safeRedirect('puantaj.php?error=' . urlencode('Bilinmeyen işlem'));
    }
} catch (Throwable $e) {
    error_log("Puantaj işlem hatası: " . $e->getMessage());
    safeRedirect('puantaj.php?error=' . urlencode($e->getMessage()));
}


