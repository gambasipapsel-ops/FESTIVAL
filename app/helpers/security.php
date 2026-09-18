<?php
/**
 * Keamanan: header, session, CSRF, escaping, rate limit, logging.
 */

declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function random_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

function csp_nonce(): string
{
    static $nonce = null;
    return $nonce ??= base64_encode(random_bytes(16));
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    $proxies = config('security.trusted_proxies', []);
    return in_array($_SERVER['REMOTE_ADDR'] ?? '', $proxies, true)
        && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    $nonce = csp_nonce();
    $csp = implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'nonce-{$nonce}' https://cdn.tailwindcss.com",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "font-src 'self' https://fonts.gstatic.com",
        "img-src 'self' data: blob:",
        "connect-src 'self'",
        "frame-src 'self' blob:",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'self'",
    ]);
    header('Content-Security-Policy: ' . $csp);
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header_remove('X-Powered-By');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli') {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.sid_length', '48');
    ini_set('session.sid_bits_per_character', '6');
    ini_set('session.gc_maxlifetime', (string) max(3600, (int) config('security.session_timeout')));
    $savePath = config('storage') . '/sessions';
    if (is_dir($savePath) && is_writable($savePath)) {
        session_save_path($savePath);
    }
    session_name('GAMBASI_SID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_path() . '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Rotasi ID berkala untuk mengurangi risiko session fixation
    $now = time();
    if (!isset($_SESSION['_created'])) {
        $_SESSION['_created'] = $now;
    } elseif ($now - (int) $_SESSION['_created'] > 900) {
        session_regenerate_id(true);
        $_SESSION['_created'] = $now;
    }
}

// ---------------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = random_token();
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token = null): bool
{
    $token ??= $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $expected = $_SESSION['_csrf'] ?? '';
    return is_string($token) && $expected !== '' && hash_equals($expected, $token);
}

/** Untuk endpoint JSON. */
function require_csrf_json(): void
{
    if (!verify_csrf()) {
        json_error('Sesi halaman kedaluwarsa. Muat ulang halaman lalu coba lagi.', 'CSRF_ERROR', 419);
    }
}

// ---------------------------------------------------------------------------
// IP & rate limiting
// ---------------------------------------------------------------------------

function client_ip(): string
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $proxies = config('security.trusted_proxies', []);
    if ($proxies && in_array($remote, $proxies, true) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $first = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            return $first;
        }
    }
    return $remote;
}

/**
 * Sliding-window rate limiter berbasis file.
 * @return bool true jika request diizinkan
 */
function rate_limit(string $bucket, ?int $max = null, ?int $window = null, ?string $identity = null): bool
{
    [$defMax, $defWindow] = config("security.rate_limits.$bucket", [60, 60]);
    $max ??= $defMax;
    $window ??= $defWindow;
    $identity ??= client_ip();

    $file = config('storage') . '/ratelimit/' . hash('sha256', $bucket . '|' . $identity) . '.json';
    $fp = @fopen($file, 'c+');
    if ($fp === false) {
        return true; // jangan blokir pengguna jika storage bermasalah
    }
    try {
        flock($fp, LOCK_EX);
        $content = stream_get_contents($fp);
        $hits = json_decode($content ?: '[]', true);
        $now = time();
        $hits = array_values(array_filter(is_array($hits) ? $hits : [], fn($t) => is_int($t) && $t > $now - $window));
        $allowed = count($hits) < $max;
        if ($allowed) {
            $hits[] = $now;
        }
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($hits));
        return $allowed;
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function rate_limit_reset(string $bucket, ?string $identity = null): void
{
    $identity ??= client_ip();
    @unlink(config('storage') . '/ratelimit/' . hash('sha256', $bucket . '|' . $identity) . '.json');
}

function require_rate_limit_json(string $bucket): void
{
    if (!rate_limit($bucket)) {
        header('Retry-After: 60');
        json_error('Terlalu banyak permintaan. Silakan tunggu beberapa saat.', 'RATE_LIMITED', 429);
    }
}

// ---------------------------------------------------------------------------
// Logging (tanpa data sensitif)
// ---------------------------------------------------------------------------

function app_log(string $level, string $message, array $context = []): void
{
    $sensitive = ['nik', 'password', 'secret', 'data_base64', 'payload', 'nama_ayah', 'nama_ibu', 'nama_wali'];
    array_walk_recursive($context, function (&$v, $k) use ($sensitive) {
        if (is_string($k) && in_array(strtolower($k), $sensitive, true)) {
            $v = '[redacted]';
        }
    });
    $line = sprintf(
        "[%s] %s: %s %s\n",
        date('c'),
        strtoupper($level),
        $message,
        $context ? json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : ''
    );
    @file_put_contents(config('storage') . '/logs/app-' . date('Y-m') . '.log', $line, FILE_APPEND | LOCK_EX);
}
