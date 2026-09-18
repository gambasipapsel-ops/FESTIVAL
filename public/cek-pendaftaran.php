<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$nomor = '';
$result = null;
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $nomor = strtoupper(trim((string) ($_POST['nomor_pendaftaran'] ?? '')));
    if (!verify_csrf()) {
        $error = 'Sesi halaman kedaluwarsa. Silakan coba lagi.';
    } elseif (!rate_limit('check_status')) {
        http_response_code(429);
        $error = 'Terlalu banyak pencarian. Silakan tunggu beberapa menit.';
    } elseif (!preg_match('/^GMB-SB-2026-\d{4,6}$/', $nomor)) {
        $error = 'Format nomor pendaftaran tidak valid. Contoh: GMB-SB-2026-0001';
    } else {
        $res = gas_request('check_status', ['nomor_pendaftaran' => $nomor]);
        if (!empty($res['success'])) {
            // Hanya field non-sensitif
            $result = [
                'nomor_pendaftaran' => (string) ($res['data']['nomor_pendaftaran'] ?? ''),
                'nama_club'         => (string) ($res['data']['nama_club'] ?? ''),
                'kategori'          => (string) ($res['data']['kategori'] ?? ''),
                'status'            => (string) ($res['data']['status'] ?? ''),
            ];
        } elseif (($res['error_code'] ?? '') === 'NOT_FOUND') {
            $error = 'Nomor pendaftaran tidak ditemukan. Periksa kembali nomor Anda.';
        } else {
            $error = $res['message'] ?: 'Pengecekan gagal. Silakan coba lagi.';
        }
    }
}

render_view('header', ['title' => 'Cek Pendaftaran — GAMBASI Papua Selatan 2026', 'active' => 'cek']);
?>
<main id="main" class="relative min-h-[80vh] overflow-hidden bg-royal-950 pb-20 pt-28">
  <div class="hero-pitch absolute inset-0 opacity-40" aria-hidden="true"></div>
  <div class="relative mx-auto max-w-xl px-4 sm:px-6">
    <div class="text-center text-white">
      <p class="text-xs font-bold uppercase tracking-[0.2em] text-gold-400"><?= e(config('event.nama')) ?></p>
      <h1 class="mt-2 font-display text-4xl font-extrabold uppercase sm:text-5xl">Cek Pendaftaran</h1>
      <p class="mt-2 text-white/70">Masukkan nomor pendaftaran untuk melihat status verifikasi.</p>
    </div>

    <form method="post" class="mt-8 rounded-[2rem] bg-white p-6 shadow-2xl sm:p-8" novalidate>
      <?= csrf_field() ?>
      <label for="nomor_pendaftaran" class="field-label">Nomor Pendaftaran</label>
      <input id="nomor_pendaftaran" name="nomor_pendaftaran" value="<?= e($nomor) ?>" required
             class="field-input mt-1 font-mono text-lg uppercase tracking-wider" placeholder="GMB-SB-2026-0001"
             pattern="GMB-SB-2026-\d{4,6}" maxlength="20" autocomplete="off" autocapitalize="characters"
             <?= $error ? 'aria-invalid="true" aria-describedby="cek-error"' : '' ?>>
      <?php if ($error): ?>
        <p id="cek-error" class="field-error mt-2" role="alert"><?= e($error) ?></p>
      <?php endif; ?>
      <button type="submit" class="btn-primary mt-5 w-full">Cek Status</button>
    </form>

    <?php if ($result): ?>
      <section class="mt-6 rounded-[2rem] bg-white p-6 shadow-2xl sm:p-8" aria-labelledby="hasil-title" aria-live="polite">
        <h2 id="hasil-title" class="font-display text-2xl font-bold uppercase text-slate-900">Hasil Pengecekan</h2>
        <dl class="mt-4 divide-y divide-slate-100">
          <div class="flex justify-between gap-4 py-3"><dt class="text-slate-500">Nomor Pendaftaran</dt><dd class="font-mono font-bold text-slate-900"><?= e($result['nomor_pendaftaran']) ?></dd></div>
          <div class="flex justify-between gap-4 py-3"><dt class="text-slate-500">Nama Club</dt><dd class="text-right font-semibold text-slate-900"><?= e($result['nama_club']) ?></dd></div>
          <div class="flex justify-between gap-4 py-3"><dt class="text-slate-500">Kategori</dt><dd class="font-semibold text-slate-900"><?= e(category_label($result['kategori'])) ?></dd></div>
          <div class="flex items-center justify-between gap-4 py-3"><dt class="text-slate-500">Status</dt><dd><?= status_badge($result['status']) ?></dd></div>
        </dl>
        <p class="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-600"><?= e(config('status.' . $result['status'] . '.desc', '')) ?></p>
        <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <p class="text-sm text-slate-600"><?= in_array($result['status'], ['REVISION', 'REJECTED'], true) ? 'Tanyakan detail perbaikan kepada admin.' : 'Ada pertanyaan tentang status ini?' ?></p>
          <?php render_view('partials/wa-button', [
              'label' => 'Tanya Admin',
              'variant' => in_array($result['status'], ['REVISION', 'REJECTED'], true) ? 'solid' : 'outline',
              'context' => 'Nomor pendaftaran: ' . $result['nomor_pendaftaran'] . "\nClub: " . $result['nama_club'] . "\nStatus saat ini: " . $result['status'],
          ]); ?>
        </div>
      </section>
    <?php endif; ?>

    <p class="mt-6 text-center text-sm text-white/60">Nomor pendaftaran hilang? Hubungi admin via WhatsApp <a class="font-semibold text-gold-400 hover:underline" href="<?= e(admin_whatsapp_link()) ?>" target="_blank" rel="noopener noreferrer"><?= e(admin_whatsapp_display()) ?></a>.</p>
    <p class="mt-2 text-center text-sm text-white/60">Belum mendaftar? <a class="font-semibold text-gold-400 hover:underline" href="<?= e(url('/daftar-sepakbola.php')) ?>">Daftarkan club</a></p>
  </div>
</main>
<?php render_view('footer'); ?>
