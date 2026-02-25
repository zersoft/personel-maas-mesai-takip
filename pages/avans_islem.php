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

require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Giriş kontrolü
requireRole('user');

ob_start();

// Tüm POST istekleri için CSRF doğrulaması
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

try {
    // DELETE (POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) throw new Exception('Geçersiz avans.');
        $del = $pdo->prepare('DELETE FROM avans_takip WHERE id = ?');
        $del->execute([$id]);
        logUserAction('avans_takip', 'DELETE', $id, "Avans silindi");
        safeRedirect('avans_takip.php?success=' . urlencode('Avans silindi.'));
    }

    // Ortak girişler (INSERT/UPDATE)
    $action = $_POST['action'] ?? 'insert';
    $personel_id = isset($_POST['personel_id']) ? (int)$_POST['personel_id'] : 0;
    $tarih = $_POST['tarih'] ?? date('Y-m-d');
    $bordro_ay = isset($_POST['bordro_ay']) && $_POST['bordro_ay'] !== '' ? (int)$_POST['bordro_ay'] : null;
    $bordro_yil = isset($_POST['bordro_yil']) && $_POST['bordro_yil'] !== '' ? (int)$_POST['bordro_yil'] : null;
    $aciklama = $_POST['aciklama'] ?? '';
    if ($personel_id <= 0) throw new Exception('Geçersiz personel.');

    $banka_tutari = parseMoney($_POST['banka_tutari'] ?? 0);
    $nakit_tutari = parseMoney($_POST['nakit_tutari'] ?? 0);
    $avans_tutari = parseMoney($_POST['avans_tutari'] ?? 0);
    if (($banka_tutari + $nakit_tutari) == 0 && $avans_tutari > 0) {
        $banka_tutari = $avans_tutari;
    }

    if ($action === 'update') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) throw new Exception('Geçersiz avans.');
        
        // updated_by kontrolü
        $hasUpdatedBy = false;
        try {
            $checkStmt = $pdo->query("SHOW COLUMNS FROM avans_takip LIKE 'updated_by'");
            $hasUpdatedBy = $checkStmt->rowCount() > 0;
        } catch(PDOException $e) {}
        
        $userId = getCurrentUserId();
        
        if ($hasUpdatedBy) {
            $upd = $pdo->prepare('UPDATE avans_takip SET personel_id=?, tarih=?, bordro_ay=?, bordro_yil=?, avans_tutari=?, banka_tutari=?, nakit_tutari=?, aciklama=?, updated_by=? WHERE id=?');
            $upd->execute([$personel_id, $tarih, $bordro_ay, $bordro_yil, ($banka_tutari + $nakit_tutari), $banka_tutari, $nakit_tutari, $aciklama, $userId, $id]);
        } else {
            $upd = $pdo->prepare('UPDATE avans_takip SET personel_id=?, tarih=?, bordro_ay=?, bordro_yil=?, avans_tutari=?, banka_tutari=?, nakit_tutari=?, aciklama=? WHERE id=?');
            $upd->execute([$personel_id, $tarih, $bordro_ay, $bordro_yil, ($banka_tutari + $nakit_tutari), $banka_tutari, $nakit_tutari, $aciklama, $id]);
        }
        
        logUserAction('avans_takip', 'UPDATE', $id, "Avans güncellendi: " . formatMoney($banka_tutari + $nakit_tutari));
        safeRedirect('avans_takip.php?success=' . urlencode('Avans güncellendi.'));
    } else {
        // created_by kontrolü
        $hasCreatedBy = false;
        try {
            $checkStmt = $pdo->query("SHOW COLUMNS FROM avans_takip LIKE 'created_by'");
            $hasCreatedBy = $checkStmt->rowCount() > 0;
        } catch(PDOException $e) {}
        
        $userId = getCurrentUserId();
        
        if ($hasCreatedBy) {
            $ins = $pdo->prepare('INSERT INTO avans_takip (personel_id, tarih, bordro_ay, bordro_yil, avans_tutari, banka_tutari, nakit_tutari, aciklama, created_by) VALUES (?,?,?,?,?,?,?,?,?)');
            $ins->execute([$personel_id, $tarih, $bordro_ay, $bordro_yil, ($banka_tutari + $nakit_tutari), $banka_tutari, $nakit_tutari, $aciklama, $userId]);
        } else {
            $ins = $pdo->prepare('INSERT INTO avans_takip (personel_id, tarih, bordro_ay, bordro_yil, avans_tutari, banka_tutari, nakit_tutari, aciklama) VALUES (?,?,?,?,?,?,?,?)');
            $ins->execute([$personel_id, $tarih, $bordro_ay, $bordro_yil, ($banka_tutari + $nakit_tutari), $banka_tutari, $nakit_tutari, $aciklama]);
        }
        
        $newId = $pdo->lastInsertId();
        logUserAction('avans_takip', 'INSERT', $newId, "Yeni avans eklendi: " . formatMoney($banka_tutari + $nakit_tutari));
        safeRedirect('avans_takip.php?success=1');
    }
} catch (Throwable $e) {
    error_log("Avans işlem hatası: " . $e->getMessage());
    safeRedirect('avans_takip.php?error=' . urlencode($e->getMessage()));
}


