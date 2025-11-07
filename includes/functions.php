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
    if (empty($value) || $value === '') return 0;
    
    // Zaten sayısal ise direkt dön
    if (is_numeric($value)) {
        return (float)$value;
    }
    
    // String ise parse et
    $value = trim($value);
    
    // Binlik ayırıcıları kaldır (nokta veya virgül)
    // Önce binlik ayırıcıyı tespit et
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
        // Sadece virgül var - muhtemelen Türk formatı (ondalık)
        $value = str_replace(',', '.', $value);
    } elseif ($hasDot) {
        // Sadece nokta var - muhtemelen İngiliz formatı (ondalık)
        // Binlik ayırıcı olabilir, kontrol et
        $parts = explode('.', $value);
        if (count($parts) === 2 && strlen($parts[1]) <= 2) {
            // Ondalık kısım 2 haneden az/equal - muhtemelen ondalık
            // Olduğu gibi bırak
        } else {
            // Muhtemelen binlik ayırıcı - kaldır
            $value = str_replace('.', '', $value);
        }
    }
    
    return (float)$value;
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
    return "<div class='alert $alertClass alert-dismissible fade show' role='alert'>
                $message
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