<?php
/**
 * Sürüm notları – en yeni sürüm en üstte.
 * Her iyileştirmede bu dosyaya yeni bir blok ekleyin ve config/app.php içinde APP_VERSION değerini güncelleyin.
 */
return [
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
        'title'   => 'Personel Takip Sistemi 2.0',
        'notes'   => [
            'Ana modüller: Personel, Bordro, Puantaj, Fazla Mesai, Avans, Tazminat, Raporlar.',
            'Kullanıcı ve rol yönetimi.',
        ],
    ],
];
