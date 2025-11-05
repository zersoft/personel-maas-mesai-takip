<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

// Silme işlemi
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        $id = $_GET['id'];
        $stmt = $pdo->prepare("DELETE FROM fazla_mesai WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: fazla_mesai.php?success=1');
        exit;
    } catch(PDOException $e) {
        header('Location: fazla_mesai.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}

// Güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    try {
        $id = $_POST['id'] ?? null;
        if (!$id) {
            throw new Exception('Fazla mesai ID bulunamadı');
        }
        
        $personel_id = $_POST['personel_id'] ?? null;
        $tarih = $_POST['tarih'] ?? null;
        $saat = $_POST['saat'] ?? 0;
        $saat_ucreti = $_POST['saat_ucreti'] ?? 0;
        $odendi = isset($_POST['odendi']) ? 1 : 0;
        $aciklama = $_POST['aciklama'] ?? null;

        if (!$personel_id || !$tarih) {
            throw new Exception('Personel ve tarih seçilmedi');
        }

        $stmt = $pdo->prepare("
            UPDATE fazla_mesai SET
                personel_id = ?, tarih = ?, saat = ?, saat_ucreti = ?, odendi = ?, aciklama = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $personel_id,
            $tarih,
            $saat,
            $saat_ucreti,
            $odendi,
            $aciklama ?: null,
            $id
        ]);

        header('Location: fazla_mesai.php?success=1');
        exit;
    } catch(PDOException $e) {
        header('Location: fazla_mesai_duzenle.php?id=' . ($id ?? '') . '&error=' . urlencode($e->getMessage()));
        exit;
    } catch(Exception $e) {
        header('Location: fazla_mesai_duzenle.php?id=' . ($id ?? '') . '&error=' . urlencode($e->getMessage()));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $personel_id = $_POST['personel_id'] ?? null;
        $tarih = $_POST['tarih'] ?? null;
        $saat = $_POST['saat'] ?? 0;
        $saat_ucreti = $_POST['saat_ucreti'] ?? 0;
        $odendi = isset($_POST['odendi']) ? 1 : 0;
        $aciklama = $_POST['aciklama'] ?? null;

        if (!$personel_id || !$tarih) {
            throw new Exception('Personel ve tarih seçilmedi');
        }

        $stmt = $pdo->prepare("
            INSERT INTO fazla_mesai 
            (personel_id, tarih, saat, saat_ucreti, odendi, aciklama) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $personel_id,
            $tarih,
            $saat,
            $saat_ucreti,
            $odendi,
            $aciklama ?: null
        ]);

        header('Location: fazla_mesai.php?success=1');
        exit;
    } catch(PDOException $e) {
        header('Location: fazla_mesai.php?error=' . urlencode($e->getMessage()));
        exit;
    } catch(Exception $e) {
        header('Location: fazla_mesai.php?error=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('Location: fazla_mesai.php');
    exit;
}
?>

