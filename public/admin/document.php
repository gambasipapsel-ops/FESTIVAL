<?php
/**
 * Akses dokumen terkontrol: hanya admin dengan izin view_documents.
 * File di Google Drive tetap privat; PHP mengambilnya via Apps Script lalu men-stream ke admin.
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

require_admin('view_documents');
$adminCtx = admin_api_context();
// Lepas lock session agar beberapa thumbnail dapat dimuat paralel
session_write_close();

$target = $_GET['target'] ?? '';
$id = is_string($_GET['id'] ?? null) ? $_GET['id'] : '';
$type = $_GET['type'] ?? '';
$valid = ($target === 'player' && in_array($type, ['akte', 'foto'], true) && preg_match('/^PMN-[A-Z0-9]{6,20}$/', $id))
    || ($target === 'club' && $type === 'logo' && preg_match('/^CLB-[A-Z0-9]{6,20}$/', $id));

function doc_fail(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo $message;
    exit;
}

if (!$valid) {
    doc_fail(400, 'Permintaan dokumen tidak valid.');
}

$res = gas_request('admin_get_file', ['target' => $target, 'id' => $id, 'type' => $type], $adminCtx, 60);
if (empty($res['success'])) {
    $code = (string) ($res['error_code'] ?? '');
    doc_fail(http_status_for($code), (string) ($res['message'] ?? 'Dokumen tidak dapat dimuat.'));
}

$mime = (string) ($res['data']['mime_type'] ?? '');
$ext = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$mime] ?? null;
$bin = base64_decode((string) ($res['data']['data_base64'] ?? ''), true);
if ($ext === null || $bin === false || $bin === '') {
    doc_fail(502, 'Dokumen tidak valid.');
}

$detected = (new finfo(FILEINFO_MIME_TYPE))->buffer($bin);
if ($detected !== $mime) {
    doc_fail(502, 'Dokumen tidak valid.');
}

$filename = strtoupper($type) . '_' . $id . '.' . $ext;
$disposition = !empty($_GET['download']) ? 'attachment' : 'inline';

header_remove('Content-Security-Policy');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; object-src 'self'; frame-ancestors 'self'");
header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($bin));
header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
echo $bin;
