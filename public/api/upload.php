<?php
/**
 * Langkah 2 submission: upload satu dokumen (akte / foto / logo).
 * File divalidasi di PHP (finfo, ekstensi, ukuran) lalu diteruskan ke Apps Script
 * yang memvalidasi ulang dan menyimpannya secara privat di Google Drive.
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

require_method('POST');

// Jika body melebihi post_max_size, PHP mengosongkan $_POST/$_FILES
$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > (int) config('upload.max_bytes') + 1024 * 1024) {
    json_error('Ukuran file melebihi 5 MB.', 'FILE_TOO_LARGE', 413);
}

require_csrf_json();
require_rate_limit_json('upload');

$token = $_POST['submission_token'] ?? '';
if (!is_valid_submission_token($token)) {
    json_error('Token pendaftaran tidak valid.', 'INVALID_TOKEN', 422);
}
if (!isset($_SESSION['submissions'][$token])) {
    json_error('Sesi pendaftaran tidak ditemukan. Silakan kirim ulang pendaftaran.', 'INVALID_STATE', 409);
}

$slot = is_string($_POST['slot'] ?? null) ? $_POST['slot'] : '';
if (!preg_match('/^(?:(akte|foto):[a-z0-9]{6,16}|logo)$/', $slot, $m)) {
    json_error('Slot dokumen tidak valid.', 'INVALID_SLOT', 422);
}
$docType = $slot === 'logo' ? 'logo' : $m[1];

$check = validate_uploaded_file($_FILES['file'] ?? null, $docType);
if (!$check['ok']) {
    json_error($check['message'], $check['code'], http_status_for($check['code']));
}

$content = file_get_contents($check['path']);
if ($content === false || strlen($content) !== $check['size']) {
    json_error('File gagal dibaca. Silakan unggah ulang.', 'INVALID_FILE', 422);
}

// Lepas lock session selama request panjang ke Apps Script
session_write_close();

$res = gas_request('upload_file', [
    'submission_token' => $token,
    'slot'             => $slot,
    'file_name'        => $check['name'],
    'mime_type'        => $check['mime'],
    'data_base64'      => base64_encode($content),
], null, (int) config('api.upload_timeout'));
unset($content);

app_log('info', 'upload', ['doc' => $docType, 'size' => $check['size'], 'ok' => $res['success'], 'code' => $res['error_code'] ?? '']);

forward_api_result($res, ['slot', 'size', 'replaced']);
