-- Bordro: Ek Ödenek’i kanal bazında ayır ve toplam_odenecek formülünü güncelle
ALTER TABLE bordro
  ADD COLUMN ek_odenek_banka DECIMAL(10,2) DEFAULT 0 AFTER ek_odenek,
  ADD COLUMN ek_odenek_nakit DECIMAL(10,2) DEFAULT 0 AFTER ek_odenek_banka;

-- Eski veriyi geriye dönük doldur: mevcut ek_odenek değerlerini varsayılan olarak nakit’e taşı
UPDATE bordro
SET ek_odenek_nakit = COALESCE(ek_odenek, 0)
WHERE COALESCE(ek_odenek, 0) > 0 AND COALESCE(ek_odenek_nakit, 0) = 0;

-- toplam_odenecek: (Brüt − Kesintiler) + Ek Ödenek (Banka + Nakit)
ALTER TABLE bordro
  MODIFY COLUMN toplam_odenecek DECIMAL(10,2)
  GENERATED ALWAYS AS (
    GREATEST(brut_maas - (COALESCE(izin_kesintisi,0) + COALESCE(sgk_kesintisi,0) + COALESCE(diger_kesintiler,0)), 0)
    + COALESCE(ek_odenek_banka,0) + COALESCE(ek_odenek_nakit,0)
  ) STORED;


