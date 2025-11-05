-- Para sütunlarındaki fazladan 2 sıfırı kaldır (100'e böl)
UPDATE personel_listesi 
SET 
    maas = maas / 100,
    maas_sgk = maas_sgk / 100,
    mesai_saat_ucreti = mesai_saat_ucreti / 100;

