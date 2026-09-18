<?php
/**
 * Aksi admin via fetch (JSON): update status & catatan.
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

require_method('POST');
require_admin('view');
require_csrf_json();

$body = read_json_body(16000);
$action = (string) ($body['action'] ?? '');
$target = in_array($body['target'] ?? '', ['club', 'player'], true) ? $body['target'] : '';
$id = is_string($body['id'] ?? null) && preg_match('/^(CLB|PMN)-[A-Z0-9]{6,20}$/', $body['id']) ? $body['id'] : '';
if ($target === '' || $id === '') {
    json_error('Data tidak valid.', 'VALIDATION_ERROR', 422);
}
$note = is_string($body['note'] ?? null) ? mb_substr(trim($body['note']), 0, 500) : '';

switch ($action) {
    case 'update_status':
        require_admin('update_status');
        $status = is_string($body['status'] ?? null) ? strtoupper($body['status']) : '';
        if (!array_key_exists($status, config('status'))) {
            json_error('Status tidak valid.', 'VALIDATION_ERROR', 422);
        }
        $res = gas_admin('admin_update_status', ['target' => $target, 'id' => $id, 'status' => $status, 'note' => $note]);
        forward_api_result($res, ['target', 'id', 'status', 'previous']);

    case 'add_note':
        require_admin('add_note');
        if ($note === '') {
            json_error('Catatan tidak boleh kosong.', 'VALIDATION_ERROR', 422);
        }
        $res = gas_admin('admin_add_note', ['target' => $target, 'id' => $id, 'note' => $note]);
        forward_api_result($res, ['catatan_admin']);

    default:
        json_error('Aksi tidak dikenal.', 'BAD_REQUEST', 400);
}
