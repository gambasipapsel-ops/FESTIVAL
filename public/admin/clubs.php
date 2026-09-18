<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

require_admin('view');

$q = query_param('q');
$kategori = query_param('kategori', ['U10', 'U12']);
$status = query_param('status', array_keys(config('status')));
$page = max(1, (int) ($_GET['page'] ?? 1));

$res = gas_admin('admin_list_clubs', compact('q', 'kategori', 'status', 'page') + ['per_page' => 25]);

render_view('admin/header', ['title' => 'Club', 'active' => 'clubs']);
render_view('admin/filters', ['q' => $q, 'kategori' => $kategori, 'status' => $status,
    'placeholder' => 'Nomor pendaftaran, nama club, atau nama pemain']);
?>
<div class="mt-4 flex flex-wrap items-center justify-between gap-3">
  <p class="text-sm text-slate-600">
    <?= !empty($res['success']) ? (int) $res['data']['pagination']['total'] . ' club ditemukan' : '' ?>
  </p>
  <?php render_view('admin/export-form', ['dataset' => 'clubs', 'kategori' => $kategori, 'status' => $status]); ?>
</div>

<?php if (empty($res['success'])): ?>
  <div class="mt-4"><?= api_error_notice($res) ?></div>
<?php else: ?>
  <div class="mt-3 overflow-x-auto rounded-2xl bg-white ring-1 ring-slate-200">
    <table class="admin-table">
      <thead><tr><th>Nomor</th><th>Club</th><th>Kategori</th><th>Official</th><th>Wilayah</th><th>Pemain</th><th>Status</th><th>Terdaftar</th><th><span class="sr-only">Aksi</span></th></tr></thead>
      <tbody>
      <?php if (!$res['data']['items']): ?>
        <tr><td colspan="9" class="py-10 text-center text-slate-500">Tidak ada data yang cocok.</td></tr>
      <?php endif; ?>
      <?php foreach ($res['data']['items'] as $c): ?>
        <tr>
          <td class="whitespace-nowrap font-mono text-xs font-semibold"><?= e($c['nomor_pendaftaran']) ?></td>
          <td class="font-semibold text-slate-900"><?= e($c['nama_club']) ?></td>
          <td><?= e(category_label($c['kategori'])) ?></td>
          <td class="text-xs"><p>M: <?= e($c['nama_manager']) ?></p><p>P: <?= e($c['nama_pelatih']) ?></p><p class="text-slate-500"><?= e($c['whatsapp']) ?></p></td>
          <td class="text-xs"><?= e($c['distrik']) ?><br><span class="text-slate-500"><?= e($c['kabupaten']) ?></span></td>
          <td class="text-center"><?= (int) $c['jumlah_pemain'] ?></td>
          <td><?= status_badge($c['status']) ?></td>
          <td class="whitespace-nowrap text-xs text-slate-500"><?= e(format_datetime($c['created_at'])) ?></td>
          <td><a class="btn-chip" href="<?= e(url('/admin/club.php?id=' . $c['club_id'])) ?>">Detail</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= pagination_links($res['data']['pagination']) ?>
<?php endif; ?>
<?php render_view('admin/footer'); ?>
