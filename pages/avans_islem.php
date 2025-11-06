<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

ob_start();

function safeRedirect($url) {
    // Çıktıyı temizle ve header ile yönlendir
    if (ob_get_level() > 0) { @ob_end_clean(); }
    header('Location: ' . $url);
    // Header çalışmazsa (ör. önceden çıktı) JS/Meta ile yönlendir
    echo '<!doctype html><html><head><meta charset="utf-8">'
        . '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
        . '</head><body>'
        . '<script>location.replace(' . json_encode($url) . ');</script>'
        . '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">Yönlendiriliyorsunuz...</a>'
        . '</body></html>';
    exit;
}

function parseMoney($value) {
    if ($value === null || $value === '' || $value === false) return 0;
    $value = (string)$value;
    $value = str_replace('₺', '', $value);
    $value = trim($value);
    if ($value === '' || $value === '0') return 0;
    if (strpos($value, ',') !== false) {
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
    // DELETE (GET)
    if (isset($_GET['action']) && $_GET['action'] === 'delete') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) throw new Exception('Geçersiz avans.');
        $del = $pdo->prepare('DELETE FROM avans_takip WHERE id = ?');
        $del->execute([$id]);
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
        $upd = $pdo->prepare('UPDATE avans_takip SET personel_id=?, tarih=?, bordro_ay=?, bordro_yil=?, avans_tutari=?, banka_tutari=?, nakit_tutari=?, aciklama=? WHERE id=?');
        $upd->execute([$personel_id, $tarih, $bordro_ay, $bordro_yil, ($banka_tutari + $nakit_tutari), $banka_tutari, $nakit_tutari, $aciklama, $id]);
        safeRedirect('avans_takip.php?success=' . urlencode('Avans güncellendi.'));
    } else {
        $ins = $pdo->prepare('INSERT INTO avans_takip (personel_id, tarih, bordro_ay, bordro_yil, avans_tutari, banka_tutari, nakit_tutari, aciklama) VALUES (?,?,?,?,?,?,?,?)');
        $ins->execute([$personel_id, $tarih, $bordro_ay, $bordro_yil, ($banka_tutari + $nakit_tutari), $banka_tutari, $nakit_tutari, $aciklama]);
        safeRedirect('avans_takip.php?success=1');
    }
} catch (Throwable $e) {
    safeRedirect('avans_takip.php?error=' . urlencode($e->getMessage()));
}


