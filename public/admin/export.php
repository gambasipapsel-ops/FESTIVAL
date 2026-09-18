<?php
/**
 * Export CSV data administratif (tanpa dokumen binary).
 * Data sensitif (NIK, orang tua/wali) hanya untuk role dengan izin export_sensitive.
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

require_admin('export');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !verify_csrf()) {
    http_response_code(400);
    exit('Permintaan tidak valid.');
}

$dataset = in_array($_POST['dataset'] ?? '', ['clubs', 'players'], true) ? $_POST['dataset'] : '';
$sensitive = !empty($_POST['sensitive']);
if ($dataset === '') {
    http_response_code(400);
    exit('Dataset tidak valid.');
}
if ($sensitive) {
    require_admin('export_sensitive');
}
$kategori = in_array($_POST['kategori'] ?? '', ['U10', 'U12'], true) ? $_POST['kategori'] : '';
$status = array_key_exists($_POST['status'] ?? '', config('status')) ? $_POST['status'] : '';

$res = gas_admin('admin_export', [
    'dataset' => $dataset,
    'include_sensitive' => $sensitive,
    'kategori' => $kategori,
    'status' => $status,
], 90);

if (empty($res['success'])) {
    http_response_code(http_status_for((string) ($res['error_code'] ?? '')));
    header('Content-Type: text/plain; charset=utf-8');
    exit('Export gagal: ' . ($res['message'] ?? ''));
}

/** Netralkan formula injection saat CSV dibuka di Excel/Sheets. */
function csv_safe(mixed $v): string
{
    $s = (string) $v;
    if ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
        $s = "'" . $s;
    }
    return $s;
}

$filename = sprintf('gambasi_%s%s_%s.csv', $dataset, $sensitive ? '_sensitif' : '', date('Ymd_His'));
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, private');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
fputcsv($out, $res['data']['columns'], ',', '"', '\\');
foreach ($res['data']['rows'] as $row) {
    // NIK/nomor WA diawali tanda kutip tunggal agar tidak berubah jadi notasi ilmiah di Excel
    fputcsv($out, array_map(fn($v) => csv_safe(is_string($v) && preg_match('/^\d{10,}$/', $v) ? "'" . $v : $v), $row), ',', '"', '\\');
}
fclose($out);
