<?php
/**
 * Sürüm notları – en yeni sürüm en üstte.
 * Her iyileştirmede bu dosyaya yeni bir blok ekleyin ve config/app.php içinde APP_VERSION değerini güncelleyin.
 */
return [
    [
        'version' => '2.0.4',
        'date'    => '2025-02-15',
        'title'   => 'OYS markası, Kantar Raporları, Viewer rolü',
        'notes'   => [
            'Uygulama adı OYS (Ocak Yönetim Sistemi) olarak güncellendi; taş ocağı / agrega üretimi odağı.',
            'Simge bi-layers (katmanlar) ile değiştirildi.',
            'Kantar Raporları: Perakende Satış, Özet Rapor, Özet Malzeme Satış, Cari Ekstre sekmeleri ve PDF çıktıları.',
            'Periyot seçimi (Bugün, Bu Hafta, Bu Ay, Bu Yıl); varsayılan tarih aralığı bugün; seçili periyot butonu vurgulandı.',
            'Özet Malzeme Satış: sadece dönem sütunları; seçili dönemdeki toplam miktara göre Oran (%) sütunu eklendi.',
            'Perakende Satış: personel sütunu kaldırıldı; filtre müşteri veya plaka ile arama yapıyor.',
            'Cari Ekstre: müşteri seçimi Select2 ile aranabilir hale getirildi.',
            'Özet Rapor PDF: sütun başlıkları kısaltıldı (üst üste binme düzeltildi).',
            'Viewer rolü: ekleme ve düzenleme butonları görünmüyor; tüm işlem sayfaları requireRole(\'user\') ile korunuyor.',
            'Veritabanı ayarları .env ve config/load_env.php ile yönetiliyor; raporlama DB ayrı tanımlanabiliyor.',
        ],
    ],
    [
        'version' => '2.0.3',
        'date'    => '2025-02-14',
        'title'   => 'Fazla mesai alacak raporu ve PDF',
        'notes'   => [
            'Kalan FM Alacakları sekmesi eklendi: tüm dönem için personel bazlı toplam FM, toplam ödeme ve kalan alacak.',
            'Kalan alacaklar raporu için PDF indirme (ekranla aynı veri).',
            'Bakiyesizleri göster/gizle seçeneği hem ekranda hem PDF çıktısında.',
        ],
    ],
    [
        'version' => '2.0.2',
        'date'    => '2025-02-14',
        'title'   => 'Bordro ve rapor iyileştirmeleri',
        'notes'   => [
            'Bordro özeti sayfası ve PDF çıktısı iyileştirmeleri.',
            'Bordro detay ve ödeme özeti raporları güncellendi.',
        ],
    ],
    [
        'version' => '2.0.1',
        'date'    => '2025-02-01',
        'title'   => 'Genel iyileştirmeler',
        'notes'   => [
            'Avans takip raporu ve PDF desteği.',
            'Fazla mesai kayıtları ve kümülatif toplamlar filtreleri.',
        ],
    ],
    [
        'version' => '2.0.0',
        'date'    => '2025-01-15',
        'title'   => 'OYS 2.0 - Ocak Yönetim Sistemi',
        'notes'   => [
            'Ana modüller: Personel, Bordro, Puantaj, Fazla Mesai, Avans, Tazminat, Raporlar.',
            'Kullanıcı ve rol yönetimi.',
        ],
    ],
];
