<?php
/**
 * Helper tampilan.
 */

declare(strict_types=1);

/**
 * Base path aplikasi (mis. "" untuk http://gambasi.test, "/gambasi" untuk http://localhost/gambasi).
 * Diambil dari path pada APP_URL.
 */
function base_path(): string
{
    static $base = null;
    if ($base === null) {
        $path = (string) parse_url((string) config('app.url'), PHP_URL_PATH);
        $base = rtrim($path, '/');
    }
    return $base;
}

function url(string $path = '/'): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = GAMBASI_ROOT . '/public/assets/' . ltrim($path, '/');
    $v = is_file($file) ? substr(md5((string) filemtime($file)), 0, 8) : '1';
    return url('/assets/' . ltrim($path, '/')) . '?v=' . $v;
}

/**
 * Logo resmi GAMBASI (WebP + fallback PNG).
 * @param string $size 'sm' (≤160px) atau 'lg' (≤600px)
 */
function logo_img(string $class = 'h-10 w-auto', string $size = 'sm', string $alt = 'Logo GAMBASI', bool $eager = false): string
{
    $base = $size === 'lg' ? 'images/logo-gambasi' : 'images/logo-gambasi-sm';
    [$w, $h] = $size === 'lg' ? [600, 653] : [160, 174];
    return '<picture><source srcset="' . e(asset($base . '.webp')) . '" type="image/webp">'
        . '<img src="' . e(asset($base . '.png')) . '" alt="' . e($alt) . '" width="' . $w . '" height="' . $h . '"'
        . ' class="' . e($class) . '" decoding="async"' . ($eager ? ' fetchpriority="high"' : ' loading="lazy"') . '></picture>';
}

function category_label(string $code): string
{
    return config('event.kategori')[$code] ?? $code;
}

function status_label(string $status): string
{
    return config("status.$status.label", $status);
}

function status_badge(string $status): string
{
    $classes = [
        'PENDING'  => 'bg-amber-100 text-amber-800 ring-amber-300',
        'REVIEW'   => 'bg-sky-100 text-sky-800 ring-sky-300',
        'REVISION' => 'bg-orange-100 text-orange-800 ring-orange-300',
        'VERIFIED' => 'bg-emerald-100 text-emerald-800 ring-emerald-300',
        'REJECTED' => 'bg-rose-100 text-rose-800 ring-rose-300',
    ][$status] ?? 'bg-slate-100 text-slate-700 ring-slate-300';
    return '<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset '
        . $classes . '">' . e(status_label($status)) . '</span>';
}

/** Nilai info panitia atau teks default jika belum ditetapkan. */
function info_or_tba(string $key, string $fallback = 'Akan diumumkan panitia'): string
{
    $v = trim((string) config("info.$key", ''));
    return $v !== '' ? $v : $fallback;
}

function has_info(string $key): bool
{
    return trim((string) config("info.$key", '')) !== '';
}

function is_registration_free(): bool
{
    return (bool) config('info.pendaftaran_gratis', false);
}

/** Badge "Pendaftaran GRATIS". @param string $tone dark|light */
function free_badge(string $tone = 'dark', string $extra = ''): string
{
    if (!is_registration_free()) {
        return '';
    }
    $cls = $tone === 'dark'
        ? 'bg-emerald-400/15 text-emerald-300 ring-emerald-400/40'
        : 'bg-emerald-50 text-emerald-800 ring-emerald-300';
    return '<span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-extrabold uppercase tracking-wider ring-1 '
        . $cls . ' ' . e($extra) . '">'
        . '<svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>'
        . 'Pendaftaran GRATIS · Tidak dipungut biaya</span>';
}

function whatsapp_link(string $number, string $text = ''): string
{
    $n = normalize_phone($number);
    if (!$n) {
        return '';
    }
    return 'https://wa.me/' . $n . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

/**
 * Link WhatsApp ke admin/penyelenggara dengan pesan pembuka.
 * Jangan sertakan data sensitif (NIK, data anak) di dalam pesan.
 */
function admin_whatsapp_link(string $context = ''): string
{
    $text = 'Halo Admin ' . config('event.nama') . ', saya ingin bertanya tentang pendaftaran '
        . 'Festival Sepak Bola Usia Dini U-10 & U-12.';
    if ($context !== '') {
        $text .= "\n" . $context;
    }
    return whatsapp_link((string) config('info.kontak_whatsapp'), $text);
}

function admin_whatsapp_display(): string
{
    return (string) config('info.kontak_whatsapp');
}

function format_datetime(string $iso): string
{
    if ($iso === '') {
        return '-';
    }
    try {
        $dt = new DateTimeImmutable($iso);
        return $dt->setTimezone(new DateTimeZone('Asia/Jayapura'))->format('d/m/Y H:i') . ' WIT';
    } catch (Exception) {
        return $iso;
    }
}

function format_date_id(string $iso): string
{
    if (!is_valid_iso_date($iso)) {
        return $iso;
    }
    $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September',
        'Oktober', 'November', 'Desember'];
    [$y, $m, $d] = explode('-', $iso);
    return (int) $d . ' ' . $bulan[(int) $m] . ' ' . $y;
}

function mask_phone(string $n): string
{
    return strlen($n) > 6 ? substr($n, 0, 4) . str_repeat('•', strlen($n) - 7) . substr($n, -3) : $n;
}

function render_view(string $name, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    require GAMBASI_ROOT . '/app/views/' . $name . '.php';
}

function render_admin_forbidden(): void
{
    render_view('admin/header', ['title' => 'Akses ditolak', 'active' => '']);
    echo '<div class="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">'
        . '<h1 class="text-xl font-bold text-slate-900">Akses ditolak</h1>'
        . '<p class="mt-2 text-slate-600">Akun Anda tidak memiliki izin untuk membuka halaman ini.</p>'
        . '<a class="mt-4 inline-block font-semibold text-emerald-700 hover:underline" href="' . e(url('/admin/')) . '">Kembali ke Dashboard</a></div>';
    render_view('admin/footer');
}

function api_error_notice(array $res): string
{
    return '<div class="notice notice-error" role="alert"><strong>Data tidak dapat dimuat.</strong> '
        . e($res['message'] ?? 'Terjadi kesalahan.') . ' <span class="text-xs opacity-70">(' . e($res['error_code'] ?? '') . ')</span></div>';
}

/** Opsi <select> dengan nilai terpilih. */
function select_options(array $options, string $selected, string $emptyLabel = 'Semua'): string
{
    $html = '<option value="">' . e($emptyLabel) . '</option>';
    foreach ($options as $value => $label) {
        $html .= '<option value="' . e((string) $value) . '"' . ((string) $value === $selected ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    return $html;
}

function query_param(string $key, array $allowed = []): string
{
    $v = is_string($_GET[$key] ?? null) ? trim($_GET[$key]) : '';
    if ($allowed && !in_array($v, $allowed, true)) {
        return '';
    }
    return mb_substr($v, 0, 80);
}

/** Pagination links untuk halaman admin (mempertahankan query string). */
function pagination_links(array $p): string
{
    if (($p['pages'] ?? 1) <= 1) {
        return '';
    }
    $q = $_GET;
    $html = '<nav class="mt-4 flex flex-wrap items-center gap-2" aria-label="Navigasi halaman">';
    for ($i = 1; $i <= $p['pages']; $i++) {
        if ($p['pages'] > 10 && abs($i - $p['page']) > 2 && $i !== 1 && $i !== $p['pages']) {
            if (abs($i - $p['page']) === 3) {
                $html .= '<span class="px-1 text-slate-400">…</span>';
            }
            continue;
        }
        $q['page'] = $i;
        $active = $i === (int) $p['page'];
        $html .= '<a href="?' . e(http_build_query($q)) . '" class="min-w-9 rounded-lg px-3 py-1.5 text-center text-sm font-semibold '
            . ($active ? 'bg-emerald-700 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50') . '"'
            . ($active ? ' aria-current="page"' : '') . '>' . $i . '</a>';
    }
    return $html . '</nav>';
}
