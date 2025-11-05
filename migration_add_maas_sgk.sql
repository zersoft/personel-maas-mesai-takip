-- maas_sgk alanını personel_listesi tablosuna ekle
ALTER TABLE personel_listesi 
ADD COLUMN maas_sgk DECIMAL(10,2) DEFAULT 0 AFTER maas;


