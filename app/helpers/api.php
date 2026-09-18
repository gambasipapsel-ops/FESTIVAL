<?php
/**
 * Client Google Apps Script Web App.
 * URL & secret hanya ada di server; browser tidak pernah menghubungi Apps Script langsung.
 */

declare(strict_types=1);

function api_fail(string $message, string $code): array
{
    return ['success' => false, 'message' => $message, 'error_code' => $code];
}

/**
 * Kirim request ke Apps Script.
 * @return array respons terstandar (success/message/data atau error_code)
 */
function gas_request(string $action, array $data = [], ?array $admin = null, ?int $timeout = null): array
{
    $url = (string) config('api.url');
    $secret = (string) config('api.secret');
    if ($url === '' || $secret === '' || !preg_match('#^https?://#i', $url)) {
        app_log('error', 'API belum dikonfigurasi', ['action' => $action]);
        return api_fail('Layanan pendaftaran belum dikonfigurasi. Hubungi panitia.', 'CONFIG_ERROR');
    }
    if (!function_exists('curl_init')) {
        return api_fail('Ekstensi cURL PHP belum aktif.', 'CONFIG_ERROR');
    }

    $body = ['action' => $action, 'secret' => $secret, 'data' => $data];
    if ($admin !== null) {
        $body['admin'] = $admin;
    }
    $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    unset($body);

    $timeout ??= (int) config('api.timeout');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        // Apps Script membalas 302 ke googleusercontent.com; ikuti sebagai GET
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS | CURLPROTO_HTTP,
        CURLOPT_USERAGENT      => 'GAMBASI-PHP/1.0',
    ]);
    $started = microtime(true);
    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    $ms = (int) ((microtime(true) - $started) * 1000);

    if ($errno === CURLE_OPERATION_TIMEDOUT) {
        app_log('error', 'API timeout', ['action' => $action, 'ms' => $ms]);
        return api_fail('Server pendaftaran tidak merespons tepat waktu. Silakan coba lagi.', 'API_TIMEOUT');
    }
    if ($errno !== 0 || $response === false) {
        app_log('error', 'API connection error', ['action' => $action, 'errno' => $errno]);
        return api_fail('Tidak dapat terhubung ke server pendaftaran. Silakan coba lagi.', 'API_UNAVAILABLE');
    }
    if ($status !== 200) {
        app_log('error', 'API HTTP error', ['action' => $action, 'status' => $status]);
        return api_fail('Server pendaftaran sedang bermasalah. Silakan coba lagi.', 'API_UNAVAILABLE');
    }

    $decoded = json_decode((string) $response, true);
    if (!is_array($decoded) || !array_key_exists('success', $decoded)) {
        // Mis. halaman login Google karena deployment Web App salah dikonfigurasi
        app_log('error', 'API invalid response', ['action' => $action, 'content_type' => $contentType]);
        return api_fail('Respons server pendaftaran tidak valid. Hubungi panitia.', 'API_UNAVAILABLE');
    }
    if (empty($decoded['success'])) {
        $code = (string) ($decoded['error_code'] ?? 'SERVER_ERROR');
        if (in_array($code, ['UNAUTHORIZED', 'CONFIG_ERROR', 'SERVER_ERROR', 'SHEETS_ERROR', 'DRIVE_ERROR'], true)) {
            app_log('warning', 'API error', ['action' => $action, 'code' => $code]);
        }
        if ($code === 'UNAUTHORIZED' && $admin === null) {
            // Secret tidak cocok: jangan bocorkan detail ke publik
            return api_fail('Layanan pendaftaran belum dikonfigurasi dengan benar. Hubungi panitia.', 'CONFIG_ERROR');
        }
    }
    return [
        'success'    => (bool) $decoded['success'],
        'message'    => (string) ($decoded['message'] ?? ''),
        'data'       => is_array($decoded['data'] ?? null) ? $decoded['data'] : [],
        'error_code' => (string) ($decoded['error_code'] ?? ''),
        'errors'     => is_array($decoded['errors'] ?? null) ? $decoded['errors'] : [],
    ];
}

/**
 * Konfigurasi publik (aturan usia, batas pemain, status pendaftaran) dengan cache 5 menit.
 */
function gas_public_config(bool $refresh = false): array
{
    $file = config('storage') . '/cache/public_config.json';
    if (!$refresh && is_file($file) && filemtime($file) > time() - 300) {
        $cached = json_decode((string) file_get_contents($file), true);
        if (is_array($cached)) {
            return $cached;
        }
    }
    $res = gas_request('public_config');
    if (!empty($res['success'])) {
        $data = $res['data'] + ['available' => true];
        @file_put_contents($file, json_encode($data), LOCK_EX);
        return $data;
    }
    return [
        'available' => false,
        'error_code' => $res['error_code'] ?? 'API_UNAVAILABLE',
        'registration_open' => false,
        'age_rules' => ['U10' => null, 'U12' => null],
        'players' => ['min' => 1, 'max' => 40, 'configured' => false],
        'max_file_bytes' => config('upload.max_bytes'),
    ];
}

function gas_public_config_clear(): void
{
    @unlink(config('storage') . '/cache/public_config.json');
}

/** Request admin: menyertakan konteks admin dari session. */
function gas_admin(string $action, array $data = [], ?int $timeout = null): array
{
    $ctx = admin_api_context();
    if ($ctx === null) {
        return api_fail('Sesi admin berakhir. Silakan login kembali.', 'UNAUTHORIZED');
    }
    return gas_request($action, $data, $ctx, $timeout);
}
