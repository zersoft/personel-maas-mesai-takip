<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

// Silme işlemi
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        $id = $_GET['id'];
        $stmt = $pdo->prepare("DELETE FROM personel_listesi WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: personel_listesi.php?success=1');
        exit;
    } catch(PDOException $e) {
        header('Location: personel_listesi.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}

// Güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    try {
        $id = $_POST['id'] ?? null;
        if (!$id) {
            throw new Exception('Personel ID bulunamadı');
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

        header('Location: personel_listesi.php?success=1');
        exit;
    } catch(PDOException $e) {
        header('Location: personel_duzenle.php?id=' . ($id ?? '') . '&error=' . urlencode($e->getMessage()));
        exit;
    } catch(Exception $e) {
        header('Location: personel_duzenle.php?id=' . ($id ?? '') . '&error=' . urlencode($e->getMessage()));
        exit;
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

        header('Location: personel_listesi.php?success=1');
        exit;
    } catch(PDOException $e) {
        header('Location: personel_listesi.php?error=' . urlencode($e->getMessage()));
        exit;
    } catch(Exception $e) {
        header('Location: personel_listesi.php?error=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('Location: personel_listesi.php');
    exit;
}
?>


