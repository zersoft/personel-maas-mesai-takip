<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

// Silme işlemi
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        $id = $_GET['id'];
        $stmt = $pdo->prepare("DELETE FROM bordro WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: bordro.php?success=1');
        exit;
    } catch(PDOException $e) {
        header('Location: bordro.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}

// Güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    try {
        $id = $_POST['id'] ?? null;
        if (!$id) {
            throw new Exception('Bordro ID bulunamadı');
        }
        
        $personel_id = $_POST['personel_id'] ?? null;
        $yil = $_POST['yil'] ?? date('Y');
        $ay = $_POST['ay'] ?? date('n');
        $brut_maas = $_POST['brut_maas'] ?? 0;
        $sgk_banka = $_POST['sgk_banka'] ?? 0;
        $ek_odenek = $_POST['ek_odenek'] ?? 0;
        $odeme_tipi = $_POST['odeme_tipi'] ?? 'BANKA';
        $izin_gunu = $_POST['izin_gunu'] ?? 0;
        $izin_kesintisi = $_POST['izin_kesintisi'] ?? 0;
        $sgk_kesintisi = $_POST['sgk_kesintisi'] ?? 0;
        $diger_kesintiler = $_POST['diger_kesintiler'] ?? 0;
        $kesinti_aciklama = $_POST['kesinti_aciklama'] ?? null;
        $aciklama = $_POST['aciklama'] ?? null;

        if (!$personel_id) {
            throw new Exception('Personel seçilmedi');
        }

        $stmt = $pdo->prepare("
            UPDATE bordro SET
                personel_id = ?, yil = ?, ay = ?, brut_maas = ?, sgk_banka = ?, 
                ek_odenek = ?, odeme_tipi = ?, izin_gunu = ?, izin_kesintisi = ?, 
                sgk_kesintisi = ?, diger_kesintiler = ?, kesinti_aciklama = ?, aciklama = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $personel_id,
            $yil,
            $ay,
            $brut_maas,
            $sgk_banka,
            $ek_odenek,
            $odeme_tipi,
            $izin_gunu,
            $izin_kesintisi,
            $sgk_kesintisi,
            $diger_kesintiler,
            $kesinti_aciklama ?: null,
            $aciklama ?: null,
            $id
        ]);

        header('Location: bordro.php?success=1');
        exit;
    } catch(PDOException $e) {
        header('Location: bordro_duzenle.php?id=' . ($id ?? '') . '&error=' . urlencode($e->getMessage()));
        exit;
    } catch(Exception $e) {
        header('Location: bordro_duzenle.php?id=' . ($id ?? '') . '&error=' . urlencode($e->getMessage()));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $personel_id = $_POST['personel_id'] ?? null;
        $yil = $_POST['yil'] ?? date('Y');
        $ay = $_POST['ay'] ?? date('n');
        $brut_maas = $_POST['brut_maas'] ?? 0;
        $sgk_banka = $_POST['sgk_banka'] ?? 0;
        $ek_odenek = $_POST['ek_odenek'] ?? 0;
        $odeme_tipi = $_POST['odeme_tipi'] ?? 'BANKA';
        $izin_gunu = $_POST['izin_gunu'] ?? 0;
        $izin_kesintisi = $_POST['izin_kesintisi'] ?? 0;
        $sgk_kesintisi = $_POST['sgk_kesintisi'] ?? 0;
        $diger_kesintiler = $_POST['diger_kesintiler'] ?? 0;
        $kesinti_aciklama = $_POST['kesinti_aciklama'] ?? null;
        $aciklama = $_POST['aciklama'] ?? null;

        if (!$personel_id) {
            throw new Exception('Personel seçilmedi');
        }

        $stmt = $pdo->prepare("
            INSERT INTO bordro 
            (personel_id, yil, ay, brut_maas, sgk_banka, ek_odenek, odeme_tipi, 
             izin_gunu, izin_kesintisi, sgk_kesintisi, diger_kesintiler, kesinti_aciklama, aciklama) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $personel_id,
            $yil,
            $ay,
            $brut_maas,
            $sgk_banka,
            $ek_odenek,
            $odeme_tipi,
            $izin_gunu,
            $izin_kesintisi,
            $sgk_kesintisi,
            $diger_kesintiler,
            $kesinti_aciklama ?: null,
            $aciklama ?: null
        ]);

        header('Location: bordro.php?success=1');
        exit;
    } catch(PDOException $e) {
        header('Location: bordro.php?error=' . urlencode($e->getMessage()));
        exit;
    } catch(Exception $e) {
        header('Location: bordro.php?error=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('Location: bordro.php');
    exit;
}
?>

