-- Bordro tablosuna nakit alanı ekle ve toplam_odeme'yi toplam_odenecek olarak güncelle

-- 1. Önce toplam_odeme'yi geçici olarak kaldır
ALTER TABLE bordro DROP COLUMN toplam_odeme;

-- 2. Nakit alanını ekle (brut_maas - sgk_banka)
ALTER TABLE bordro 
ADD COLUMN nakit DECIMAL(10,2) GENERATED ALWAYS AS (brut_maas - sgk_banka) STORED AFTER sgk_banka;

-- 3. Toplam ödenecek alanını ekle (brut_maas + ek_odenek - kesintiler)
ALTER TABLE bordro 
ADD COLUMN toplam_odenecek DECIMAL(10,2) GENERATED ALWAYS AS (
    brut_maas + ek_odenek - COALESCE(izin_kesintisi, 0) - COALESCE(sgk_kesintisi, 0) - COALESCE(diger_kesintiler, 0)
) STORED AFTER diger_kesintiler;

