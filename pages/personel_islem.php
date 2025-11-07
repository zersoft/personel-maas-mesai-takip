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

require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Giriş kontrolü
requireLogin();

ob_start();

// Silme işlemi (soft delete)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        $id = (int)$_GET['id'];
        if ($id <= 0) {
            throw new Exception('Geçersiz ID');
        }
        $stmt = $pdo->prepare("UPDATE personel_listesi SET aktif = 0, silinme_tarihi = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        logUserAction('personel_listesi', 'DELETE', $id, "Personel silindi (soft delete)");
        safeRedirect('personel_listesi.php?success=' . urlencode('Personel silindi (geri alınabilir).'));
    } catch(PDOException $e) {
        error_log("Personel silme hatası: " . $e->getMessage());
        safeRedirect('personel_listesi.php?error=' . urlencode($e->getMessage()));
    }
}

// Geri alma (restore)
if (isset($_GET['action']) && $_GET['action'] === 'restore' && isset($_GET['id'])) {
    try {
        $id = (int)$_GET['id'];
        if ($id <= 0) { throw new Exception('Geçersiz ID'); }
        $stmt = $pdo->prepare("UPDATE personel_listesi SET aktif = 1, silinme_tarihi = NULL WHERE id = ?");
        $stmt->execute([$id]);
        logUserAction('personel_listesi', 'UPDATE', $id, "Personel geri alındı");
        safeRedirect('personel_listesi.php?durum=silinmis&success=' . urlencode('Personel geri alındı.'));
    } catch(PDOException $e) {
        error_log("Personel geri alma hatası: " . $e->getMessage());
        safeRedirect('personel_listesi.php?error=' . urlencode($e->getMessage()));
    }
}

// Güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    try {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : null;
        if (!$id || $id <= 0) {
            throw new Exception('Geçersiz personel ID');
        }
        
        $ad_soyad = $_POST['ad_soyad'] ?? '';
        $tc_no = $_POST['tc_no'] ?? null;
        $pozisyon = $_POST['pozisyon'] ?? null;
        
        // Para alanlarını parse et
        $maas = parseMoney($_POST['maas'] ?? 0);
        $maas_sgk = parseMoney($_POST['maas_sgk'] ?? 0);
        $mesai_saat_ucreti = parseMoney($_POST['mesai_saat_ucreti'] ?? 0);
        
        $ise_giris_tarihi = $_POST['ise_giris_tarihi'] ?? null;
        $banka_adi = $_POST['banka_adi'] ?? null;
        $iban = $_POST['iban'] ?? null;
        $aktif = isset($_POST['aktif']) ? 1 : 0;
        
        $userId = getCurrentUserId();

        // updated_by ve updated_at alanlarını kontrol et (eğer varsa)
        $hasUpdatedBy = false;
        try {
            $checkStmt = $pdo->query("SHOW COLUMNS FROM personel_listesi LIKE 'updated_by'");
            $hasUpdatedBy = $checkStmt->rowCount() > 0;
        } catch(PDOException $e) {}

        if ($hasUpdatedBy) {
            $stmt = $pdo->prepare("
                UPDATE personel_listesi SET
                    ad_soyad = ?, tc_no = ?, pozisyon = ?, maas = ?, maas_sgk = ?, 
                    ise_giris_tarihi = ?, banka_adi = ?, iban = ?, mesai_saat_ucreti = ?, aktif = ?, updated_by = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $ad_soyad,
                $tc_no ?: null,
                $pozisyon ?: null,
                $maas,
                $maas_sgk,
                $ise_giris_tarihi ?: null,
                $banka_adi ?: null,
                $iban ?: null,
                $mesai_saat_ucreti,
                $aktif,
                $userId,
                $id
            ]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE personel_listesi SET
                    ad_soyad = ?, tc_no = ?, pozisyon = ?, maas = ?, maas_sgk = ?, 
                    ise_giris_tarihi = ?, banka_adi = ?, iban = ?, mesai_saat_ucreti = ?, aktif = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $ad_soyad,
                $tc_no ?: null,
                $pozisyon ?: null,
                $maas,
                $maas_sgk,
                $ise_giris_tarihi ?: null,
                $banka_adi ?: null,
                $iban ?: null,
                $mesai_saat_ucreti,
                $aktif,
                $id
            ]);
        }

        logUserAction('personel_listesi', 'UPDATE', $id, "Personel güncellendi: $ad_soyad");
        safeRedirect('personel_listesi.php?success=1');
    } catch(PDOException $e) {
        error_log("Personel güncelleme hatası: " . $e->getMessage());
        safeRedirect('personel_duzenle.php?id=' . ($id ?? '') . '&error=' . urlencode($e->getMessage()));
    } catch(Exception $e) {
        error_log("Personel güncelleme hatası: " . $e->getMessage());
        safeRedirect('personel_duzenle.php?id=' . ($id ?? '') . '&error=' . urlencode($e->getMessage()));
    }
}

// Ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $ad_soyad = $_POST['ad_soyad'] ?? '';
        $tc_no = $_POST['tc_no'] ?? null;
        $pozisyon = $_POST['pozisyon'] ?? null;
        
        // Para alanlarını parse et
        $maas = parseMoney($_POST['maas'] ?? 0);
        $maas_sgk = parseMoney($_POST['maas_sgk'] ?? 0);
        $mesai_saat_ucreti = parseMoney($_POST['mesai_saat_ucreti'] ?? 0);
        
        $ise_giris_tarihi = $_POST['ise_giris_tarihi'] ?? null;
        $banka_adi = $_POST['banka_adi'] ?? null;
        $iban = $_POST['iban'] ?? null;
        $aktif = isset($_POST['aktif']) ? 1 : 0;
        
        $userId = getCurrentUserId();
        
        // created_by alanını kontrol et (eğer varsa)
        $hasCreatedBy = false;
        try {
            $checkStmt = $pdo->query("SHOW COLUMNS FROM personel_listesi LIKE 'created_by'");
            $hasCreatedBy = $checkStmt->rowCount() > 0;
        } catch(PDOException $e) {}

        if ($hasCreatedBy) {
            $stmt = $pdo->prepare("
                INSERT INTO personel_listesi 
                (ad_soyad, tc_no, pozisyon, maas, maas_sgk, ise_giris_tarihi, banka_adi, iban, mesai_saat_ucreti, aktif, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $ad_soyad,
                $tc_no ?: null,
                $pozisyon ?: null,
                $maas,
                $maas_sgk,
                $ise_giris_tarihi ?: null,
                $banka_adi ?: null,
                $iban ?: null,
                $mesai_saat_ucreti,
                $aktif,
                $userId
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO personel_listesi 
                (ad_soyad, tc_no, pozisyon, maas, maas_sgk, ise_giris_tarihi, banka_adi, iban, mesai_saat_ucreti, aktif) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $ad_soyad,
                $tc_no ?: null,
                $pozisyon ?: null,
                $maas,
                $maas_sgk,
                $ise_giris_tarihi ?: null,
                $banka_adi ?: null,
                $iban ?: null,
                $mesai_saat_ucreti,
                $aktif
            ]);
        }

        $newId = $pdo->lastInsertId();
        logUserAction('personel_listesi', 'INSERT', $newId, "Yeni personel eklendi: $ad_soyad");
        safeRedirect('personel_listesi.php?success=1');
    } catch(PDOException $e) {
        error_log("Personel ekleme hatası: " . $e->getMessage());
        safeRedirect('personel_listesi.php?error=' . urlencode($e->getMessage()));
    } catch(Exception $e) {
        error_log("Personel ekleme hatası: " . $e->getMessage());
        safeRedirect('personel_listesi.php?error=' . urlencode($e->getMessage()));
    }
} else {
    safeRedirect('personel_listesi.php');
}
?>


