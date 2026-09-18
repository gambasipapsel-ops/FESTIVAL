<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

require_admin('update_status');

$queue = ['PENDING' => 'Pending', 'REVIEW' => 'Review', 'REVISION' => 'Revisi'];
$status = query_param('status', array_keys($queue)) ?: 'PENDING';
$kategori = query_param('kategori', ['U10', 'U12']);
$q = query_param('q');
$page = max(1, (int) ($_GET['page'] ?? 1));

$res = gas_admin('admin_list_clubs', compact('q', 'kategori', 'status', 'page') + ['per_page' => 20]);
$stats = gas_admin('admin_stats');

render_view('admin/header', ['title' => 'Verifikasi', 'active' => 'verification']);
?>
<nav class="flex flex-wrap gap-2" aria-label="Antrean verifikasi">
  <?php foreach ($queue as $code => $label):
      $count = (int) ($stats['data']['status'][$code] ?? 0);
      $active = $code === $status; ?>
    <a href="?<?= e(http_build_query(['status' => $code, 'kategori' => $kategori])) ?>"
       class="flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold ring-1 <?= $active ? 'bg-royal-700 text-white ring-royal-700' : 'bg-white text-slate-700 ring-slate-200 hover:bg-slate-50' ?>"
       <?= $active ? 'aria-current="page"' : '' ?>>
      <?= e($label) ?> <span class="rounded-full px-2 text-xs <?= $active ? 'bg-white/20' : 'bg-slate-100' ?>"><?= $count ?></span>
    </a>
  <?php endforeach; ?>
  <form method="get" class="auto-filter ml-auto flex gap-2">
    <input type="hidden" name="status" value="<?= e($status) ?>">
    <label for="v-kat" class="sr-only">Kategori</label>
    <select id="v-kat" name="kategori" class="field-input min-h-0 py-2"><?= select_options(config('event.kategori'), $kategori, 'Semua kategori') ?></select>
    <label for="v-q" class="sr-only">Cari</label>
    <input id="v-q" type="search" name="q" value="<?= e($q) ?>" class="field-input min-h-0 py-2" placeholder="Cari…" data-debounce>
  </form>
</nav>

<p class="mt-4 text-sm text-slate-600">Buka detail club untuk memeriksa data pemain, akte kelahiran, dan foto sebelum mengubah status. Status REVISION/REJECTED wajib disertai catatan.</p>

<?php if (empty($res['success'])): ?>
  <div class="mt-4"><?= api_error_notice($res) ?></div>
<?php else: ?>
  <div class="mt-4 space-y-3">
    <?php if (!$res['data']['items']): ?>
      <div class="rounded-2xl bg-white p-10 text-center text-slate-500 ring-1 ring-slate-200">Tidak ada club dalam antrean ini. 🎉</div>
    <?php endif; ?>
    <?php foreach ($res['data']['items'] as $c): ?>
      <article class="grid gap-4 rounded-2xl bg-white p-4 ring-1 ring-slate-200 md:grid-cols-3">
        <div class="md:col-span-1">
          <p class="font-mono text-xs font-bold text-royal-700"><?= e($c['nomor_pendaftaran']) ?></p>
          <h2 class="text-lg font-bold text-slate-900"><?= e($c['nama_club']) ?></h2>
          <p class="text-sm text-slate-600"><?= e(category_label($c['kategori'])) ?> · <?= (int) $c['jumlah_pemain'] ?> pemain · <?= e($c['distrik']) ?></p>
          <p class="mt-1 text-xs text-slate-500">Masuk <?= e(format_datetime($c['created_at'])) ?></p>
          <a href="<?= e(url('/admin/club.php?id=' . $c['club_id'])) ?>" class="btn-primary mt-3 min-h-0 px-4 py-2 text-sm">Periksa Detail</a>
        </div>
        <div class="md:col-span-2">
          <?php render_view('admin/status-control', ['target' => 'club', 'id' => $c['club_id'], 'current' => $c['status'], 'notes' => '']); ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <?= pagination_links($res['data']['pagination']) ?>
<?php endif; ?>
<?php render_view('admin/footer'); ?>
