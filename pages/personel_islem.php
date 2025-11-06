<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

ob_start();

function safeRedirect($url) {
    if (ob_get_level() > 0) { @ob_end_clean(); }
    header('Location: ' . $url);
    echo '<!doctype html><html><head><meta charset="utf-8">'
        . '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' 
        . '</head><body>'
        . '<script>location.replace(' . json_encode($url) . ');</script>'
        . '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">Yönlendiriliyorsunuz...</a>'
        . '</body></html>';
    exit;
}

// Silme işlemi (soft delete)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        $id = (int)$_GET['id']; // SQL injection koruması için integer cast
        if ($id <= 0) {
            throw new Exception('Geçersiz ID');
        }
        $stmt = $pdo->prepare("UPDATE personel_listesi SET aktif = 0, silinme_tarihi = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        safeRedirect('personel_listesi.php?success=' . urlencode('Personel silindi (geri alınabilir).'));
    } catch(PDOException $e) {
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
        safeRedirect('personel_listesi.php?durum=silinmis&success=' . urlencode('Personel geri alındı.'));
    } catch(PDOException $e) {
        safeRedirect('personel_listesi.php?error=' . urlencode($e->getMessage()));
    }
}

// Güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    try {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : null; // SQL injection koruması için integer cast
        if (!$id || $id <= 0) {
            throw new Exception('Geçersiz personel ID');
        }
        
        $ad_soyad = $_POST['ad_soyad'] ?? '';
        $tc_no = $_POST['tc_no'] ?? null;
        $pozisyon = $_POST['pozisyon'] ?? null;
        $maas = $_POST['maas'] ?? 0;
        $maas_sgk = $_POST['maas_sgk'] ?? 0;
        $ise_giris_tarihi = $_POST['ise_giris_tarihi'] ?? null;
        $banka_adi = $_POST['banka_adi'] ?? null;
        $iban = $_POST['iban'] ?? null;
        $mesai_saat_ucreti = $_POST['mesai_saat_ucreti'] ?? 0;
        $aktif = isset($_POST['aktif']) ? 1 : 0;

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

        safeRedirect('personel_listesi.php?success=1');
    } catch(PDOException $e) {
        safeRedirect('personel_duzenle.php?id=' . ($id ?? '') . '&error=' . urlencode($e->getMessage()));
    } catch(Exception $e) {
        safeRedirect('personel_duzenle.php?id=' . ($id ?? '') . '&error=' . urlencode($e->getMessage()));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $ad_soyad = $_POST['ad_soyad'] ?? '';
        $tc_no = $_POST['tc_no'] ?? null;
        $pozisyon = $_POST['pozisyon'] ?? null;
        $maas = $_POST['maas'] ?? 0;
        $maas_sgk = $_POST['maas_sgk'] ?? 0;
        $ise_giris_tarihi = $_POST['ise_giris_tarihi'] ?? null;
        $banka_adi = $_POST['banka_adi'] ?? null;
        $iban = $_POST['iban'] ?? null;
        $mesai_saat_ucreti = $_POST['mesai_saat_ucreti'] ?? 0;
        $aktif = isset($_POST['aktif']) ? 1 : 0;

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

        safeRedirect('personel_listesi.php?success=1');
    } catch(PDOException $e) {
        safeRedirect('personel_listesi.php?error=' . urlencode($e->getMessage()));
    } catch(Exception $e) {
        safeRedirect('personel_listesi.php?error=' . urlencode($e->getMessage()));
    }
} else {
    safeRedirect('personel_listesi.php');
}
?>


