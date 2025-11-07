-- Fazla Mesai Basitleştirme Migrasyonu
-- Ödeme takibi sadece fazla_mesai_odeme tablosunda yapılacak

-- 1. fazla_mesai tablosundan odendi alanını kaldır (eğer varsa)
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
               WHERE TABLE_SCHEMA = DATABASE() 
               AND TABLE_NAME = 'fazla_mesai' 
               AND COLUMN_NAME = 'odendi');
SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE fazla_mesai DROP COLUMN odendi', 'SELECT ''Column odendi does not exist''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Tutar alanının tipini kontrol et ve gerekirse düzelt
-- (Eğer GENERATED column ise önce onu kaldır, sonra normal column ekle)
SET @is_generated := (SELECT COUNT(*) FROM information_schema.COLUMNS 
                      WHERE TABLE_SCHEMA = DATABASE() 
                      AND TABLE_NAME = 'fazla_mesai' 
                      AND COLUMN_NAME = 'tutar'
                      AND EXTRA LIKE '%GENERATED%');
SET @sqlstmt2 := IF(@is_generated > 0, 
                    'ALTER TABLE fazla_mesai DROP COLUMN tutar, ADD COLUMN tutar DECIMAL(10,2) DEFAULT 0 AFTER saat_ucreti', 
                    'SELECT ''Tutar column is already a regular column''');
PREPARE stmt2 FROM @sqlstmt2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- 3. Mevcut kayıtlar için tutar hesapla (eğer 0 veya NULL ise)
UPDATE fazla_mesai SET tutar = saat * saat_ucreti WHERE tutar = 0 OR tutar IS NULL;

