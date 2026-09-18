<?php
/**
 * Langkah 3 submission: finalisasi. Nomor pendaftaran dibuat oleh Apps Script
 * hanya setelah data & seluruh dokumen wajib terverifikasi tersimpan.
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

require_method('POST');
require_csrf_json();
require_rate_limit_json('submit_finalize');

$body = read_json_body(4096);
$token = $body['submission_token'] ?? '';
if (!is_valid_submission_token($token)) {
    json_error('Token pendaftaran tidak valid.', 'INVALID_TOKEN', 422);
}
if (!isset($_SESSION['submissions'][$token])) {
    json_error('Sesi pendaftaran tidak ditemukan. Silakan kirim ulang pendaftaran.', 'INVALID_STATE', 409);
}

$res = gas_request('submit_finalize', ['submission_token' => $token], null, 120);

$data = $res['data'] ?? [];
$valid = !empty($res['success'])
    && is_string($data['nomor_pendaftaran'] ?? null)
    && preg_match('/^GMB-SB-2026-\d{4,6}$/', $data['nomor_pendaftaran']);

if (!empty($res['success']) && !$valid) {
    // Jangan pernah menampilkan sukses tanpa nomor pendaftaran yang valid dari backend
    app_log('error', 'finalize success without valid nomor');
    json_error('Status pendaftaran belum dapat dipastikan. Silakan coba kirim lagi.', 'SERVER_ERROR', 502);
}

if ($valid) {
    $_SESSION['last_success'] = [
        'nomor_pendaftaran' => $data['nomor_pendaftaran'],
        'nama_club'         => (string) ($data['nama_club'] ?? ''),
        'kategori'          => (string) ($data['kategori'] ?? ''),
        'status'            => (string) ($data['status'] ?? 'PENDING'),
        'jumlah_pemain'     => (int) ($data['jumlah_pemain'] ?? 0),
        'at'                => time(),
    ];
    unset($_SESSION['submissions'][$token]);
    app_log('info', 'submit_finalize ok', ['nomor' => $data['nomor_pendaftaran'], 'already' => !empty($data['already_submitted'])]);
} else {
    app_log('warning', 'submit_finalize failed', ['code' => $res['error_code'] ?? '']);
}

forward_api_result($res, ['nomor_pendaftaran', 'nama_club', 'kategori', 'status', 'jumlah_pemain', 'already_submitted']);
