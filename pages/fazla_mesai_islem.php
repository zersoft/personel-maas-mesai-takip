<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

// Çıktı tamponu: yönlendirme sorunlarını engelle
if (ob_get_level() === 0) { ob_start(); }

// Para/numara formatlarını güvenli parse eden yardımcı
function parseMoneyLocal($value) {
    if ($value === null || $value === '' || $value === false) return 0;
    $value = (string)$value;
    $value = str_replace('₺', '', $value);
    $value = trim($value);
    if ($value === '') return 0;
    if (strpos($value, ',') !== false) {
        // TR format: binlik nokta, ondalık virgül
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
    } else {
        // EN format veya sade sayı: son nokta ondalık kabul edilir
        $parts = explode('.', $value);
        if (count($parts) > 1) {
            $last = array_pop($parts);
            if (strlen($last) <= 2) {
                $value = implode('', $parts) . '.' . $last;
            } else {
                $value = implode('', $parts) . $last;
            }
        }
    }
    $value = preg_replace('/[^0-9.]/', '', $value);
    return (float)$value;
}

// Silme işlemi
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        $id = (int)$_GET['id']; // SQL injection koruması için integer cast
        if ($id <= 0) {
            throw new Exception('Geçersiz ID');
        }
        $stmt = $pdo->prepare("DELETE FROM fazla_mesai WHERE id = ?");
        $stmt->execute([$id]);
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

        $stmt = $pdo->prepare("
            UPDATE fazla_mesai SET
                personel_id = ?, tarih = ?, saat = ?, saat_ucreti = ?, aciklama = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $personel_id,
            $tarih,
            $saat,
            $saat_ucreti,
            $aciklama ?: null,
            $id
        ]);

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

        $stmt = $pdo->prepare("
            INSERT INTO fazla_mesai 
            (personel_id, tarih, saat, saat_ucreti, aciklama) 
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $personel_id,
            $tarih,
            $saat,
            $saat_ucreti,
            $aciklama ?: null
        ]);

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
        $stmt = $pdo->prepare('INSERT INTO fazla_mesai (personel_id, tarih, saat, saat_ucreti, aciklama) VALUES (?,?,?,?,?)');
        
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
                $stmt->execute([$personel_id, $tarih, $saat, $saat_ucreti, $aciklama]);
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

