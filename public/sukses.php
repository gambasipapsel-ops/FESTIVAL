<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

// Halaman sukses hanya menampilkan data dari session hasil finalisasi backend,
// bukan dari parameter URL (mencegah tampilan "sukses" palsu).
$s = $_SESSION['last_success'] ?? null;
if (!is_array($s) || empty($s['nomor_pendaftaran']) || time() - (int) ($s['at'] ?? 0) > 3600) {
    redirect(url('/cek-pendaftaran.php'));
}
header('Cache-Control: no-store');

render_view('header', ['title' => 'Pendaftaran Berhasil — GAMBASI Papua Selatan 2026', 'active' => '']);
?>
<main id="main" class="relative min-h-[80vh] overflow-hidden bg-royal-950 pb-20 pt-28 text-white">
  <div class="hero-pitch absolute inset-0 opacity-40" aria-hidden="true"></div>
  <div class="relative mx-auto max-w-2xl px-4 sm:px-6">
    <div class="rounded-[2rem] bg-white p-6 text-center text-slate-800 shadow-2xl sm:p-10">
      <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-royal-100 text-royal-700">
        <svg class="h-10 w-10" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
      </div>
      <h1 class="mt-6 font-display text-4xl font-extrabold uppercase text-slate-900 sm:text-5xl">Pendaftaran Berhasil</h1>
      <p class="mt-2 text-slate-600">Data club, pemain, dan seluruh dokumen telah tersimpan. Pendaftaran Anda <strong>menunggu verifikasi panitia</strong>.</p>

      <div class="mt-8 rounded-2xl bg-slate-50 p-5 ring-1 ring-slate-200">
        <p class="text-xs font-bold uppercase tracking-widest text-slate-500">Nomor Pendaftaran</p>
        <p id="reg-number" class="mt-1 select-all whitespace-nowrap font-mono text-[1.45rem] font-bold tracking-wide text-royal-800 min-[400px]:text-3xl sm:text-4xl sm:tracking-wider"><?= e($s['nomor_pendaftaran']) ?></p>
        <button type="button" id="copy-number" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-white px-3 py-1.5 text-sm font-semibold text-royal-800 ring-1 ring-royal-300 hover:bg-royal-50">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 012-2h10"/></svg>
          Salin nomor
        </button>
      </div>

      <dl class="mt-6 grid grid-cols-1 gap-3 text-left sm:grid-cols-3">
        <div class="rounded-xl bg-slate-50 p-4"><dt class="text-xs text-slate-500">Club</dt><dd class="font-semibold text-slate-900"><?= e($s['nama_club']) ?></dd></div>
        <div class="rounded-xl bg-slate-50 p-4"><dt class="text-xs text-slate-500">Kategori</dt><dd class="font-semibold text-slate-900"><?= e(category_label($s['kategori'])) ?></dd></div>
        <div class="rounded-xl bg-slate-50 p-4"><dt class="text-xs text-slate-500">Status</dt><dd class="mt-0.5"><?= status_badge($s['status']) ?></dd></div>
      </dl>
      <?php if (!empty($s['jumlah_pemain'])): ?>
        <p class="mt-3 text-sm text-slate-500"><?= (int) $s['jumlah_pemain'] ?> pemain terdaftar.</p>
      <?php endif; ?>

      <div class="notice notice-warn mt-6 text-left text-sm">
        Simpan nomor pendaftaran ini (screenshot atau catat). Nomor diperlukan untuk mengecek status verifikasi.
      </div>

      <div class="mt-6 rounded-2xl bg-[#25D366]/10 p-4 text-left ring-1 ring-[#25D366]/40">
        <p class="text-sm text-slate-700">Ada pertanyaan tentang pendaftaran ini? Hubungi admin/penyelenggara via WhatsApp <strong class="whitespace-nowrap text-slate-900"><?= e(admin_whatsapp_display()) ?></strong>. Nomor pendaftaran akan otomatis disertakan.</p>
        <div class="mt-3">
          <?php render_view('partials/wa-button', [
              'label' => 'Tanya Admin',
              'context' => 'Nomor pendaftaran: ' . $s['nomor_pendaftaran'] . "\nClub: " . $s['nama_club'] . "\nKategori: " . category_label($s['kategori']),
          ]); ?>
        </div>
      </div>

      <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
        <a href="<?= e(url('/cek-pendaftaran.php')) ?>" class="btn-primary">Cek Status Pendaftaran</a>
        <a href="<?= e(url('/daftar-sepakbola.php')) ?>" class="btn-secondary">Daftarkan Club/Kategori Lain</a>
      </div>
      <div class="mt-8 border-t border-slate-100 pt-6">
        <p class="text-sm font-semibold text-slate-700">Pantau pengumuman resmi di media sosial GAMBASI</p>
        <div class="mt-3 flex justify-center"><?php render_view('partials/social-links', ['variant' => 'light']); ?></div>
      </div>
      <a href="<?= e(url('/')) ?>" class="mt-4 inline-block text-sm font-semibold text-slate-500 hover:text-royal-700">Kembali ke beranda</a>
    </div>
  </div>
</main>
<?php render_view('footer', ['scripts' => ['js/sukses.js']]); ?>
