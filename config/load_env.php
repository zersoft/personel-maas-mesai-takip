<?php
/**
 * .env dosyasını yükler ve $_ENV / putenv ile kullanılabilir yapar.
 * Proje kökündeki .env dosyasını okur (config/ bir üst dizin).
 */
if (isset($_ENV['_ENV_LOADED'])) {
    return;
}

$envFile = __DIR__ . '/../.env';
if (!is_file($envFile)) {
    $_ENV['_ENV_LOADED'] = true;
    return;
}

$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($lines === false) {
    $_ENV['_ENV_LOADED'] = true;
    return;
}

foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || strpos($line, '#') === 0) {
        continue;
    }
    if (strpos($line, '=') === false) {
        continue;
    }
    $parts = explode('=', $line, 2);
    $key = trim($parts[0]);
    $value = isset($parts[1]) ? trim($parts[1]) : '';
    if ($key === '') {
        continue;
    }
    // Tırnakları kaldır (tek veya çift)
    if ((strpos($value, '"') === 0 && substr($value, -1) === '"') ||
        (strpos($value, "'") === 0 && substr($value, -1) === "'")) {
        $value = substr($value, 1, -1);
    }
    $_ENV[$key] = $value;
    putenv("$key=$value");
}

$_ENV['_ENV_LOADED'] = true;
