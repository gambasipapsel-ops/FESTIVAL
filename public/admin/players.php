<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

require_admin('view');

$q = query_param('q');
$kategori = query_param('kategori', ['U10', 'U12']);
$status = query_param('status', array_keys(config('status')));
$docs = query_param('docs', ['complete', 'missing']);
$page = max(1, (int) ($_GET['page'] ?? 1));

$res = gas_admin('admin_list_players', compact('q', 'kategori', 'status', 'docs', 'page') + ['per_page' => 30]);

render_view('admin/header', ['title' => 'Pemain', 'active' => 'players']);
render_view('admin/filters', [
    'q' => $q, 'kategori' => $kategori, 'status' => $status,
    'placeholder' => 'Nama pemain, nama club, nomor pendaftaran',
    'extra' => ['docs' => ['Dokumen', ['complete' => 'Lengkap', 'missing' => 'Belum lengkap'], $docs]],
]);
?>
<div class="mt-4 flex flex-wrap items-center justify-between gap-3">
  <p class="text-sm text-slate-600"><?= !empty($res['success']) ? (int) $res['data']['pagination']['total'] . ' pemain ditemukan' : '' ?></p>
  <?php render_view('admin/export-form', ['dataset' => 'players', 'kategori' => $kategori, 'status' => $status]); ?>
</div>

<?php if (empty($res['success'])): ?>
  <div class="mt-4"><?= api_error_notice($res) ?></div>
<?php else: ?>
  <div class="mt-3 overflow-x-auto rounded-2xl bg-white ring-1 ring-slate-200">
    <table class="admin-table">
      <thead><tr><th>Nama</th><th>NIK</th><th>Tgl Lahir</th><th>Kategori</th><th>Posisi / No</th><th>Club</th><?php if (!empty($res['data']['can_view_sensitive'])): ?><th>Orang Tua/Wali</th><?php endif; ?><th>Dokumen</th><th>Status</th><th><span class="sr-only">Aksi</span></th></tr></thead>
      <tbody>
      <?php if (!$res['data']['items']): ?>
        <tr><td colspan="10" class="py-10 text-center text-slate-500">Tidak ada data yang cocok.</td></tr>
      <?php endif; ?>
      <?php foreach ($res['data']['items'] as $p): ?>
        <tr>
          <td class="font-semibold text-slate-900"><?= e($p['nama_lengkap']) ?></td>
          <td class="whitespace-nowrap font-mono text-xs"><?= e($p['nik']) ?></td>
          <td class="whitespace-nowrap text-xs"><?= e(format_date_id($p['tanggal_lahir'])) ?></td>
          <td><?= e(category_label($p['kategori'])) ?></td>
          <td class="whitespace-nowrap text-xs"><?= e($p['posisi_label']) ?> · #<?= e($p['nomor_punggung']) ?></td>
          <td class="text-xs"><span class="font-semibold"><?= e($p['nama_club']) ?></span><br><span class="font-mono text-slate-500"><?= e($p['nomor_pendaftaran']) ?></span></td>
          <?php if (!empty($res['data']['can_view_sensitive'])): ?>
            <td class="text-xs"><?= e(implode(' / ', array_filter([$p['nama_ayah'], $p['nama_ibu'], $p['nama_wali']]))) ?><br><span class="text-slate-500"><?= e($p['whatsapp_wali']) ?></span></td>
          <?php endif; ?>
          <td class="whitespace-nowrap text-xs">
            <span class="<?= $p['has_akte'] ? 'text-royal-700' : 'text-rose-700' ?>"><?= $p['has_akte'] ? '✓' : '✗' ?> Akte</span><br>
            <span class="<?= $p['has_foto'] ? 'text-royal-700' : 'text-rose-700' ?>"><?= $p['has_foto'] ? '✓' : '✗' ?> Foto</span>
          </td>
          <td><?= status_badge($p['status_verifikasi']) ?></td>
          <td><a class="btn-chip" href="<?= e(url('/admin/club.php?id=' . $p['club_id']) . '#' . $p['pemain_id']) ?>">Detail</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= pagination_links($res['data']['pagination']) ?>
<?php endif; ?>
<?php render_view('admin/footer'); ?>
