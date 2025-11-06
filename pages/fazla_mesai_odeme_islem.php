<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

// Çıktı tamponu; yönlendirme sorunlarını engelle
if (ob_get_level() === 0) { ob_start(); }

// Para parse (TR/EN uyumlu)
function parseMoneyLocal($value) {
    if ($value === null || $value === '' || $value === false) return 0;
    $value = (string)$value;
    $value = str_replace('₺', '', $value);
    $value = trim($value);
    if ($value === '') return 0;
    if (strpos($value, ',') !== false && strpos($value, '.') !== false) {
        // TR format
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $personel_id = isset($_POST['personel_id']) ? (int)$_POST['personel_id'] : null;
        
        if (!$personel_id || $personel_id <= 0) {
            throw new Exception('Geçersiz personel ID');
        }
        $odeme_tarihi = $_POST['odeme_tarihi'] ?? date('Y-m-d');
        $odeme_tutari = parseMoneyLocal($_POST['odeme_tutari'] ?? 0);
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

        $toplamTutar = 0.0;
        foreach($fazlaMesailer as $fm) {
            $toplamTutar += (float)$fm['tutar'];
        }

        if ($odeme_tutari > $toplamTutar) {
            throw new Exception('Ödeme tutarı toplam tutardan fazla olamaz');
        }

        $pdo->beginTransaction();

        if ($tamamini_ode || $odeme_tutari >= $toplamTutar) {
            // Tümünü öde
            $update = $pdo->prepare("UPDATE fazla_mesai SET odendi = 1 WHERE personel_id = ? AND odendi = 0");
            $update->execute([$personel_id]);
        } else {
            // Kısmi ödeme - satır bölme yaklaşımı
            $kalanTutar = (float)$odeme_tutari;
            foreach($fazlaMesailer as $fm) {
                if ($kalanTutar <= 0) break;
                
                $rowTutar = (float)$fm['tutar'];
                if ($kalanTutar >= $rowTutar) {
                    // Tamamını öde
                    $update = $pdo->prepare("UPDATE fazla_mesai SET odendi = 1 WHERE id = ?");
                    $update->execute([$fm['id']]);
                    $kalanTutar -= $rowTutar;
                } else {
                    // Bu satır için kısmi ödeme: satırı böl
                    $saatUcreti = (float)$fm['saat_ucreti'];
                    if ($saatUcreti <= 0) {
                        throw new Exception('Saat ücreti geçersiz.');
                    }
                    $orijinalSaat = (float)$fm['saat'];
                    $odenmisSaat = round($kalanTutar / $saatUcreti, 2);
                    if ($odenmisSaat <= 0) {
                        throw new Exception('Ödeme tutarı çok küçük.');
                    }
                    if ($odenmisSaat > $orijinalSaat) {
                        $odenmisSaat = $orijinalSaat;
                    }
                    $kalanSaat = round($orijinalSaat - $odenmisSaat, 2);

                    // 1) Orijinal satırı kalan saatle güncelle (ödenmemiş)
                    $updOrig = $pdo->prepare("UPDATE fazla_mesai SET saat = ?, odendi = 0 WHERE id = ?");
                    $updOrig->execute([$kalanSaat, $fm['id']]);

                    // 2) Ödenen kısmı yeni satır olarak ekle (ödenmiş)
                    $insPaid = $pdo->prepare("INSERT INTO fazla_mesai (personel_id, tarih, saat, saat_ucreti, odendi, aciklama) VALUES (?, ?, ?, ?, 1, ?)");
                    $insPaid->execute([
                        $personel_id,
                        $odeme_tarihi,
                        $odenmisSaat,
                        $saatUcreti,
                        'Kısmi ödeme (otomatik)'
                    ]);

                    $kalanTutar = 0.0;
                }
            }
        }

        // Ödeme kaydı oluştur (ayrı tabloya)
        $odenenTutar = ($tamamini_ode || $odeme_tutari >= $toplamTutar) ? $toplamTutar : $odeme_tutari;
        $insertPay = $pdo->prepare("INSERT INTO fazla_mesai_odeme (personel_id, odeme_tarihi, tutar, aciklama) VALUES (?, ?, ?, ?)");
        $insertPay->execute([
            $personel_id,
            $odeme_tarihi,
            $odenenTutar,
            $aciklama ?: null
        ]);

        $pdo->commit();
        if (ob_get_level() > 0) { @ob_end_clean(); }
        safeRedirect('fazla_mesai.php?success=1');
    } catch(PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (ob_get_level() > 0) { @ob_end_clean(); }
        safeRedirect('fazla_mesai_odeme.php?personel_id=' . ($personel_id ?? '') . '&error=' . urlencode($e->getMessage()));
    } catch(Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (ob_get_level() > 0) { @ob_end_clean(); }
        safeRedirect('fazla_mesai_odeme.php?personel_id=' . ($personel_id ?? '') . '&error=' . urlencode($e->getMessage()));
    }
} else {
    if (ob_get_level() > 0) { @ob_end_clean(); }
    safeRedirect('fazla_mesai.php');
}
?>

