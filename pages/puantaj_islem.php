<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

ob_start();

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
        $stmt = $pdo->prepare("INSERT INTO puantaj (personel_id, tarih, durum, saat, aciklama) VALUES (?,?,?,?,?)");
        $stmt->execute([$personel_id, $tarih, $durum, $saat, $aciklama]);
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
        $stmt = $pdo->prepare('UPDATE puantaj SET tarih=?, durum=?, saat=?, aciklama=? WHERE id=?');
        $stmt->execute([$tarih, $durum, $saat, $aciklama, $id]);
        $ay = (int)date('n', strtotime($tarih));
        $yil = (int)date('Y', strtotime($tarih));
        safeRedirect('puantaj_ekstre.php?mode=donem&ay=' . $ay . '&yil=' . $yil . '&success=1');
    } elseif ($action === 'bulk_insert') {
        $tarih = $_POST['tarih'] ?? date('Y-m-d');
        $items = $_POST['items'] ?? [];
        if (empty($items)) safeRedirect('toplu_puantaj.php?error=' . urlencode('Kayıt seçilmedi'));
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO puantaj (personel_id, tarih, durum, saat, aciklama) VALUES (?,?,?,?,?)');
        foreach ($items as $pid => $it) {
            if (!isset($it['dahil'])) continue;
            $personel_id = (int)$pid;
            $durum = $it['durum'] ?? 'Calisti';
            $saat = isset($it['saat']) ? floatval($it['saat']) : 8.00;
            $aciklama = $it['aciklama'] ?? null;
            if ($personel_id > 0) { $stmt->execute([$personel_id, $tarih, $durum, $saat, $aciklama]); }
        }
        $pdo->commit();
        $ay = (int)date('n', strtotime($tarih));
        $yil = (int)date('Y', strtotime($tarih));
        safeRedirect('puantaj.php?ay=' . $ay . '&yil=' . $yil . '&success=1');
    } else {
        safeRedirect('puantaj.php?error=' . urlencode('Bilinmeyen işlem'));
    }
} catch (Throwable $e) {
    safeRedirect('puantaj.php?error=' . urlencode($e->getMessage()));
}


