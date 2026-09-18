<?php
/**
 * Validasi sisi PHP (lapisan kedua). Apps Script tetap memvalidasi ulang semuanya.
 */

declare(strict_types=1);

const GAMBASI_POSITIONS = ['KIPER' => 'Kiper', 'BELAKANG' => 'Belakang', 'TENGAH' => 'Tengah', 'DEPAN' => 'Depan'];
const GAMBASI_GENDERS = ['L' => 'Laki-laki', 'P' => 'Perempuan'];

function clean_text(mixed $value, int $max = 255): string
{
    if (!is_scalar($value)) {
        return '';
    }
    $s = (string) $value;
    if (!mb_check_encoding($s, 'UTF-8')) {
        $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    }
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F<>]/u', '', $s) ?? '';
    $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');
    return mb_substr($s, 0, $max);
}

function normalize_phone(mixed $value): string
{
    $s = preg_replace('/[\s\-().]/', '', is_scalar($value) ? (string) $value : '') ?? '';
    if (str_starts_with($s, '+')) {
        $s = substr($s, 1);
    }
    if (str_starts_with($s, '0')) {
        $s = '62' . substr($s, 1);
    }
    return preg_match('/^628\d{7,12}$/', $s) ? $s : '';
}

function is_valid_nik(string $nik): bool
{
    return (bool) preg_match('/^\d{16}$/', $nik) && $nik !== str_repeat('0', 16);
}

function is_valid_iso_date(string $date): bool
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
        return false;
    }
    return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
}

function age_on(string $birth, string $ref): int
{
    return (new DateTimeImmutable($birth))->diff(new DateTimeImmutable($ref))->y;
}

function is_valid_submission_token(mixed $t): bool
{
    return is_string($t) && (bool) preg_match('/^[A-Za-z0-9-]{32,64}$/', $t);
}

/**
 * Validasi & normalisasi payload pendaftaran.
 * @return array{0: array, 1: array<int, array{field:string,message:string}>}
 */
function validate_registration_payload(array $in, array $publicConfig): array
{
    $errors = [];
    $err = function (string $field, string $message) use (&$errors): void {
        $errors[] = ['field' => $field, 'message' => $message];
    };

    $kategori = is_string($in['kategori'] ?? null) ? $in['kategori'] : '';
    if (!in_array($kategori, ['U10', 'U12'], true)) {
        return [[], [['field' => 'kategori', 'message' => 'Kategori tidak valid. Pilih U10 atau U12.', 'code' => 'INVALID_CATEGORY']]];
    }

    $c = is_array($in['club'] ?? null) ? $in['club'] : [];
    $o = is_array($in['official'] ?? null) ? $in['official'] : [];
    $club = [
        'nama_club' => clean_text($c['nama_club'] ?? '', 100),
        'alamat'    => clean_text($c['alamat'] ?? '', 250),
        'kampung'   => clean_text($c['kampung'] ?? '', 100),
        'distrik'   => clean_text($c['distrik'] ?? '', 100),
        'kabupaten' => clean_text($c['kabupaten'] ?? '', 100),
        'provinsi'  => clean_text($c['provinsi'] ?? '', 100),
        'has_logo'  => ($c['has_logo'] ?? false) === true,
    ];
    $official = [
        'nama_manager' => clean_text($o['nama_manager'] ?? '', 100),
        'nama_pelatih' => clean_text($o['nama_pelatih'] ?? '', 100),
        'whatsapp'     => normalize_phone($o['whatsapp'] ?? ''),
        'email'        => strtolower(clean_text($o['email'] ?? '', 120)),
    ];

    if (mb_strlen($club['nama_club']) < 3) $err('club.nama_club', 'Nama club wajib diisi (minimal 3 karakter).');
    if (mb_strlen($club['alamat']) < 5) $err('club.alamat', 'Alamat wajib diisi.');
    if ($club['distrik'] === '') $err('club.distrik', 'Distrik wajib diisi.');
    if ($club['kabupaten'] === '') $err('club.kabupaten', 'Kabupaten wajib diisi.');
    if ($club['provinsi'] === '') $err('club.provinsi', 'Provinsi wajib diisi.');
    if (mb_strlen($official['nama_manager']) < 3) $err('official.nama_manager', 'Nama manager wajib diisi.');
    if (mb_strlen($official['nama_pelatih']) < 3) $err('official.nama_pelatih', 'Nama pelatih wajib diisi.');
    if ($official['whatsapp'] === '') $err('official.whatsapp', 'Nomor WhatsApp tidak valid (contoh: 081234567890).');
    if ($official['email'] !== '' && !filter_var($official['email'], FILTER_VALIDATE_EMAIL)) {
        $err('official.email', 'Format email tidak valid.');
    }

    $limits = $publicConfig['players'] ?? ['min' => 1, 'max' => 40];
    $list = is_array($in['players'] ?? null) ? array_values($in['players']) : [];
    if (count($list) < (int) $limits['min']) $err('players', 'Minimal ' . (int) $limits['min'] . ' pemain.');
    if (count($list) > (int) $limits['max']) {
        return [[], [['field' => 'players', 'message' => 'Maksimal ' . (int) $limits['max'] . ' pemain per club.']]];
    }

    $ageRule = $publicConfig['age_rules'][$kategori] ?? null;
    $today = date('Y-m-d');
    $players = [];
    $seenRef = $seenNik = $seenNo = [];

    foreach ($list as $i => $raw) {
        $raw = is_array($raw) ? $raw : [];
        $label = 'Pemain #' . ($i + 1);
        $f = "players[$i].";
        $p = [
            'ref'            => is_string($raw['ref'] ?? null) ? $raw['ref'] : '',
            'nama_lengkap'   => clean_text($raw['nama_lengkap'] ?? '', 100),
            'nik'            => preg_replace('/\s/', '', is_scalar($raw['nik'] ?? null) ? (string) $raw['nik'] : '') ?? '',
            'tempat_lahir'   => clean_text($raw['tempat_lahir'] ?? '', 80),
            'tanggal_lahir'  => is_string($raw['tanggal_lahir'] ?? null) ? $raw['tanggal_lahir'] : '',
            'jenis_kelamin'  => strtoupper(is_string($raw['jenis_kelamin'] ?? null) ? $raw['jenis_kelamin'] : ''),
            'posisi'         => strtoupper(is_string($raw['posisi'] ?? null) ? $raw['posisi'] : ''),
            'nomor_punggung' => ltrim(is_scalar($raw['nomor_punggung'] ?? null) ? (string) $raw['nomor_punggung'] : '', '0'),
            'nama_ayah'      => clean_text($raw['nama_ayah'] ?? '', 100),
            'nama_ibu'       => clean_text($raw['nama_ibu'] ?? '', 100),
            'nama_wali'      => clean_text($raw['nama_wali'] ?? '', 100),
            'whatsapp_wali'  => normalize_phone($raw['whatsapp_wali'] ?? ''),
            'alamat'         => clean_text($raw['alamat'] ?? '', 250),
        ];

        if (!preg_match('/^[a-z0-9]{6,16}$/', $p['ref'])) $err($f . 'ref', "$label: referensi pemain tidak valid.");
        elseif (isset($seenRef[$p['ref']])) $err($f . 'ref', "$label: referensi pemain duplikat.");
        $seenRef[$p['ref']] = true;

        if (mb_strlen($p['nama_lengkap']) < 3) $err($f . 'nama_lengkap', "$label: nama lengkap wajib diisi.");

        if (!is_valid_nik($p['nik'])) $err($f . 'nik', "$label: NIK harus 16 digit angka.");
        elseif (isset($seenNik[$p['nik']])) $err($f . 'nik', "$label: NIK sama dengan pemain lain di pendaftaran ini.");
        $seenNik[$p['nik']] = true;

        if ($p['tempat_lahir'] === '') $err($f . 'tempat_lahir', "$label: tempat lahir wajib diisi.");

        if (!is_valid_iso_date($p['tanggal_lahir'])) {
            $err($f . 'tanggal_lahir', "$label: tanggal lahir tidak valid.");
        } elseif ($p['tanggal_lahir'] > $today) {
            $err($f . 'tanggal_lahir', "$label: tanggal lahir tidak boleh di masa depan.");
        } elseif (is_array($ageRule)) {
            $age = age_on($p['tanggal_lahir'], (string) $ageRule['reference_date']);
            if ($age < (int) $ageRule['min'] || $age > (int) $ageRule['max']) {
                $errors[] = [
                    'field' => $f . 'tanggal_lahir',
                    'code' => 'INVALID_AGE',
                    'message' => sprintf('%s: usia %d tahun (per %s) tidak sesuai kategori %s (%d–%d tahun).',
                        $label, $age, $ageRule['reference_date'], $kategori === 'U10' ? 'U-10' : 'U-12',
                        $ageRule['min'], $ageRule['max']),
                ];
            }
        }

        if (!isset(GAMBASI_GENDERS[$p['jenis_kelamin']])) $err($f . 'jenis_kelamin', "$label: jenis kelamin wajib dipilih.");
        if (!isset(GAMBASI_POSITIONS[$p['posisi']])) $err($f . 'posisi', "$label: posisi wajib dipilih.");

        if (!preg_match('/^\d{1,2}$/', $p['nomor_punggung']) || (int) $p['nomor_punggung'] < 1) {
            $err($f . 'nomor_punggung', "$label: nomor punggung harus 1–99.");
        } elseif (isset($seenNo[$p['nomor_punggung']])) {
            $err($f . 'nomor_punggung', "$label: nomor punggung {$p['nomor_punggung']} sudah dipakai pemain lain.");
        }
        $seenNo[$p['nomor_punggung']] = true;

        if ($p['nama_ayah'] === '' && $p['nama_ibu'] === '' && $p['nama_wali'] === '') {
            $err($f . 'nama_ayah', "$label: isi minimal salah satu nama ayah, ibu, atau wali.");
        }
        if ($p['whatsapp_wali'] === '') $err($f . 'whatsapp_wali', "$label: nomor WhatsApp orang tua/wali tidak valid.");
        if (mb_strlen($p['alamat']) < 5) $err($f . 'alamat', "$label: alamat wajib diisi.");

        $players[] = $p;
    }

    return [[
        'kategori' => $kategori,
        'club'     => $club,
        'official' => $official,
        'players'  => $players,
    ], $errors];
}

/**
 * Pastikan berkas PDF benar-benar diawali header "%PDF" pada byte ke-0.
 *
 * finfo/mime_content_type masih menganggap berkas sebagai application/pdf walau ada
 * sampah di depan header (mis. dokumen HTML utuh lalu "%PDF-1.4" — berkas polyglot
 * yang bisa diperlakukan sebagai HTML oleh pembaca lain). Apps Script juga menolak
 * berkas seperti itu, jadi pemeriksaan ini menahannya lebih awal di sisi PHP.
 */
function validate_pdf_magic_bytes(string $path): bool
{
    $fh = @fopen($path, 'rb');
    if ($fh === false) {
        return false;
    }
    $head = fread($fh, 4);
    fclose($fh);
    return $head === '%PDF';
}

/**
 * Validasi file upload PHP ($_FILES entry).
 * Tidak mempercayai ekstensi/MIME dari browser: MIME dideteksi dari isi file (finfo).
 * @return array{ok:bool, code?:string, message?:string, mime?:string, ext?:string, name?:string, size?:int, path?:string}
 */
function validate_uploaded_file(mixed $file, string $docType): array
{
    $rules = config("upload.rules.$docType");
    if (!$rules) {
        return ['ok' => false, 'code' => 'INVALID_FILE', 'message' => 'Jenis dokumen tidak dikenal.'];
    }
    if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
        return ['ok' => false, 'code' => 'INVALID_FILE', 'message' => 'File tidak ditemukan.'];
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return ['ok' => false, 'code' => 'FILE_TOO_LARGE', 'message' => 'Ukuran file melebihi 5 MB.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'code' => 'INVALID_FILE', 'message' => 'File gagal diunggah. Silakan coba lagi.'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'code' => 'INVALID_FILE', 'message' => 'File tidak valid.'];
    }
    $size = (int) filesize($file['tmp_name']);
    if ($size <= 0) {
        return ['ok' => false, 'code' => 'INVALID_FILE', 'message' => 'File kosong.'];
    }
    if ($size > (int) config('upload.max_bytes')) {
        return ['ok' => false, 'code' => 'FILE_TOO_LARGE', 'message' => 'Ukuran file melebihi 5 MB.'];
    }

    $original = basename(str_replace('\\', '/', (string) ($file['name'] ?? '')));
    $parts = explode('.', strtolower($original));
    if (count($parts) < 2) {
        return ['ok' => false, 'code' => 'INVALID_FILE', 'message' => 'Nama file harus memiliki ekstensi.'];
    }
    foreach (array_slice($parts, 1) as $part) {
        if (in_array($part, config('upload.blocked_exts'), true)) {
            return ['ok' => false, 'code' => 'INVALID_FILE', 'message' => 'Tipe file tidak diizinkan.'];
        }
    }
    $ext = end($parts);
    if (!in_array($ext, $rules['exts'], true)) {
        return ['ok' => false, 'code' => 'INVALID_FILE',
            'message' => 'Format file tidak diizinkan. Gunakan: ' . strtoupper(implode(', ', $rules['exts'])) . '.'];
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
    if (!in_array($mime, $rules['mimes'], true)) {
        return ['ok' => false, 'code' => 'INVALID_MIME', 'message' => 'Isi file tidak sesuai format yang diizinkan.'];
    }
    if ($mime === 'application/pdf' && !validate_pdf_magic_bytes($file['tmp_name'])) {
        return ['ok' => false, 'code' => 'INVALID_MIME', 'message' => 'Berkas PDF tidak valid (header PDF tidak ditemukan).'];
    }
    $extOk = ($mime === 'application/pdf' && $ext === 'pdf')
        || ($mime === 'image/jpeg' && in_array($ext, ['jpg', 'jpeg'], true))
        || ($mime === 'image/png' && $ext === 'png');
    if (!$extOk) {
        return ['ok' => false, 'code' => 'INVALID_MIME', 'message' => 'Ekstensi file tidak sesuai dengan isinya.'];
    }
    if (str_starts_with($mime, 'image/') && @getimagesize($file['tmp_name']) === false && function_exists('getimagesize')) {
        // getimagesize butuh header gambar yang valid
        return ['ok' => false, 'code' => 'INVALID_MIME', 'message' => 'File gambar rusak atau tidak valid.'];
    }

    $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', pathinfo($original, PATHINFO_FILENAME)) ?: 'dokumen';
    return [
        'ok'   => true,
        'mime' => $mime,
        'ext'  => $ext,
        'name' => substr($safeName, 0, 60) . '.' . $ext,
        'size' => $size,
        'path' => $file['tmp_name'],
    ];
}
