<?php
ob_start(); // Output buffering başlat
require_once '../config/db.php';
require_once '../includes/functions.php';

// Silme işlemi
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        $id = (int)$_GET['id']; // SQL injection koruması için integer cast
        if ($id <= 0) {
            throw new Exception('Geçersiz ID');
        }
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
        $id = isset($_POST['id']) ? (int)$_POST['id'] : null; // SQL injection koruması için integer cast
        if (!$id || $id <= 0) {
            throw new Exception('Geçersiz bordro ID');
        }
        
        // Para alanlarını parse et (TR ve EN format destekli)
        function parseMoney($value) {
            if ($value === null) return 0;
            $value = trim((string)$value);
            if ($value === '' || $value === '0') return 0;

            // TL sembolü ve boşlukları kaldır
            $value = str_replace('₺', '', $value);
            $value = trim($value);

            if (strpos($value, ',') !== false) {
                // TR formatı: 57.500,00 -> 57500.00
                $value = str_replace('.', '', $value); // binlikleri kaldır
                $value = str_replace(',', '.', $value); // ondalığı noktaya çevir
            } else {
                // EN veya sade sayı: 57500.00 ya da 57.500.00 (binlik .)
                $parts = explode('.', $value);
                if (count($parts) > 2) {
                    // Birden fazla nokta varsa sonuncusu ondalık, diğerleri binlik sayılmalı
                    $last = array_pop($parts);
                    $value = implode('', $parts) . '.' . $last;
                }
                // Tek noktalıysa olduğu gibi bırak (57500.00)
            }

            // Yalnızca rakam ve tek nokta kalsın
            $value = preg_replace('/[^0-9.]/', '', $value);
            $parts = explode('.', $value);
            if (count($parts) > 2) {
                $value = implode('', array_slice($parts, 0, -1)) . '.' . end($parts);
            }
            return (float)$value;
        }
        
        $personel_id = isset($_POST['personel_id']) ? (int)$_POST['personel_id'] : null;
        $yil = isset($_POST['yil']) ? (int)$_POST['yil'] : date('Y');
        $ay = isset($_POST['ay']) ? (int)$_POST['ay'] : date('n');
        
        // Validasyon
        if (!$personel_id || $personel_id <= 0) {
            throw new Exception('Geçersiz personel ID');
        }
        if ($ay < 1 || $ay > 12) {
            throw new Exception('Geçersiz ay değeri');
        }
        if ($yil < 2000 || $yil > 2100) {
            throw new Exception('Geçersiz yıl değeri');
        }
        
        $brut_maas = parseMoney($_POST['brut_maas_raw'] ?? ($_POST['brut_maas'] ?? 0));
        $sgk_banka = parseMoney($_POST['sgk_banka_raw'] ?? ($_POST['sgk_banka'] ?? 0));
        $ek_odenek = parseMoney($_POST['ek_odenek_raw'] ?? ($_POST['ek_odenek'] ?? 0));
        $izin_gunu = isset($_POST['izin_gunu']) ? floatval($_POST['izin_gunu']) : 0;
        $izin_kesintisi = parseMoney($_POST['izin_kesintisi_raw'] ?? ($_POST['izin_kesintisi'] ?? 0));
        $sgk_kesintisi = parseMoney($_POST['sgk_kesintisi_raw'] ?? ($_POST['sgk_kesintisi'] ?? 0));
        $diger_kesintiler = parseMoney($_POST['diger_kesintiler_raw'] ?? ($_POST['diger_kesintiler'] ?? 0));
        $kesinti_aciklama = isset($_POST['kesinti_aciklama']) ? trim($_POST['kesinti_aciklama']) : null;
        $aciklama = isset($_POST['aciklama']) ? trim($_POST['aciklama']) : null;

        $stmt = $pdo->prepare("
            UPDATE bordro SET
                personel_id = ?, yil = ?, ay = ?, brut_maas = ?, sgk_banka = ?, 
                ek_odenek = ?, izin_gunu = ?, izin_kesintisi = ?, 
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
            $izin_gunu,
            $izin_kesintisi,
            $sgk_kesintisi,
            $diger_kesintiler,
            $kesinti_aciklama ?: null,
            $aciklama ?: null,
            $id
        ]);

        ob_end_clean(); // Output buffer'ı temizle
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
        $personel_id = isset($_POST['personel_id']) ? (int)$_POST['personel_id'] : null;
        $yil = isset($_POST['yil']) ? (int)$_POST['yil'] : date('Y');
        $ay = isset($_POST['ay']) ? (int)$_POST['ay'] : date('n');
        
        // Ay ve yıl validasyonu
        if ($ay < 1 || $ay > 12) {
            throw new Exception('Geçersiz ay değeri');
        }
        if ($yil < 2000 || $yil > 2100) {
            throw new Exception('Geçersiz yıl değeri');
        }
        
        // Para alanlarını parse et (TR ve EN format destekli)
        function parseMoney($value) {
            if ($value === null) return 0;
            $value = trim((string)$value);
            if ($value === '' || $value === '0') return 0;

            $value = str_replace('₺', '', $value);
            $value = trim($value);

            if (strpos($value, ',') !== false) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $parts = explode('.', $value);
                if (count($parts) > 2) {
                    $last = array_pop($parts);
                    $value = implode('', $parts) . '.' . $last;
                }
            }

            $value = preg_replace('/[^0-9.]/', '', $value);
            $parts = explode('.', $value);
            if (count($parts) > 2) {
                $value = implode('', array_slice($parts, 0, -1)) . '.' . end($parts);
            }
            return (float)$value;
        }
        
        $brut_maas = parseMoney($_POST['brut_maas_raw'] ?? ($_POST['brut_maas'] ?? 0));
        $sgk_banka = parseMoney($_POST['sgk_banka_raw'] ?? ($_POST['sgk_banka'] ?? 0));
        $ek_odenek = parseMoney($_POST['ek_odenek_raw'] ?? ($_POST['ek_odenek'] ?? 0));
        $izin_gunu = $_POST['izin_gunu'] ?? 0;
        $izin_kesintisi = parseMoney($_POST['izin_kesintisi_raw'] ?? ($_POST['izin_kesintisi'] ?? 0));
        $sgk_kesintisi = parseMoney($_POST['sgk_kesintisi_raw'] ?? ($_POST['sgk_kesintisi'] ?? 0));
        $diger_kesintiler = parseMoney($_POST['diger_kesintiler_raw'] ?? ($_POST['diger_kesintiler'] ?? 0));
        $kesinti_aciklama = $_POST['kesinti_aciklama'] ?? null;
        $aciklama = $_POST['aciklama'] ?? null;

        if (!$personel_id) {
            throw new Exception('Personel seçilmedi');
        }

        $stmt = $pdo->prepare("
            INSERT INTO bordro 
            (personel_id, yil, ay, brut_maas, sgk_banka, ek_odenek, 
             izin_gunu, izin_kesintisi, sgk_kesintisi, diger_kesintiler, kesinti_aciklama, aciklama) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $personel_id,
            $yil,
            $ay,
            $brut_maas,
            $sgk_banka,
            $ek_odenek,
            $izin_gunu,
            $izin_kesintisi,
            $sgk_kesintisi,
            $diger_kesintiler,
            $kesinti_aciklama ?: null,
            $aciklama ?: null
        ]);

        ob_end_clean(); // Output buffer'ı temizle
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

