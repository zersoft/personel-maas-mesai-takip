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

// Output buffering başlat - header gönderilmeden önce çıktı olmasın
ob_start();

require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Giriş kontrolü
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $ay = isset($_POST['ay']) ? (int)$_POST['ay'] : date('n');
        $yil = isset($_POST['yil']) ? (int)$_POST['yil'] : date('Y');
        $selected_personel = isset($_POST['selected_personel']) ? $_POST['selected_personel'] : [];
        
        $brut_maaslar_raw = $_POST['brut_maas_raw'] ?? [];
        $sgk_bankalar_raw = $_POST['sgk_banka_raw'] ?? [];
        $ek_odenek_banka_raw = $_POST['ek_odenek_banka_raw'] ?? [];
        $ek_odenek_nakit_raw = $_POST['ek_odenek_nakit_raw'] ?? [];
        $brut_maaslar = $_POST['brut_maas'] ?? [];
        $sgk_bankalar = $_POST['sgk_banka'] ?? [];
        $ek_odenek_banka = $_POST['ek_odenek_banka'] ?? [];
        $ek_odenek_nakit = $_POST['ek_odenek_nakit'] ?? [];
        $izin_gunleri = $_POST['izin_gunu'] ?? [];
        $izin_kesintileri_raw = $_POST['izin_kesintisi_raw'] ?? [];
        $sgk_kesintileri_raw = $_POST['sgk_kesintisi_raw'] ?? [];
        $diger_kesintiler_raw = $_POST['diger_kesintiler_raw'] ?? [];
        $izin_kesintileri = $_POST['izin_kesintisi'] ?? [];
        $sgk_kesintileri = $_POST['sgk_kesintisi'] ?? [];
        $diger_kesintiler = $_POST['diger_kesintiler'] ?? [];

        if (empty($selected_personel)) {
            throw new Exception('Lütfen en az bir personel seçin');
        }

        $pdo->beginTransaction();
        
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
                 izin_gunu, izin_kesintisi, sgk_kesintisi, diger_kesintiler, banka_avans, nakit_avans, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO bordro 
                (personel_id, yil, ay, brut_maas, sgk_banka, ek_odenek, ek_odenek_banka, ek_odenek_nakit, 
                 izin_gunu, izin_kesintisi, sgk_kesintisi, diger_kesintiler, banka_avans, nakit_avans) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
        }

        $eklenenSayisi = 0;
        foreach($selected_personel as $personel_id) {
            $personel_id = (int)$personel_id;
            
            // Mevcut bordroyu kontrol et
            $check = $pdo->prepare("SELECT id FROM bordro WHERE personel_id = ? AND ay = ? AND yil = ?");
            $check->execute([$personel_id, $ay, $yil]);
            if ($check->fetch()) {
                continue; // Mevcut bordroyu atla
            }
            
            // Verileri hazırla
            $brut_maas = parseMoney($brut_maaslar_raw[$personel_id] ?? ($brut_maaslar[$personel_id] ?? 0));
            $sgk_banka = parseMoney($sgk_bankalar_raw[$personel_id] ?? ($sgk_bankalar[$personel_id] ?? 0));
            $eko_banka = parseMoney($ek_odenek_banka_raw[$personel_id] ?? ($ek_odenek_banka[$personel_id] ?? 0));
            $eko_nakit = parseMoney($ek_odenek_nakit_raw[$personel_id] ?? ($ek_odenek_nakit[$personel_id] ?? 0));
            $ek_odenek_toplam = $eko_banka + $eko_nakit;
            $izin_gunu = isset($izin_gunleri[$personel_id]) ? floatval($izin_gunleri[$personel_id]) : 0;
            $izin_kesintisi = parseMoney($izin_kesintileri_raw[$personel_id] ?? ($izin_kesintileri[$personel_id] ?? 0));
            $sgk_kesintisi = parseMoney($sgk_kesintileri_raw[$personel_id] ?? ($sgk_kesintileri[$personel_id] ?? 0));
            $diger_kesintiler_val = parseMoney($diger_kesintiler_raw[$personel_id] ?? ($diger_kesintiler[$personel_id] ?? 0));
            
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
            
            if ($hasCreatedBy) {
                $stmt->execute([
                    $personel_id,
                    $yil,
                    $ay,
                    $brut_maas,
                    $sgk_banka,
                    $ek_odenek_toplam,
                    $eko_banka,
                    $eko_nakit,
                    $izin_gunu,
                    $izin_kesintisi,
                    $sgk_kesintisi,
                    $diger_kesintiler_val,
                    $banka_avans,
                    $nakit_avans,
                    $userId
                ]);
            } else {
                $stmt->execute([
                    $personel_id,
                    $yil,
                    $ay,
                    $brut_maas,
                    $sgk_banka,
                    $ek_odenek_toplam,
                    $eko_banka,
                    $eko_nakit,
                    $izin_gunu,
                    $izin_kesintisi,
                    $sgk_kesintisi,
                    $diger_kesintiler_val,
                    $banka_avans,
                    $nakit_avans
                ]);
            }
            
            $newId = $pdo->lastInsertId();
            logUserAction('bordro', 'INSERT', $newId, "Toplu bordro eklendi: Personel ID $personel_id, Ay $ay/$yil");
            $eklenenSayisi++;
        }

        $pdo->commit();
        
        // Redirect yap
        if ($eklenenSayisi > 0) {
            safeRedirect('toplu_bordro.php?success=1&ay=' . $ay . '&yil=' . $yil . '&eklenen=' . $eklenenSayisi);
        } else {
            safeRedirect('toplu_bordro.php?info=1&ay=' . $ay . '&yil=' . $yil . '&mesaj=' . urlencode('Seçili personeller için bordro zaten mevcut.'));
        }
    } catch(PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Toplu Bordro Hatası: ' . $e->getMessage());
        safeRedirect('toplu_bordro.php?error=' . urlencode('Veritabanı hatası: ' . $e->getMessage()));
    } catch(Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Toplu Bordro Hatası: ' . $e->getMessage());
        safeRedirect('toplu_bordro.php?error=' . urlencode($e->getMessage()));
    }
} else {
    safeRedirect('toplu_bordro.php');
}
?>

