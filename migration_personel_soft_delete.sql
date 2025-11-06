-- Personel soft delete için silinme_tarihi alanı ekle
ALTER TABLE personel_listesi
  ADD COLUMN silinme_tarihi DATETIME NULL DEFAULT NULL AFTER aktif;

-- Silinen kayıtları hızlı sorgulama için indeks
CREATE INDEX idx_personel_silinme_tarihi ON personel_listesi(silinme_tarihi);


