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

-- =====================================
-- VERİTABANI OLUŞTURMA TAMAMLANDI ✅
-- =====================================
