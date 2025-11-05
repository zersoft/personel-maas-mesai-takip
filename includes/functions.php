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
    $alertClass = $type === 'success' ? 'alert-success' : 'alert-danger';
    return "<div class='alert $alertClass alert-dismissible fade show' role='alert'>
                $message
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
}
?>

