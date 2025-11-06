<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

if (ob_get_level() === 0) { ob_start(); }

function parseMoneyLocal($value) {
    if ($value === null || $value === '' || $value === false) return 0;
    $value = (string)$value;
    $value = str_replace('₺', '', $value);
    $value = trim($value);
    if ($value === '') return 0;
    if (strpos($value, ',') !== false && strpos($value, '.') !== false) {
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
    } elseif (strpos($value, ',') !== false) {
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
    } else {
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

try {
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        if ($id <= 0) {
            throw new Exception('Geçersiz ödeme ID');
        }
        $del = $pdo->prepare("DELETE FROM fazla_mesai_odeme WHERE id = ?");
        $del->execute([$id]);
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
        $upd = $pdo->prepare("UPDATE fazla_mesai_odeme SET personel_id = ?, odeme_tarihi = ?, tutar = ?, aciklama = ? WHERE id = ?");
        $upd->execute([$personel_id, $odeme_tarihi, $tutar, $aciklama ?: null, $id]);
        if (ob_get_level() > 0) { @ob_end_clean(); }
        safeRedirect('fazla_mesai_odeme_listesi.php?success=1');
    }

    // Unknown route
    if (ob_get_level() > 0) { @ob_end_clean(); }
    safeRedirect('fazla_mesai_odeme_listesi.php');
} catch(PDOException $e) {
    if (ob_get_level() > 0) { @ob_end_clean(); }
    safeRedirect('fazla_mesai_odeme_listesi.php?error=' . urlencode($e->getMessage()));
} catch(Exception $e) {
    if (ob_get_level() > 0) { @ob_end_clean(); }
    safeRedirect('fazla_mesai_odeme_listesi.php?error=' . urlencode($e->getMessage()));
}

?>


