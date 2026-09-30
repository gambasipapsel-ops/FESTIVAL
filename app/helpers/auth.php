<?php
/**
 * Autentikasi admin: password hash (file JSON di luar document root), session, role, timeout.
 * Akun dibuat lewat CLI: php tools/create-admin.php
 */

declare(strict_types=1);

function admin_users(): array
{
    static $users = null;
    if ($users !== null) {
        return $users;
    }
    $file = (string) config('admin.users_file');
    $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    $users = [];
    foreach (($data['users'] ?? []) as $u) {
        if (!empty($u['username']) && !empty($u['password_hash']) && isset(config('admin.roles')[$u['role'] ?? ''])) {
            $users[strtolower($u['username'])] = $u;
        }
    }
    return $users;
}

/**
 * @return array{ok:bool, message:string}
 */
function admin_attempt_login(string $username, string $password): array
{
    $username = strtolower(clean_text($username, 60));
    $generic = ['ok' => false, 'message' => 'Username atau password salah.'];

    if (!rate_limit('admin_login') || !rate_limit('admin_login', 5, 900, 'user:' . $username)) {
        return ['ok' => false, 'message' => 'Terlalu banyak percobaan login. Coba lagi dalam 15 menit.'];
    }

    $user = admin_users()[$username] ?? null;
    // Tetap jalankan password_verify agar waktu respons serupa
    $hash = $user['password_hash'] ?? password_hash(random_token(8), PASSWORD_DEFAULT);
    $valid = password_verify($password, $hash);

    if (!$user || !$valid || ($user['active'] ?? true) === false) {
        app_log('warning', 'Admin login failed', ['username' => $username, 'ip' => client_ip()]);
        gas_request('admin_auth_event', ['aktivitas' => 'ADMIN_LOGIN_FAILED', 'username' => $username, 'detail' => 'ip=' . client_ip()]);
        return $generic;
    }

    session_regenerate_id(true);
    $_SESSION['_created'] = time();
    $_SESSION['admin'] = [
        'username' => $user['username'],
        'name'     => $user['name'] ?? $user['username'],
        'role'     => $user['role'],
        'login_at' => time(),
        'last'     => time(),
        'ua'       => hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''),
    ];
    $_SESSION['_csrf'] = random_token();
    rate_limit_reset('admin_login', 'user:' . $username);

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        app_log('info', 'Admin password hash perlu diperbarui', ['username' => $username]);
    }
    gas_request('admin_auth_event', ['aktivitas' => 'ADMIN_LOGIN', 'username' => $user['username'], 'detail' => 'ip=' . client_ip()]);
    return ['ok' => true, 'message' => 'Login berhasil.'];
}

function admin_current(): ?array
{
    $a = $_SESSION['admin'] ?? null;
    if (!is_array($a)) {
        return null;
    }
    $timeout = (int) config('security.session_timeout');
    $user = admin_users()[strtolower($a['username'])] ?? null;
    $expired = time() - (int) $a['last'] > $timeout;
    $uaChanged = !hash_equals($a['ua'], hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''));
    // Akun dihapus/nonaktif/role berubah -> sesi dicabut
    $revoked = !$user || ($user['active'] ?? true) === false || $user['role'] !== $a['role'];
    if ($expired || $uaChanged || $revoked) {
        unset($_SESSION['admin']);
        $_SESSION['_flash_login'] = $expired ? 'Sesi berakhir karena tidak ada aktivitas. Silakan login kembali.' : 'Silakan login kembali.';
        return null;
    }
    $_SESSION['admin']['last'] = time();
    return $_SESSION['admin'];
}

function admin_can(string $permission): bool
{
    $a = admin_current();
    return $a !== null && in_array($permission, config('admin.roles')[$a['role']] ?? [], true);
}

function admin_api_context(): ?array
{
    $a = admin_current();
    return $a ? ['username' => $a['username'], 'role' => $a['role']] : null;
}

/**
 * Wajib login (dan izin tertentu). Halaman HTML diarahkan ke login; JSON mendapat 401/403.
 */
function require_admin(?string $permission = null): array
{
    $a = admin_current();
    // Hanya path yang dicatat, bukan query string: parameter pencarian admin (q=)
    // bisa berisi nama pemain, dan log tidak boleh memuat data pribadi peserta.
    $page = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: ($_SERVER['SCRIPT_NAME'] ?? '-');
    $perm = $permission ?? '-';

    if ($a === null) {
        app_log('warning', 'Akses admin ditolak: belum login', [
            'page' => $page, 'permission' => $perm, 'ip' => client_ip(),
        ]);
        if (is_json_request()) {
            json_error('Sesi admin berakhir. Silakan login kembali.', 'UNAUTHORIZED', 401);
        }
        redirect(url('/admin/login.php'));
    }
    if ($permission !== null && !admin_can($permission)) {
        app_log('warning', 'Akses admin ditolak: izin kurang', [
            'username' => $a['username'], 'role' => $a['role'],
            'page' => $page, 'permission' => $perm, 'ip' => client_ip(),
        ]);
        if (is_json_request()) {
            json_error('Anda tidak memiliki izin untuk aksi ini.', 'FORBIDDEN', 403);
        }
        http_response_code(403);
        render_admin_forbidden();
        exit;
    }

    app_log('info', 'Akses admin', [
        'username' => $a['username'], 'role' => $a['role'],
        'page' => $page, 'permission' => $perm,
        'method' => $_SERVER['REQUEST_METHOD'] ?? '-', 'ip' => client_ip(),
    ]);

    header('Cache-Control: no-store, private');
    return $a;
}

function admin_logout(): void
{
    $a = $_SESSION['admin'] ?? null;
    if (is_array($a)) {
        gas_request('admin_auth_event', ['aktivitas' => 'ADMIN_LOGOUT', 'username' => $a['username']]);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $p['path'],
            'secure' => $p['secure'], 'httponly' => true, 'samesite' => $p['samesite']]);
    }
    session_destroy();
}
