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

// Çıktı tamponu: yönlendirme sorunlarını engelle
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
        $stmt = $pdo->prepare("DELETE FROM fazla_mesai WHERE id = ?");
        $stmt->execute([$id]);
        logUserAction('fazla_mesai', 'DELETE', $id, "Fazla mesai silindi");
        if (ob_get_level() > 0) { @ob_end_clean(); }
        // Filtre parametrelerini koru (referer'dan)
        $returnParams = '';
        if (isset($_SERVER['HTTP_REFERER'])) {
            $referer = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_QUERY);
            if ($referer) {
                parse_str($referer, $params);
                unset($params['id'], $params['success'], $params['error']);
                if (!empty($params)) $returnParams = '&' . http_build_query($params);
            }
        }
        safeRedirect('fazla_mesai.php?success=1' . $returnParams);
    } catch(PDOException $e) {
        if (ob_get_level() > 0) { @ob_end_clean(); }
        safeRedirect('fazla_mesai.php?error=' . urlencode($e->getMessage()));
    }
}

// Güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    try {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : null; // SQL injection koruması için integer cast
        if (!$id || $id <= 0) {
            throw new Exception('Geçersiz fazla mesai ID');
        }
        
        $personel_id = isset($_POST['personel_id']) ? (int)$_POST['personel_id'] : null;
        $tarih = $_POST['tarih'] ?? null;
        $saat = isset($_POST['saat']) ? (float)$_POST['saat'] : 0;
        $saat_ucreti = isset($_POST['saat_ucreti_raw'])
            ? parseMoneyLocal($_POST['saat_ucreti_raw'])
            : parseMoneyLocal($_POST['saat_ucreti'] ?? 0);
        $aciklama = $_POST['aciklama'] ?? null;

        if (!$personel_id || !$tarih) {
            throw new Exception('Personel ve tarih seçilmedi');
        }

        // Sunucu tarafı doğrulama (şema sınırları ile uyumlu)
        if ($saat < 0) { $saat = 0; }
        if ($saat > 999.99) { $saat = 999.99; } // DECIMAL(5,2)
        if ($saat_ucreti < 0) { $saat_ucreti = 0; }
        if ($saat_ucreti > 9999.99) {
            throw new Exception('Saat ücreti çok yüksek (maksimum 9.999,99 ₺).');
        }

        $userId = getCurrentUserId();
        
        // updated_by kontrolü
        $hasUpdatedBy = false;
        try {
            $checkStmt = $pdo->query("SHOW COLUMNS FROM fazla_mesai LIKE 'updated_by'");
            $hasUpdatedBy = $checkStmt->rowCount() > 0;
        } catch(PDOException $e) {}

        // Tutar hesapla
        $tutar = $saat * $saat_ucreti;

        if ($hasUpdatedBy) {
            $stmt = $pdo->prepare("
                UPDATE fazla_mesai SET
                    personel_id = ?, tarih = ?, saat = ?, saat_ucreti = ?, tutar = ?, aciklama = ?, updated_by = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $personel_id,
                $tarih,
                $saat,
                $saat_ucreti,
                $tutar,
                $aciklama ?: null,
                $userId,
                $id
            ]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE fazla_mesai SET
                    personel_id = ?, tarih = ?, saat = ?, saat_ucreti = ?, tutar = ?, aciklama = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $personel_id,
                $tarih,
                $saat,
                $saat_ucreti,
                $tutar,
                $aciklama ?: null,
                $id
            ]);
        }

        logUserAction('fazla_mesai', 'UPDATE', $id, "Fazla mesai güncellendi");
        if (ob_get_level() > 0) { @ob_end_clean(); }
        // Filtre parametrelerini koru
        $returnParams = isset($_POST['return_params']) ? $_POST['return_params'] : '';
        safeRedirect('fazla_mesai.php?success=1' . ($returnParams ? '&' . $returnParams : ''));
    } catch(PDOException $e) {
        if (ob_get_level() > 0) { @ob_end_clean(); }
        safeRedirect('fazla_mesai_duzenle.php?id=' . ($id ?? '') . '&error=' . urlencode($e->getMessage()));
    } catch(Exception $e) {
        if (ob_get_level() > 0) { @ob_end_clean(); }
        safeRedirect('fazla_mesai_duzenle.php?id=' . ($id ?? '') . '&error=' . urlencode($e->getMessage()));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $personel_id = isset($_POST['personel_id']) ? (int)$_POST['personel_id'] : null;
        $tarih = $_POST['tarih'] ?? null;
        $saat = isset($_POST['saat']) ? (float)$_POST['saat'] : 0;
        $saat_ucreti = isset($_POST['saat_ucreti_raw'])
            ? parseMoneyLocal($_POST['saat_ucreti_raw'])
            : parseMoneyLocal($_POST['saat_ucreti'] ?? 0);
        $aciklama = $_POST['aciklama'] ?? null;

        if (!$personel_id || !$tarih) {
            throw new Exception('Personel ve tarih seçilmedi');
        }

        // Sunucu tarafı doğrulama (şema sınırları ile uyumlu)
        if ($saat < 0) { $saat = 0; }
        if ($saat > 999.99) { $saat = 999.99; } // DECIMAL(5,2)
        if ($saat_ucreti < 0) { $saat_ucreti = 0; }
        if ($saat_ucreti > 9999.99) {
            throw new Exception('Saat ücreti çok yüksek (maksimum 9.999,99 ₺).');
        }

        $userId = getCurrentUserId();
        
        // created_by kontrolü
        $hasCreatedBy = false;
        try {
            $checkStmt = $pdo->query("SHOW COLUMNS FROM fazla_mesai LIKE 'created_by'");
            $hasCreatedBy = $checkStmt->rowCount() > 0;
        } catch(PDOException $e) {}

        // Tutar hesapla
        $tutar = $saat * $saat_ucreti;

        if ($hasCreatedBy) {
            $stmt = $pdo->prepare("
                INSERT INTO fazla_mesai 
                (personel_id, tarih, saat, saat_ucreti, tutar, aciklama, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $personel_id,
                $tarih,
                $saat,
                $saat_ucreti,
                $tutar,
                $aciklama ?: null,
                $userId
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO fazla_mesai 
                (personel_id, tarih, saat, saat_ucreti, tutar, aciklama) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $personel_id,
                $tarih,
                $saat,
                $saat_ucreti,
                $tutar,
                $aciklama ?: null
            ]);
        }

        $newId = $pdo->lastInsertId();
        logUserAction('fazla_mesai', 'INSERT', $newId, "Yeni fazla mesai eklendi");
        if (ob_get_level() > 0) { @ob_end_clean(); }
        safeRedirect('fazla_mesai.php?success=1');
    } catch(PDOException $e) {
        if (ob_get_level() > 0) { @ob_end_clean(); }
        safeRedirect('fazla_mesai.php?error=' . urlencode($e->getMessage()));
    } catch(Exception $e) {
        if (ob_get_level() > 0) { @ob_end_clean(); }
        safeRedirect('fazla_mesai.php?error=' . urlencode($e->getMessage()));
    }
}

// Toplu ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_insert') {
    try {
        $tarih = $_POST['tarih'] ?? date('Y-m-d');
        $items = $_POST['items'] ?? [];
        
        if (empty($items)) {
            throw new Exception('Kayıt seçilmedi');
        }
        
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO fazla_mesai (personel_id, tarih, saat, saat_ucreti, tutar, aciklama) VALUES (?,?,?,?,?,?)');
        
        foreach ($items as $pid => $it) {
            if (!isset($it['dahil'])) continue;
            $personel_id = (int)$pid;
            $saat = isset($it['saat']) ? floatval($it['saat']) : 0;
            if ($saat <= 0) continue;
            
            $saat_ucreti = isset($it['saat_ucreti_raw']) 
                ? parseMoneyLocal($it['saat_ucreti_raw']) 
                : parseMoneyLocal($it['saat_ucreti'] ?? 0);
            $aciklama = $it['aciklama'] ?? null;
            
            if ($personel_id > 0 && $saat > 0) {
                $tutar = $saat * $saat_ucreti;
                $stmt->execute([$personel_id, $tarih, $saat, $saat_ucreti, $tutar, $aciklama]);
            }
        }
        
        $pdo->commit();
        if (ob_get_level() > 0) { @ob_end_clean(); }
        safeRedirect('fazla_mesai.php?success=1');
    } catch(PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (ob_get_level() > 0) { @ob_end_clean(); }
        safeRedirect('toplu_fazla_mesai.php?error=' . urlencode($e->getMessage()));
    } catch(Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (ob_get_level() > 0) { @ob_end_clean(); }
        safeRedirect('toplu_fazla_mesai.php?error=' . urlencode($e->getMessage()));
    }
}

if (!isset($_POST['action']) && !isset($_GET['action'])) {
    if (ob_get_level() > 0) { @ob_end_clean(); }
    safeRedirect('fazla_mesai.php');
}
?>

