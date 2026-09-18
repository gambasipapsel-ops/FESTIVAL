<?php
/**
 * Bootstrap: dimuat oleh setiap halaman/endpoint di public/.
 */

declare(strict_types=1);

date_default_timezone_set('Asia/Jayapura');
mb_internal_encoding('UTF-8');

$GLOBALS['GAMBASI_CONFIG'] = require __DIR__ . '/config/config.php';

require_once __DIR__ . '/helpers/response.php';
require_once __DIR__ . '/helpers/security.php';
require_once __DIR__ . '/helpers/validation.php';
require_once __DIR__ . '/helpers/api.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/view.php';

/**
 * Ambil konfigurasi dengan dot notation, mis. config('event.nama').
 */
function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['GAMBASI_CONFIG'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

// ---- Error handling: jangan pernah tampilkan detail error ke publik ----
error_reporting(E_ALL);
ini_set('display_errors', config('app.debug') ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', config('storage') . '/logs/php-error.log');

set_exception_handler(function (Throwable $e): void {
    app_log('error', 'Unhandled exception', ['type' => get_class($e), 'msg' => $e->getMessage(),
        'file' => basename($e->getFile()) . ':' . $e->getLine()]);
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (is_json_request()) {
        json_error('Terjadi kesalahan pada server. Silakan coba lagi.', 'SERVER_ERROR', 500);
    }
    echo '<!doctype html><meta charset="utf-8"><title>Terjadi kesalahan</title>'
        . '<p style="font-family:sans-serif;padding:2rem">Terjadi kesalahan pada server. Silakan coba lagi nanti.</p>';
    exit;
});

foreach (['cache', 'ratelimit', 'logs', 'admin', 'sessions'] as $dir) {
    $path = config('storage') . '/' . $dir;
    if (!is_dir($path)) {
        @mkdir($path, 0750, true);
    }
}

send_security_headers();
start_secure_session();
