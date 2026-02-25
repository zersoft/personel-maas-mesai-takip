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

ob_start(); // Output buffering başlat
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Giriş kontrolü
requireRole('user');

// Tüm POST istekleri için CSRF doğrulaması
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

// Silme işlemi (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    try {
        $id = (int)$_POST['id'];
        if ($id <= 0) {
            throw new Exception('Geçersiz ID');
        }
        $stmt = $pdo->prepare("DELETE FROM bordro WHERE id = ?");
        $stmt->execute([$id]);
        logUserAction('bordro', 'DELETE', $id, "Bordro silindi");
        safeRedirect('bordro.php?success=1');
    } catch(PDOException $e) {
        error_log("Bordro silme hatası: " . $e->getMessage());
        safeRedirect('bordro.php?error=' . urlencode($e->getMessage()));
    }
}

// Güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    try {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : null; // SQL injection koruması için integer cast
        if (!$id || $id <= 0) {
            throw new Exception('Geçersiz bordro ID');
        }
        
        
        $personel_id = isset($_POST['personel_id']) ? (int)$_POST['personel_id'] : null;
        $yil = isset($_POST['yil']) ? (int)$_POST['yil'] : date('Y');
        $ay = isset($_POST['ay']) ? (int)$_POST['ay'] : date('n');
        
        // Validasyon
        if (!$personel_id || $personel_id <= 0) {
            throw new Exception('Geçersiz personel ID');
        }
        if ($ay < 1 || $ay > 12) {
            throw new Exception('Geçersiz ay değeri');
        }
        if ($yil < 2000 || $yil > 2100) {
            throw new Exception('Geçersiz yıl değeri');
        }
        
        $brut_maas = parseMoney($_POST['brut_maas_raw'] ?? ($_POST['brut_maas'] ?? 0));
        $sgk_banka = parseMoney($_POST['sgk_banka_raw'] ?? ($_POST['sgk_banka'] ?? 0));
        // Ek ödenek kanal bazında
        $ek_odenek_banka = parseMoney($_POST['ek_odenek_banka_raw'] ?? ($_POST['ek_odenek_banka'] ?? 0));
        $ek_odenek_nakit = parseMoney($_POST['ek_odenek_nakit_raw'] ?? ($_POST['ek_odenek_nakit'] ?? 0));
        if (($ek_odenek_banka + $ek_odenek_nakit) == 0) {
            $legacy_eko = parseMoney($_POST['ek_odenek'] ?? 0);
            if ($legacy_eko > 0) { $ek_odenek_nakit = $legacy_eko; }
        }
        $ek_odenek = $ek_odenek_banka + $ek_odenek_nakit;
        $izin_gunu = isset($_POST['izin_gunu']) ? floatval($_POST['izin_gunu']) : 0;
        $izin_kesintisi = parseMoney($_POST['izin_kesintisi_raw'] ?? ($_POST['izin_kesintisi'] ?? 0));
        $sgk_kesintisi = parseMoney($_POST['sgk_kesintisi_raw'] ?? ($_POST['sgk_kesintisi'] ?? 0));
        $diger_kesintiler = parseMoney($_POST['diger_kesintiler_raw'] ?? ($_POST['diger_kesintiler'] ?? 0));
        $kesinti_aciklama = isset($_POST['kesinti_aciklama']) ? trim($_POST['kesinti_aciklama']) : null;
        $aciklama = isset($_POST['aciklama']) ? trim($_POST['aciklama']) : null;

        // İlgili dönem avanslarını hesapla (bordro_ay/yil öncelikli, yoksa tarih)
        $avSorgu = $pdo->prepare("SELECT 
                COALESCE(SUM(banka_tutari),0) AS banka,
                COALESCE(SUM(nakit_tutari),0) AS nakit
            FROM avans_takip 
            WHERE personel_id = ? AND (
                (bordro_ay = ? AND bordro_yil = ?) OR (bordro_ay IS NULL AND bordro_yil IS NULL AND MONTH(tarih) = ? AND YEAR(tarih) = ?)
            )");
        $avSorgu->execute([$personel_id, $ay, $yil, $ay, $yil]);
        $avRow = $avSorgu->fetch() ?: ['banka'=>0,'nakit'=>0];
        $banka_avans = (float)$avRow['banka'];
        $nakit_avans = (float)$avRow['nakit'];
        
        $userId = getCurrentUserId();
        
        // updated_by kontrolü
        $hasUpdatedBy = false;
        try {
            $checkStmt = $pdo->query("SHOW COLUMNS FROM bordro LIKE 'updated_by'");
            $hasUpdatedBy = $checkStmt->rowCount() > 0;
        } catch(PDOException $e) {}

        if ($hasUpdatedBy) {
            $stmt = $pdo->prepare("
                UPDATE bordro SET
                    personel_id = ?, yil = ?, ay = ?, brut_maas = ?, sgk_banka = ?, 
                    ek_odenek = ?, ek_odenek_banka = ?, ek_odenek_nakit = ?,
                    izin_gunu = ?, izin_kesintisi = ?, 
                    sgk_kesintisi = ?, diger_kesintiler = ?, kesinti_aciklama = ?, aciklama = ?,
                    banka_avans = ?, nakit_avans = ?, updated_by = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $personel_id,
                $yil,
                $ay,
                $brut_maas,
                $sgk_banka,
                $ek_odenek,
                $ek_odenek_banka,
                $ek_odenek_nakit,
                $izin_gunu,
                $izin_kesintisi,
                $sgk_kesintisi,
                $diger_kesintiler,
                $kesinti_aciklama ?: null,
                $aciklama ?: null,
                $banka_avans,
                $nakit_avans,
                $userId,
                $id
            ]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE bordro SET
                    personel_id = ?, yil = ?, ay = ?, brut_maas = ?, sgk_banka = ?, 
                    ek_odenek = ?, ek_odenek_banka = ?, ek_odenek_nakit = ?,
                    izin_gunu = ?, izin_kesintisi = ?, 
                    sgk_kesintisi = ?, diger_kesintiler = ?, kesinti_aciklama = ?, aciklama = ?,
                    banka_avans = ?, nakit_avans = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $personel_id,
                $yil,
                $ay,
                $brut_maas,
                $sgk_banka,
                $ek_odenek,
                $ek_odenek_banka,
                $ek_odenek_nakit,
                $izin_gunu,
                $izin_kesintisi,
                $sgk_kesintisi,
                $diger_kesintiler,
                $kesinti_aciklama ?: null,
                $aciklama ?: null,
                $banka_avans,
                $nakit_avans,
                $id
            ]);
        }

        logUserAction('bordro', 'UPDATE', $id, "Bordro güncellendi: Ay $ay/$yil");
        safeRedirect('bordro.php?success=1');
    } catch(PDOException $e) {
        error_log("Bordro güncelleme hatası: " . $e->getMessage());
        safeRedirect('bordro_duzenle.php?id=' . ($id ?? '') . '&error=' . urlencode($e->getMessage()));
    } catch(Exception $e) {
        error_log("Bordro güncelleme hatası: " . $e->getMessage());
        safeRedirect('bordro_duzenle.php?id=' . ($id ?? '') . '&error=' . urlencode($e->getMessage()));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $personel_id = isset($_POST['personel_id']) ? (int)$_POST['personel_id'] : null;
        $yil = isset($_POST['yil']) ? (int)$_POST['yil'] : date('Y');
        $ay = isset($_POST['ay']) ? (int)$_POST['ay'] : date('n');
        
        // Ay ve yıl validasyonu
        if ($ay < 1 || $ay > 12) {
            throw new Exception('Geçersiz ay değeri');
        }
        if ($yil < 2000 || $yil > 2100) {
            throw new Exception('Geçersiz yıl değeri');
        }
        
        
        $brut_maas = parseMoney($_POST['brut_maas_raw'] ?? ($_POST['brut_maas'] ?? 0));
        $sgk_banka = parseMoney($_POST['sgk_banka_raw'] ?? ($_POST['sgk_banka'] ?? 0));
        // Ek ödenek kanal bazında
        $ek_odenek_banka = parseMoney($_POST['ek_odenek_banka_raw'] ?? ($_POST['ek_odenek_banka'] ?? 0));
        $ek_odenek_nakit = parseMoney($_POST['ek_odenek_nakit_raw'] ?? ($_POST['ek_odenek_nakit'] ?? 0));
        if (($ek_odenek_banka + $ek_odenek_nakit) == 0) {
            $legacy_eko = parseMoney($_POST['ek_odenek'] ?? 0);
            if ($legacy_eko > 0) { $ek_odenek_nakit = $legacy_eko; }
        }
        $ek_odenek = $ek_odenek_banka + $ek_odenek_nakit;
        $izin_gunu = $_POST['izin_gunu'] ?? 0;
        $izin_kesintisi = parseMoney($_POST['izin_kesintisi_raw'] ?? ($_POST['izin_kesintisi'] ?? 0));
        $sgk_kesintisi = parseMoney($_POST['sgk_kesintisi_raw'] ?? ($_POST['sgk_kesintisi'] ?? 0));
        $diger_kesintiler = parseMoney($_POST['diger_kesintiler_raw'] ?? ($_POST['diger_kesintiler'] ?? 0));
        $kesinti_aciklama = $_POST['kesinti_aciklama'] ?? null;
        $aciklama = $_POST['aciklama'] ?? null;

        if (!$personel_id) {
            throw new Exception('Personel seçilmedi');
        }

        // Bu personelin ilgili ay/yıl avansları (önce bordro_ay/yil; yoksa tarih ay/yıl)
        $avSorgu = $pdo->prepare("SELECT 
                COALESCE(SUM(banka_tutari),0) AS banka,
                COALESCE(SUM(nakit_tutari),0) AS nakit
            FROM avans_takip 
            WHERE personel_id = ? AND (
                (bordro_ay = ? AND bordro_yil = ?) OR (bordro_ay IS NULL AND bordro_yil IS NULL AND MONTH(tarih) = ? AND YEAR(tarih) = ?)
            )");
        $avSorgu->execute([$personel_id, $ay, $yil, $ay, $yil]);
        $avRow = $avSorgu->fetch() ?: ['banka'=>0,'nakit'=>0];
        $banka_avans = (float)$avRow['banka'];
        $nakit_avans = (float)$avRow['nakit'];
        
        $userId = getCurrentUserId();
        
        // created_by kontrolü
        $hasCreatedBy = false;
        try {
            $checkStmt = $pdo->query("SHOW COLUMNS FROM bordro LIKE 'created_by'");
            $hasCreatedBy = $checkStmt->rowCount() > 0;
        } catch(PDOException $e) {}

        if ($hasCreatedBy) {
            $stmt = $pdo->prepare("
                INSERT INTO bordro 
                (personel_id, yil, ay, brut_maas, sgk_banka, ek_odenek, ek_odenek_banka, ek_odenek_nakit,
                 izin_gunu, izin_kesintisi, sgk_kesintisi, diger_kesintiler, kesinti_aciklama, aciklama, banka_avans, nakit_avans, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $personel_id,
                $yil,
                $ay,
                $brut_maas,
                $sgk_banka,
                $ek_odenek,
                $ek_odenek_banka,
                $ek_odenek_nakit,
                $izin_gunu,
                $izin_kesintisi,
                $sgk_kesintisi,
                $diger_kesintiler,
                $kesinti_aciklama ?: null,
                $aciklama ?: null,
                $banka_avans,
                $nakit_avans,
                $userId
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO bordro 
                (personel_id, yil, ay, brut_maas, sgk_banka, ek_odenek, ek_odenek_banka, ek_odenek_nakit,
                 izin_gunu, izin_kesintisi, sgk_kesintisi, diger_kesintiler, kesinti_aciklama, aciklama, banka_avans, nakit_avans) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $personel_id,
                $yil,
                $ay,
                $brut_maas,
                $sgk_banka,
                $ek_odenek,
                $ek_odenek_banka,
                $ek_odenek_nakit,
                $izin_gunu,
                $izin_kesintisi,
                $sgk_kesintisi,
                $diger_kesintiler,
                $kesinti_aciklama ?: null,
                $aciklama ?: null,
                $banka_avans,
                $nakit_avans
            ]);
        }

        $newId = $pdo->lastInsertId();
        logUserAction('bordro', 'INSERT', $newId, "Yeni bordro eklendi: Ay $ay/$yil");
        safeRedirect('bordro.php?success=1');
    } catch(PDOException $e) {
        error_log("Bordro ekleme hatası: " . $e->getMessage());
        safeRedirect('bordro.php?error=' . urlencode($e->getMessage()));
    } catch(Exception $e) {
        error_log("Bordro ekleme hatası: " . $e->getMessage());
        safeRedirect('bordro.php?error=' . urlencode($e->getMessage()));
    }
} else {
    safeRedirect('bordro.php');
}
?>

