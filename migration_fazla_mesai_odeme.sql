-- Fazla Mesai Ödeme Tablosu
CREATE TABLE IF NOT EXISTS fazla_mesai_odeme (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personel_id INT NOT NULL,
    odeme_tarihi DATE NOT NULL,
    tutar DECIMAL(10,2) NOT NULL DEFAULT 0,
    aciklama TEXT,
    odeme_zamani TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (personel_id) REFERENCES personel_listesi(id) ON DELETE CASCADE
);


