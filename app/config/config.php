<?php
/**
 * Konfigurasi aplikasi PHP GAMBASI Papua Selatan 2026.
 * Nilai rahasia dibaca dari file .env (di luar document root), bukan di-hardcode.
 */

declare(strict_types=1);

define('GAMBASI_ROOT', dirname(__DIR__, 2));

/**
 * Parser .env sederhana (KEY=VALUE, komentar #, nilai boleh diberi tanda kutip).
 */
function gambasi_load_env(string $file): array
{
    $vars = [];
    if (!is_file($file) || !is_readable($file)) {
        return $vars;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
            continue;
        }
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
            $value = substr($value, 1, -1);
        }
        $vars[$key] = $value;
    }
    return $vars;
}

// GAMBASI_ENV_FILE memungkinkan file env alternatif (dipakai oleh test otomatis).
$envFile = getenv('GAMBASI_ENV_FILE') ?: GAMBASI_ROOT . '/.env';
$GLOBALS['GAMBASI_ENV'] = gambasi_load_env($envFile);

function env(string $key, string $default = ''): string
{
    $v = $GLOBALS['GAMBASI_ENV'][$key] ?? null;
    return $v === null ? $default : (string) $v;
}

function env_bool(string $key, bool $default = false): bool
{
    $v = strtolower(env($key, $default ? 'true' : 'false'));
    return in_array($v, ['1', 'true', 'yes', 'on'], true);
}

define('GAMBASI_API_URL', env('GAMBASI_API_URL'));

return [
    'app' => [
        'name'  => 'GAMBASI Papua Selatan 2026',
        'env'   => env('APP_ENV', 'production'),
        'debug' => env_bool('APP_DEBUG'),
        'url'   => rtrim(env('APP_URL'), '/'),
        'tailwind_mode' => env('TAILWIND_MODE', 'cdn'),
    ],

    // EVENT MASTER DATA - RESMI, JANGAN DIUBAH TANPA INSTRUKSI
    'event' => [
        'nama'      => 'GAMBASI PAPUA SELATAN',
        'kegiatan'  => 'FESTIVAL SEPAK BOLA USIA DINI U-10 & U-12',
        'kompetisi' => 'PIALA DPR PAPUA SELATAN KE-2 TAHUN 2026',
        'tanggal'   => '30 OKTOBER – 1 NOVEMBER 2026',
        'lokasi'    => 'LAPANGAN KODIM MERAUKE',
        'tema'      => 'Membangun Karakter, Sportivitas, dan Kecintaan terhadap Sepak Bola Sejak Dini',
        'wilayah'   => 'MERAUKE — PAPUA SELATAN',
        'kategori'  => ['U10' => 'U-10', 'U12' => 'U-12'],
        'tanggal_mulai_iso' => '2026-10-30',
    ],

    'api' => [
        'url'            => GAMBASI_API_URL,
        'secret'         => env('GAMBASI_API_SECRET'),
        'timeout'        => max(5, (int) env('API_TIMEOUT', '30')),
        'upload_timeout' => max(30, (int) env('API_UPLOAD_TIMEOUT', '120')),
    ],

    // Informasi yang belum ditetapkan dibiarkan kosong -> tampil "Akan diumumkan panitia"
    'info' => [
        'kontak_nama'        => env('PANITIA_NAMA_KONTAK'),
        // Nomor WhatsApp admin/penyelenggara resmi (dapat diganti via PANITIA_WHATSAPP di .env)
        'kontak_whatsapp'    => env('PANITIA_WHATSAPP') !== '' ? env('PANITIA_WHATSAPP') : '082345328926',
        'kontak_email'       => env('PANITIA_EMAIL') !== '' ? env('PANITIA_EMAIL') : 'gambasipapsel@gmail.com',
        'sekretariat'        => env('SEKRETARIAT'),
        // Ketetapan panitia: pendaftaran GRATIS (tidak dipungut biaya)
        'biaya_pendaftaran'  => env('BIAYA_PENDAFTARAN') !== '' ? env('BIAYA_PENDAFTARAN') : 'GRATIS — tidak dipungut biaya',
        'pendaftaran_gratis' => env('BIAYA_PENDAFTARAN') === '' || stripos(env('BIAYA_PENDAFTARAN'), 'gratis') !== false,
        'jadwal_pendaftaran' => env('JADWAL_PENDAFTARAN'),
        'batas_pendaftaran'  => env('BATAS_PENDAFTARAN'),
        'jadwal_verifikasi'  => env('JADWAL_VERIFIKASI'),
        'technical_meeting'  => env('TECHNICAL_MEETING'),
    ],

    // Media sosial resmi (dapat diganti via .env)
    'social' => [
        'facebook'  => ['label' => 'Facebook',  'handle' => 'GAMBASI Papua Selatan',
            'url' => env('SOCIAL_FACEBOOK') !== '' ? env('SOCIAL_FACEBOOK') : 'https://www.facebook.com/profile.php?id=61593233879268'],
        'instagram' => ['label' => 'Instagram', 'handle' => '@gambasipapuaselatan',
            'url' => env('SOCIAL_INSTAGRAM') !== '' ? env('SOCIAL_INSTAGRAM') : 'https://www.instagram.com/gambasipapuaselatan'],
        'tiktok'    => ['label' => 'TikTok',    'handle' => '@gambasipapuaselatan',
            'url' => env('SOCIAL_TIKTOK') !== '' ? env('SOCIAL_TIKTOK') : 'https://www.tiktok.com/@gambasipapuaselatan'],
    ],

    'upload' => [
        'max_bytes' => 5 * 1024 * 1024,
        'rules' => [
            'akte' => ['mimes' => ['application/pdf', 'image/jpeg', 'image/png'], 'exts' => ['pdf', 'jpg', 'jpeg', 'png']],
            'foto' => ['mimes' => ['image/jpeg', 'image/png'], 'exts' => ['jpg', 'jpeg', 'png']],
            'logo' => ['mimes' => ['image/jpeg', 'image/png'], 'exts' => ['jpg', 'jpeg', 'png']],
        ],
        'blocked_exts' => ['php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar', 'js', 'mjs', 'exe', 'bat',
            'cmd', 'sh', 'com', 'msi', 'vbs', 'ps1', 'jar', 'html', 'htm', 'svg', 'scr', 'dll'],
    ],

    'security' => [
        'session_timeout' => max(5, (int) env('SESSION_TIMEOUT_MINUTES', '30')) * 60,
        'trusted_proxies' => array_filter(array_map('trim', explode(',', env('TRUSTED_PROXIES')))),
        'rate_limits' => [
            // Longgar karena banyak club bisa mendaftar dari satu jaringan (sekolah/warnet)
            'submit_init'     => [30, 600],
            'upload'          => [400, 600],
            'submit_finalize' => [40, 600],
            'check_status'    => [20, 300],
            'admin_login'     => [5, 900],
        ],
    ],

    'admin' => [
        'users_file' => GAMBASI_ROOT . '/' . ltrim(env('ADMIN_USERS_FILE', 'storage/admin/admins.json'), '/'),
        'roles' => [
            'SUPERADMIN'  => ['view', 'view_sensitive', 'view_documents', 'update_status', 'add_note',
                'export', 'export_sensitive', 'view_logs', 'settings'],
            'VERIFIKATOR' => ['view', 'view_sensitive', 'view_documents', 'update_status', 'add_note',
                'export', 'view_logs'],
            'VIEWER'      => ['view', 'export'],
        ],
    ],

    'status' => [
        'PENDING'  => ['label' => 'Pending',  'desc' => 'Pendaftaran diterima, menunggu diperiksa panitia.'],
        'REVIEW'   => ['label' => 'Review',   'desc' => 'Sedang diperiksa panitia.'],
        'REVISION' => ['label' => 'Revisi',   'desc' => 'Perlu perbaikan data/dokumen. Hubungi panitia.'],
        'VERIFIED' => ['label' => 'Terverifikasi', 'desc' => 'Pendaftaran telah diverifikasi panitia.'],
        'REJECTED' => ['label' => 'Ditolak',  'desc' => 'Pendaftaran tidak memenuhi ketentuan.'],
    ],

    'storage' => (function (): string {
        $p = rtrim(env('STORAGE_PATH'), '/\\');
        if ($p === '') {
            return GAMBASI_ROOT . '/storage';
        }
        $absolute = str_starts_with($p, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $p);
        return $absolute ? $p : GAMBASI_ROOT . '/' . $p;
    })(),
];
