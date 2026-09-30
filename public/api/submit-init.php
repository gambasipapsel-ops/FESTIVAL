<?php
/**
 * Langkah 1 submission: validasi payload & buka sesi upload di Apps Script.
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

require_method('POST');
require_csrf_json();
require_rate_limit_json('submit_init');

$body = read_json_body(512000);
$token = $body['submission_token'] ?? '';
if (!is_valid_submission_token($token)) {
    json_error('Token pendaftaran tidak valid. Muat ulang halaman.', 'INVALID_TOKEN', 422);
}

$pub = gas_public_config();
if (empty($pub['available'])) {
    json_error('Layanan pendaftaran belum dapat diakses. Silakan coba lagi nanti.', (string) ($pub['error_code'] ?? 'API_UNAVAILABLE'), 503);
}
if (empty($pub['registration_open'])) {
    $msg = match ($pub['registration_window'] ?? '') {
        'before' => 'Pendaftaran belum dibuka. Pendaftaran dibuka ' . info_or_tba('pendaftaran_buka') . '.',
        'after'  => 'Pendaftaran sudah ditutup pada ' . info_or_tba('pendaftaran_tutup') . '.',
        default  => 'Pendaftaran sedang ditutup oleh panitia.',
    };
    json_error($msg, 'REGISTRATION_CLOSED', 410);
}

[$payload, $errors] = validate_registration_payload(is_array($body['payload'] ?? null) ? $body['payload'] : [], $pub);
if ($errors) {
    $codes = array_unique(array_map(fn($e) => $e['code'] ?? 'VALIDATION_ERROR', $errors));
    if ($codes === ['INVALID_CATEGORY']) {
        json_error($errors[0]['message'], 'INVALID_CATEGORY', 422);
    }
    if ($codes === ['INVALID_AGE']) {
        json_error('Usia pemain tidak sesuai kategori.', 'INVALID_AGE', 422, $errors);
    }
    json_error('Data pendaftaran belum valid. Periksa kembali isian Anda.', 'VALIDATION_ERROR', 422, $errors);
}

$gasPayload = [
    'kategori' => $payload['kategori'],
    'club'     => $payload['club'],
    'official' => $payload['official'],
    'players'  => $payload['players'],
];
$res = gas_request('submit_init', ['submission_token' => $token, 'payload' => $gasPayload], null, 90);

if (!empty($res['success'])) {
    $subs = $_SESSION['submissions'] ?? [];
    $subs[$token] = time();
    arsort($subs);
    $_SESSION['submissions'] = array_slice($subs, 0, 5, true);

    if (!empty($res['data']['already_submitted'])) {
        $_SESSION['last_success'] = array_intersect_key($res['data'], array_flip(
            ['nomor_pendaftaran', 'nama_club', 'kategori', 'status', 'jumlah_pemain']
        )) + ['at' => time()];
    }
} elseif (in_array($res['error_code'] ?? '', ['REGISTRATION_CLOSED', 'CONFIG_ERROR'], true)) {
    gas_public_config_clear();
}

app_log('info', 'submit_init', ['ok' => $res['success'], 'code' => $res['error_code'] ?? '', 'players' => count($payload['players'])]);

forward_api_result($res, ['submission_token', 'state', 'required_slots', 'optional_slots', 'uploaded_slots',
    'already_submitted', 'nomor_pendaftaran']);
