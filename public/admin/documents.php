<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

require_admin('view_documents');

$q = query_param('q');
$kategori = query_param('kategori', ['U10', 'U12']);
$status = query_param('status', array_keys(config('status')));
$docs = query_param('docs', ['complete', 'missing']);
$page = max(1, (int) ($_GET['page'] ?? 1));

$res = gas_admin('admin_list_players', compact('q', 'kategori', 'status', 'docs', 'page') + ['per_page' => 24]);

$doc = fn(string $rid, string $type, bool $download = false) =>
    url('/admin/document.php?' . http_build_query(['target' => 'player', 'id' => $rid, 'type' => $type] + ($download ? ['download' => 1] : [])));

render_view('admin/header', ['title' => 'Dokumen', 'active' => 'documents']);
render_view('admin/filters', [
    'q' => $q, 'kategori' => $kategori, 'status' => $status,
    'placeholder' => 'Nama pemain, nama club, nomor pendaftaran',
    'extra' => ['docs' => ['Dokumen', ['complete' => 'Lengkap', 'missing' => 'Belum lengkap'], $docs]],
]);
?>
<p class="mt-4 text-sm text-slate-600">Dokumen bersifat privat. Setiap pembukaan akte/foto dicatat pada log audit (ADMIN_VIEW).</p>

<?php if (empty($res['success'])): ?>
  <div class="mt-4"><?= api_error_notice($res) ?></div>
<?php else: ?>
  <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
    <?php if (!$res['data']['items']): ?>
      <div class="col-span-full rounded-2xl bg-white p-10 text-center text-slate-500 ring-1 ring-slate-200">Tidak ada data yang cocok.</div>
    <?php endif; ?>
    <?php foreach ($res['data']['items'] as $p): ?>
      <article class="flex flex-col rounded-2xl bg-white p-3 ring-1 ring-slate-200">
        <?php if ($p['has_foto']): ?>
          <button type="button" class="doc-open" data-src="<?= e($doc($p['pemain_id'], 'foto')) ?>" data-kind="image" data-title="Foto — <?= e($p['nama_lengkap']) ?>" data-download="<?= e($doc($p['pemain_id'], 'foto', true)) ?>">
            <img src="<?= e($doc($p['pemain_id'], 'foto')) ?>" alt="Foto full body <?= e($p['nama_lengkap']) ?>" class="doc-thumb" loading="lazy">
          </button>
        <?php else: ?>
          <div class="doc-thumb flex items-center justify-center text-xs text-rose-600">Foto tidak ada</div>
        <?php endif; ?>
        <h2 class="mt-2 truncate text-sm font-bold text-slate-900" title="<?= e($p['nama_lengkap']) ?>"><?= e($p['nama_lengkap']) ?></h2>
        <p class="truncate text-xs text-slate-500"><?= e($p['nama_club']) ?> · <?= e(category_label($p['kategori'])) ?></p>
        <div class="mt-1"><?= status_badge($p['status_verifikasi']) ?></div>
        <div class="mt-auto flex flex-wrap gap-1.5 pt-2">
          <?php if ($p['has_akte']): ?>
            <button type="button" class="doc-open btn-chip" data-src="<?= e($doc($p['pemain_id'], 'akte')) ?>" data-kind="auto" data-title="Akte — <?= e($p['nama_lengkap']) ?>" data-download="<?= e($doc($p['pemain_id'], 'akte', true)) ?>">Akte</button>
          <?php else: ?><span class="btn-chip btn-chip-danger">Akte ✗</span><?php endif; ?>
          <a class="btn-chip" href="<?= e(url('/admin/club.php?id=' . $p['club_id']) . '#' . $p['pemain_id']) ?>">Detail</a>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <?= pagination_links($res['data']['pagination']) ?>
<?php endif; ?>
<?php render_view('admin/doc-viewer'); ?>
<?php render_view('admin/footer'); ?>
