<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$pub = gas_public_config();
$available = !empty($pub['available']);
$open = $available && !empty($pub['registration_open']);
$rulesReady = $available && !empty($pub['age_rules']['U10']) && !empty($pub['age_rules']['U12']);
$canSubmit = $open && $rulesReady;
$preKategori = in_array($_GET['kategori'] ?? '', ['U10', 'U12'], true) ? $_GET['kategori'] : '';

$clientConfig = [
    'kategori'   => $preKategori,
    'canSubmit'  => $canSubmit,
    'ageRules'   => $pub['age_rules'] ?? ['U10' => null, 'U12' => null],
    'players'    => $pub['players'] ?? ['min' => 1, 'max' => 40],
    'maxBytes'   => (int) config('upload.max_bytes'),
    'endpoints'  => [
        'init'     => url('/api/submit-init.php'),
        'upload'   => url('/api/upload.php'),
        'finalize' => url('/api/submit-finalize.php'),
        'csrf'     => url('/api/csrf.php'),
        'success'  => url('/sukses.php'),
    ],
    'positions'  => GAMBASI_POSITIONS,
    'genders'    => GAMBASI_GENDERS,
    'today'      => date('Y-m-d'),
];

$steps = ['Club', 'Official', 'Pemain', 'Dokumen', 'Review', 'Kirim'];

render_view('header', ['title' => 'Pendaftaran Club Sepak Bola — GAMBASI Papua Selatan 2026', 'active' => 'daftar', 'bodyClass' => 'has-sticky-actions']);
?>
<main id="main" class="pb-32 pt-16 lg:pb-16">
  <section class="relative overflow-hidden bg-royal-950 text-white">
    <div class="hero-pitch absolute inset-0 opacity-70" aria-hidden="true"></div>
    <div class="relative mx-auto flex max-w-5xl items-center gap-5 px-4 py-10 sm:px-6 sm:py-14">
      <div class="min-w-0 flex-1">
        <p class="text-xs font-bold uppercase tracking-[0.2em] text-gold-400"><?= e(config('event.kompetisi')) ?></p>
        <h1 class="mt-2 font-display text-4xl font-extrabold uppercase leading-none sm:text-6xl">Pendaftaran Club</h1>
        <p class="mt-3 max-w-2xl text-white/75">Festival Sepak Bola Usia Dini U-10 &amp; U-12 · <?= e(config('event.tanggal')) ?> · <?= e(config('event.lokasi')) ?></p>
        <?php if (is_registration_free()): ?><div class="mt-4"><?= free_badge('dark') ?></div><?php endif; ?>
      </div>
      <?= logo_img('hero-logo hidden h-36 w-auto shrink-0 sm:block', 'sm', 'Logo GAMBASI', true) ?>
    </div>
  </section>

  <div class="mx-auto max-w-5xl px-4 sm:px-6">
    <?php if (!$available): ?>
      <div class="notice notice-error mt-6" role="alert">
        <strong>Layanan pendaftaran belum dapat diakses.</strong>
        Silakan coba beberapa saat lagi atau hubungi panitia. Anda tetap dapat mengisi formulir sebagai draft.
      </div>
    <?php elseif (!$open): ?>
      <div class="notice notice-error mt-6" role="alert">
        <strong>Pendaftaran sedang ditutup.</strong> Pendaftaran dibuka <?= e(info_or_tba('pendaftaran_buka')) ?> dan ditutup <?= e(info_or_tba('pendaftaran_tutup')) ?>. Pantau informasi resmi dari panitia.
      </div>
    <?php elseif (!$rulesReady): ?>
      <div class="notice notice-warn mt-6" role="alert">
        <strong>Ketentuan batas usia belum ditetapkan panitia.</strong>
        Formulir dapat diisi sebagai draft, namun pengiriman akan dibuka setelah ketentuan usia diumumkan.
      </div>
    <?php endif; ?>

    <!-- Progress -->
    <nav class="sticky top-16 z-30 -mx-4 mt-6 bg-slate-50/95 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-2xl sm:px-0" aria-label="Langkah pendaftaran">
      <ol id="stepper" class="flex items-center gap-1 sm:gap-2">
        <?php foreach ($steps as $i => $label): ?>
          <li class="step-item flex flex-1 flex-col items-center gap-1" data-step="<?= $i + 1 ?>">
            <span class="step-dot flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold sm:h-10 sm:w-10"><?= $i + 1 ?></span>
            <span class="step-label hidden text-[11px] font-semibold uppercase tracking-wide sm:block"><?= e($label) ?></span>
          </li>
        <?php endforeach; ?>
      </ol>
      <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200" aria-hidden="true">
        <div id="step-progress" class="h-full rounded-full bg-gradient-to-r from-flame-500 via-gold-500 to-royal-600 transition-all duration-500" style="width:0%"></div>
      </div>
      <p id="step-announcer" class="sr-only" aria-live="polite"></p>
    </nav>

    <div class="mt-4 flex flex-col gap-3 rounded-2xl bg-white p-4 ring-1 ring-[#25D366]/40 sm:flex-row sm:items-center sm:justify-between">
      <p class="text-sm text-slate-700">
        <strong class="text-slate-900">Butuh bantuan saat mengisi formulir?</strong><br class="sm:hidden">
        Tanya langsung admin/penyelenggara via WhatsApp <strong><?= e(admin_contacts_text()) ?></strong>.
      </p>
      <?php render_view('partials/wa-button', ['label' => 'Tanya Admin', 'variant' => 'outline', 'id' => 'ask-admin']); ?>
    </div>

    <div id="draft-banner" class="notice notice-info mt-4 hidden" role="status">
      <span id="draft-text">Draft tersimpan di perangkat ini.</span>
      <button type="button" id="draft-clear" class="ml-2 font-semibold underline">Hapus draft</button>
    </div>

    <form id="reg-form" class="mt-6" novalidate autocomplete="on">
      <!-- ============ STEP 1: KATEGORI + CLUB ============ -->
      <section class="form-step" data-step="1" aria-labelledby="s1-title">
        <div class="form-card">
          <h2 id="s1-title" class="form-card-title">1. Kategori &amp; Data Club</h2>

          <fieldset class="mt-6">
            <legend class="field-label">Kategori <span class="req">*</span></legend>
            <div class="mt-2 grid grid-cols-2 gap-3" role="radiogroup">
              <?php foreach (['U10' => 'U-10', 'U12' => 'U-12'] as $code => $label): $rule = $pub['age_rules'][$code] ?? null; ?>
                <label class="cat-option">
                  <input type="radio" name="kategori" value="<?= e($code) ?>" class="peer sr-only" required>
                  <span class="cat-box">
                    <span class="font-display text-4xl font-extrabold sm:text-5xl"><?= e($label) ?></span>
                    <span class="mt-1 block text-xs text-slate-500">
                      <?= $rule ? e(sprintf('Usia %d–%d th (per %s)', $rule['min'], $rule['max'], format_date_id((string) $rule['reference_date']))) : 'Sesuai ketentuan panitia' ?>
                    </span>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>
            <p class="field-error" id="err-kategori" hidden></p>
            <p class="mt-3 text-sm text-slate-600"><strong class="text-slate-900">Khusus U-10:</strong> pertandingan dapat diikuti oleh club maupun sekolah dasar yang mendaftar.</p>
          </fieldset>

          <div class="mt-6 grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
              <label for="club-nama_club" class="field-label">Nama Club <span class="req">*</span></label>
              <input id="club-nama_club" data-group="club" data-name="nama_club" class="field-input" maxlength="100" required autocomplete="organization" placeholder="Contoh: SSB Garuda Merauke (U-10 juga dapat: SD Negeri 1 Merauke)">
              <p class="field-error" hidden></p>
            </div>
            <div class="sm:col-span-2">
              <label for="club-alamat" class="field-label">Alamat <span class="req">*</span></label>
              <textarea id="club-alamat" data-group="club" data-name="alamat" class="field-input" rows="2" maxlength="250" required autocomplete="street-address"></textarea>
              <p class="field-error" hidden></p>
            </div>
            <div>
              <label for="club-kampung" class="field-label">Kampung/Kelurahan</label>
              <input id="club-kampung" data-group="club" data-name="kampung" class="field-input" maxlength="100">
              <p class="field-error" hidden></p>
            </div>
            <div>
              <label for="club-distrik" class="field-label">Distrik <span class="req">*</span></label>
              <input id="club-distrik" data-group="club" data-name="distrik" class="field-input" maxlength="100" required>
              <p class="field-error" hidden></p>
            </div>
            <div>
              <label for="club-kabupaten" class="field-label">Kabupaten <span class="req">*</span></label>
              <input id="club-kabupaten" data-group="club" data-name="kabupaten" class="field-input" maxlength="100" required value="Merauke">
              <p class="field-error" hidden></p>
            </div>
            <div>
              <label for="club-provinsi" class="field-label">Provinsi <span class="req">*</span></label>
              <input id="club-provinsi" data-group="club" data-name="provinsi" class="field-input" maxlength="100" required value="Papua Selatan">
              <p class="field-error" hidden></p>
            </div>
          </div>

          <div class="mt-6">
            <span class="field-label">Logo Club <span class="text-xs font-normal text-slate-500">(opsional · JPG/PNG · maks 5 MB)</span></span>
            <div class="upload-box mt-2" id="doc-logo" data-slot="logo" data-kind="logo"></div>
          </div>
        </div>
      </section>

      <!-- ============ STEP 2: OFFICIAL ============ -->
      <section class="form-step" data-step="2" aria-labelledby="s2-title" hidden>
        <div class="form-card">
          <h2 id="s2-title" class="form-card-title">2. Data Official</h2>
          <div class="mt-6 grid gap-5 sm:grid-cols-2">
            <div>
              <label for="official-nama_manager" class="field-label">Nama Manager <span class="req">*</span></label>
              <input id="official-nama_manager" data-group="official" data-name="nama_manager" class="field-input" maxlength="100" required autocomplete="name">
              <p class="field-error" hidden></p>
            </div>
            <div>
              <label for="official-nama_pelatih" class="field-label">Nama Pelatih <span class="req">*</span></label>
              <input id="official-nama_pelatih" data-group="official" data-name="nama_pelatih" class="field-input" maxlength="100" required>
              <p class="field-error" hidden></p>
            </div>
            <div>
              <label for="official-whatsapp" class="field-label">Nomor WhatsApp <span class="req">*</span></label>
              <input id="official-whatsapp" data-group="official" data-name="whatsapp" class="field-input" type="tel" inputmode="tel" maxlength="16" required autocomplete="tel" placeholder="08xxxxxxxxxx">
              <p class="field-hint">Nomor aktif untuk dihubungi panitia.</p>
              <p class="field-error" hidden></p>
            </div>
            <div>
              <label for="official-email" class="field-label">Email</label>
              <input id="official-email" data-group="official" data-name="email" class="field-input" type="email" inputmode="email" maxlength="120" autocomplete="email" placeholder="nama@email.com">
              <p class="field-error" hidden></p>
            </div>
          </div>
        </div>
      </section>

      <!-- ============ STEP 3: PEMAIN ============ -->
      <section class="form-step" data-step="3" aria-labelledby="s3-title" hidden>
        <div class="form-card">
          <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
              <h2 id="s3-title" class="form-card-title">3. Data Pemain</h2>
              <p class="mt-1 text-sm text-slate-600"><span id="player-count">0</span> pemain · <span id="player-limit"></span></p>
            </div>
          </div>
          <p class="notice notice-info mt-4 text-sm">NIK tidak disimpan di draft perangkat demi keamanan. Jika halaman dimuat ulang, NIK perlu diisi kembali.</p>
          <div id="players" class="mt-6 space-y-4"></div>
          <p class="field-error mt-3" id="err-players" hidden></p>
          <button type="button" id="add-player" class="mt-5 flex w-full items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-royal-400 bg-royal-50 px-4 py-4 font-display text-lg font-bold uppercase tracking-wider text-royal-800 transition hover:bg-royal-100 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-royal-300">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            Tambah Pemain
          </button>
        </div>
      </section>

      <!-- ============ STEP 4: DOKUMEN ============ -->
      <section class="form-step" data-step="4" aria-labelledby="s4-title" hidden>
        <div class="form-card">
          <h2 id="s4-title" class="form-card-title">4. Dokumen Pemain</h2>
          <ul class="mt-3 space-y-1 text-sm text-slate-600">
            <li><strong>Akte kelahiran asli:</strong> PDF, JPG, JPEG, PNG · maks 5 MB</li>
            <li><strong>Foto full body:</strong> JPG, JPEG, PNG · maks 5 MB</li>
          </ul>
          <p class="mt-3 rounded-xl bg-gold-300/30 px-4 py-3 text-sm font-semibold text-amber-900">Pemain wajib mengunggah akte kelahiran yang asli. Dokumen yang tidak berwarna (hitam-putih) dianggap bukan asli.</p>
          <p class="mt-3 rounded-xl bg-gold-300/30 px-4 py-3 text-sm font-semibold text-amber-900">Foto wajib memperlihatkan pemain secara penuh dari kepala sampai kaki.</p>
          <div id="documents" class="mt-6 space-y-4"></div>
        </div>
      </section>

      <!-- ============ STEP 5: REVIEW ============ -->
      <section class="form-step" data-step="5" aria-labelledby="s5-title" hidden>
        <div class="form-card">
          <h2 id="s5-title" class="form-card-title">5. Review Pendaftaran</h2>
          <p class="mt-1 text-sm text-slate-600">Periksa kembali seluruh data sebelum dikirim.</p>
          <div id="review" class="mt-6 space-y-6"></div>
          <label class="mt-6 flex gap-3 rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
            <input type="checkbox" id="agree" class="mt-1 h-5 w-5 shrink-0 rounded border-slate-300 text-royal-700 focus:ring-royal-500">
            <span class="text-sm text-slate-700">Saya menyatakan data dan dokumen yang diisi adalah benar, serta telah mendapat persetujuan orang tua/wali pemain untuk digunakan panitia sebagai keperluan administrasi <?= e(config('event.nama')) ?>.</span>
          </label>
          <p class="field-error" id="err-agree" hidden></p>
        </div>
      </section>

      <!-- ============ STEP 6: SUBMIT ============ -->
      <section class="form-step" data-step="6" aria-labelledby="s6-title" hidden>
        <div class="form-card">
          <h2 id="s6-title" class="form-card-title">6. Mengirim Pendaftaran</h2>
          <p class="mt-1 text-sm text-slate-600">Jangan tutup halaman ini sampai proses selesai.</p>
          <ol id="submit-steps" class="mt-6 space-y-3">
            <li data-phase="init" class="submit-phase"><span class="phase-icon"></span><span>Memvalidasi data pendaftaran</span></li>
            <li data-phase="upload" class="submit-phase"><span class="phase-icon"></span><span>Mengunggah dokumen <span id="upload-counter" class="font-semibold"></span></span></li>
            <li data-phase="finalize" class="submit-phase"><span class="phase-icon"></span><span>Menyimpan data &amp; membuat nomor pendaftaran</span></li>
          </ol>
          <div class="mt-6 h-3 overflow-hidden rounded-full bg-slate-200" role="progressbar" aria-label="Progres pengiriman" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="submit-progressbar">
            <div id="submit-progress" class="h-full rounded-full bg-gradient-to-r from-flame-500 via-gold-500 to-royal-600 transition-all duration-300" style="width:0%"></div>
          </div>
          <div id="submit-error" class="notice notice-error mt-6" role="alert" hidden>
            <p id="submit-error-text" class="font-semibold"></p>
            <ul id="submit-error-list" class="mt-2 list-disc space-y-1 pl-5 text-sm"></ul>
            <div class="mt-4 flex flex-wrap gap-2">
              <button type="button" id="submit-retry" class="btn-primary">Coba Kirim Lagi</button>
              <button type="button" id="submit-back" class="btn-secondary">Perbaiki Data</button>
              <?php render_view('partials/wa-button', ['label' => 'Laporkan ke Admin', 'variant' => 'outline', 'id' => 'ask-admin-error']); ?>
            </div>
          </div>
        </div>
      </section>

      <!-- Actions (sticky di mobile) -->
      <div id="form-actions" class="sticky-actions">
        <div class="mx-auto flex max-w-5xl items-center gap-3">
          <button type="button" id="btn-prev" class="btn-secondary flex-1 sm:flex-none" hidden>Kembali</button>
          <span class="hidden flex-1 text-sm text-slate-500 sm:block" id="save-state" aria-live="polite"></span>
          <button type="button" id="btn-next" class="btn-primary flex-1 sm:flex-none">Lanjut</button>
          <button type="button" id="btn-submit" class="btn-primary flex-1 sm:flex-none" hidden <?= $canSubmit ? '' : 'disabled aria-disabled="true"' ?>>Kirim Pendaftaran</button>
        </div>
      </div>
    </form>
  </div>

  <template id="tpl-player">
    <article class="player-card rounded-2xl bg-slate-50 ring-1 ring-slate-200" data-ref="">
      <header class="flex items-center gap-3 p-4">
        <span class="player-no flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-royal-700 font-display text-lg font-bold text-white"></span>
        <button type="button" class="player-toggle min-w-0 flex-1 text-left" aria-expanded="true">
          <span class="player-title block truncate font-semibold text-slate-900">Pemain baru</span>
          <span class="player-sub block truncate text-xs text-slate-500">Lengkapi data pemain</span>
        </button>
        <button type="button" class="player-remove rounded-lg p-2 text-rose-600 hover:bg-rose-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-400" aria-label="Hapus pemain">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg>
        </button>
      </header>
      <div class="player-body grid gap-4 border-t border-slate-200 p-4 sm:grid-cols-2">
        <div class="sm:col-span-2"><label class="field-label" data-for="nama_lengkap">Nama Lengkap <span class="req">*</span></label><input class="field-input" data-name="nama_lengkap" maxlength="100" required><p class="field-error" hidden></p></div>
        <div><label class="field-label" data-for="nik">NIK <span class="req">*</span></label><input class="field-input font-mono" data-name="nik" inputmode="numeric" maxlength="16" pattern="\d{16}" required autocomplete="off"><p class="field-hint">16 digit sesuai Kartu Keluarga.</p><p class="field-error" hidden></p></div>
        <div><label class="field-label" data-for="tempat_lahir">Tempat Lahir <span class="req">*</span></label><input class="field-input" data-name="tempat_lahir" maxlength="80" required><p class="field-error" hidden></p></div>
        <div><label class="field-label" data-for="tanggal_lahir">Tanggal Lahir <span class="req">*</span></label><input class="field-input" type="date" data-name="tanggal_lahir" required><p class="field-hint age-hint"></p><p class="field-error" hidden></p></div>
        <div><label class="field-label" data-for="jenis_kelamin">Jenis Kelamin <span class="req">*</span></label><select class="field-input" data-name="jenis_kelamin" required><option value="">Pilih</option></select><p class="field-error" hidden></p></div>
        <div><label class="field-label" data-for="posisi">Posisi <span class="req">*</span></label><select class="field-input" data-name="posisi" required><option value="">Pilih</option></select><p class="field-error" hidden></p></div>
        <div><label class="field-label" data-for="nomor_punggung">Nomor Punggung <span class="req">*</span></label><input class="field-input" type="number" data-name="nomor_punggung" min="1" max="99" inputmode="numeric" required><p class="field-error" hidden></p></div>
        <div><label class="field-label" data-for="nama_ayah">Nama Ayah</label><input class="field-input" data-name="nama_ayah" maxlength="100"><p class="field-error" hidden></p></div>
        <div><label class="field-label" data-for="nama_ibu">Nama Ibu</label><input class="field-input" data-name="nama_ibu" maxlength="100"><p class="field-error" hidden></p></div>
        <div><label class="field-label" data-for="nama_wali">Nama Wali</label><input class="field-input" data-name="nama_wali" maxlength="100"><p class="field-hint">Isi minimal salah satu: ayah, ibu, atau wali.</p><p class="field-error" hidden></p></div>
        <div><label class="field-label" data-for="whatsapp_wali">WhatsApp Orang Tua/Wali <span class="req">*</span></label><input class="field-input" type="tel" data-name="whatsapp_wali" inputmode="tel" maxlength="16" required placeholder="08xxxxxxxxxx"><p class="field-error" hidden></p></div>
        <div class="sm:col-span-2"><label class="field-label" data-for="alamat">Alamat <span class="req">*</span></label><textarea class="field-input" data-name="alamat" rows="2" maxlength="250" required></textarea><p class="field-error" hidden></p></div>
      </div>
    </article>
  </template>

  <script type="application/json" id="reg-config"><?= json_encode($clientConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
</main>
<?php render_view('footer', ['scripts' => ['js/register.js']]); ?>
