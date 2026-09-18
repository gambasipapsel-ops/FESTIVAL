# GAMBASI PAPUA SELATAN 2026 — Sistem Pendaftaran Festival Sepak Bola U-10 & U-12

**Festival Sepak Bola Usia Dini U-10 & U-12 · Piala DPR Papua Selatan Ke-2 Tahun 2026**
30 Oktober – 1 November 2026 · Lapangan Kodim Merauke · Merauke — Papua Selatan
Tema: *"Membangun Karakter, Sportivitas, dan Kecintaan terhadap Sepak Bola Sejak Dini"*

Isi sistem: landing page, pendaftaran club multi-step (data club, official, pemain, akte kelahiran, foto full body), cek status publik, dan dashboard admin untuk verifikasi, dokumen, log, export, dan pengaturan.

```
Browser ──► PHP (Laragon/Apache) ──► Google Apps Script Web App ──► Google Sheets (database)
            proxy + CSRF + validasi     validasi ulang, LockService,     Google Drive (dokumen PRIVAT)
            session admin               nomor pendaftaran, audit log
```

URL dan secret Apps Script **hanya ada di server PHP**. Browser tidak pernah menghubungi Apps Script secara langsung.

---

## Daftar Isi
1. [Requirement](#1-requirement)
2. [Install Laragon](#2-install-laragon)
3. [Setup PHP](#3-setup-php)
4. [Setup Virtual Host](#4-setup-virtual-host)
5. [Setup Google Sheets](#5-setup-google-sheets)
6. [Setup Google Drive](#6-setup-google-drive)
7. [Setup Apps Script](#7-setup-apps-script)
8. [Script Properties](#8-script-properties)
9. [Deploy Web App](#9-deploy-web-app)
10. [Configure API URL](#10-configure-api-url)
11. [Testing](#11-testing)
12. [Security](#12-security)
13. [Troubleshooting](#13-troubleshooting)
14. [Production deployment](#14-production-deployment)
15. [Referensi teknis](#15-referensi-teknis)

---

## 1. Requirement

| Komponen | Versi / catatan |
|---|---|
| Laragon | Full, dengan Apache 2.4 |
| PHP | 8.1 atau lebih baru (diuji dengan 8.3). Ekstensi: `curl`, `fileinfo`, `mbstring`, `openssl`, `json` (opsional `gd` untuk test) |
| Akun Google | Pemilik Spreadsheet, folder Drive, dan Apps Script |
| Browser | Chrome/Edge/Firefox/Safari versi terbaru (mobile & desktop) |

Tidak memakai MySQL, framework PHP, Node.js, React/Vue, Supabase, maupun Firebase.
Node.js **hanya** dipakai (opsional) untuk menjalankan test offline kode `.gs` (lihat [Testing](#11-testing)).

## 2. Install Laragon

1. Unduh Laragon Full dari situs resmi Laragon, lalu install (default `C:\laragon`).
2. Jalankan Laragon, lalu klik **Start All**.
3. Salin/clone project ke `C:\laragon\www\gambasi`.

## 3. Setup PHP

1. Laragon → **Menu → PHP → Extensions**, pastikan `curl`, `fileinfo`, `mbstring`, dan `openssl` aktif.
2. Batas upload sudah diatur di `public/.htaccess` (mod_php) dan `public/.user.ini` (FastCGI):
   `upload_max_filesize=6M`, `post_max_size=8M`. Dokumen tetap dibatasi 5 MB oleh aplikasi.
3. Salin konfigurasi:
   ```bash
   copy .env.example .env
   ```
4. Buat akun admin pertama (password minimal 10 karakter, disimpan sebagai hash di `storage/admin/admins.json`):
   ```bash
   php tools/create-admin.php superadmin SUPERADMIN "Nama Ketua Panitia"
   ```
   Perintah lain:
   ```bash
   php tools/create-admin.php verifikator1 VERIFIKATOR "Nama Verifikator"
   ```
   ```bash
   php tools/create-admin.php --list
   ```
   ```bash
   php tools/create-admin.php --disable verifikator1
   ```
   Di Windows, password terlihat saat diketik. Untuk menyembunyikannya, set variabel `ADMIN_PASSWORD` sebelum menjalankan perintah.

**Role admin**

| Role | Hak akses |
|---|---|
| `SUPERADMIN` | Semua akses: lihat NIK & dokumen, ubah status, catatan, export (termasuk data sensitif), log, pengaturan |
| `VERIFIKATOR` | Lihat NIK & dokumen, ubah status, catatan, export non-sensitif, log |
| `VIEWER` | Lihat ringkasan & daftar (NIK disamarkan, tanpa data orang tua, tanpa dokumen), export non-sensitif |

## 4. Setup Virtual Host

Laragon otomatis membuat host `http://gambasi.test` untuk folder `C:\laragon\www\gambasi` (**Menu → Apache → Reload** atau restart Laragon jika belum muncul).

- Jika DocumentRoot mengarah ke root project, `.htaccess` di root meneruskan semua request ke `public/` dan memblokir `app/`, `storage/`, `google-apps-script/`, `tests/`, `tools/`, serta file `.env`.
- **Disarankan:** arahkan DocumentRoot langsung ke `public/`. Buka `C:\laragon\etc\apache2\sites-enabled\auto.gambasi.test.conf`, lalu ubah:
  ```apache
  DocumentRoot "C:/laragon/www/gambasi/public"
  <Directory "C:/laragon/www/gambasi/public">
      AllowOverride All
      Require all granted
  </Directory>
  ```
  Simpan, lalu Reload Apache. Agar tidak ditimpa Laragon, salin file itu dengan nama lain (mis. `gambasi.conf`) dan nonaktifkan *Auto virtual hosts* untuk project ini.
- Isi `APP_URL=http://gambasi.test` di `.env`. Jika situs dibuka lewat subfolder (mis. `http://localhost/gambasi`), isi `APP_URL=http://localhost/gambasi`.

## 5. Setup Google Sheets

Spreadsheet **`GAMBASI_DATABASE_2026`** dibuat otomatis oleh fungsi `setupProject()` (langkah 7) beserta sheet dan header berikut:

| Sheet | Kegunaan |
|---|---|
| `CLUB` | `club_id, nomor_pendaftaran, kategori, nama_club, nama_manager, nama_pelatih, whatsapp, email, alamat, kampung, distrik, kabupaten, provinsi, logo_file_id, logo_url, jumlah_pemain, status, catatan_admin, created_at, updated_at` |
| `PEMAIN` | `pemain_id, club_id, nomor_pendaftaran, kategori, nama_lengkap, nik, tempat_lahir, tanggal_lahir, jenis_kelamin, posisi, nomor_punggung, nama_ayah, nama_ibu, nama_wali, whatsapp_wali, alamat, akte_file_id, akte_url, foto_file_id, foto_url, status_verifikasi, catatan_admin, created_at, updated_at` |
| `LOG` | `log_id, club_id, pemain_id, aktivitas, status, timestamp, aktor, detail` — tanpa NIK dan tanpa isi dokumen |
| `SUBMISSION` | Tabel internal untuk idempotensi dan recovery (`submission_token, state, …`) |

Jika ingin memakai spreadsheet yang sudah ada, isi Script Property `SPREADSHEET_ID` **sebelum** menjalankan `setupProject()`.

> Jangan bagikan spreadsheet ke pihak yang tidak berwenang karena berisi NIK dan data orang tua.
> Semua nilai ditulis sebagai teks (diawali apostrof) supaya NIK tidak berubah menjadi notasi ilmiah dan untuk mencegah formula injection.

## 6. Setup Google Drive

Struktur folder dibuat otomatis dan bersifat **privat** (tidak pernah "Anyone with the link"):

```
GAMBASI PAPUA SELATAN 2026
├── CLUB
│   ├── GMB-SB-2026-0001_NAMA_CLUB
│   │   ├── AKTE_KELAHIRAN   (AKTE_GMB-SB-2026-0001_01_NAMA.pdf)
│   │   └── FOTO_FULL_BODY   (FOTO_GMB-SB-2026-0001_01_NAMA.jpg)
│   └── _PROSES_xxxx         (folder sementara saat upload, di-rename saat final)
└── LOGO_CLUB
```

- Folder tidak dibuat ganda meskipun request diulang.
- Admin melihat dokumen lewat `admin/document.php` (setelah login, dengan izin `view_documents`, dan tercatat di log `ADMIN_VIEW`), bukan lewat link Drive.

## 7. Setup Apps Script

> **Wajib login sebagai `gambasipapsel@gmail.com`** (akun resmi panitia) sebelum langkah berikut.
> Spreadsheet, folder Drive, dan semua dokumen peserta akan tersimpan di Google Drive akun ini (https://drive.google.com/drive/home).
> Jika dijalankan dari akun lain, `setupProject()` akan berhenti, dan Web App menolak menyimpan pendaftaran (`CONFIG_ERROR`).
> Akun resmi bisa diganti lewat Script Property `STORAGE_OWNER_EMAIL`.

1. Buka https://script.google.com dengan akun `gambasipapsel@gmail.com` → **New project**, beri nama `GAMBASI API 2026`.
2. Buat file sesuai folder `google-apps-script/` (salin isinya):
   `Code.gs, Config.gs, ClubService.gs, PlayerService.gs, FileService.gs, Validation.gs, Response.gs, Utils.gs, Auth.gs, LockService.gs, LogService.gs`
3. **Project Settings → Show "appsscript.json"**, lalu ganti isinya dengan `google-apps-script/appsscript.json` (timezone `Asia/Jayapura`, runtime V8).
   *Alternatif:* pakai `clasp push` dari folder `google-apps-script/`.
4. Pilih fungsi **`setupProject`** → **Run** → izinkan akses (Sheets & Drive).
   Fungsi ini membuat spreadsheet, sheet, folder, `ENVIRONMENT`, `REGISTRATION_OPEN`, dan `API_SECRET` bila belum ada.
5. (Disarankan) Jalankan **`installCleanupTrigger`** sekali. Trigger ini menandai submission yang terhenti lebih dari 48 jam sebagai `EXPIRED` dan memindahkan file sementaranya ke trash Drive.

## 8. Script Properties

**Project Settings → Script Properties**

| Property | Wajib | Keterangan |
|---|---|---|
| `SPREADSHEET_ID` | ✔ | Diisi otomatis oleh `setupProject()` |
| `ROOT_FOLDER_ID` | ✔ | Diisi otomatis oleh `setupProject()` |
| `API_SECRET` | ✔ | Minimal 32 karakter acak. Harus sama dengan `GAMBASI_API_SECRET` di `.env` |
| `STORAGE_OWNER_EMAIL` | | Akun Google resmi pemilik penyimpanan (default `gambasipapsel@gmail.com`) |
| `ENVIRONMENT` | | `production` / `staging` |
| `REGISTRATION_OPEN` | | `true` / `false` (default `true`) |
| `MIN_AGE_U10`, `MAX_AGE_U10` | ✔* | Batas usia U-10 (tahun penuh) |
| `MIN_AGE_U12`, `MAX_AGE_U12` | ✔* | Batas usia U-12 (tahun penuh) |
| `AGE_REFERENCE_DATE` | ✔* | Tanggal acuan perhitungan usia, format `YYYY-MM-DD` |
| `MIN_PLAYERS`, `MAX_PLAYERS` | | Jumlah pemain per club (batas teknis maksimal 40) |

\* **Aturan usia sengaja tidak diisi default.** Sistem tidak mengarang batas usia maupun tanggal cutoff. Selama nilai tersebut kosong, formulir hanya bisa disimpan sebagai draft dan backend menolak pendaftaran dengan `CONFIG_ERROR`. Setelah regulasi resmi ditetapkan, SUPERADMIN dapat mengisinya dari **Admin → Pengaturan** (atau langsung di Script Properties).

## 9. Deploy Web App

1. **Deploy → New deployment → Select type: Web app**.
2. *Execute as*: **Me** (pemilik data). *Who has access*: **Anyone**.
   Akses publik diperlukan agar server PHP bisa memanggil tanpa OAuth. Setiap request tetap wajib membawa `API_SECRET`, dan tanpa secret tersebut semua aksi ditolak (`UNAUTHORIZED`). `doGet` hanya mengembalikan status health tanpa data.
3. Salin **Web app URL** (berakhiran `/exec`).
4. Setiap kali kode `.gs` diubah: **Deploy → Manage deployments → Edit → Version: New version** (URL tetap sama).

## 10. Configure API URL

Isi `.env` di root project:

```ini
APP_URL=http://gambasi.test
GAMBASI_API_URL=https://script.google.com/macros/s/XXXXXXXX/exec
GAMBASI_API_SECRET=<sama dengan Script Property API_SECRET>

# WhatsApp admin/penyelenggara (default 082345328926 bila dikosongkan)
PANITIA_WHATSAPP=082345328926

# Informasi publik (biarkan kosong jika belum resmi -> tampil "Akan diumumkan panitia")
PANITIA_NAMA_KONTAK=
PANITIA_EMAIL=
SEKRETARIAT=
BIAYA_PENDAFTARAN=
JADWAL_PENDAFTARAN=
BATAS_PENDAFTARAN=
JADWAL_VERIFIKASI=
TECHNICAL_MEETING=
```

Buka `http://gambasi.test/admin/settings.php`. Semua indikator di **Status Sistem** harus `OK`.

## 11. Testing

### Otomatis (tanpa akun Google)

```bash
tools\run-tests.bat
```

Perintah ini menjalankan dua suite:

1. `tests/gas/run-tests.js`: kode `.gs` **asli** dijalankan di atas tiruan in-memory SpreadsheetApp, DriveApp, LockService, dan lainnya (71 test).
2. `tests/php/run-tests.php`: uji end-to-end. PHP built-in server dijalankan dengan backend tiruan (`tests/gas/server.js`), lalu request HTTP nyata dikirim (68 test).

Cakupan: landing page, form, U-10, U-12, kategori/usia/NIK/HP tidak valid, field kosong, upload PDF/JPG/PNG, file >5 MB, MIME palsu, `.php`/double extension, akte/foto hilang, banyak pemain, duplicate submission (idempotensi), duplicate club & NIK, API mati, Google Drive error, Google Sheets error (rollback + retry), login admin, brute force, role VIEWER/VERIFIKATOR, update status, catatan (anti-XSS), cek status publik (tanpa data sensitif), export CSV, rate limit, CSRF, session, dan buka/tutup pendaftaran.

> Test memakai folder `tests/.tmp` dan tidak menyentuh `storage/` maupun Google.
> Node.js hanya alat test; aplikasi berjalan tanpa Node.

### Preview lokal dengan backend tiruan

```bash
node tests/gas/server.js 8800
```
```bash
set GAMBASI_ENV_FILE=C:\laragon\www\gambasi\tests\dev\dev.env && php -S 127.0.0.1:8801 -t public
```
Buat admin dev dengan `set GAMBASI_ENV_FILE=...` lalu jalankan `php tools/create-admin.php ...`.

### Manual dengan Google (staging)

1. Deploy Apps Script, isi aturan usia, lalu daftarkan 1 club U-10 dan 1 club U-12 dari smartphone.
2. Pastikan baris muncul di `CLUB`/`PEMAIN`, folder `GMB-SB-2026-000X_…` terbentuk, dan file **tidak** berstatus "Anyone with the link".
3. Tekan **Kirim** dua kali atau muat ulang saat proses berjalan. Nomor pendaftaran harus tetap sama.
4. Cek `/cek-pendaftaran.php`, lalu ubah status di admin dan cek kembali.
5. Uji tampilan pada lebar 320, 375, 390, 430, 768, 1024, 1280, dan 1440 px.

## 12. Security

| Area | Implementasi |
|---|---|
| Secret | `API_SECRET` hanya di `.env` (server) & Script Properties. Tidak pernah ada di HTML/JS. Perbandingan constant-time. |
| CSRF | Token session di semua POST (form & fetch `X-CSRF-Token`), otomatis di-refresh bila kedaluwarsa. |
| Session | Cookie `HttpOnly`, `SameSite=Lax`, `Secure` (HTTPS), strict mode, regenerasi ID saat login & berkala, timeout idle (default 30 menit), terikat User-Agent, dicabut jika akun dinonaktifkan/role berubah. |
| Admin | Password `password_hash` di `storage/admin/admins.json` (di luar web root), role-based permission di PHP **dan** Apps Script, lockout brute force, login/logout tercatat. |
| Rate limit | Per IP (file-based): submit, upload, finalize, cek status, login. |
| Header | CSP (nonce), `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, HSTS (HTTPS). |
| Upload | Whitelist ekstensi + MIME dari isi file (`finfo` & magic bytes di Apps Script), maks 5 MB, tolak `.php/.phtml/.js/.exe/.bat/.cmd/.sh` dll. termasuk double extension, `getimagesize` untuk gambar, nama file disanitasi. Folder `assets/` tidak bisa mengeksekusi PHP. |
| Dokumen | Drive privat. Diakses hanya via proxy admin berizin, `Cache-Control: no-store`, dan dicatat `ADMIN_VIEW`. Drive ID/URL tidak pernah dikirim ke browser. |
| Data publik | Cek status hanya menampilkan nomor, nama club, kategori, dan status. Halaman sukses hanya dari session hasil backend (bukan parameter URL). |
| Integritas | "Pendaftaran berhasil" hanya muncul setelah Apps Script memverifikasi semua dokumen wajib ada di Drive dan baris `CLUB` + `PEMAIN` tersimpan. Jika gagal, baris di-rollback, submission ditandai `FAILED`, dan dapat di-retry dengan token yang sama. |
| Anti duplikat | `submission_token` (idempotency key) + LockService. Club dengan nama & kategori sama serta NIK yang sudah aktif ditolak. |
| Output | Semua output di-escape (`htmlspecialchars`/`escapeHtml`). CSV dan Sheets dilindungi dari formula injection. |
| Error | Tanpa stack trace ke client. Log di `storage/logs` tanpa NIK/isi dokumen. |
| Draft | Autosave di perangkat (24 jam) **tanpa NIK** dan tanpa file. |

## 13. Troubleshooting

| Gejala | Penyebab & solusi |
|---|---|
| "Layanan pendaftaran belum dikonfigurasi" | `.env` belum berisi `GAMBASI_API_URL`/`GAMBASI_API_SECRET`, atau secret tidak sama dengan Script Property. |
| "Respons server pendaftaran tidak valid" | Deployment Web App tidak disetel *Anyone*, sehingga yang kembali adalah halaman login Google. Ulangi langkah 9. |
| "Ketentuan batas usia belum ditetapkan" | Isi `MIN/MAX_AGE_*` dan `AGE_REFERENCE_DATE` (Admin → Pengaturan). |
| "Ekstensi cURL PHP belum aktif" | Aktifkan `curl` di Laragon. Jika Apache dijalankan manual, pastikan folder PHP ada di `PATH`. |
| Upload gagal untuk file > 2 MB | `php_value` di `.htaccess` tidak terbaca (PHP-FPM/FastCGI). Gunakan `.user.ini` atau set di `php.ini`. |
| `BUSY` | Banyak pendaftaran bersamaan (LockService). Tunggu sebentar lalu klik **Coba Kirim Lagi**. Data aman. |
| `SSL certificate problem` | Perbarui `curl.cainfo` di `php.ini` dengan CA bundle terbaru. |
| 403 saat membuka situs | Pastikan `AllowOverride All` dan `mod_rewrite` aktif. |
| Admin terus logout | Timeout idle (`SESSION_TIMEOUT_MINUTES`), User-Agent berubah, atau folder `storage/sessions` tidak writable. |
| Log | `storage/logs/app-YYYY-MM.log`, `storage/logs/php-error.log`, dan **Executions** di editor Apps Script. |

## 14. Production deployment

1. Server Apache + PHP 8.1+ dengan **HTTPS** (Let's Encrypt). DocumentRoot = `public/`.
   - **Redirect HTTP → HTTPS sudah aktif otomatis** (301) lewat `.htaccess`; butuh `mod_rewrite`.
     Pengembangan lokal dikecualikan: `localhost`, `127.0.0.1`, `::1`, dan semua host `*.test`
     (mis. `http://gambasi.test` tetap terbuka tanpa redirect).
     Di belakang reverse proxy/CDN, redirect dilewati bila header `X-Forwarded-Proto: https` dikirim —
     isi juga `TRUSTED_PROXIES` di `.env` agar PHP mengenali koneksi sebagai HTTPS.
     Aturannya ada di `.htaccess` root (dijalankan sebelum rewrite ke `public/`, supaya URL redirect
     tidak berawalan `/public`) dan di `public/.htaccess` (untuk DocumentRoot yang langsung ke `public/`).
   - Jika DocumentRoot menunjuk ke `public/`, beri `AllowOverride None` pada folder induk project:
     ```apache
     <Directory "/path/ke/gambasi">
         AllowOverride None
         Require all denied
     </Directory>
     ```
     Tanpa ini Apache dapat membaca `.htaccess` induk tanpa izin override dan membalas HTTP 500
     (`<IfModule not allowed here`).
2. `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://domain-anda`, `TAILWIND_MODE=build`.
3. Build CSS tanpa Node memakai [Tailwind Standalone CLI v3](https://github.com/tailwindlabs/tailwindcss/releases) (simpan binary di `tools/bin/`):
   ```bash
   tools\bin\tailwindcss.exe -c tools/tailwind/tailwind.config.js -i tools/tailwind/input.css -o public/assets/css/tailwind.css --minify
   ```
   Setelah itu, CDN Tailwind tidak dimuat lagi.
4. Permission: `storage/` writable oleh user web server. `.env` dan `storage/admin/admins.json` hanya bisa dibaca server (mis. `chmod 640`).
5. Jika server berada di belakang reverse proxy/CDN, isi `TRUSTED_PROXIES` agar rate limit memakai IP asli.
6. Apps Script: `ENVIRONMENT=production`, buat `API_SECRET` baru khusus produksi, deploy versi baru, dan pasang trigger cleanup.
7. Jangan menyalin folder `tests/` dan `tools/bin/` ke server produksi (keduanya juga diblokir `.htaccess`).
8. Backup berkala: **File → Make a copy** spreadsheet dan folder Drive. Hapus data pribadi peserta sesuai kebijakan panitia setelah kegiatan selesai.

## 15. Referensi teknis

### Struktur folder

```
gambasi/
├── app/
│   ├── bootstrap.php
│   ├── config/config.php          # event master data, rules, role, rate limit
│   ├── helpers/  api.php · security.php · validation.php · response.php · auth.php · view.php
│   └── views/    header.php · footer.php · partials/ · admin/
├── public/                        # DocumentRoot
│   ├── index.php · daftar-sepakbola.php · cek-pendaftaran.php · sukses.php
│   ├── api/      csrf.php · submit-init.php · upload.php · submit-finalize.php
│   ├── admin/    login.php · logout.php · index.php · clubs.php · club.php · players.php
│   │             verification.php · documents.php · document.php · logs.php · settings.php
│   │             export.php · action.php
│   └── assets/   css/app.css · js/{app,landing,register,sukses,admin}.js · icons/
├── google-apps-script/   Code.gs · Config.gs · ClubService.gs · PlayerService.gs · FileService.gs
│                         Validation.gs · Response.gs · Utils.gs · Auth.gs · LockService.gs
│                         LogService.gs · appsscript.json
├── storage/              cache · ratelimit · logs · sessions · admin (tidak dapat diakses web)
├── tools/                create-admin.php · run-tests.bat · tailwind/
├── tests/                gas/ (fake Google + test) · php/ (integrasi) · dev/
├── .env.example · .gitignore · .htaccess · README.md
```

### Alur submission (transaction-like)

```
Browser                    PHP                          Apps Script
  │ submit-init (JSON) ──► validasi ──► submit_init ──► validasi penuh + cek duplikat
  │                                                     SUBMISSION(state=UPLOADING) + folder _PROSES_
  │ upload (per file) ───► finfo/ext/size ──► upload_file ─► magic bytes → Drive (privat) → verifikasi
  │                                                     files_json[slot] (replace = file lama di-trash)
  │ submit-finalize ─────► ─────────────► submit_finalize ─► [Lock] semua dokumen wajib ada?
  │                                                     nomor GMB-SB-2026-XXXX (sekali per token)
  │                                                     tulis PEMAIN + CLUB → verifikasi baca ulang
  │                                                     gagal → rollback baris, state=FAILED (retry aman)
  │ ◄── sukses + nomor ◄──────────────────────────── state=COMPLETE, payload sementara dihapus
```

Retry dengan token yang sama selalu aman: `init`/`finalize` pada submission yang sudah `COMPLETE` mengembalikan nomor yang sama (`already_submitted: true`).

### Format API

```json
{ "success": true,  "message": "Pendaftaran berhasil", "data": { "nomor_pendaftaran": "GMB-SB-2026-0001" } }
{ "success": false, "message": "Pendaftaran gagal", "error_code": "VALIDATION_ERROR", "errors": [{ "field": "players[0].nik", "message": "…" }] }
```

Kode error: `VALIDATION_ERROR, INVALID_CATEGORY, INVALID_AGE, INVALID_FILE, INVALID_MIME, FILE_TOO_LARGE, MISSING_FILES, INVALID_TOKEN, INVALID_SLOT, INVALID_STATE, DUPLICATE_CLUB, DUPLICATE_NIK, ALREADY_SUBMITTED, SUBMISSION_EXPIRED, REGISTRATION_CLOSED, NOT_FOUND, UNAUTHORIZED, FORBIDDEN, CSRF_ERROR, RATE_LIMITED, BUSY, DRIVE_ERROR, SHEETS_ERROR, CONFIG_ERROR, API_UNAVAILABLE, API_TIMEOUT, SERVER_ERROR`.

### Status

`PENDING` (default) → `REVIEW` → `REVISION` / `VERIFIED` / `REJECTED`. `REVISION` dan `REJECTED` wajib disertai catatan.

### Audit log

`CREATE_CLUB, ADD_PLAYER, UPLOAD_AKTE, UPLOAD_FOTO, UPLOAD_LOGO, SUBMIT, SUBMIT_FAILED, ADMIN_LOGIN, ADMIN_LOGIN_FAILED, ADMIN_LOGOUT, ADMIN_VIEW, ADMIN_UPDATE_STATUS, ADMIN_ADD_NOTE, ADMIN_EXPORT, ADMIN_UPDATE_SETTING, SUBMISSION_EXPIRED`

### Penyimpanan di Google Drive (akun `gambasipapsel@gmail.com`)

| Data | Lokasi di Drive akun resmi |
|---|---|
| Database (club, pemain, log) | Spreadsheet `GAMBASI_DATABASE_2026` |
| Akte kelahiran & foto full body | `GAMBASI PAPUA SELATAN 2026/CLUB/<nomor>_<club>/…` |
| Logo club | `GAMBASI PAPUA SELATAN 2026/LOGO_CLUB` |

- Semua file bersifat privat dan hanya bisa dibuka oleh pemilik akun, atau oleh admin melalui panel admin.
- **Admin → Pengaturan** menampilkan akun yang sedang dipakai Apps Script, tombol **Buka Google Drive**, dan peringatan jika akunnya tidak sesuai.
- **Kuota:** akun Gmail gratis mendapat 15 GB, dipakai bersama Gmail dan Google Photos. Satu pemain butuh maksimal sekitar 10 MB (akte + foto), jadi pantau kuota di https://drive.google.com/settings/storage.

**Tentang "menggantikan hosting":** Google Drive berfungsi sebagai **penyimpanan data dan dokumen**, sehingga tidak perlu database atau server file terpisah. Namun Google Drive **tidak bisa menjalankan website PHP** (fitur hosting web di Drive sudah dihentikan Google). Halaman website tetap perlu dijalankan di:

- Laragon (komputer panitia yang terhubung internet, bisa dibuka publik lewat `ngrok` yang sudah tersedia di Laragon), atau
- hosting PHP biasa (shared hosting/VPS), yang cukup paket kecil karena semua data berada di Google Drive.

### Biaya pendaftaran

Pendaftaran **GRATIS, tidak dipungut biaya** (`BIAYA_PENDAFTARAN`, default "GRATIS — tidak dipungut biaya"). Keterangan ini tampil di:

- badge hero dan CTA, kartu info, daftar persyaratan, dan FAQ (termasuk imbauan waspada penipuan);
- header formulir pendaftaran.

Jika suatu saat ada biaya, isi `BIAYA_PENDAFTARAN` dengan keterangan resmi. Badge "GRATIS" hanya tampil bila nilainya kosong atau mengandung kata "gratis".

### Email & media sosial resmi

| Kanal | Nilai | Variabel `.env` |
|---|---|---|
| Email | gambasipapsel@gmail.com | `PANITIA_EMAIL` |
| Facebook | https://www.facebook.com/profile.php?id=61593233879268 | `SOCIAL_FACEBOOK` |
| Instagram | https://www.instagram.com/gambasipapuaselatan | `SOCIAL_INSTAGRAM` |
| TikTok | https://www.tiktok.com/@gambasipapuaselatan | `SOCIAL_TIKTOK` |

Nilai di atas menjadi default jika variabel dikosongkan. Kanal ini ditampilkan di footer (ikon dan daftar kontak), di landing page (kotak "Ikuti informasi resmi" dan FAQ), serta di halaman sukses.

### Logo & warna

Logo resmi disimpan di `public/assets/images/logo-gambasi(.png|.webp)` dan `logo-gambasi-sm(.png|.webp)`, dengan ikon di `public/assets/icons/` (favicon 32/64, apple-touch-icon, icon-512).
Jika logo diperbarui, buat ulang semua aset dengan:

```bash
php tools/build-logo.php "C:\path\LOGO GAMBASI.png"
```

Palet warna mengikuti logo:

| Token Tailwind | Warna | Asal di logo |
|---|---|---|
| `royal-*` | biru (utama) | sayap kanan |
| `flame-*` | merah | sayap kiri |
| `gold-*` | emas (CTA, judul) | perisai & tulisan |

Garis tiga warna `.brand-stripe` (merah-emas-biru) dipakai di header, footer, CTA, dan sidebar admin.

### WhatsApp admin / penyelenggara

Nomor resmi **0823-4532-8926** (`PANITIA_WHATSAPP`) terhubung melalui link `wa.me` dengan pesan pembuka otomatis di:

- tombol melayang **Tanya Admin** di semua halaman publik, serta footer;
- landing page (FAQ, kotak "Masih ada pertanyaan?", dan CTA);
- formulir pendaftaran (kotak bantuan, plus tombol **Laporkan ke Admin** saat pengiriman gagal). Kategori, nama club, dan kode error disertakan otomatis;
- halaman sukses (menyertakan nomor pendaftaran, club, dan kategori);
- cek pendaftaran (menyertakan nomor dan status; ditonjolkan untuk status REVISION/REJECTED).

Pesan tidak pernah memuat NIK atau data anak/orang tua.

### Data yang belum ditetapkan (tidak dikarang)

Hadiah, jumlah pemain/official resmi, batas usia, tanggal cutoff, regulasi pertandingan, kontak panitia, rekening, sponsor, dan nomor WhatsApp **tidak** diisi oleh sistem. Semuanya disediakan sebagai konfigurasi (`.env` atau Script Properties/Admin → Pengaturan) dan ditampilkan sebagai "Akan diumumkan panitia" selama masih kosong.
# FESTIVAL
