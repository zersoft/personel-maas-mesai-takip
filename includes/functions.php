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
 * Para formatı
 */
function formatMoney($amount) {
    return number_format($amount, 2, ',', '.') . ' ₺';
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
?>

