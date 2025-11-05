-- Bordro tablosuna ödeme tipi ve kesintiler ekleme
ALTER TABLE bordro 
ADD COLUMN odeme_tipi ENUM('BANKA', 'NAKIT') DEFAULT 'BANKA' AFTER ek_odenek,
ADD COLUMN izin_gunu DECIMAL(5,2) DEFAULT 0 AFTER odeme_tipi,
ADD COLUMN izin_kesintisi DECIMAL(10,2) DEFAULT 0 AFTER izin_gunu,
ADD COLUMN sgk_kesintisi DECIMAL(10,2) DEFAULT 0 AFTER izin_kesintisi,
ADD COLUMN diger_kesintiler DECIMAL(10,2) DEFAULT 0 AFTER sgk_kesintisi,
ADD COLUMN kesinti_aciklama TEXT AFTER diger_kesintiler;

-- Toplam ödeme formülünü güncelle (kesintileri de dahil et)
ALTER TABLE bordro 
MODIFY COLUMN toplam_odeme DECIMAL(10,2) GENERATED ALWAYS AS (
    brut_maas + ek_odenek - sgk_banka - izin_kesintisi - sgk_kesintisi - diger_kesintiler
) STORED;

-- Fazla mesai tablosuna ödeme tarihi ve ödeme tutarı ekleme
ALTER TABLE fazla_mesai 
ADD COLUMN odeme_tarihi DATE NULL AFTER odendi,
ADD COLUMN odeme_tutari DECIMAL(10,2) DEFAULT 0 AFTER tutar;

