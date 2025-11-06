-- Ödeme tipi alanını kaldır (artık sgk_banka ve nakit alanları yeterli)

ALTER TABLE bordro DROP COLUMN odeme_tipi;

