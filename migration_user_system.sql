-- Kullanıcı ve Audit Log Sistemi Migrasyonu

-- 1. Kullanıcılar tablosu
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

-- 2. Kullanıcı işlem logları
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

-- 3. Tüm tablolara updated_at ve updated_by ekle (koşullu)

-- personel_listesi
SET @col1 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='personel_listesi' AND COLUMN_NAME='updated_at');
SET @sql1 := IF(@col1=0, 'ALTER TABLE personel_listesi ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'SELECT "updated_at already exists"');
PREPARE stmt1 FROM @sql1; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

SET @col2 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='personel_listesi' AND COLUMN_NAME='updated_by');
SET @sql2 := IF(@col2=0, 'ALTER TABLE personel_listesi ADD COLUMN updated_by INT NULL', 'SELECT "updated_by already exists"');
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

SET @col3 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='personel_listesi' AND COLUMN_NAME='created_by');
SET @sql3 := IF(@col3=0, 'ALTER TABLE personel_listesi ADD COLUMN created_by INT NULL', 'SELECT "created_by already exists"');
PREPARE stmt3 FROM @sql3; EXECUTE stmt3; DEALLOCATE PREPARE stmt3;

-- bordro
SET @col4 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bordro' AND COLUMN_NAME='updated_at');
SET @sql4 := IF(@col4=0, 'ALTER TABLE bordro ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'SELECT "updated_at already exists"');
PREPARE stmt4 FROM @sql4; EXECUTE stmt4; DEALLOCATE PREPARE stmt4;

SET @col5 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bordro' AND COLUMN_NAME='updated_by');
SET @sql5 := IF(@col5=0, 'ALTER TABLE bordro ADD COLUMN updated_by INT NULL', 'SELECT "updated_by already exists"');
PREPARE stmt5 FROM @sql5; EXECUTE stmt5; DEALLOCATE PREPARE stmt5;

SET @col6 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bordro' AND COLUMN_NAME='created_by');
SET @sql6 := IF(@col6=0, 'ALTER TABLE bordro ADD COLUMN created_by INT NULL', 'SELECT "created_by already exists"');
PREPARE stmt6 FROM @sql6; EXECUTE stmt6; DEALLOCATE PREPARE stmt6;

-- fazla_mesai
SET @col7 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fazla_mesai' AND COLUMN_NAME='updated_at');
SET @sql7 := IF(@col7=0, 'ALTER TABLE fazla_mesai ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'SELECT "updated_at already exists"');
PREPARE stmt7 FROM @sql7; EXECUTE stmt7; DEALLOCATE PREPARE stmt7;

SET @col8 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fazla_mesai' AND COLUMN_NAME='updated_by');
SET @sql8 := IF(@col8=0, 'ALTER TABLE fazla_mesai ADD COLUMN updated_by INT NULL', 'SELECT "updated_by already exists"');
PREPARE stmt8 FROM @sql8; EXECUTE stmt8; DEALLOCATE PREPARE stmt8;

SET @col9 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fazla_mesai' AND COLUMN_NAME='created_by');
SET @sql9 := IF(@col9=0, 'ALTER TABLE fazla_mesai ADD COLUMN created_by INT NULL', 'SELECT "created_by already exists"');
PREPARE stmt9 FROM @sql9; EXECUTE stmt9; DEALLOCATE PREPARE stmt9;

-- fazla_mesai_odeme
SET @col10 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fazla_mesai_odeme' AND COLUMN_NAME='updated_at');
SET @sql10 := IF(@col10=0, 'ALTER TABLE fazla_mesai_odeme ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'SELECT "updated_at already exists"');
PREPARE stmt10 FROM @sql10; EXECUTE stmt10; DEALLOCATE PREPARE stmt10;

SET @col11 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fazla_mesai_odeme' AND COLUMN_NAME='updated_by');
SET @sql11 := IF(@col11=0, 'ALTER TABLE fazla_mesai_odeme ADD COLUMN updated_by INT NULL', 'SELECT "updated_by already exists"');
PREPARE stmt11 FROM @sql11; EXECUTE stmt11; DEALLOCATE PREPARE stmt11;

SET @col12 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fazla_mesai_odeme' AND COLUMN_NAME='created_by');
SET @sql12 := IF(@col12=0, 'ALTER TABLE fazla_mesai_odeme ADD COLUMN created_by INT NULL', 'SELECT "created_by already exists"');
PREPARE stmt12 FROM @sql12; EXECUTE stmt12; DEALLOCATE PREPARE stmt12;

-- avans_takip
SET @col13 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avans_takip' AND COLUMN_NAME='updated_at');
SET @sql13 := IF(@col13=0, 'ALTER TABLE avans_takip ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'SELECT "updated_at already exists"');
PREPARE stmt13 FROM @sql13; EXECUTE stmt13; DEALLOCATE PREPARE stmt13;

SET @col14 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avans_takip' AND COLUMN_NAME='updated_by');
SET @sql14 := IF(@col14=0, 'ALTER TABLE avans_takip ADD COLUMN updated_by INT NULL', 'SELECT "updated_by already exists"');
PREPARE stmt14 FROM @sql14; EXECUTE stmt14; DEALLOCATE PREPARE stmt14;

SET @col15 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='avans_takip' AND COLUMN_NAME='created_by');
SET @sql15 := IF(@col15=0, 'ALTER TABLE avans_takip ADD COLUMN created_by INT NULL', 'SELECT "created_by already exists"');
PREPARE stmt15 FROM @sql15; EXECUTE stmt15; DEALLOCATE PREPARE stmt15;

-- tazminat_takip
SET @col16 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tazminat_takip' AND COLUMN_NAME='updated_at');
SET @sql16 := IF(@col16=0, 'ALTER TABLE tazminat_takip ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'SELECT "updated_at already exists"');
PREPARE stmt16 FROM @sql16; EXECUTE stmt16; DEALLOCATE PREPARE stmt16;

SET @col17 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tazminat_takip' AND COLUMN_NAME='updated_by');
SET @sql17 := IF(@col17=0, 'ALTER TABLE tazminat_takip ADD COLUMN updated_by INT NULL', 'SELECT "updated_by already exists"');
PREPARE stmt17 FROM @sql17; EXECUTE stmt17; DEALLOCATE PREPARE stmt17;

SET @col18 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tazminat_takip' AND COLUMN_NAME='created_by');
SET @sql18 := IF(@col18=0, 'ALTER TABLE tazminat_takip ADD COLUMN created_by INT NULL', 'SELECT "created_by already exists"');
PREPARE stmt18 FROM @sql18; EXECUTE stmt18; DEALLOCATE PREPARE stmt18;

-- puantaj (zaten updated_at var, sadece user alanları)
SET @col19 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='puantaj' AND COLUMN_NAME='updated_by');
SET @sql19 := IF(@col19=0, 'ALTER TABLE puantaj ADD COLUMN updated_by INT NULL', 'SELECT "updated_by already exists"');
PREPARE stmt19 FROM @sql19; EXECUTE stmt19; DEALLOCATE PREPARE stmt19;

SET @col20 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='puantaj' AND COLUMN_NAME='created_by');
SET @sql20 := IF(@col20=0, 'ALTER TABLE puantaj ADD COLUMN created_by INT NULL', 'SELECT "created_by already exists"');
PREPARE stmt20 FROM @sql20; EXECUTE stmt20; DEALLOCATE PREPARE stmt20;

