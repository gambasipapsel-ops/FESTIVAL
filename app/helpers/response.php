<?php
/**
 * Helper respons HTTP / JSON.
 * Format: {"success":true,"message":"...","data":{}} atau
 *         {"success":false,"message":"...","error_code":"..."}
 */

declare(strict_types=1);

function is_json_request(): bool
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return str_contains($uri, '/api/') || str_contains($accept, 'application/json');
}

function json_response(array $payload, int $status = 200): never
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_ok(string $message, array $data = []): never
{
    json_response(['success' => true, 'message' => $message, 'data' => (object) $data]);
}

function json_error(string $message, string $code, int $status = 400, array $errors = []): never
{
    $payload = ['success' => false, 'message' => $message, 'error_code' => $code];
    if ($errors) {
        $payload['errors'] = array_values($errors);
    }
    json_response($payload, $status);
}

/** Map error_code dari Apps Script ke HTTP status yang wajar. */
function http_status_for(string $code): int
{
    return match ($code) {
        'VALIDATION_ERROR', 'INVALID_CATEGORY', 'INVALID_AGE', 'INVALID_FILE', 'INVALID_MIME',
        'INVALID_SLOT', 'INVALID_TOKEN', 'MISSING_FILES', 'BAD_REQUEST' => 422,
        'FILE_TOO_LARGE' => 413,
        'DUPLICATE_CLUB', 'DUPLICATE_NIK', 'ALREADY_SUBMITTED', 'INVALID_STATE' => 409,
        'NOT_FOUND' => 404,
        'UNAUTHORIZED', 'CSRF_ERROR' => 401,
        'FORBIDDEN' => 403,
        'RATE_LIMITED' => 429,
        'REGISTRATION_CLOSED', 'SUBMISSION_EXPIRED' => 410,
        'BUSY', 'API_UNAVAILABLE', 'API_NOT_PUBLIC', 'API_WRONG_BACKEND', 'API_TIMEOUT', 'DRIVE_ERROR', 'SHEETS_ERROR', 'CONFIG_ERROR' => 503,
        default => 500,
    };
}

/** Teruskan respons Apps Script ke browser tanpa field tambahan. */
function forward_api_result(array $result, ?array $allowedDataKeys = null): never
{
    if (!empty($result['success'])) {
        $data = is_array($result['data'] ?? null) ? $result['data'] : [];
        if ($allowedDataKeys !== null) {
            $data = array_intersect_key($data, array_flip($allowedDataKeys));
        }
        json_ok((string) ($result['message'] ?? 'OK'), $data);
    }
    $code = (string) ($result['error_code'] ?? 'SERVER_ERROR');
    json_error(
        (string) ($result['message'] ?? 'Terjadi kesalahan.'),
        $code,
        http_status_for($code),
        is_array($result['errors'] ?? null) ? $result['errors'] : []
    );
}

function require_method(string $method): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
        header('Allow: ' . $method);
        json_error('Metode tidak diizinkan.', 'METHOD_NOT_ALLOWED', 405);
    }
}

function read_json_body(int $maxBytes = 512000): array
{
    $len = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($len > $maxBytes) {
        json_error('Data terlalu besar.', 'BAD_REQUEST', 413);
    }
    $raw = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
    if ($raw === false || strlen($raw) > $maxBytes) {
        json_error('Data terlalu besar.', 'BAD_REQUEST', 413);
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_error('Format data tidak valid.', 'BAD_REQUEST', 400);
    }
    return $data;
}

function redirect(string $path, int $status = 302): never
{
    header('Location: ' . $path, true, $status);
    exit;
}
