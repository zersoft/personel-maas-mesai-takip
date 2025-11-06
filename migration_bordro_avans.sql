-- Bordro tarafına kanal bazlı avans kolonları
ALTER TABLE bordro
  ADD COLUMN banka_avans DECIMAL(10,2) DEFAULT 0 AFTER diger_kesintiler,
  ADD COLUMN nakit_avans DECIMAL(10,2) DEFAULT 0 AFTER banka_avans;

-- Avans kaydına bordro dönemi (ay/yıl) ekleyelim
ALTER TABLE avans_takip
  ADD COLUMN bordro_ay TINYINT NULL AFTER tarih,
  ADD COLUMN bordro_yil INT NULL AFTER bordro_ay;

-- Eski verilerde bordro_ay/yil boşsa tarih üzerinden doldur (opsiyonel)
UPDATE avans_takip
SET bordro_ay = MONTH(tarih),
    bordro_yil = YEAR(tarih)
WHERE bordro_ay IS NULL OR bordro_yil IS NULL;


