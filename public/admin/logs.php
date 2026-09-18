<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

require_admin('view_logs');

$aktivitas = query_param('aktivitas');
if ($aktivitas !== '' && !preg_match('/^[A-Z_]{3,40}$/', $aktivitas)) {
    $aktivitas = '';
}
$q = query_param('q');
$page = max(1, (int) ($_GET['page'] ?? 1));
$res = gas_admin('admin_list_logs', compact('aktivitas', 'q', 'page') + ['per_page' => 50]);
$activities = array_combine($res['data']['activities'] ?? [], $res['data']['activities'] ?? []) ?: [];

render_view('admin/header', ['title' => 'Log Aktivitas', 'active' => 'logs']);
?>
<form method="get" class="auto-filter grid gap-3 rounded-2xl bg-white p-4 ring-1 ring-slate-200 sm:grid-cols-3" role="search">
  <div>
    <label for="l-act" class="field-label">Aktivitas</label>
    <select id="l-act" name="aktivitas" class="field-input"><?= select_options($activities, $aktivitas) ?></select>
  </div>
  <div>
    <label for="l-q" class="field-label">Cari (ID club/pemain, admin)</label>
    <input id="l-q" type="search" name="q" value="<?= e($q) ?>" class="field-input" data-debounce>
  </div>
  <div class="flex items-end gap-2"><button class="btn-primary flex-1">Terapkan</button><a href="?" class="btn-secondary">Reset</a></div>
</form>
<p class="mt-3 text-xs text-slate-500">Log tidak memuat NIK, isi dokumen, maupun data orang tua/wali.</p>

<?php if (empty($res['success'])): ?>
  <div class="mt-4"><?= api_error_notice($res) ?></div>
<?php else: ?>
  <div class="mt-3 overflow-x-auto rounded-2xl bg-white ring-1 ring-slate-200">
    <table class="admin-table">
      <thead><tr><th>Waktu</th><th>Aktivitas</th><th>Status</th><th>Aktor</th><th>Club</th><th>Pemain</th><th>Detail</th></tr></thead>
      <tbody>
      <?php if (!$res['data']['items']): ?>
        <tr><td colspan="7" class="py-10 text-center text-slate-500">Belum ada log.</td></tr>
      <?php endif; ?>
      <?php foreach ($res['data']['items'] as $l): ?>
        <tr>
          <td class="whitespace-nowrap text-xs text-slate-500"><?= e(format_datetime($l['timestamp'])) ?></td>
          <td class="whitespace-nowrap font-mono text-xs font-semibold"><?= e($l['aktivitas']) ?></td>
          <td class="text-xs <?= $l['status'] === 'OK' ? 'text-royal-700' : 'text-rose-700' ?>"><?= e($l['status']) ?></td>
          <td class="text-xs"><?= e($l['aktor']) ?></td>
          <td class="whitespace-nowrap font-mono text-xs">
            <?php if ($l['club_id']): ?><a class="text-royal-700 hover:underline" href="<?= e(url('/admin/club.php?id=' . $l['club_id'])) ?>"><?= e($l['club_id']) ?></a><?php endif; ?>
          </td>
          <td class="whitespace-nowrap font-mono text-xs"><?= e($l['pemain_id']) ?></td>
          <td class="text-xs text-slate-600"><?= e($l['detail']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= pagination_links($res['data']['pagination']) ?>
<?php endif; ?>
<?php render_view('admin/footer'); ?>
