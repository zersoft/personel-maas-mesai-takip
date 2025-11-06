<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $personel_id = isset($_POST['personel_id']) ? (int)$_POST['personel_id'] : null;
        
        if (!$personel_id || $personel_id <= 0) {
            throw new Exception('Geçersiz personel ID');
        }
        $odeme_tarihi = $_POST['odeme_tarihi'] ?? date('Y-m-d');
        $odeme_tutari = $_POST['odeme_tutari'] ?? 0;
        $tamamini_ode = isset($_POST['tamamini_ode']) ? true : false;
        $aciklama = $_POST['aciklama'] ?? null;

        if (!$personel_id) {
            throw new Exception('Personel seçilmedi');
        }

        // Ödenmemiş fazla mesaileri getir
        $stmt = $pdo->prepare("SELECT * FROM fazla_mesai WHERE personel_id = ? AND odendi = 0 ORDER BY tarih ASC");
        $stmt->execute([$personel_id]);
        $fazlaMesailer = $stmt->fetchAll();

        if (empty($fazlaMesailer)) {
            throw new Exception('Ödenmemiş fazla mesai bulunamadı');
        }

        $toplamTutar = 0;
        foreach($fazlaMesailer as $fm) {
            $toplamTutar += $fm['tutar'];
        }

        if ($odeme_tutari > $toplamTutar) {
            throw new Exception('Ödeme tutarı toplam tutardan fazla olamaz');
        }

        $pdo->beginTransaction();

        if ($tamamini_ode || $odeme_tutari >= $toplamTutar) {
            // Tümünü öde
            $update = $pdo->prepare("UPDATE fazla_mesai SET odendi = 1, odeme_tarihi = ?, odeme_tutari = tutar WHERE personel_id = ? AND odendi = 0");
            $update->execute([$odeme_tarihi, $personel_id]);
        } else {
            // Kısmi ödeme - eksi değerle düşme
            $kalanTutar = $odeme_tutari;
            foreach($fazlaMesailer as $fm) {
                if ($kalanTutar <= 0) break;
                
                if ($kalanTutar >= $fm['tutar']) {
                    // Tamamını öde
                    $update = $pdo->prepare("UPDATE fazla_mesai SET odendi = 1, odeme_tarihi = ?, odeme_tutari = ? WHERE id = ?");
                    $update->execute([$odeme_tarihi, $fm['tutar'], $fm['id']]);
                    $kalanTutar -= $fm['tutar'];
                } else {
                    // Kısmi ödeme - eksi değerle yeni kayıt oluştur
                    $update = $pdo->prepare("UPDATE fazla_mesai SET odendi = 1, odeme_tarihi = ?, odeme_tutari = ? WHERE id = ?");
                    $update->execute([$odeme_tarihi, $kalanTutar, $fm['id']]);
                    
                    // Eksi değerle yeni kayıt
                    $insert = $pdo->prepare("INSERT INTO fazla_mesai (personel_id, tarih, saat, saat_ucreti, odendi, aciklama) VALUES (?, ?, ?, ?, 0, ?)");
                    $kalanSaat = ($fm['tutar'] - $kalanTutar) / $fm['saat_ucreti'];
                    $insert->execute([
                        $personel_id,
                        $odeme_tarihi,
                        -$kalanSaat,
                        $fm['saat_ucreti'],
                        'Kısmi ödeme sonrası kalan tutar'
                    ]);
                    $kalanTutar = 0;
                }
            }
        }

        $pdo->commit();
        header('Location: fazla_mesai.php?success=1');
        exit;
    } catch(PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header('Location: fazla_mesai_odeme.php?personel_id=' . ($personel_id ?? '') . '&error=' . urlencode($e->getMessage()));
        exit;
    } catch(Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header('Location: fazla_mesai_odeme.php?personel_id=' . ($personel_id ?? '') . '&error=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('Location: fazla_mesai.php');
    exit;
}
?>

