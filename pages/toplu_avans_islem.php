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

// Çıktı tamponu
if (ob_get_level() === 0) { ob_start(); }

require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Giriş ve yetki kontrolü
requireRole('user');

// CSRF koruması
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
} else {
    safeRedirect('avans_takip.php');
}

try {
    $bordro_ay = isset($_POST['bordro_ay']) ? (int)$_POST['bordro_ay'] : (int)date('n');
    $bordro_yil = isset($_POST['bordro_yil']) ? (int)$_POST['bordro_yil'] : (int)date('Y');
    if ($bordro_ay < 1 || $bordro_ay > 12) { $bordro_ay = (int)date('n'); }
    if ($bordro_yil < 2000 || $bordro_yil > 2100) { $bordro_yil = (int)date('Y'); }

    $items = $_POST['items'] ?? [];
    if (empty($items) || !is_array($items)) {
        throw new Exception('Kaydedilecek veri bulunamadı.');
    }

    $userId = getCurrentUserId();

    // created_by ve updated_by kolon kontrolleri
    $hasCreatedBy = false;
    $hasUpdatedBy = false;
    try {
        $cStmt = $pdo->query("SHOW COLUMNS FROM avans_takip LIKE 'created_by'");
        $hasCreatedBy = $cStmt->rowCount() > 0;
        $uStmt = $pdo->query("SHOW COLUMNS FROM avans_takip LIKE 'updated_by'");
        $hasUpdatedBy = $uStmt->rowCount() > 0;
    } catch (PDOException $e) {}

    $eklenenSayisi = 0;
    $guncellenenSayisi = 0;
    $silinenSayisi = 0;

    $pdo->beginTransaction();

    // Hazırlanmış sorgular
    if ($hasCreatedBy) {
        $insertStmt = $pdo->prepare("INSERT INTO avans_takip 
            (personel_id, tarih, bordro_ay, bordro_yil, avans_tutari, banka_tutari, nakit_tutari, aciklama, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    } else {
        $insertStmt = $pdo->prepare("INSERT INTO avans_takip 
            (personel_id, tarih, bordro_ay, bordro_yil, avans_tutari, banka_tutari, nakit_tutari, aciklama) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    }

    if ($hasUpdatedBy) {
        $updateStmt = $pdo->prepare("UPDATE avans_takip 
            SET personel_id = ?, tarih = ?, bordro_ay = ?, bordro_yil = ?, avans_tutari = ?, banka_tutari = ?, nakit_tutari = ?, aciklama = ?, updated_by = ? 
            WHERE id = ?");
    } else {
        $updateStmt = $pdo->prepare("UPDATE avans_takip 
            SET personel_id = ?, tarih = ?, bordro_ay = ?, bordro_yil = ?, avans_tutari = ?, banka_tutari = ?, nakit_tutari = ?, aciklama = ? 
            WHERE id = ?");
    }

    $deleteStmt = $pdo->prepare("DELETE FROM avans_takip WHERE id = ?");

    foreach ($items as $it) {
        // Sadece dahil edilmiş (işaretli) kayıtlar işlenir
        if (!isset($it['dahil']) || (int)$it['dahil'] !== 1) {
            continue;
        }

        $existingId = isset($it['existing_id']) ? (int)$it['existing_id'] : 0;
        $personelId = isset($it['personel_id']) ? (int)$it['personel_id'] : 0;
        if ($personelId <= 0) {
            continue;
        }

        $tarih = !empty($it['tarih']) ? trim($it['tarih']) : date('Y-m-d');
        $bankaTutari = parseMoney($it['banka_tutari'] ?? 0);
        $nakitTutari = parseMoney($it['nakit_tutari'] ?? 0);
        $avansTutari = $bankaTutari + $nakitTutari;
        $aciklama = isset($it['aciklama']) ? trim($it['aciklama']) : '';

        if ($existingId > 0) {
            // Mevcut kayıt
            if ($avansTutari > 0) {
                // Güncelle
                if ($hasUpdatedBy) {
                    $updateStmt->execute([$personelId, $tarih, $bordro_ay, $bordro_yil, $avansTutari, $bankaTutari, $nakitTutari, $aciklama, $userId, $existingId]);
                } else {
                    $updateStmt->execute([$personelId, $tarih, $bordro_ay, $bordro_yil, $avansTutari, $bankaTutari, $nakitTutari, $aciklama, $existingId]);
                }
                $guncellenenSayisi++;
                logUserAction('avans_takip', 'UPDATE', $existingId, "Toplu avans güncellendi: " . formatMoney($avansTutari));
            } else {
                // Tutar 0 yapılmışsa kaydı sil
                $deleteStmt->execute([$existingId]);
                $silinenSayisi++;
                logUserAction('avans_takip', 'DELETE', $existingId, "Toplu işlemde tutar 0 yapıldığı için avans silindi");
            }
        } else {
            // Yeni kayıt (yalnızca tutar > 0 ise ekle)
            if ($avansTutari > 0) {
                if ($hasCreatedBy) {
                    $insertStmt->execute([$personelId, $tarih, $bordro_ay, $bordro_yil, $avansTutari, $bankaTutari, $nakitTutari, $aciklama, $userId]);
                } else {
                    $insertStmt->execute([$personelId, $tarih, $bordro_ay, $bordro_yil, $avansTutari, $bankaTutari, $nakitTutari, $aciklama]);
                }
                $newId = (int)$pdo->lastInsertId();
                $eklenenSayisi++;
                logUserAction('avans_takip', 'INSERT', $newId, "Toplu avans eklendi: " . formatMoney($avansTutari));
            }
        }
    }

    $pdo->commit();

    if (ob_get_level() > 0) { @ob_end_clean(); }
    $redirectUrl = sprintf('toplu_avans.php?ay=%d&yil=%d&success=1&eklenen=%d&guncellenen=%d&silinen=%d', $bordro_ay, $bordro_yil, $eklenenSayisi, $guncellenenSayisi, $silinenSayisi);
    safeRedirect($redirectUrl);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Toplu avans işlem hatası: " . $e->getMessage());
    if (ob_get_level() > 0) { @ob_end_clean(); }
    $redirectUrl = sprintf('toplu_avans.php?ay=%d&yil=%d&error=%s', $bordro_ay ?? date('n'), $bordro_yil ?? date('Y'), urlencode('İşlem sırasında bir hata oluştu: ' . $e->getMessage()));
    safeRedirect($redirectUrl);
}
