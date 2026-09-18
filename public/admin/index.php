<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

$admin = require_admin('view');
$res = gas_admin('admin_stats');
$d = $res['data'] ?? [];

render_view('admin/header', ['title' => 'Dashboard', 'active' => 'dashboard']);
?>
<?php if (empty($res['success'])): ?>
  <?= api_error_notice($res) ?>
<?php else: ?>
  <p class="text-slate-600">Selamat datang, <strong><?= e($admin['name']) ?></strong>. Ringkasan pendaftaran <?= e(config('event.nama')) ?>.</p>

  <div class="mt-5 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
    <div class="stat-card bg-gradient-to-br from-royal-700 to-royal-900 text-white">
      <p class="text-xs font-bold uppercase tracking-widest text-white/70">Total Club</p>
      <p class="mt-1 font-display text-5xl font-extrabold"><?= (int) $d['total_club'] ?></p>
      <p class="text-xs text-white/70">U-10: <?= (int) $d['kategori']['U10']['clubs'] ?> · U-12: <?= (int) $d['kategori']['U12']['clubs'] ?></p>
    </div>
    <div class="stat-card bg-gradient-to-br from-ink-900 to-ink-950 text-white">
      <p class="text-xs font-bold uppercase tracking-widest text-white/70">Total Pemain</p>
      <p class="mt-1 font-display text-5xl font-extrabold"><?= (int) $d['total_pemain'] ?></p>
      <p class="text-xs text-white/70">U-10: <?= (int) $d['kategori']['U10']['players'] ?> · U-12: <?= (int) $d['kategori']['U12']['players'] ?></p>
    </div>
    <div class="stat-card col-span-2">
      <p class="text-xs font-bold uppercase tracking-widest text-slate-500">Pemain terverifikasi</p>
      <?php $pv = (int) ($d['player_status']['VERIFIED'] ?? 0); $pt = max(1, (int) $d['total_pemain']); ?>
      <p class="mt-1 font-display text-5xl font-extrabold text-slate-900"><?= $pv ?><span class="text-2xl text-slate-400"> / <?= (int) $d['total_pemain'] ?></span></p>
      <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-royal-600" style="width: <?= round($pv / $pt * 100) ?>%"></div></div>
      <?php if (!empty($d['submission_in_progress'])): ?>
        <p class="mt-2 text-xs text-slate-500"><?= (int) $d['submission_in_progress'] ?> pendaftaran sedang/terhenti dalam proses unggah (belum final).</p>
      <?php endif; ?>
    </div>
  </div>

  <h2 class="mt-8 font-display text-xl font-bold uppercase text-slate-900">Status Club</h2>
  <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
    <?php foreach (config('status') as $code => $meta): ?>
      <a href="<?= e(url('/admin/clubs.php?status=' . $code)) ?>" class="stat-card transition hover:-translate-y-0.5 hover:shadow-md">
        <?= status_badge($code) ?>
        <p class="mt-2 font-display text-4xl font-extrabold text-slate-900"><?= (int) ($d['status'][$code] ?? 0) ?></p>
        <p class="text-xs text-slate-500"><?= e($code) ?></p>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="mt-8 rounded-2xl bg-white ring-1 ring-slate-200">
    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
      <h2 class="font-display text-xl font-bold uppercase text-slate-900">Pendaftaran Terbaru</h2>
      <a href="<?= e(url('/admin/clubs.php')) ?>" class="text-sm font-semibold text-royal-700 hover:underline">Lihat semua</a>
    </div>
    <div class="overflow-x-auto">
      <table class="admin-table">
        <thead><tr><th>Nomor</th><th>Club</th><th>Kategori</th><th>Pemain</th><th>Status</th><th>Waktu</th></tr></thead>
        <tbody>
        <?php if (empty($d['latest'])): ?>
          <tr><td colspan="6" class="py-8 text-center text-slate-500">Belum ada pendaftaran.</td></tr>
        <?php endif; ?>
        <?php foreach ($d['latest'] as $c): ?>
          <tr>
            <td class="font-mono text-xs"><a class="font-semibold text-royal-700 hover:underline" href="<?= e(url('/admin/club.php?id=' . $c['club_id'])) ?>"><?= e($c['nomor_pendaftaran']) ?></a></td>
            <td class="font-semibold text-slate-900"><?= e($c['nama_club']) ?></td>
            <td><?= e(category_label($c['kategori'])) ?></td>
            <td><?= (int) $c['jumlah_pemain'] ?></td>
            <td><?= status_badge($c['status']) ?></td>
            <td class="whitespace-nowrap text-slate-500"><?= e(format_datetime($c['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
<?php render_view('admin/footer'); ?>
