<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$ev = config('event');
$pub = gas_public_config();
$regOpen = !empty($pub['available']) && !empty($pub['registration_open']);

function age_rule_text(?array $rule): string
{
    if (!$rule) {
        return 'Batas usia sesuai ketentuan resmi panitia.';
    }
    return sprintf('Usia %d–%d tahun, dihitung per %s.', $rule['min'], $rule['max'], format_date_id((string) $rule['reference_date']));
}

$steps = [
    ['Pilih Kategori & Data Club', 'Pilih kategori U-10 atau U-12, lalu isi identitas dan alamat club.'],
    ['Data Official', 'Isi nama manager, pelatih, serta nomor WhatsApp yang aktif.'],
    ['Data Pemain', 'Tambahkan pemain satu per satu lengkap dengan NIK, tanggal lahir, dan data orang tua/wali.'],
    ['Unggah Dokumen', 'Unggah akte kelahiran asli (berwarna) dan foto full body untuk setiap pemain.'],
    ['Review & Kirim', 'Periksa kembali seluruh data, lalu kirim pendaftaran.'],
    ['Nomor Pendaftaran', 'Simpan nomor pendaftaran (GMB-SB-2026-xxxx) dan pantau status verifikasi.'],
];

$tempatMeeting = info_or_tba('tempat_meeting');

$timeline = [
    ['Pendaftaran Online', has_info('jadwal_pendaftaran') ? (string) config('info.jadwal_pendaftaran') : ($regOpen ? 'Sedang dibuka' : 'Akan diumumkan panitia'),
        sprintf('Dibuka %s, ditutup %s.', info_or_tba('pendaftaran_buka'), info_or_tba('pendaftaran_tutup'))],
    ['Verifikasi Dokumen', info_or_tba('jadwal_verifikasi'), 'Panitia memeriksa data club, pemain, akte kelahiran asli, dan foto.'],
    ['Pra Meeting', info_or_tba('pra_meeting'), 'Bertempat di ' . $tempatMeeting . '.'],
    ['Technical Meeting', info_or_tba('technical_meeting'), 'Informasi teknis pelaksanaan festival bagi official club. Bertempat di ' . $tempatMeeting . '.'],
    ['Kick Off', $ev['kick_off'], $ev['lokasi']],
    ['Festival GAMBASI', $ev['tanggal'], $ev['lokasi']],
];

$faqs = [
    ['Kapan pendaftaran dibuka dan ditutup?', 'Pendaftaran dibuka pada ' . info_or_tba('pendaftaran_buka') . ' dan ditutup pada ' . info_or_tba('pendaftaran_tutup') . '.'],
    ['Siapa yang dapat mendaftar?', 'Club/SSB sepak bola usia dini yang memiliki pemain sesuai kategori U-10 atau U-12 dan memenuhi ketentuan panitia. Khusus kategori U-10, pertandingan dapat diikuti oleh club maupun sekolah dasar yang mendaftar.'],
    ['Kapan Pra Meeting, Technical Meeting, dan Kick Off?', 'Pra Meeting: ' . info_or_tba('pra_meeting') . ' · Technical Meeting: ' . info_or_tba('technical_meeting') . ' (keduanya di ' . $tempatMeeting . ') · Kick Off: ' . $ev['kick_off'] . '.'],
    ['Apakah satu club boleh mendaftar di dua kategori?', 'Ya. Lakukan pendaftaran terpisah untuk kategori U-10 dan U-12. Setiap pendaftaran mendapat nomor pendaftaran sendiri.'],
    ['Dokumen apa saja yang wajib diunggah?', 'Setiap pemain wajib mengunggah akte kelahiran asli (PDF/JPG/PNG) dan foto full body (JPG/PNG), masing-masing maksimal 5 MB. Akte kelahiran asli berwarna; dokumen yang tidak berwarna (hitam-putih) dianggap bukan asli. Logo club bersifat opsional.'],
    ['Bagaimana ketentuan foto full body?', 'Foto wajib memperlihatkan pemain secara penuh dari kepala sampai kaki, jelas, dan tidak buram. Foto hanya digunakan untuk kebutuhan administrasi peserta.'],
    ['Bagaimana cara mengecek status pendaftaran?', 'Buka menu Cek Pendaftaran dan masukkan nomor pendaftaran Anda, contoh: GMB-SB-2026-0001.'],
    ['Apa arti status pendaftaran?', 'PENDING: menunggu diperiksa · REVIEW: sedang diperiksa · REVISION: perlu perbaikan · VERIFIED: terverifikasi · REJECTED: tidak memenuhi ketentuan.'],
    ['Apakah data anak aman?', 'NIK, akte kelahiran, foto, dan data orang tua disimpan secara privat dan hanya dapat diakses panitia yang berwenang. Data tersebut tidak ditampilkan di halaman publik.'],
    ['Berapa biaya pendaftaran?', is_registration_free()
        ? 'Pendaftaran GRATIS, tidak dipungut biaya apa pun. Waspadai pihak yang meminta transfer atau pembayaran atas nama GAMBASI Papua Selatan, dan laporkan ke admin via WhatsApp ' . admin_whatsapp_display() . '.'
        : (string) config('info.biaya_pendaftaran')],
    ['Saya salah mengisi data setelah mengirim, bagaimana?', 'Hubungi admin melalui WhatsApp ' . admin_whatsapp_display() . ' dengan menyebutkan nomor pendaftaran. Panitia dapat memberi status REVISION beserta catatan perbaikan.'],
    ['Ke mana saya bisa bertanya?', 'Hubungi admin/penyelenggara melalui WhatsApp ' . admin_contacts_text() . ' atau email ' . config('info.kontak_email') . ', atau tekan tombol "Tanya Admin" yang ada di setiap halaman.'],
    ['Di mana saya bisa mendapat informasi terbaru?', 'Ikuti media sosial resmi: Facebook "GAMBASI Papua Selatan", Instagram @gambasipapuaselatan, dan TikTok @gambasipapuaselatan.'],
];

render_view('header', ['title' => 'GAMBASI Papua Selatan 2026 — Festival Sepak Bola Usia Dini U-10 & U-12', 'active' => 'home']);
?>
<main id="main">
  <!-- ================= HERO ================= -->
  <section class="hero relative isolate overflow-hidden bg-royal-950 pt-16 text-white" aria-labelledby="hero-title">
    <div class="hero-pitch absolute inset-0 -z-10" aria-hidden="true"></div>
    <div class="absolute inset-0 -z-10 bg-gradient-to-b from-royal-950/30 via-royal-950/60 to-royal-950" aria-hidden="true"></div>

    <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 pb-20 pt-14 sm:px-6 sm:pt-20 lg:grid-cols-12 lg:px-8 lg:pb-28">
      <div class="lg:col-span-7">
        <div class="mb-6 flex justify-center lg:hidden">
          <?= logo_img('hero-logo h-48 w-auto sm:h-60', 'lg', 'Logo GAMBASI', true) ?>
        </div>
        <p class="inline-flex items-center gap-2 rounded-full border border-gold-400/40 bg-gold-500/10 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-gold-300 sm:text-xs">
          <span class="h-1.5 w-1.5 rounded-full bg-gold-400" aria-hidden="true"></span>
          <?= e($ev['kompetisi']) ?>
        </p>
        <?php if (is_registration_free()): ?><div class="mt-3"><?= free_badge('dark') ?></div><?php endif; ?>
        <h1 id="hero-title" class="mt-6 font-display font-extrabold uppercase leading-[0.85] tracking-tight">
          <span class="text-gold-gradient block text-[4.5rem] sm:text-[7rem] lg:text-[9rem]">GAMBASI</span>
          <span class="block text-[2.1rem] text-white sm:text-6xl lg:text-7xl">Papua Selatan</span>
        </h1>
        <p class="mt-6 max-w-xl font-display text-2xl font-bold uppercase tracking-wide text-white sm:text-3xl">
          Festival Sepak Bola Usia Dini <span class="whitespace-nowrap text-royal-300">U-10 &amp; U-12</span>
        </p>
        <p class="mt-4 max-w-xl text-base text-white/75 sm:text-lg">“<?= e($ev['tema']) ?>”</p>

        <ul class="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
          <li class="flex items-center gap-3 rounded-2xl bg-white/5 px-4 py-3 ring-1 ring-white/10">
            <svg class="h-6 w-6 text-gold-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>
            <span class="text-sm font-semibold sm:text-base">30 Oktober – 1 November 2026</span>
          </li>
          <li class="flex items-center gap-3 rounded-2xl bg-white/5 px-4 py-3 ring-1 ring-white/10">
            <svg class="h-6 w-6 text-gold-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7-6.2-7-11a7 7 0 1114 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
            <span class="text-sm font-semibold sm:text-base">Lapangan Kodim Merauke</span>
          </li>
          <li class="flex items-center gap-3 rounded-2xl bg-white/5 px-4 py-3 ring-1 ring-white/10">
            <svg class="h-6 w-6 text-gold-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
            <span class="text-sm font-semibold sm:text-base">Kick Off 30 Oktober · 14.00 WIT</span>
          </li>
        </ul>

        <div class="mt-10 flex flex-col gap-3 sm:flex-row">
          <a href="<?= e(url('/daftar-sepakbola.php')) ?>" class="btn-glow inline-flex items-center justify-center gap-2 rounded-2xl bg-gold-500 px-7 py-4 text-base font-extrabold uppercase tracking-wider text-ink-950 transition hover:-translate-y-0.5 hover:bg-gold-400 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-gold-300/60">
            Daftarkan Club
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
          </a>
          <a href="#persyaratan" class="inline-flex items-center justify-center rounded-2xl border border-white/25 px-7 py-4 text-base font-bold uppercase tracking-wider text-white transition hover:bg-white/10 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/30">
            Lihat Persyaratan
          </a>
        </div>

        <p class="mt-5 flex items-center gap-2 text-sm text-white/70">
          <?php if ($regOpen): ?>
            <span class="relative flex h-2.5 w-2.5"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-400"></span></span>
            Pendaftaran online sedang dibuka
          <?php elseif (!empty($pub['available']) && ($pub['registration_window'] ?? '') === 'before'): ?>
            <span class="h-2.5 w-2.5 rounded-full bg-gold-400" aria-hidden="true"></span> Pendaftaran online dibuka <?= e(info_or_tba('pendaftaran_buka')) ?>
          <?php elseif (!empty($pub['available']) && ($pub['registration_window'] ?? '') === 'after'): ?>
            <span class="h-2.5 w-2.5 rounded-full bg-rose-400" aria-hidden="true"></span> Pendaftaran online sudah ditutup
          <?php elseif (!empty($pub['available'])): ?>
            <span class="h-2.5 w-2.5 rounded-full bg-rose-400" aria-hidden="true"></span> Pendaftaran online sedang ditutup
          <?php else: ?>
            <span class="h-2.5 w-2.5 rounded-full bg-slate-400" aria-hidden="true"></span> Status pendaftaran akan diumumkan panitia
          <?php endif; ?>
        </p>
      </div>

      <div class="lg:col-span-5">
        <div class="mb-6 hidden justify-center lg:flex">
          <?= logo_img('hero-logo h-72 w-auto xl:h-80', 'lg', 'Logo GAMBASI', true) ?>
        </div>
        <div class="relative mx-auto max-w-md rounded-[2rem] bg-gradient-to-br from-white/10 to-white/[0.02] p-6 ring-1 ring-white/15 backdrop-blur sm:p-8">
          <p class="text-xs font-bold uppercase tracking-[0.2em] text-gold-400">Menuju Kick-off</p>
          <div id="countdown" class="mt-4 grid grid-cols-4 gap-2 text-center" data-target="<?= e($ev['kick_off_iso']) ?>" aria-live="off">
            <?php foreach (['Hari', 'Jam', 'Menit', 'Detik'] as $unit): ?>
              <div class="rounded-2xl bg-royal-950/60 px-1 py-3 ring-1 ring-white/10">
                <span class="block font-display text-3xl font-extrabold tabular-nums sm:text-4xl" data-unit="<?= e(strtolower($unit)) ?>">--</span>
                <span class="text-[10px] font-semibold uppercase tracking-widest text-white/60 sm:text-xs"><?= e($unit) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="mt-6 grid grid-cols-2 gap-3">
            <?php foreach (['U10' => 'U-10', 'U12' => 'U-12'] as $code => $label): ?>
              <div class="rounded-2xl bg-white p-4 text-ink-950">
                <p class="text-[11px] font-bold uppercase tracking-widest text-royal-700">Kategori</p>
                <p class="font-display text-4xl font-extrabold"><?= e($label) ?></p>
              </div>
            <?php endforeach; ?>
          </div>
          <p class="mt-6 border-t border-white/10 pt-4 text-sm text-white/70"><?= e($ev['wilayah']) ?></p>
        </div>
      </div>
    </div>
  </section>

  <!-- ================= TENTANG ================= -->
  <section id="tentang" class="scroll-mt-20 py-20 sm:py-24" aria-labelledby="tentang-title">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="grid gap-12 lg:grid-cols-2 lg:items-center">
        <div>
          <p class="section-kicker">Tentang GAMBASI</p>
          <h2 id="tentang-title" class="section-title">Festival sepak bola untuk generasi muda Papua Selatan</h2>
          <p class="mt-5 text-lg leading-relaxed text-slate-600">
            <strong class="text-slate-900">GAMBASI Papua Selatan</strong> menghadirkan Festival Sepak Bola Usia Dini untuk kategori
            <strong class="text-slate-900">U-10 dan U-12</strong> dalam rangka <strong class="text-slate-900"><?= e($ev['kompetisi']) ?></strong>,
            yang diselenggarakan di Lapangan Kodim Merauke.
          </p>
          <p class="mt-4 text-lg leading-relaxed text-slate-600">
            Festival ini menjadi ruang bagi anak-anak untuk bermain, belajar, dan bertumbuh melalui sepak bola —
            sejalan dengan tema <em>“<?= e($ev['tema']) ?>”</em>.
          </p>
        </div>
        <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-1 xl:grid-cols-3">
          <?php foreach ([
              ['Karakter', 'Disiplin, tanggung jawab, dan kerja sama tim sejak usia dini.', 'M12 2l3 6 6 .9-4.5 4.3 1 6.3L12 16.6 6.5 19.5l1-6.3L3 8.9 9 8z'],
              ['Sportivitas', 'Menghormati lawan, wasit, dan aturan permainan.', 'M7 11l5-5 5 5M12 6v13'],
              ['Cinta Sepak Bola', 'Menumbuhkan kegembiraan bermain sepak bola.', 'M12 21a9 9 0 100-18 9 9 0 000 18zM12 7l3 2-1 4h-4l-1-4z'],
          ] as [$t, $d, $icon]): ?>
            <div class="card p-6">
              <span class="icon-badge"><svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="<?= e($icon) ?>"/></svg></span>
              <h3 class="mt-4 font-display text-xl font-bold uppercase tracking-wide text-slate-900"><?= e($t) ?></h3>
              <p class="mt-2 text-sm leading-relaxed text-slate-600"><?= e($d) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- ================= INFORMASI EVENT ================= -->
  <section id="informasi" class="scroll-mt-20 bg-white py-20 sm:py-24" aria-labelledby="info-title">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="max-w-2xl">
        <p class="section-kicker">Informasi Event</p>
        <h2 id="info-title" class="section-title">Detail pelaksanaan</h2>
      </div>
      <dl class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <?php foreach ([
            ['Nama Kompetisi', $ev['kompetisi']],
            ['Tanggal', $ev['tanggal']],
            ['Lokasi', $ev['lokasi']],
            ['Wilayah', $ev['wilayah']],
            ['Kegiatan', $ev['kegiatan']],
            ['Kategori', 'U-10 & U-12'],
            ['Biaya Pendaftaran', info_or_tba('biaya_pendaftaran')],
            ['Pendaftaran Dibuka', info_or_tba('pendaftaran_buka')],
            ['Penutupan Pendaftaran', info_or_tba('pendaftaran_tutup')],
            ['Kick Off', $ev['kick_off']],
            ['Pra Meeting', info_or_tba('pra_meeting') . ' · ' . $tempatMeeting],
            ['Technical Meeting', info_or_tba('technical_meeting') . ' · ' . $tempatMeeting],
        ] as [$k, $v]): ?>
          <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
            <dt class="text-xs font-bold uppercase tracking-widest text-royal-700"><?= e($k) ?></dt>
            <dd class="mt-2 font-display text-xl font-bold uppercase leading-tight text-slate-900"><?= e($v) ?></dd>
          </div>
        <?php endforeach; ?>
      </dl>
    </div>
  </section>

  <!-- ================= KATEGORI ================= -->
  <section id="kategori" class="scroll-mt-20 py-20 sm:py-24" aria-labelledby="kategori-title">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="max-w-2xl">
        <p class="section-kicker">Kategori Peserta</p>
        <h2 id="kategori-title" class="section-title">Dua kategori usia dini</h2>
      </div>
      <div class="mt-10 grid gap-6 md:grid-cols-2">
        <?php foreach (['U10' => 'U-10', 'U12' => 'U-12'] as $code => $label): ?>
          <article class="category-card group relative overflow-hidden rounded-3xl p-8 text-white shadow-xl sm:p-10 <?= $code === 'U10' ? 'bg-gradient-to-br from-flame-600 via-flame-700 to-royal-950' : 'bg-gradient-to-br from-royal-600 via-royal-800 to-royal-950' ?>">
            <div class="hero-pitch absolute inset-0 opacity-20 mix-blend-overlay" aria-hidden="true"></div>
            <?= logo_img('pointer-events-none absolute -bottom-8 -right-6 h-56 w-auto opacity-20 transition group-hover:opacity-30', 'lg', '') ?>
            <div class="relative">
              <p class="text-xs font-bold uppercase tracking-[0.2em] text-gold-400">Festival Sepak Bola Usia Dini</p>
              <h3 class="mt-2 font-display text-7xl font-extrabold sm:text-8xl"><?= e($label) ?></h3>
              <p class="mt-4 text-white/80"><?= e(age_rule_text($pub['age_rules'][$code] ?? null)) ?></p>
              <?php if ($code === 'U10'): ?>
                <p class="mt-3 inline-block rounded-xl bg-gold-400/20 px-3 py-2 text-sm font-semibold text-gold-300 ring-1 ring-gold-400/40">Khusus U-10: pertandingan dapat diikuti oleh club maupun sekolah dasar yang mendaftar.</p>
              <?php endif; ?>
              <p class="mt-1 text-sm text-white/60">Kode kategori pada formulir: <span class="font-mono font-semibold text-white"><?= e($code) ?></span></p>
              <a href="<?= e(url('/daftar-sepakbola.php?kategori=' . $code)) ?>" class="mt-8 inline-flex items-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-bold uppercase tracking-wider text-royal-900 transition group-hover:bg-gold-400 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-gold-300/60">
                Daftar <?= e($label) ?>
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
              </a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ================= PERSYARATAN + DOKUMEN ================= -->
  <section id="persyaratan" class="scroll-mt-20 bg-white py-20 sm:py-24" aria-labelledby="syarat-title">
    <div class="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
      <div>
        <p class="section-kicker">Persyaratan Pendaftaran</p>
        <h2 id="syarat-title" class="section-title">Siapkan sebelum mendaftar</h2>
        <ul class="mt-8 space-y-4">
          <?php foreach ([
              is_registration_free() ? 'Pendaftaran GRATIS — tidak dipungut biaya apa pun.' : 'Biaya pendaftaran: ' . config('info.biaya_pendaftaran'),
              'Club/SSB mendaftar pada kategori U-10 atau U-12.',
              'Khusus U-10: pertandingan dapat diikuti oleh club maupun sekolah dasar yang mendaftar.',
              'Data club lengkap: nama club, alamat, distrik, kabupaten, dan provinsi.',
              'Official club: nama manager dan nama pelatih, serta nomor WhatsApp aktif.',
              'Data setiap pemain: nama lengkap, NIK (16 digit), tempat & tanggal lahir, jenis kelamin, posisi, dan nomor punggung.',
              'Nama orang tua/wali dan nomor WhatsApp orang tua/wali untuk setiap pemain.',
              'Usia pemain sesuai ketentuan kategori yang ditetapkan panitia.',
              'Jumlah pemain per club: ' . (!empty($pub['players']['configured']) ? sprintf('%d–%d pemain.', $pub['players']['min'], $pub['players']['max']) : 'sesuai ketentuan panitia.'),
          ] as $req): ?>
            <li class="flex gap-3">
              <svg class="mt-0.5 h-6 w-6 shrink-0 text-royal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5"/></svg>
              <span class="text-slate-700"><?= e($req) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div id="dokumen" class="scroll-mt-20">
        <p class="section-kicker">Dokumen Wajib</p>
        <h2 class="section-title">Untuk setiap pemain</h2>
        <div class="mt-8 space-y-4">
          <div class="card flex gap-4 p-6">
            <span class="icon-badge"><svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5M10 13h6M10 17h6"/></svg></span>
            <div>
              <h3 class="font-display text-xl font-bold uppercase text-slate-900">Akte Kelahiran Asli <span class="req-pill">Wajib</span></h3>
              <p class="mt-1 text-sm text-slate-600">Format PDF, JPG, JPEG, atau PNG. Maksimal 5 MB. Pastikan tulisan terbaca jelas.</p>
              <p class="mt-2 rounded-lg bg-gold-300/30 px-3 py-2 text-sm font-semibold text-amber-900">Unggah akte kelahiran yang asli. Dokumen yang tidak berwarna (hitam-putih) dianggap bukan asli.</p>
            </div>
          </div>
          <div class="card flex gap-4 p-6">
            <span class="icon-badge"><svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="5" r="2.5"/><path d="M12 8v7M8 11h8M10 21l2-6 2 6"/></svg></span>
            <div>
              <h3 class="font-display text-xl font-bold uppercase text-slate-900">Foto Full Body <span class="req-pill">Wajib</span></h3>
              <p class="mt-1 text-sm text-slate-600">Format JPG, JPEG, atau PNG. Maksimal 5 MB.</p>
              <p class="mt-2 rounded-lg bg-gold-300/30 px-3 py-2 text-sm font-semibold text-amber-900">Foto wajib memperlihatkan pemain secara penuh dari kepala sampai kaki.</p>
            </div>
          </div>
          <div class="card flex gap-4 p-6">
            <span class="icon-badge bg-slate-100 text-slate-600"><svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l8 4v6c0 5-3.5 9-8 10-4.5-1-8-5-8-10V6z"/></svg></span>
            <div>
              <h3 class="font-display text-xl font-bold uppercase text-slate-900">Logo Club <span class="rounded-full bg-slate-100 px-2 py-0.5 align-middle text-[11px] font-bold text-slate-600">Opsional</span></h3>
              <p class="mt-1 text-sm text-slate-600">Format JPG atau PNG. Maksimal 5 MB.</p>
            </div>
          </div>
          <p class="flex gap-2 rounded-2xl bg-royal-50 p-4 text-sm text-royal-900 ring-1 ring-royal-200">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 118 0v4"/></svg>
            Dokumen disimpan secara privat dan hanya dapat diakses panitia yang berwenang untuk keperluan administrasi peserta.
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- ================= MEKANISME ================= -->
  <section id="mekanisme" class="scroll-mt-20 py-20 sm:py-24" aria-labelledby="mekanisme-title">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="max-w-2xl">
        <p class="section-kicker">Mekanisme Pendaftaran</p>
        <h2 id="mekanisme-title" class="section-title">Enam langkah mudah</h2>
        <p class="mt-3 text-slate-600">Formulir dapat diisi nyaman melalui smartphone. Isian otomatis tersimpan sebagai draft di perangkat Anda.</p>
      </div>
      <ol class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($steps as $i => [$t, $d]): ?>
          <li class="card relative overflow-hidden p-6">
            <span class="absolute -right-2 -top-6 font-display text-8xl font-extrabold text-slate-100" aria-hidden="true"><?= $i + 1 ?></span>
            <div class="relative">
              <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-royal-700 font-display text-lg font-bold text-white"><?= $i + 1 ?></span>
              <h3 class="mt-4 font-display text-xl font-bold uppercase text-slate-900"><?= e($t) ?></h3>
              <p class="mt-1 text-sm text-slate-600"><?= e($d) ?></p>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>
    </div>
  </section>

  <!-- ================= TIMELINE ================= -->
  <section id="timeline" class="scroll-mt-20 bg-ink-950 py-20 text-white sm:py-24" aria-labelledby="timeline-title">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
      <p class="section-kicker text-gold-400">Timeline</p>
      <h2 id="timeline-title" class="font-display text-4xl font-extrabold uppercase tracking-tight sm:text-5xl">Jadwal kegiatan</h2>
      <ol class="relative mt-12 border-l-2 border-white/15 pl-8">
        <?php foreach ($timeline as $i => [$t, $when, $d]): $last = $i === count($timeline) - 1; ?>
          <li class="relative pb-10 last:pb-0">
            <span class="absolute -left-[2.55rem] top-0.5 flex h-6 w-6 items-center justify-center rounded-full <?= $last ? 'bg-gold-500' : 'bg-royal-500' ?> ring-4 ring-ink-950" aria-hidden="true"></span>
            <p class="text-xs font-bold uppercase tracking-widest <?= $last ? 'text-gold-400' : 'text-royal-300' ?>"><?= e($when) ?></p>
            <h3 class="mt-1 font-display text-2xl font-bold uppercase"><?= e($t) ?></h3>
            <p class="mt-1 text-white/70"><?= e($d) ?></p>
          </li>
        <?php endforeach; ?>
      </ol>
    </div>
  </section>

  <!-- ================= FAQ ================= -->
  <section id="faq" class="scroll-mt-20 py-20 sm:py-24" aria-labelledby="faq-title">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
      <p class="section-kicker">FAQ</p>
      <h2 id="faq-title" class="section-title">Pertanyaan yang sering diajukan</h2>
      <div class="mt-8 space-y-3">
        <?php foreach ($faqs as [$q, $a]): ?>
          <details class="faq group rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 open:ring-royal-300">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 rounded-lg font-semibold text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-royal-500">
              <?= e($q) ?>
              <svg class="h-5 w-5 shrink-0 text-royal-700 transition group-open:rotate-45" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            </summary>
            <p class="mt-3 leading-relaxed text-slate-600"><?= e($a) ?></p>
          </details>
        <?php endforeach; ?>
      </div>
      <div id="kontak-admin" class="mt-8 flex scroll-mt-24 flex-col items-start gap-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-[#25D366]/40 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p class="font-display text-xl font-bold uppercase text-slate-900">Masih ada pertanyaan?</p>
          <p class="text-sm text-slate-600">Hubungi admin/penyelenggara via WhatsApp <strong class="whitespace-nowrap text-slate-900"><?= e(admin_whatsapp_display()) ?></strong>
            atau email <a class="font-semibold text-royal-700 hover:underline" href="mailto:<?= e(config('info.kontak_email')) ?>"><?= e(config('info.kontak_email')) ?></a></p>
          <ul class="mt-2 space-y-1 text-sm text-slate-600">
            <?php foreach (admin_contacts() as $c): ?>
              <li>Admin <?= e($c['nama']) ?>: <a class="font-semibold whitespace-nowrap text-royal-700 hover:underline" href="<?= e($c['link']) ?>" target="_blank" rel="noopener noreferrer"><?= e($c['nomor']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php render_view('partials/wa-button', ['label' => 'Chat Admin']); ?>
      </div>
      <div id="media-sosial" class="mt-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="font-display text-xl font-bold uppercase text-slate-900">Ikuti informasi resmi GAMBASI</p>
        <p class="text-sm text-slate-600">Pengumuman jadwal, hasil, dan dokumentasi festival.</p>
        <div class="mt-4"><?php render_view('partials/social-links', ['variant' => 'light', 'withLabel' => true]); ?></div>
      </div>
    </div>
  </section>

  <!-- ================= CTA ================= -->
  <section class="px-4 pb-20 sm:px-6 sm:pb-24 lg:px-8" aria-labelledby="cta-title">
    <div class="relative mx-auto max-w-7xl overflow-hidden rounded-[2rem] bg-royal-950 px-6 py-14 text-center text-white shadow-2xl sm:px-12 sm:py-20">
      <div class="hero-pitch absolute inset-0" aria-hidden="true"></div>
      <div class="brand-stripe absolute inset-x-0 top-0" aria-hidden="true"></div>
      <div class="relative">
        <div class="mb-6 flex justify-center"><?= logo_img('hero-logo h-32 w-auto sm:h-40', 'sm', 'Logo GAMBASI') ?></div>
        <?php if (is_registration_free()): ?><div class="mb-4 flex justify-center"><?= free_badge('dark') ?></div><?php endif; ?>
        <h2 id="cta-title" class="font-display text-4xl font-extrabold uppercase tracking-tight sm:text-6xl">Siap turun ke lapangan?</h2>
        <p class="mx-auto mt-4 max-w-2xl text-lg text-white/80">Daftarkan club Anda di <?= e($ev['kegiatan']) ?> — <?= e($ev['kompetisi']) ?>.</p>
        <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
          <a href="<?= e(url('/daftar-sepakbola.php')) ?>" class="btn-glow rounded-2xl bg-gold-500 px-8 py-4 text-base font-extrabold uppercase tracking-wider text-ink-950 transition hover:bg-gold-400">Daftarkan Club</a>
          <a href="<?= e(url('/cek-pendaftaran.php')) ?>" class="rounded-2xl border border-white/30 px-8 py-4 text-base font-bold uppercase tracking-wider transition hover:bg-white/10">Cek Pendaftaran</a>
        </div>
        <p class="mt-6 text-sm text-white/80">
          Butuh bantuan? Tanya admin via WhatsApp
          <a href="<?= e(admin_whatsapp_link()) ?>" target="_blank" rel="noopener noreferrer" class="font-bold text-gold-400 underline-offset-4 hover:underline"><?= e(admin_whatsapp_display()) ?></a>
        </p>
      </div>
    </div>
  </section>
</main>
<?php render_view('footer', ['scripts' => ['js/landing.js']]); ?>
