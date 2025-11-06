-- Avans kanallarını ekleme (banka/nakit) ve mevcut veriyi uyarlama
ALTER TABLE avans_takip
  ADD COLUMN banka_tutari DECIMAL(10,2) DEFAULT 0 AFTER avans_tutari,
  ADD COLUMN nakit_tutari DECIMAL(10,2) DEFAULT 0 AFTER banka_tutari;

-- Eski kayıtlarda tek alan kullanıldıysa, banka olarak varsay
UPDATE avans_takip
SET banka_tutari = avans_tutari
WHERE (banka_tutari IS NULL OR banka_tutari = 0) AND avans_tutari > 0;


