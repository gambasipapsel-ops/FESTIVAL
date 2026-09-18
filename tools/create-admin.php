<?php
/**
 * CLI: buat / perbarui / nonaktifkan akun admin.
 *
 *   php tools/create-admin.php <username> <SUPERADMIN|VERIFIKATOR|VIEWER> ["Nama Lengkap"]
 *   php tools/create-admin.php --disable <username>
 *   php tools/create-admin.php --list
 *
 * Password dibaca dari prompt (atau STDIN / env ADMIN_PASSWORD untuk otomatisasi),
 * disimpan sebagai password_hash di storage/admin/admins.json (di luar document root).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$config = require dirname(__DIR__) . '/app/config/config.php';
$file = $config['admin']['users_file'];
$roles = array_keys($config['admin']['roles']);

function load_users(string $file): array
{
    $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    return is_array($data['users'] ?? null) ? $data['users'] : [];
}

function save_users(string $file, array $users): void
{
    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0750, true);
    }
    $json = json_encode(['users' => array_values($users)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    file_put_contents($file, $json . "\n", LOCK_EX);
    @chmod($file, 0640);
}

function read_password(): string
{
    $env = getenv('ADMIN_PASSWORD');
    if (is_string($env) && $env !== '') {
        return $env;
    }
    if (function_exists('posix_isatty') && !posix_isatty(STDIN)) {
        return rtrim((string) fgets(STDIN), "\r\n");
    }
    if (PHP_OS_FAMILY !== 'Windows') {
        shell_exec('stty -echo');
    }
    fwrite(STDOUT, 'Password (min. 10 karakter): ');
    $p = rtrim((string) fgets(STDIN), "\r\n");
    if (PHP_OS_FAMILY !== 'Windows') {
        shell_exec('stty echo');
    }
    fwrite(STDOUT, PHP_EOL);
    return $p;
}

$args = array_slice($argv, 1);
$users = load_users($file);

if (($args[0] ?? '') === '--list') {
    foreach ($users as $u) {
        printf("%-20s %-12s %s%s\n", $u['username'], $u['role'], $u['name'] ?? '', ($u['active'] ?? true) ? '' : ' (nonaktif)');
    }
    exit(0);
}

if (($args[0] ?? '') === '--disable') {
    $name = strtolower($args[1] ?? '');
    $found = false;
    foreach ($users as &$u) {
        if (strtolower($u['username']) === $name) {
            $u['active'] = false;
            $found = true;
        }
    }
    unset($u);
    if (!$found) {
        fwrite(STDERR, "User tidak ditemukan.\n");
        exit(1);
    }
    save_users($file, $users);
    echo "User $name dinonaktifkan.\n";
    exit(0);
}

$username = strtolower(trim($args[0] ?? ''));
$role = strtoupper(trim($args[1] ?? ''));
$name = trim($args[2] ?? $username);

if (!preg_match('/^[a-z0-9._-]{3,40}$/', $username) || !in_array($role, $roles, true)) {
    fwrite(STDERR, "Penggunaan: php tools/create-admin.php <username> <" . implode('|', $roles) . "> [\"Nama\"]\n");
    exit(1);
}

$password = read_password();
if (strlen($password) < 10) {
    fwrite(STDERR, "Password minimal 10 karakter.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$updated = false;
foreach ($users as &$u) {
    if (strtolower($u['username']) === $username) {
        $u = ['username' => $username, 'name' => $name, 'role' => $role, 'password_hash' => $hash,
            'active' => true, 'updated_at' => date('c')];
        $updated = true;
    }
}
unset($u);
if (!$updated) {
    $users[] = ['username' => $username, 'name' => $name, 'role' => $role, 'password_hash' => $hash,
        'active' => true, 'created_at' => date('c')];
}
save_users($file, $users);
echo ($updated ? 'Diperbarui' : 'Dibuat') . ": $username ($role)\n";
