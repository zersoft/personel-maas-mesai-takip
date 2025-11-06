-- Bordro tablosundaki para değerlerini düzelt (2 sıfır fazla eklenmişse)
-- Sadece anormal yüksek değerleri düzelt (örneğin 1000'den büyük olanları 100'e böl)

UPDATE bordro 
SET 
    brut_maas = CASE 
        WHEN brut_maas >= 1000 THEN brut_maas / 100 
        ELSE brut_maas 
    END,
    sgk_banka = CASE 
        WHEN sgk_banka >= 1000 THEN sgk_banka / 100 
        ELSE sgk_banka 
    END,
    ek_odenek = CASE 
        WHEN ek_odenek >= 1000 THEN ek_odenek / 100 
        ELSE ek_odenek 
    END,
    izin_kesintisi = CASE 
        WHEN izin_kesintisi >= 1000 THEN izin_kesintisi / 100 
        ELSE izin_kesintisi 
    END,
    sgk_kesintisi = CASE 
        WHEN sgk_kesintisi >= 1000 THEN sgk_kesintisi / 100 
        ELSE sgk_kesintisi 
    END,
    diger_kesintiler = CASE 
        WHEN diger_kesintiler >= 1000 THEN diger_kesintiler / 100 
        ELSE diger_kesintiler 
    END
WHERE 
    brut_maas >= 1000 
    OR sgk_banka >= 1000 
    OR ek_odenek >= 1000 
    OR izin_kesintisi >= 1000 
    OR sgk_kesintisi >= 1000 
    OR diger_kesintiler >= 1000;

