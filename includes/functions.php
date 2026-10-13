<?php
/**
 * Yardımcı Fonksiyonlar
 */

/**
 * Güvenli çıktı için HTML escape
 */
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Tarih formatlama
 */
function formatDate($date, $format = 'd.m.Y') {
    return date($format, strtotime($date));
}

/**
 * Tarih formatı (gün adı ile)
 */
function formatDateWithDay($date) {
    $gunler = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];
    $timestamp = strtotime($date);
    $gun = $gunler[date('w', $timestamp)];
    return date('d.m.Y', $timestamp) . ' <span class="text-muted small">' . $gun . '</span>';
}

/**
 * Para formatı
 */
function formatMoney($amount) {
    return number_format($amount, 2, ',', '.') . ' ₺';
}

/**
 * Para değerini parse et (TR/EN format desteği)
 */
function parseMoney($value) {
    if (empty($value) && $value !== '0' && $value !== 0) return 0;
    
    // Sayısal tip ise (float veya int) direkt dön
    if (is_int($value) || is_float($value)) {
        return (float)$value;
    }
    
    // String ise parse et
    $value = trim((string)$value);
    if ($value === '' || $value === '0') return 0;
    
    // TL simgesi ve boşlukları temizle
    $value = str_replace(['₺', 'TL', ' '], '', $value);
    
    // Binlik ayırıcıları kaldır (nokta veya virgül)
    $hasDot = strpos($value, '.') !== false;
    $hasComma = strpos($value, ',') !== false;
    
    if ($hasDot && $hasComma) {
        // Hem nokta hem virgül var - hangisi binlik hangisi ondalık?
        $dotPos = strrpos($value, '.');
        $commaPos = strrpos($value, ',');
        
        if ($dotPos > $commaPos) {
            // Nokta ondalık, virgül binlik (İngiliz formatı: 1,234.56)
            $value = str_replace(',', '', $value);
        } else {
            // Virgül ondalık, nokta binlik (Türk formatı: 1.234,56)
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }
    } elseif ($hasComma) {
        // Sadece virgül var - Türk formatı (ondalık: 20000,00 veya 20,50)
        $value = str_replace(',', '.', $value);
    } elseif ($hasDot) {
        $parts = explode('.', $value);
        // Birden fazla nokta varsa (1.000.000) veya son parça 3 basamaklı ise (20.000) -> Türkçe binlik ayracıdır
        if (count($parts) > 2 || (count($parts) === 2 && strlen($parts[1]) === 3)) {
            $value = str_replace('.', '', $value);
        }
    }
    
    return (float)$value;
}

/**
 * Bordro ödeme ve kesinti dağılımını hesapla (Banka / Nakit / Toplam)
 * Avans ve kesintiler kanallardan bağımsız olarak tüm kazançtan düşülür;
 * Nakit avans banka ödemesinden de kesilebilir, hiçbir kesinti kaybolmaz.
 */
function calculateBordroPay($brutMaas, $sgkBanka, $ekBanka = 0, $ekNakit = 0, $izinK = 0, $sgkK = 0, $digerK = 0, $avBanka = 0, $avNakit = 0) {
    $brutMaas = (float)$brutMaas;
    $sgkBanka = (float)$sgkBanka;
    $ekBanka = (float)$ekBanka;
    $ekNakit = (float)$ekNakit;
    $izinK = (float)$izinK;
    $sgkK = (float)$sgkK;
    $digerK = (float)$digerK;
    $avBanka = (float)$avBanka;
    $avNakit = (float)$avNakit;

    // Şirkette tüm maaşlar bankadan ödendiği için, sgk_banka belirtilmemişse veya 0 ise
    // maaşın tamamı banka baz kabul edilir.
    if ($sgkBanka <= 0) {
        $bankaBaz = $brutMaas;
        $nakitBaz = 0.0;
    } else {
        $bankaBaz = min($sgkBanka, $brutMaas);
        $nakitBaz = max($brutMaas - $sgkBanka, 0.0);
    }

    $bankaPay = $bankaBaz + $ekBanka;
    $nakitPay = $nakitBaz + $ekNakit;

    // 1) Nakit avansı önce nakit payından düş; yetmezse kalanı banka ödemesinden kes
    if ($avNakit > 0) {
        if ($nakitPay >= $avNakit) {
            $nakitPay -= $avNakit;
        } else {
            $kalanAvNakit = $avNakit - $nakitPay;
            $nakitPay = 0.0;
            $bankaPay = max($bankaPay - $kalanAvNakit, 0.0);
        }
    }

    // 2) Banka avansını önce banka payından düş; yetmezse kalanı nakitten kes
    if ($avBanka > 0) {
        if ($bankaPay >= $avBanka) {
            $bankaPay -= $avBanka;
        } else {
            $kalanAvBanka = $avBanka - $bankaPay;
            $bankaPay = 0.0;
            $nakitPay = max($nakitPay - $kalanAvBanka, 0.0);
        }
    }

    // 3) Diğer kesintiler (izin, sgk, diğer): önce nakitten, yetmezse bankadan düş
    $digerKesintiTop = $izinK + $sgkK + $digerK;
    if ($digerKesintiTop > 0) {
        if ($nakitPay >= $digerKesintiTop) {
            $nakitPay -= $digerKesintiTop;
        } else {
            $kalanKesinti = $digerKesintiTop - $nakitPay;
            $nakitPay = 0.0;
            $bankaPay = max($bankaPay - $kalanKesinti, 0.0);
        }
    }

    $toplamOdenecek = $bankaPay + $nakitPay;
    $toplamKesinti = $izinK + $sgkK + $digerK + $avBanka + $avNakit;
    $toplamEkOdenek = $ekBanka + $ekNakit;

    return [
        'banka_net' => $bankaPay,
        'nakit_net' => $nakitPay,
        'toplam_odenecek' => $toplamOdenecek,
        'toplam_kesinti' => $toplamKesinti,
        'toplam_ek_odenek' => $toplamEkOdenek,
    ];
}

/**
 * Mesaj gösterimi
 */
function showMessage($message, $type = 'success') {
    $alertClasses = [
        'success' => 'alert-success',
        'danger' => 'alert-danger',
        'info' => 'alert-info',
        'warning' => 'alert-warning'
    ];
    $alertClass = $alertClasses[$type] ?? 'alert-info';
    $escapedMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    return "<div class='alert $alertClass alert-dismissible fade show' role='alert'>
                $escapedMessage
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
}

/**
 * Türkçe ay ismi
 */
function getTurkishMonthName($monthNumber) {
    $months = [
        1 => 'Ocak',
        2 => 'Şubat',
        3 => 'Mart',
        4 => 'Nisan',
        5 => 'Mayıs',
        6 => 'Haziran',
        7 => 'Temmuz',
        8 => 'Ağustos',
        9 => 'Eylül',
        10 => 'Ekim',
        11 => 'Kasım',
        12 => 'Aralık'
    ];
    return $months[(int)$monthNumber] ?? '';
}

/**
 * Güvenli yönlendirme (headers already sent durumunda bile çalışır)
 */
function safeRedirect($url) {
    if (ob_get_level() > 0) { @ob_end_clean(); }
    header('Location: ' . $url);
    echo '<!doctype html><html><head><meta charset="utf-8">'
        . '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
        . '</head><body>'
        . '<script>location.replace(' . json_encode($url) . ');</script>'
        . '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">Yönlendiriliyorsunuz...</a>'
        . '</body></html>';
    exit;
}