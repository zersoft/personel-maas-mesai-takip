-- =====================================
-- PERSONEL MAAŞ & MESAI TAKİP SİSTEMİ
-- Veritabanı Şeması (MySQL 8+)
-- =====================================

CREATE DATABASE IF NOT EXISTS zersoftn_personel_takip CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

USE zersoftn_personel_takip;

-- 1. Personel Listesi
CREATE TABLE personel_listesi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ad_soyad VARCHAR(150) NOT NULL,
    tc_no VARCHAR(11),
    pozisyon VARCHAR(100),
    ise_giris_tarihi DATE,
    maas DECIMAL(10,2) DEFAULT 0,
    maas_sgk DECIMAL(10,2) DEFAULT 0,
    banka_adi VARCHAR(100),
    iban VARCHAR(26),
    mesai_saat_ucreti DECIMAL(6,2) DEFAULT 0,
    aktif BOOLEAN DEFAULT 1,
    silinme_tarihi DATETIME NULL DEFAULT NULL,
    olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Bordro
CREATE TABLE bordro (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personel_id INT NOT NULL,
    yil INT NOT NULL,
    ay TINYINT NOT NULL,
    brut_maas DECIMAL(10,2) DEFAULT 0,
    sgk_banka DECIMAL(10,2) DEFAULT 0,
    nakit DECIMAL(10,2) GENERATED ALWAYS AS (brut_maas - sgk_banka) STORED,
    ek_odenek DECIMAL(10,2) DEFAULT 0,
    ek_odenek_banka DECIMAL(10,2) DEFAULT 0,
    ek_odenek_nakit DECIMAL(10,2) DEFAULT 0,
    izin_gunu DECIMAL(4,1) DEFAULT 0,
    izin_kesintisi DECIMAL(10,2) DEFAULT 0,
    sgk_kesintisi DECIMAL(10,2) DEFAULT 0,
    diger_kesintiler DECIMAL(10,2) DEFAULT 0,
    banka_avans DECIMAL(10,2) DEFAULT 0,
    nakit_avans DECIMAL(10,2) DEFAULT 0,
    kesinti_aciklama TEXT,
    toplam_odenecek DECIMAL(10,2) GENERATED ALWAYS AS (
        GREATEST(brut_maas - (COALESCE(izin_kesintisi,0) + COALESCE(sgk_kesintisi,0) + COALESCE(diger_kesintiler,0)), 0)
        + COALESCE(ek_odenek_banka,0) + COALESCE(ek_odenek_nakit,0)
    ) STORED,
    aciklama TEXT,
    olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (personel_id) REFERENCES personel_listesi(id) ON DELETE CASCADE
);

-- 3. Fazla Mesai
CREATE TABLE fazla_mesai (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personel_id INT NOT NULL,
    tarih DATE NOT NULL,
    saat DECIMAL(5,2) DEFAULT 0,
    saat_ucreti DECIMAL(6,2) DEFAULT 0,
    tutar DECIMAL(10,2) GENERATED ALWAYS AS (saat * saat_ucreti) STORED,
    odendi BOOLEAN DEFAULT 0,
    aciklama TEXT,
    olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (personel_id) REFERENCES personel_listesi(id) ON DELETE CASCADE
);

-- 3.1 Fazla Mesai Ödeme Kayıtları
CREATE TABLE IF NOT EXISTS fazla_mesai_odeme (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personel_id INT NOT NULL,
    odeme_tarihi DATE NOT NULL,
    tutar DECIMAL(10,2) NOT NULL DEFAULT 0,
    aciklama TEXT,
    odeme_zamani TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (personel_id) REFERENCES personel_listesi(id) ON DELETE CASCADE
);

-- 4. Avans Takip
CREATE TABLE avans_takip (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personel_id INT NOT NULL,
    tarih DATE NOT NULL,
    bordro_ay TINYINT NULL,
    bordro_yil INT NULL,
    avans_tutari DECIMAL(10,2) DEFAULT 0,
    banka_tutari DECIMAL(10,2) DEFAULT 0,
    nakit_tutari DECIMAL(10,2) DEFAULT 0,
    aciklama TEXT,
    olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (personel_id) REFERENCES personel_listesi(id) ON DELETE CASCADE
);

-- 5. Tazminat Takip
CREATE TABLE tazminat_takip (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personel_id INT NOT NULL,
    tarih DATE,
    plan VARCHAR(50),
    islem VARCHAR(50),
    tutar DECIMAL(10,2) DEFAULT 0,
    aciklama TEXT,
    olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (personel_id) REFERENCES personel_listesi(id) ON DELETE CASCADE
);

-- 6. Aylık Raporlar
CREATE TABLE rapor_ozet (
    id INT AUTO_INCREMENT PRIMARY KEY,
    yil INT NOT NULL,
    ay TINYINT NOT NULL,
    toplam_banka DECIMAL(10,2) DEFAULT 0,
    toplam_nakit DECIMAL(10,2) DEFAULT 0,
    toplam_mesai DECIMAL(10,2) DEFAULT 0,
    toplam_avans DECIMAL(10,2) DEFAULT 0,
    toplam_tazminat DECIMAL(10,2) DEFAULT 0,
    olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- İndeksler
CREATE INDEX idx_personel_id ON bordro(personel_id);
CREATE INDEX idx_mesai_personel ON fazla_mesai(personel_id);
CREATE INDEX idx_avans_personel ON avans_takip(personel_id);
CREATE INDEX idx_tazminat_personel ON tazminat_takip(personel_id);

-- 7. Puantaj (Günlük kayıtlar)
CREATE TABLE IF NOT EXISTS puantaj (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personel_id INT NOT NULL,
    tarih DATE NOT NULL,
    durum VARCHAR(20) NOT NULL DEFAULT 'Calisti', -- Calisti, Izin, Rapor, Devamsizlik, HTatil, RTatil
    saat DECIMAL(5,2) NOT NULL DEFAULT 8.00,
    aciklama VARCHAR(255) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NULL,
    updated_by INT NULL,
    INDEX idx_puantaj_personel (personel_id),
    INDEX idx_puantaj_tarih (tarih),
    FOREIGN KEY (personel_id) REFERENCES personel_listesi(id) ON DELETE CASCADE
);

-- 8. Kullanıcılar
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    ad_soyad VARCHAR(150) NOT NULL,
    email VARCHAR(100),
    rol VARCHAR(20) NOT NULL DEFAULT 'user', -- admin, user, viewer
    aktif BOOLEAN DEFAULT 1,
    son_giris DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Varsayılan admin kullanıcısı (şifre: admin123)
INSERT INTO users (username, password, ad_soyad, rol) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sistem Yöneticisi', 'admin')
ON DUPLICATE KEY UPDATE id=id;

-- 9. Kullanıcı işlem logları
CREATE TABLE IF NOT EXISTS user_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    islem_tipi VARCHAR(50) NOT NULL, -- INSERT, UPDATE, DELETE
    tablo_adi VARCHAR(50) NOT NULL,
    kayit_id INT NULL,
    aciklama TEXT NULL,
    ip_adresi VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_tablo (tablo_adi),
    INDEX idx_tarih (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================
-- VERİTABANI OLUŞTURMA TAMAMLANDI ✅
-- =====================================
