<?php
/**
 * DEV-ONLY: uji integrasi end-to-end lapisan PHP.
 *
 * Menjalankan:
 *  - fake Apps Script server (node tests/gas/server.js) -> kode .gs ASLI + fake Google services
 *  - PHP built-in server untuk folder public/ (env test terpisah)
 *  - PHP built-in server kedua dengan API URL yang mati (uji API error)
 *
 *   php tests/php/run-tests.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
// Folder kerja khusus runner ini (tidak mengganggu tests/.tmp milik server dev)
$tmp = $root . '/tests/.tmp/php-it';
define('IT_TMP', $tmp);
$php = PHP_BINARY;
$node = getenv('NODE_BIN') ?: 'node';
const GAS_PORT = 8790;
const WEB_PORT = 8791;
const DOWN_PORT = 8792;
const SECRET = 'test-secret-0123456789abcdef0123456789abcdef';

// ------------------------------------------------------------------ setup
function rrmdir(string $dir): void
{
    if (!is_dir($dir)) return;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($dir);
}
rrmdir($tmp);
mkdir($tmp . '/storage', 0777, true);
mkdir($tmp . '/storage-down', 0777, true);
mkdir($tmp . '/files', 0777, true);

$envCommon = "APP_ENV=test\nAPP_DEBUG=false\nGAMBASI_API_SECRET=" . SECRET . "\nADMIN_USERS_FILE=tests/.tmp/php-it/admins.json\nSESSION_TIMEOUT_MINUTES=30\n";
file_put_contents("$tmp/test.env", $envCommon . "APP_URL=http://127.0.0.1:" . WEB_PORT . "\nGAMBASI_API_URL=http://127.0.0.1:" . GAS_PORT . "/exec\nSTORAGE_PATH=$tmp/storage\nPANITIA_WHATSAPP=\n");
file_put_contents("$tmp/down.env", $envCommon . "APP_URL=http://127.0.0.1:" . DOWN_PORT . "\nGAMBASI_API_URL=http://127.0.0.1:1/exec\nSTORAGE_PATH=$tmp/storage-down\n");

// Akun admin uji
$pw = 'Rahasia-Uji-123';
file_put_contents("$tmp/admins.json", json_encode(['users' => [
    ['username' => 'superadmin', 'name' => 'Super Admin', 'role' => 'SUPERADMIN', 'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'active' => true],
    ['username' => 'verif', 'name' => 'Verifikator', 'role' => 'VERIFIKATOR', 'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'active' => true],
    ['username' => 'viewer', 'name' => 'Viewer', 'role' => 'VIEWER', 'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'active' => true],
    ['username' => 'nonaktif', 'name' => 'Nonaktif', 'role' => 'VIEWER', 'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'active' => false],
]]));

// File uji nyata
$img = imagecreatetruecolor(60, 120);
imagefill($img, 0, 0, imagecolorallocate($img, 20, 120, 60));
imagejpeg($img, "$tmp/files/foto.jpg", 85);
imagepng($img, "$tmp/files/akte.png");
imagedestroy($img);
file_put_contents("$tmp/files/akte.pdf", "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [] /Count 0 >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
file_put_contents("$tmp/files/fake.jpg", "<?php echo 'not an image'; ?>");
// Isi PHP tidak berbahaya; yang diuji adalah penolakan ekstensi .php
file_put_contents("$tmp/files/shell.php", "<?php echo 'hello'; ?>");
// PDF "polyglot": finfo menyebutnya application/pdf, tapi byte awalnya HTML
file_put_contents("$tmp/files/polyglot.pdf", "<html><body onload=\"alert(1)\">hi</body></html>\n%PDF-1.4\ntrailer<<>>\n%%EOF\n");
file_put_contents("$tmp/files/geser.pdf", "\n   %PDF-1.4\ntrailer<<>>\n%%EOF\n");
file_put_contents("$tmp/files/big.jpg", file_get_contents("$tmp/files/foto.jpg") . str_repeat("\0", 5 * 1024 * 1024 + 100));

$procs = [];
function start(array $cmd, array $env, string $cwd, string $log): array
{
    $spec = [0 => ['pipe', 'r'], 1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']];
    $p = proc_open($cmd, $spec, $pipes, $cwd, $env + getenv());
    return [$p, $pipes];
}
$procs[] = start([$node, 'tests/gas/server.js', (string) GAS_PORT], ['GAS_TEST_SECRET' => SECRET], $root, "$tmp/gas.log");
$procs[] = start([$php, '-d', 'upload_max_filesize=6M', '-d', 'post_max_size=8M', '-S', '127.0.0.1:' . WEB_PORT, '-t', 'public'], ['GAMBASI_ENV_FILE' => "$tmp/test.env"], $root, "$tmp/web.log");
$procs[] = start([$php, '-S', '127.0.0.1:' . DOWN_PORT, '-t', 'public'], ['GAMBASI_ENV_FILE' => "$tmp/down.env"], $root, "$tmp/down.log");

register_shutdown_function(function () use (&$procs) {
    foreach ($procs as [$p]) {
        $st = proc_get_status($p);
        if ($st['running']) {
            if (PHP_OS_FAMILY === 'Windows') exec('taskkill /F /T /PID ' . $st['pid'] . ' >NUL 2>&1');
            else proc_terminate($p);
        }
        proc_close($p);
    }
});

function wait_port(int $port): void
{
    for ($i = 0; $i < 50; $i++) {
        $s = @fsockopen('127.0.0.1', $port, $e, $es, 0.2);
        if ($s) { fclose($s); return; }
        usleep(200000);
    }
    fwrite(STDERR, "Server port $port tidak aktif\n");
    exit(1);
}
wait_port(GAS_PORT);
wait_port(WEB_PORT);
wait_port(DOWN_PORT);

// ------------------------------------------------------------------ HTTP client
final class Browser
{
    public string $jar;
    public string $csrf = '';
    public function __construct(public string $base, string $name)
    {
        $this->jar = sys_get_temp_dir() . "/gambasi_jar_{$name}_" . getmypid();
        @unlink($this->jar);
    }
    public function request(string $method, string $path, array $opts = []): array
    {
        $ch = curl_init($this->base . $path);
        $headers = $opts['headers'] ?? [];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_COOKIEJAR => $this->jar,
            CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_USERAGENT => 'GambasiTest/1.0',
        ]);
        if (isset($opts['json'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($opts['json']));
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Accept: application/json';
        } elseif (isset($opts['form'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['multipart'] ?? false ? $opts['form'] : http_build_query($opts['form']));
        }
        if (!empty($opts['csrf'])) $headers[] = 'X-CSRF-Token: ' . $this->csrf;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $raw = (string) curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $hsize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $head = substr($raw, 0, $hsize);
        $body = substr($raw, $hsize);
        if (preg_match('/name="csrf-token" content="([^"]+)"/', $body, $m)) $this->csrf = html_entity_decode($m[1]);
        if (preg_match('/name="_csrf" value="([^"]+)"/', $body, $m)) $this->csrf = html_entity_decode($m[1]);
        return ['status' => $status, 'headers' => $head, 'body' => $body, 'json' => json_decode($body, true)];
    }
    public function get(string $p): array { return $this->request('GET', $p); }
    public function postJson(string $p, array $data, bool $csrf = true): array { return $this->request('POST', $p, ['json' => $data, 'csrf' => $csrf]); }
    public function postForm(string $p, array $data): array { return $this->request('POST', $p, ['form' => $data + ['_csrf' => $this->csrf]]); }
    public function upload(string $token, string $slot, string $file, ?string $name = null, ?string $mime = null): array
    {
        return $this->request('POST', '/api/upload.php', [
            'multipart' => true,
            'csrf' => true,
            'headers' => ['Accept: application/json'],
            'form' => ['submission_token' => $token, 'slot' => $slot, 'file' => new CURLFile($file, $mime ?? mime_content_type($file), $name ?? basename($file))],
        ]);
    }
}

function gas_control(string $path, array $data): void
{
    $ch = curl_init('http://127.0.0.1:' . GAS_PORT . $path);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($data), CURLOPT_RETURNTRANSFER => true]);
    curl_exec($ch);
    curl_close($ch);
}
function clear_ratelimit(): void
{
    foreach (glob(IT_TMP . '/storage/ratelimit/*') ?: [] as $f) @unlink($f);
}
function clear_cache(): void
{
    foreach (glob(IT_TMP . '/storage/cache/*') ?: [] as $f) @unlink($f);
}

// ------------------------------------------------------------------ mini framework
$results = [];
function test(string $name, callable $fn): void
{
    global $results;
    try {
        $fn();
        $results[] = ['PASS', $name];
    } catch (Throwable $e) {
        $results[] = ['FAIL', $name . ' :: ' . $e->getMessage()];
    }
}
function ok(bool $cond, string $msg = 'assertion failed'): void { if (!$cond) throw new RuntimeException($msg); }
function same(mixed $a, mixed $b, string $msg = ''): void
{
    if ($a !== $b) throw new RuntimeException(($msg ?: 'not equal') . ' got=' . json_encode($a) . ' want=' . json_encode($b));
}

$ref = fn(int $i) => 'r' . str_pad((string) $i, 7, '0', STR_PAD_LEFT);
$uuid = fn() => sprintf('%s-%s-4%s-a%s-%s', bin2hex(random_bytes(4)), bin2hex(random_bytes(2)), substr(bin2hex(random_bytes(2)), 1), substr(bin2hex(random_bytes(2)), 1), bin2hex(random_bytes(6)));
$player = function (int $i, array $o = []) use ($ref): array {
    return $o + [
        'ref' => $ref($i), 'nama_lengkap' => "Pemain Uji $i", 'nik' => '9101' . str_pad((string) (200000000000 + $i), 12, '0', STR_PAD_LEFT),
        'tempat_lahir' => 'Merauke', 'tanggal_lahir' => '2017-03-15', 'jenis_kelamin' => 'L', 'posisi' => 'DEPAN',
        'nomor_punggung' => (string) (($i - 1) % 99 + 1), 'nama_ayah' => "Ayah $i", 'nama_ibu' => '', 'nama_wali' => '',
        'whatsapp_wali' => '0813' . str_pad((string) (10000000 + $i), 8, '0', STR_PAD_LEFT), 'alamat' => 'Jl. Uji No. ' . $i,
    ];
};
$payload = function (string $club, array $players, string $kat = 'U10', array $o = []): array {
    return array_replace_recursive([
        'kategori' => $kat,
        'club' => ['nama_club' => $club, 'alamat' => 'Jl. Raya Mandala 1', 'kampung' => 'Maro', 'distrik' => 'Merauke',
            'kabupaten' => 'Merauke', 'provinsi' => 'Papua Selatan', 'has_logo' => false],
        'official' => ['nama_manager' => 'Manager Uji', 'nama_pelatih' => 'Pelatih Uji', 'whatsapp' => '081234567890', 'email' => 'club@example.com'],
        'players' => $players,
    ], $o);
};

$tmpFiles = "$tmp/files";
$B = fn(string $n) => new Browser('http://127.0.0.1:' . WEB_PORT, $n);

// =================================================================== PUBLIC PAGES
$pub = $B('public');

test('Landing page 200 + event master data', function () use ($pub) {
    $r = $pub->get('/');
    same($r['status'], 200);
    foreach (['GAMBASI', 'PAPUA SELATAN', 'FESTIVAL SEPAK BOLA USIA DINI U-10 &amp; U-12', 'PIALA DPR PAPUA SELATAN KE-2 TAHUN 2026',
                 '30 OKTOBER – 1 NOVEMBER 2026', 'LAPANGAN KODIM MERAUKE', 'Membangun Karakter, Sportivitas, dan Kecintaan terhadap Sepak Bola Sejak Dini',
                 'Daftarkan Club', 'Lihat Persyaratan', 'id="tentang"', 'id="informasi"', 'id="kategori"', 'id="persyaratan"',
                 'id="mekanisme"', 'id="dokumen"', 'id="timeline"', 'id="faq"', '<footer'] as $needle) {
        ok(str_contains($r['body'], $needle), "missing: $needle");
    }
    ok(str_contains($r['body'], 'Usia 6–10 tahun'), 'aturan usia dari backend tampil');
});

test('Landing tidak mengarang kontak/biaya (menampilkan "Akan diumumkan panitia")', function () use ($pub) {
    $r = $pub->get('/');
    ok(str_contains($r['body'], 'Akan diumumkan panitia'));
    ok(!preg_match('/Rp\s?\d/', $r['body']), 'tidak boleh ada nominal biaya');
});

test('WhatsApp admin 082345328926 tersambung di halaman publik', function () use ($pub) {
    $wa = 'https://wa.me/6282345328926?text=';
    $home = $pub->get('/')['body'];
    ok(str_contains($home, $wa), 'link wa.me di landing');
    ok(str_contains($home, 'id="wa-float"'), 'tombol melayang');
    ok(str_contains($home, '082345328926') && str_contains($home, 'id="kontak-admin"'));
    $form = $pub->get('/daftar-sepakbola.php')['body'];
    ok(str_contains($form, 'id="ask-admin"') && str_contains($form, 'id="ask-admin-error"') && str_contains($form, $wa));
    ok(str_contains($pub->get('/cek-pendaftaran.php')['body'], $wa));
    ok(!str_contains($home, 'wa.me/6282345328926?text=' . rawurlencode('NIK')), 'tidak ada data sensitif di pesan');
});

test('Email & media sosial resmi tampil (tanpa parameter pelacak)', function () use ($pub) {
    $home = $pub->get('/')['body'];
    foreach ([
        'mailto:gambasipapsel@gmail.com',
        'https://www.facebook.com/profile.php?id=61593233879268"',
        'https://www.instagram.com/gambasipapuaselatan"',
        'https://www.tiktok.com/@gambasipapuaselatan"',
        'id="media-sosial"',
    ] as $needle) {
        ok(str_contains($home, $needle), "missing: $needle");
    }
    ok(!str_contains($home, 'utm_source') && !str_contains($home, 'stkn=') && !str_contains($home, 'sender_device'), 'parameter pelacak');
    ok(substr_count($home, 'rel="noopener noreferrer"') >= 6, 'link eksternal aman');
});

test('Pendaftaran GRATIS tampil di landing, FAQ, dan form', function () use ($pub) {
    $home = $pub->get('/')['body'];
    ok(substr_count($home, 'Pendaftaran GRATIS · Tidak dipungut biaya') >= 2, 'badge hero & CTA');
    ok(str_contains($home, 'GRATIS — tidak dipungut biaya'), 'kartu info biaya');
    ok(str_contains($home, 'Pendaftaran GRATIS, tidak dipungut biaya apa pun'), 'FAQ');
    ok(str_contains($pub->get('/daftar-sepakbola.php')['body'], 'Pendaftaran GRATIS'), 'form');
});

test('Security headers terpasang', function () use ($B) {
    $h = strtolower($B('headers')->get('/')['headers']);
    foreach (['content-security-policy:', 'x-frame-options: sameorigin', 'x-content-type-options: nosniff', 'referrer-policy:', 'permissions-policy:'] as $x) {
        ok(str_contains($h, $x), "header $x");
    }
    ok(!str_contains($h, 'x-powered-by'), 'X-Powered-By harus hilang');
    ok(str_contains($h, 'httponly') && str_contains($h, 'samesite=lax'), 'cookie session aman');
});

test('Halaman publik tidak membocorkan API URL/secret', function () use ($pub) {
    foreach (['/', '/daftar-sepakbola.php', '/cek-pendaftaran.php'] as $p) {
        $b = $pub->get($p)['body'];
        ok(!str_contains($b, SECRET) && !str_contains($b, ':' . GAS_PORT), "bocor di $p");
    }
});

test('Form pendaftaran 200 + 6 langkah + konfigurasi klien', function () use ($pub) {
    $r = $pub->get('/daftar-sepakbola.php?kategori=U12');
    same($r['status'], 200);
    ok(substr_count($r['body'], 'class="form-step"') === 6, '6 langkah');
    ok(str_contains($r['body'], 'id="reg-config"') && str_contains($r['body'], '"kategori":"U12"') || str_contains($r['body'], '"kategori":"U12"'));
    ok(str_contains($r['body'], 'Foto wajib memperlihatkan pemain secara penuh dari kepala sampai kaki.'));
    ok(str_contains($r['body'], 'Tambah Pemain'));
});

test('sukses.php tanpa sesi -> redirect ke cek-pendaftaran (anti sukses palsu)', function () use ($B) {
    $r = $B('nosession')->get('/sukses.php');
    same($r['status'], 302);
    ok(str_contains($r['headers'], 'cek-pendaftaran.php'));
});

test('sukses.php tidak menerima nomor dari URL', function () use ($B) {
    $r = $B('spoof')->get('/sukses.php?nomor=GMB-SB-2026-9999');
    same($r['status'], 302);
});

// =================================================================== API GUARDS
test('API tanpa CSRF -> 419', function () use ($pub, $uuid, $payload, $player) {
    $pub->get('/daftar-sepakbola.php');
    $r = $pub->postJson('/api/submit-init.php', ['submission_token' => $uuid(), 'payload' => $payload('X', [$player(1)])], false);
    same($r['status'], 419);
    same($r['json']['error_code'], 'CSRF_ERROR');
});

test('API method GET -> 405', function () use ($pub) {
    same($pub->get('/api/submit-init.php')['status'], 405);
});

test('JSON rusak -> 400', function () use ($pub) {
    $r = $pub->request('POST', '/api/submit-init.php', ['csrf' => true, 'headers' => ['Content-Type: application/json', 'Accept: application/json'], 'form' => ['x' => 1]]);
    same($r['status'], 400);
});

// =================================================================== VALIDATION
$reg = $B('registrant');
$reg->get('/daftar-sepakbola.php');

test('Kategori tidak valid -> INVALID_CATEGORY', function () use ($reg, $uuid, $payload, $player) {
    $r = $reg->postJson('/api/submit-init.php', ['submission_token' => $uuid(), 'payload' => $payload('SSB A', [$player(1)], 'U14')]);
    same($r['status'], 422);
    same($r['json']['error_code'], 'INVALID_CATEGORY');
});

test('NIK tidak valid -> VALIDATION_ERROR (field nik)', function () use ($reg, $uuid, $payload, $player) {
    $r = $reg->postJson('/api/submit-init.php', ['submission_token' => $uuid(), 'payload' => $payload('SSB A', [$player(1, ['nik' => '12AB'])])]);
    same($r['json']['error_code'], 'VALIDATION_ERROR');
    ok(in_array('players[0].nik', array_column($r['json']['errors'], 'field'), true));
});

test('Nomor HP tidak valid -> VALIDATION_ERROR', function () use ($reg, $uuid, $payload, $player) {
    $r = $reg->postJson('/api/submit-init.php', ['submission_token' => $uuid(), 'payload' => $payload('SSB A', [$player(1, ['whatsapp_wali' => '0212345'])], 'U10', ['official' => ['whatsapp' => 'abc']])]);
    $fields = array_column($r['json']['errors'], 'field');
    ok(in_array('official.whatsapp', $fields, true) && in_array('players[0].whatsapp_wali', $fields, true));
});

test('Field kosong -> VALIDATION_ERROR', function () use ($reg, $uuid) {
    $r = $reg->postJson('/api/submit-init.php', ['submission_token' => $uuid(), 'payload' => ['kategori' => 'U10', 'club' => [], 'official' => [], 'players' => []]]);
    same($r['status'], 422);
    ok(count($r['json']['errors']) >= 8, 'banyak field wajib');
});

test('Usia tidak sesuai -> INVALID_AGE', function () use ($reg, $uuid, $payload, $player) {
    $r = $reg->postJson('/api/submit-init.php', ['submission_token' => $uuid(), 'payload' => $payload('SSB A', [$player(1, ['tanggal_lahir' => '2012-01-01'])])]);
    same($r['json']['error_code'], 'INVALID_AGE');
});

test('Token submission tidak valid -> INVALID_TOKEN', function () use ($reg, $payload, $player) {
    $r = $reg->postJson('/api/submit-init.php', ['submission_token' => '../../x', 'payload' => $payload('SSB A', [$player(1)])]);
    same($r['json']['error_code'], 'INVALID_TOKEN');
});

// =================================================================== FLOW U10
$tok1 = $uuid();
$p1 = $payload('SSB Garuda Muda', [$player(1), $player(2)]);

test('submit_init U-10 valid', function () use ($reg, $tok1, $p1) {
    $r = $reg->postJson('/api/submit-init.php', ['submission_token' => $tok1, 'payload' => $p1]);
    same($r['status'], 200, json_encode($r['json']));
    same($r['json']['success'], true);
    same(count($r['json']['data']['required_slots']), 4);
});

test('Upload dari sesi lain (tanpa token di sesi) -> 409', function () use ($B, $tok1, $tmpFiles, $ref) {
    $other = $B('attacker');
    $other->get('/daftar-sepakbola.php');
    $r = $other->upload($tok1, 'akte:' . $ref(1), "$tmpFiles/akte.pdf");
    same($r['status'], 409);
});

test('Upload akte PDF', function () use ($reg, $tok1, $tmpFiles, $ref) {
    $r = $reg->upload($tok1, 'akte:' . $ref(1), "$tmpFiles/akte.pdf", 'akte kelahiran.pdf', 'application/pdf');
    same($r['status'], 200, $r['body']);
});

test('Upload akte PNG', function () use ($reg, $tok1, $tmpFiles, $ref) {
    same($reg->upload($tok1, 'akte:' . $ref(2), "$tmpFiles/akte.png")['status'], 200);
});

test('Upload foto JPG', function () use ($reg, $tok1, $tmpFiles, $ref) {
    same($reg->upload($tok1, 'foto:' . $ref(1), "$tmpFiles/foto.jpg")['status'], 200);
});

test('File > 5MB ditolak (413 FILE_TOO_LARGE)', function () use ($reg, $tok1, $tmpFiles, $ref) {
    $r = $reg->upload($tok1, 'foto:' . $ref(2), "$tmpFiles/big.jpg", 'big.jpg', 'image/jpeg');
    same($r['status'], 413);
    same($r['json']['error_code'], 'FILE_TOO_LARGE');
});

test('MIME palsu (teks berekstensi .jpg) ditolak', function () use ($reg, $tok1, $tmpFiles, $ref) {
    $r = $reg->upload($tok1, 'foto:' . $ref(2), "$tmpFiles/fake.jpg", 'foto.jpg', 'image/jpeg');
    same($r['status'], 422);
    same($r['json']['error_code'], 'INVALID_MIME');
});

test('File .php ditolak', function () use ($reg, $tok1, $tmpFiles, $ref) {
    $r = $reg->upload($tok1, 'foto:' . $ref(2), "$tmpFiles/shell.php", 'shell.php', 'image/jpeg');
    same($r['json']['error_code'] ?? null, 'INVALID_FILE', 'status=' . $r['status'] . ' body=' . substr($r['body'], 0, 300));
    $r = $reg->upload($tok1, 'foto:' . $ref(2), "$tmpFiles/foto.jpg", 'foto.php.jpg', 'image/jpeg');
    same($r['json']['error_code'], 'INVALID_FILE');
});

test('PDF tanpa header %PDF di byte ke-0 ditolak (polyglot HTML)', function () use ($reg, $tok1, $tmpFiles, $ref) {
    // finfo tetap menganggapnya application/pdf, jadi ini menguji lapisan magic bytes
    same(mime_content_type("$tmpFiles/polyglot.pdf"), 'application/pdf', 'prasyarat: finfo bilang pdf');
    $r = $reg->upload($tok1, 'akte:' . $ref(2), "$tmpFiles/polyglot.pdf", 'akte.pdf', 'application/pdf');
    same($r['status'], 422);
    same($r['json']['error_code'], 'INVALID_MIME');
    ok(str_contains($r['json']['message'], 'header PDF'), $r['body']);

    same(mime_content_type("$tmpFiles/geser.pdf"), 'application/pdf');
    same($reg->upload($tok1, 'akte:' . $ref(2), "$tmpFiles/geser.pdf", 'akte.pdf', 'application/pdf')['json']['error_code'], 'INVALID_MIME');
});

test('validate_pdf_magic_bytes() langsung', function () use ($tmpFiles) {
    require_once dirname(__DIR__, 2) . '/app/helpers/validation.php';
    ok(validate_pdf_magic_bytes("$tmpFiles/akte.pdf"), 'PDF normal harus lolos');
    ok(!validate_pdf_magic_bytes("$tmpFiles/polyglot.pdf"));
    ok(!validate_pdf_magic_bytes("$tmpFiles/geser.pdf"));
    ok(!validate_pdf_magic_bytes("$tmpFiles/foto.jpg"));
    ok(!validate_pdf_magic_bytes("$tmpFiles/tidak-ada.pdf"), 'file tidak ada -> false, tanpa warning');
});

test('Foto berformat PDF ditolak', function () use ($reg, $tok1, $tmpFiles, $ref) {
    same($reg->upload($tok1, 'foto:' . $ref(2), "$tmpFiles/akte.pdf")['json']['error_code'], 'INVALID_FILE');
});

test('Slot tidak valid ditolak', function () use ($reg, $tok1, $tmpFiles) {
    same($reg->upload($tok1, '../etc', "$tmpFiles/foto.jpg")['json']['error_code'], 'INVALID_SLOT');
});

test('Finalize dengan foto belum lengkap -> MISSING_FILES, tidak sukses', function () use ($reg, $tok1, $ref) {
    $r = $reg->postJson('/api/submit-finalize.php', ['submission_token' => $tok1]);
    same($r['status'], 422);
    same($r['json']['error_code'], 'MISSING_FILES');
    same($r['json']['errors'][0]['field'], 'foto:' . $ref(2));
    same($reg->get('/sukses.php')['status'], 302, 'belum boleh ada halaman sukses');
});

test('Google Drive error saat upload -> 503, tidak sukses', function () use ($reg, $tok1, $tmpFiles, $ref) {
    gas_control('/__test/fault', ['driveCreate' => true]);
    $r = $reg->upload($tok1, 'foto:' . $ref(2), "$tmpFiles/foto.jpg");
    gas_control('/__test/fault', ['driveCreate' => false]);
    same($r['status'], 503);
    same($r['json']['error_code'], 'DRIVE_ERROR');
});

test('Upload foto pemain 2 berhasil setelah Drive pulih', function () use ($reg, $tok1, $tmpFiles, $ref) {
    same($reg->upload($tok1, 'foto:' . $ref(2), "$tmpFiles/foto.jpg")['status'], 200);
});

test('Google Sheets error saat finalize -> 503, tanpa nomor', function () use ($reg, $tok1) {
    gas_control('/__test/fault', ['setValuesFailSheet' => 'CLUB']);
    $r = $reg->postJson('/api/submit-finalize.php', ['submission_token' => $tok1]);
    gas_control('/__test/fault', ['setValuesFailSheet' => null]);
    same($r['status'], 503);
    same($r['json']['success'], false);
    ok(!isset($r['json']['data']['nomor_pendaftaran']));
});

$nomor1 = null;
test('Finalize (retry) sukses -> GMB-SB-2026-0001', function () use ($reg, $tok1, &$nomor1) {
    $r = $reg->postJson('/api/submit-finalize.php', ['submission_token' => $tok1]);
    same($r['status'], 200, $r['body']);
    same($r['json']['message'], 'Pendaftaran berhasil');
    same($r['json']['data']['nomor_pendaftaran'], 'GMB-SB-2026-0001');
    same($r['json']['data']['status'], 'PENDING');
    $nomor1 = $r['json']['data']['nomor_pendaftaran'];
});

test('Halaman sukses menampilkan nomor', function () use ($reg) {
    $r = $reg->get('/sukses.php');
    same($r['status'], 200);
    ok(str_contains($r['body'], 'GMB-SB-2026-0001') && str_contains($r['body'], 'SSB Garuda Muda'));
    ok(str_contains($r['body'], 'menunggu verifikasi'));
    ok(str_contains($r['body'], 'wa.me/6282345328926?text=') && str_contains($r['body'], rawurlencode('Nomor pendaftaran: GMB-SB-2026-0001')), 'WA admin dengan nomor pendaftaran');
});

test('Duplicate submission: init ulang token sama -> already_submitted, tanpa record baru', function () use ($reg, $tok1, $p1) {
    $r = $reg->postJson('/api/submit-init.php', ['submission_token' => $tok1, 'payload' => $p1]);
    same($r['json']['data']['already_submitted'], true);
    same($r['json']['data']['nomor_pendaftaran'], 'GMB-SB-2026-0001');
});

test('Duplicate submission: finalize ulang -> nomor sama', function () use ($reg, $tok1) {
    $r = $reg->postJson('/api/submit-finalize.php', ['submission_token' => $tok1]);
    same($r['json']['data']['nomor_pendaftaran'], 'GMB-SB-2026-0001');
    same($r['json']['data']['already_submitted'], true);
});

test('Club sama didaftarkan ulang dengan token baru -> DUPLICATE_CLUB', function () use ($reg, $uuid, $payload, $player) {
    $r = $reg->postJson('/api/submit-init.php', ['submission_token' => $uuid(), 'payload' => $payload('SSB GARUDA MUDA', [$player(50)])]);
    same($r['status'], 409);
    same($r['json']['error_code'], 'DUPLICATE_CLUB');
});

// =================================================================== FLOW U12 (banyak pemain)
test('Rate limit submit_init -> 429', function () use ($B, $uuid) {
    clear_ratelimit();
    $b = $B('rl-init');
    $b->get('/daftar-sepakbola.php');
    $last = null;
    for ($i = 0; $i < 31; $i++) $last = $b->postJson('/api/submit-init.php', ['submission_token' => $uuid(), 'payload' => ['kategori' => 'U99']]);
    same($last['status'], 429);
    clear_ratelimit();
});

test('U-12 dengan 3 pemain + logo -> GMB-SB-2026-0002', function () use ($B, $uuid, $payload, $player, $tmpFiles, $ref) {
    $b = $B('u12');
    $b->get('/daftar-sepakbola.php');
    $tok = $uuid();
    $players = [];
    foreach ([11, 12, 13] as $i) $players[] = $player($i, ['tanggal_lahir' => '2015-06-01', 'jenis_kelamin' => $i === 12 ? 'P' : 'L', 'posisi' => 'KIPER']);
    $p = $payload('Persipura Junior Merauke', $players, 'U12', ['club' => ['has_logo' => true]]);
    $r = $b->postJson('/api/submit-init.php', ['submission_token' => $tok, 'payload' => $p]);
    same($r['status'], 200, $r['body']);
    foreach ([11, 12, 13] as $i) {
        same($b->upload($tok, 'akte:' . $ref($i), "$tmpFiles/akte.pdf")['status'], 200);
        same($b->upload($tok, 'foto:' . $ref($i), "$tmpFiles/foto.jpg")['status'], 200);
    }
    same($b->upload($tok, 'logo', "$tmpFiles/akte.png", 'logo.png')['status'], 200);
    $f = $b->postJson('/api/submit-finalize.php', ['submission_token' => $tok]);
    same($f['json']['data']['nomor_pendaftaran'], 'GMB-SB-2026-0002', $f['body']);
    same($f['json']['data']['kategori'], 'U12');
    same($f['json']['data']['jumlah_pemain'], 3);
});

// =================================================================== CEK PENDAFTARAN
test('Cek pendaftaran: hanya data non-sensitif', function () use ($B, $player) {
    $b = $B('cek');
    $b->get('/cek-pendaftaran.php');
    $r = $b->postForm('/cek-pendaftaran.php', ['nomor_pendaftaran' => 'gmb-sb-2026-0001']);
    same($r['status'], 200);
    ok(str_contains($r['body'], 'SSB Garuda Muda') && str_contains($r['body'], 'U-10') && str_contains($r['body'], 'Pending'));
    ok(!str_contains($r['body'], $player(1)['nik']), 'NIK bocor');
    ok(!str_contains($r['body'], 'Ayah 1') && !str_contains($r['body'], 'Pemain Uji 1'), 'data pemain/ortu bocor');
    ok(str_contains($r['body'], rawurlencode('Status saat ini: PENDING')), 'WA admin dengan konteks status');
});

test('Cek pendaftaran: nomor tidak ada & format salah', function () use ($B) {
    $b = $B('cek2');
    $b->get('/cek-pendaftaran.php');
    ok(str_contains($b->postForm('/cek-pendaftaran.php', ['nomor_pendaftaran' => 'GMB-SB-2026-0999'])['body'], 'tidak ditemukan'));
    ok(str_contains($b->postForm('/cek-pendaftaran.php', ['nomor_pendaftaran' => '<script>'])['body'], 'Format nomor pendaftaran tidak valid'));
});

test('Cek pendaftaran tanpa CSRF ditolak', function () use ($B) {
    $b = $B('cek3');
    $r = $b->request('POST', '/cek-pendaftaran.php', ['form' => ['nomor_pendaftaran' => 'GMB-SB-2026-0001']]);
    ok(str_contains($r['body'], 'Sesi halaman kedaluwarsa'));
    ok(!str_contains($r['body'], 'SSB Garuda Muda'));
});

test('Rate limit cek status -> 429', function () use ($B) {
    clear_ratelimit();
    $b = $B('rl');
    $b->get('/cek-pendaftaran.php');
    $last = null;
    for ($i = 0; $i < 21; $i++) $last = $b->postForm('/cek-pendaftaran.php', ['nomor_pendaftaran' => 'GMB-SB-2026-0001']);
    same($last['status'], 429);
    ok(str_contains($last['body'], 'Terlalu banyak'));
    clear_ratelimit();
});

// =================================================================== API ERROR
test('API down: landing tetap tampil, submit -> 503', function () use ($uuid, $payload, $player) {
    $d = new Browser('http://127.0.0.1:' . DOWN_PORT, 'down');
    $r = $d->get('/');
    same($r['status'], 200);
    ok(str_contains($r['body'], 'Status pendaftaran akan diumumkan panitia'));
    $f = $d->get('/daftar-sepakbola.php');
    ok(str_contains($f['body'], 'Layanan pendaftaran belum dapat diakses'));
    $s = $d->postJson('/api/submit-init.php', ['submission_token' => $uuid(), 'payload' => $payload('X Club', [$player(1)])]);
    same($s['status'], 503);
    same($s['json']['success'], false);
});

test('Aturan usia kosong -> form dikunci & backend menolak', function () use ($B, $uuid, $payload, $player) {
    gas_control('/__test/props', ['MAX_AGE_U12' => null]);
    clear_cache();
    $b = $B('noage');
    $f = $b->get('/daftar-sepakbola.php');
    ok(str_contains($f['body'], 'Ketentuan batas usia belum ditetapkan panitia'));
    ok(str_contains($f['body'], 'id="btn-submit" class="btn-primary flex-1 sm:flex-none" hidden disabled'));
    gas_control('/__test/props', ['MAX_AGE_U12' => '12']);
    clear_cache();
});

// =================================================================== ADMIN
test('Admin tanpa login -> redirect login', function () use ($B) {
    foreach (['/admin/', '/admin/clubs.php', '/admin/players.php', '/admin/documents.php', '/admin/logs.php', '/admin/settings.php'] as $p) {
        $r = $B('anon')->get($p);
        same($r['status'], 302, $p);
        ok(str_contains($r['headers'], '/admin/login.php'), $p);
    }
});

test('Admin action & document tanpa login -> 401/302', function () use ($B) {
    $a = $B('anon2');
    same($a->request('POST', '/admin/action.php', ['json' => ['action' => 'update_status']])['status'], 401);
    same($a->get('/admin/document.php?target=player&id=PMN-AAAAAAAAAAAA&type=akte')['status'], 302);
    same($a->request('POST', '/admin/export.php', ['form' => ['dataset' => 'players']])['status'], 302);
});

test('Login admin: password salah ditolak', function () use ($B) {
    clear_ratelimit();
    $a = $B('wrong');
    $a->get('/admin/login.php');
    $r = $a->postForm('/admin/login.php', ['username' => 'superadmin', 'password' => 'salah-password']);
    same($r['status'], 200);
    ok(str_contains($r['body'], 'Username atau password salah'));
});

test('Login admin: akun nonaktif ditolak', function () use ($B) {
    $a = $B('disabled');
    $a->get('/admin/login.php');
    ok(str_contains($a->postForm('/admin/login.php', ['username' => 'nonaktif', 'password' => 'Rahasia-Uji-123'])['body'], 'Username atau password salah'));
});

test('Brute force login dikunci', function () use ($B) {
    clear_ratelimit();
    $a = $B('brute');
    $a->get('/admin/login.php');
    $r = null;
    for ($i = 0; $i < 6; $i++) $r = $a->postForm('/admin/login.php', ['username' => 'superadmin', 'password' => 'x' . $i]);
    ok(str_contains($r['body'], 'Terlalu banyak percobaan login'));
    clear_ratelimit();
});

$login = function (string $user) use ($B): Browser {
    $a = $B('adm_' . $user);
    $a->get('/admin/login.php');
    $r = $a->postForm('/admin/login.php', ['username' => $user, 'password' => 'Rahasia-Uji-123']);
    if ($r['status'] !== 302) throw new RuntimeException("login $user gagal: " . $r['status']);
    $a->get('/admin/');
    return $a;
};

$super = null;
test('Login SUPERADMIN + dashboard', function () use ($login, &$super) {
    clear_ratelimit();
    $super = $login('superadmin');
    $r = $super->get('/admin/');
    same($r['status'], 200);
    ok(str_contains($r['body'], 'Total Club') && str_contains($r['body'], 'GMB-SB-2026-0002'));
    ok(str_contains(strtolower($r['headers']), 'cache-control: no-store'));
});

test('Login me-regenerasi session id', function () use ($B) {
    $a = $B('fix');
    $a->get('/admin/login.php');
    $before = file_get_contents($a->jar);
    $a->postForm('/admin/login.php', ['username' => 'verif', 'password' => 'Rahasia-Uji-123']);
    preg_match_all('/GAMBASI_SID\s+(\S+)/', $before . "\n", $m1);
    preg_match_all('/GAMBASI_SID\s+(\S+)/', file_get_contents($a->jar), $m2);
    ok(end($m1[1]) !== end($m2[1]), 'session id harus berubah');
});

$clubId = null;
$playerId = null;
test('Admin clubs: list, filter kategori, search nama pemain', function () use (&$super, &$clubId) {
    $r = $super->get('/admin/clubs.php');
    ok(str_contains($r['body'], 'SSB Garuda Muda') && str_contains($r['body'], 'Persipura Junior Merauke'));
    preg_match('/club\.php\?id=(CLB-[A-Z0-9]+)/', $super->get('/admin/clubs.php?q=GMB-SB-2026-0001')['body'], $m);
    $clubId = $m[1] ?? null;
    ok($clubId !== null, 'club id');
    $f = $super->get('/admin/clubs.php?kategori=U12');
    ok(str_contains($f['body'], 'Persipura') && !str_contains($f['body'], 'SSB Garuda Muda'));
    ok(str_contains($super->get('/admin/clubs.php?q=Pemain+Uji+12')['body'], 'Persipura'));
    ok(str_contains($super->get('/admin/clubs.php?status=VERIFIED')['body'], 'Tidak ada data yang cocok'));
});

test('Admin club detail: NIK lengkap untuk SUPERADMIN, tanpa Drive ID', function () use (&$super, &$clubId, &$playerId, $player) {
    $r = $super->get('/admin/club.php?id=' . $clubId);
    same($r['status'], 200);
    ok(str_contains($r['body'], $player(1)['nik']) && str_contains($r['body'], 'Ayah 1'));
    ok(!str_contains($r['body'], 'drive.google.com') && !preg_match('/file_[0-9a-z]+_[0-9a-f]{8}/', $r['body']), 'Drive ID bocor');
    preg_match('/id="(PMN-[A-Z0-9]+)"/', $r['body'], $m);
    $playerId = $m[1] ?? null;
    ok($playerId !== null);
});

test('Admin players: filter & search', function () use (&$super) {
    $r = $super->get('/admin/players.php?kategori=U12&q=Pemain+Uji+12');
    ok(str_contains($r['body'], 'Pemain Uji 12') && !str_contains($r['body'], 'Pemain Uji 11<'));
    ok(str_contains($super->get('/admin/players.php?docs=missing')['body'], 'Tidak ada data yang cocok'));
});

test('Admin dokumen: akte PDF & foto JPG di-stream privat', function () use (&$super, &$playerId) {
    $a = $super->get('/admin/document.php?target=player&type=akte&id=' . $playerId);
    same($a['status'], 200);
    ok(str_starts_with($a['body'], '%PDF'));
    ok(str_contains(strtolower($a['headers']), 'content-type: application/pdf'));
    ok(str_contains(strtolower($a['headers']), 'cache-control: private, no-store'));
    $f = $super->get('/admin/document.php?target=player&type=foto&id=' . $playerId);
    ok(str_contains(strtolower($f['headers']), 'content-type: image/jpeg'));
    same($super->get('/admin/document.php?target=player&type=../x&id=' . $playerId)['status'], 400);
    same($super->get('/admin/documents.php')['status'], 200);
});

test('Update status: REVISION tanpa catatan ditolak', function () use (&$super, &$clubId) {
    $r = $super->postJson('/admin/action.php', ['action' => 'update_status', 'target' => 'club', 'id' => $clubId, 'status' => 'REVISION']);
    same($r['status'], 422);
});

test('Update status club -> VERIFIED + tercermin di cek publik', function () use (&$super, &$clubId, $B) {
    $r = $super->postJson('/admin/action.php', ['action' => 'update_status', 'target' => 'club', 'id' => $clubId, 'status' => 'VERIFIED', 'note' => 'Dokumen lengkap']);
    same($r['status'], 200, $r['body']);
    same($r['json']['data']['status'], 'VERIFIED');
    $b = $B('cek4');
    $b->get('/cek-pendaftaran.php');
    ok(str_contains($b->postForm('/cek-pendaftaran.php', ['nomor_pendaftaran' => 'GMB-SB-2026-0001'])['body'], 'Terverifikasi'));
});

test('Update status pemain + tambah catatan', function () use (&$super, &$playerId) {
    same($super->postJson('/admin/action.php', ['action' => 'update_status', 'target' => 'player', 'id' => $playerId, 'status' => 'VERIFIED'])['status'], 200);
    same($super->postJson('/admin/action.php', ['action' => 'add_note', 'target' => 'player', 'id' => $playerId, 'note' => 'OK <b>tebal</b>'])['status'], 200);
    same($super->postJson('/admin/action.php', ['action' => 'update_status', 'target' => 'player', 'id' => $playerId, 'status' => 'HACKED'])['status'], 422);
});

test('Catatan admin di-escape (anti XSS)', function () use (&$super, &$clubId) {
    $super->postJson('/admin/action.php', ['action' => 'add_note', 'target' => 'club', 'id' => $clubId, 'note' => '<script>alert(1)</script>']);
    $b = $super->get('/admin/club.php?id=' . $clubId)['body'];
    ok(!str_contains($b, '<script>alert(1)</script>'));
});

test('Admin action tanpa CSRF -> 419', function () use (&$super, &$clubId) {
    same($super->postJson('/admin/action.php', ['action' => 'add_note', 'target' => 'club', 'id' => $clubId, 'note' => 'x'], false)['status'], 419);
});

test('Export CSV clubs', function () use (&$super) {
    $r = $super->postForm('/admin/export.php', ['dataset' => 'clubs']);
    same($r['status'], 200);
    ok(str_contains(strtolower($r['headers']), 'text/csv'));
    ok(str_contains($r['body'], 'nomor_pendaftaran') && str_contains($r['body'], 'GMB-SB-2026-0002'));
});

test('Export CSV players sensitif (SUPERADMIN) memuat NIK sebagai teks', function () use (&$super, $player) {
    $r = $super->postForm('/admin/export.php', ['dataset' => 'players', 'sensitive' => '1']);
    ok(str_contains($r['body'], ',nik,'));
    ok(str_contains($r['body'], "'" . $player(1)['nik']));
    $n = $super->postForm('/admin/export.php', ['dataset' => 'players']);
    ok(!str_contains($n['body'], $player(1)['nik']), 'export non-sensitif tanpa NIK');
});

test('Logs page memuat aktivitas audit tanpa NIK', function () use (&$super, $player) {
    $r = $super->get('/admin/logs.php');
    foreach (['SUBMIT', 'CREATE_CLUB', 'ADD_PLAYER', 'UPLOAD_AKTE', 'UPLOAD_FOTO', 'ADMIN_LOGIN', 'ADMIN_VIEW', 'ADMIN_UPDATE_STATUS', 'ADMIN_ADD_NOTE', 'ADMIN_EXPORT'] as $a) {
        ok(str_contains($r['body'], $a), "log $a");
    }
    ok(!str_contains($r['body'], $player(1)['nik']));
});

test('Settings: tutup pendaftaran -> form ditutup & API 410, lalu buka lagi', function () use (&$super, $B, $uuid, $payload, $player) {
    $s = $super->get('/admin/settings.php');
    ok(str_contains($s['body'], 'Status Sistem'));
    ok(str_contains($s['body'], 'Penyimpanan Data (Google Drive)') && str_contains($s['body'], 'gambasipapsel@gmail.com')
        && str_contains($s['body'], 'https://drive.google.com/drive/home'), 'info akun penyimpanan');
    ok(!str_contains($s['body'], 'Akun penyimpanan salah'), 'akun terverifikasi');
    $base = ['MIN_AGE_U10' => '6', 'MAX_AGE_U10' => '10', 'MIN_AGE_U12' => '9', 'MAX_AGE_U12' => '12', 'AGE_REFERENCE_DATE' => '2026-10-30', 'MIN_PLAYERS' => '', 'MAX_PLAYERS' => ''];
    $r = $super->postForm('/admin/settings.php', $base);
    same($r['status'], 302, 'simpan (tanpa REGISTRATION_OPEN = tutup)');
    $b = $B('closed');
    ok(str_contains($b->get('/daftar-sepakbola.php')['body'], 'Pendaftaran sedang ditutup'));
    same($b->postJson('/api/submit-init.php', ['submission_token' => $uuid(), 'payload' => $payload('Club Tutup', [$player(90)])])['status'], 410);
    $super->get('/admin/settings.php');
    same($super->postForm('/admin/settings.php', $base + ['REGISTRATION_OPEN' => 'true'])['status'], 302);
    ok(!str_contains($b->get('/daftar-sepakbola.php')['body'], 'Pendaftaran sedang ditutup'));
});

test('Settings: nilai tidak valid ditolak', function () use (&$super) {
    $super->get('/admin/settings.php');
    $r = $super->postForm('/admin/settings.php', ['MIN_AGE_U10' => '12', 'MAX_AGE_U10' => '10', 'REGISTRATION_OPEN' => 'true']);
    same($r['status'], 200);
    ok(str_contains($r['body'], 'Pengaturan tidak valid'));
});

test('VIEWER: tidak bisa verifikasi, dokumen, export sensitif, pengaturan; NIK tersamar', function () use ($login, &$clubId, &$playerId, $player) {
    $v = $login('viewer');
    same($v->get('/admin/verification.php')['status'], 403);
    same($v->get('/admin/documents.php')['status'], 403);
    same($v->get('/admin/logs.php')['status'], 403);
    same($v->get('/admin/document.php?target=player&type=akte&id=' . $playerId)['status'], 403);
    same($v->postJson('/admin/action.php', ['action' => 'update_status', 'target' => 'club', 'id' => $clubId, 'status' => 'REJECTED', 'note' => 'x'])['status'], 403);
    same($v->postForm('/admin/export.php', ['dataset' => 'players', 'sensitive' => '1'])['status'], 403);
    same($v->postForm('/admin/settings.php', ['REGISTRATION_OPEN' => 'false'])['status'], 403);
    $d = $v->get('/admin/club.php?id=' . $clubId)['body'];
    ok(!str_contains($d, $player(1)['nik']) && str_contains($d, '********'), 'NIK harus tersamar');
    ok(!str_contains($d, 'Ayah 1'), 'data ortu tersembunyi');
    ok(!str_contains($d, 'doc-open'), 'tombol dokumen tidak tampil');
});

test('VERIFIKATOR: bisa verifikasi, tidak bisa export sensitif', function () use ($login, &$clubId) {
    $v = $login('verif');
    same($v->get('/admin/verification.php')['status'], 200);
    same($v->postJson('/admin/action.php', ['action' => 'update_status', 'target' => 'club', 'id' => $clubId, 'status' => 'REVIEW'])['status'], 200);
    same($v->postForm('/admin/export.php', ['dataset' => 'players', 'sensitive' => '1'])['status'], 403);
});

test('Setiap akses halaman admin tercatat di app log (username, halaman, permission)', function () use ($login, &$clubId) {
    clear_ratelimit();
    $logFile = IT_TMP . '/storage/logs/app-' . date('Y-m') . '.log';
    $before = is_file($logFile) ? file_get_contents($logFile) : '';

    $v = $login('verif');
    $v->get('/admin/clubs.php?q=Pemain+Uji+1&status=PENDING');
    $v->get('/admin/club.php?id=' . $clubId);
    $v->get('/admin/verification.php');

    $baru = substr(file_get_contents($logFile), strlen($before));
    $baris = array_values(array_filter(explode("\n", $baru), fn($l) => str_contains($l, 'Akses admin')));
    ok(count($baris) >= 3, 'jumlah baris log: ' . count($baris));

    $clubs = implode("\n", array_filter($baris, fn($l) => str_contains($l, '/admin/clubs.php')));
    ok($clubs !== '', 'halaman clubs.php tidak tercatat');
    foreach (['"username":"verif"', '"role":"VERIFIKATOR"', '"permission":"view"', '"method":"GET"', '"ip":"127.0.0.1"'] as $needle) {
        ok(str_contains($clubs, $needle), "log tidak memuat $needle");
    }
    ok(str_contains(implode("\n", $baris), '/admin/verification.php'), 'halaman verification.php tidak tercatat');
    ok(!str_contains($baru, 'Pemain+Uji') && !str_contains($baru, 'Pemain Uji'),
        'query pencarian (bisa berisi nama pemain) ikut tercatat');
    ok(!str_contains($baru, 'GAMBASI_SID') && !str_contains($baru, 'Rahasia-Uji'), 'session/password bocor ke log');
});

test('Akses admin yang ditolak juga tercatat', function () use ($B, $login, &$playerId) {
    clear_ratelimit();
    $logFile = IT_TMP . '/storage/logs/app-' . date('Y-m') . '.log';
    $before = strlen(is_file($logFile) ? file_get_contents($logFile) : '');

    $B('anon-log')->get('/admin/clubs.php');              // belum login
    $login('viewer')->get('/admin/documents.php');           // login tapi izin kurang

    $baru = substr(file_get_contents($logFile), $before);
    ok(str_contains($baru, 'Akses admin ditolak: belum login') && str_contains($baru, '/admin/clubs.php'));
    $kurang = implode("\n", array_filter(explode("\n", $baru), fn($l) => str_contains($l, 'izin kurang')));
    ok($kurang !== '', 'penolakan izin tidak tercatat');
    ok(str_contains($kurang, '"username":"viewer"') && str_contains($kurang, '"permission":"view_documents"'), $kurang);
});

test('Logout mengakhiri sesi', function () use (&$super) {
    $super->get('/admin/');
    $super->postForm('/admin/logout.php', []);
    same($super->get('/admin/')['status'], 302);
});

test('Logout via GET tidak mengeluarkan (butuh POST+CSRF)', function () use ($login) {
    clear_ratelimit();
    $a = $login('superadmin');
    $a->get('/admin/logout.php');
    same($a->get('/admin/')['status'], 200);
});

// =================================================================== OUTPUT
$pass = count(array_filter($results, fn($r) => $r[0] === 'PASS'));
$fail = count($results) - $pass;
foreach ($results as [$s, $n]) echo "$s  $n\n";
echo "\nPHP integration tests: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
