<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $ay = $_POST['ay'] ?? date('n');
        $yil = $_POST['yil'] ?? date('Y');
        $personel_ids = $_POST['personel_id'] ?? [];
        $brut_maaslar = $_POST['brut_maas'] ?? [];
        $sgk_bankalar = $_POST['sgk_banka'] ?? [];
        $ek_odenekler = $_POST['ek_odenek'] ?? [];
        $odeme_tipleri = $_POST['odeme_tipi'] ?? [];
        $izin_gunleri = $_POST['izin_gunu'] ?? [];
        $izin_kesintileri = $_POST['izin_kesintisi'] ?? [];
        $sgk_kesintileri = $_POST['sgk_kesintisi'] ?? [];
        $diger_kesintiler = $_POST['diger_kesintiler'] ?? [];

        if (empty($personel_ids)) {
            throw new Exception('Personel seçilmedi');
        }

        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO bordro 
            (personel_id, yil, ay, brut_maas, sgk_banka, ek_odenek, odeme_tipi, 
             izin_gunu, izin_kesintisi, sgk_kesintisi, diger_kesintiler) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $eklenenSayisi = 0;
        foreach($personel_ids as $index => $personel_id) {
            // Mevcut bordroyu kontrol et
            $check = $pdo->prepare("SELECT id FROM bordro WHERE personel_id = ? AND ay = ? AND yil = ?");
            $check->execute([$personel_id, $ay, $yil]);
            if ($check->fetch()) {
                continue; // Mevcut bordroyu atla
            }
            
            $stmt->execute([
                $personel_id,
                $yil,
                $ay,
                $brut_maaslar[$index] ?? 0,
                $sgk_bankalar[$index] ?? 0,
                $ek_odenekler[$index] ?? 0,
                $odeme_tipleri[$index] ?? 'BANKA',
                $izin_gunleri[$index] ?? 0,
                $izin_kesintileri[$index] ?? 0,
                $sgk_kesintileri[$index] ?? 0,
                $diger_kesintiler[$index] ?? 0
            ]);
            $eklenenSayisi++;
        }

        $pdo->commit();
        header('Location: toplu_bordro.php?success=1&ay=' . $ay . '&yil=' . $yil . '&eklenen=' . $eklenenSayisi);
        exit;
    } catch(PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header('Location: toplu_bordro.php?error=' . urlencode($e->getMessage()));
        exit;
    } catch(Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header('Location: toplu_bordro.php?error=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('Location: toplu_bordro.php');
    exit;
}
?>

