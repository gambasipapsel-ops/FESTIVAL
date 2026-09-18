<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

require_admin('view');

$id = is_string($_GET['id'] ?? null) && preg_match('/^CLB-[A-Z0-9]{6,20}$/', $_GET['id']) ? $_GET['id'] : '';
$res = $id ? gas_admin('admin_get_club', ['club_id' => $id]) : api_fail('Club tidak ditemukan.', 'NOT_FOUND');
$c = $res['data']['club'] ?? null;
$players = $res['data']['players'] ?? [];
$canDocs = !empty($res['data']['can_view_documents']);
$sensitive = !empty($res['data']['can_view_sensitive']);

$doc = fn(string $target, string $rid, string $type, bool $download = false) =>
    url('/admin/document.php?' . http_build_query(['target' => $target, 'id' => $rid, 'type' => $type] + ($download ? ['download' => 1] : [])));

render_view('admin/header', ['title' => $c ? $c['nama_club'] : 'Detail Club', 'active' => 'clubs']);
?>
<a href="<?= e(url('/admin/clubs.php')) ?>" class="text-sm font-semibold text-royal-700 hover:underline">← Daftar club</a>

<?php if (!$c): ?>
  <div class="mt-4"><?= api_error_notice($res) ?></div>
<?php else: ?>
  <div class="mt-4 grid gap-5 lg:grid-cols-3">
    <section class="rounded-2xl bg-white p-5 ring-1 ring-slate-200 lg:col-span-2" aria-labelledby="club-info">
      <div class="flex items-start gap-4">
        <?php if ($c['has_logo'] && $canDocs): ?>
          <img src="<?= e($doc('club', $c['club_id'], 'logo')) ?>" alt="Logo <?= e($c['nama_club']) ?>" class="h-20 w-20 shrink-0 rounded-xl bg-slate-100 object-contain ring-1 ring-slate-200" loading="lazy">
        <?php else: ?>
          <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-xl bg-royal-50 font-display text-3xl font-extrabold text-royal-700"><?= e(mb_strtoupper(mb_substr($c['nama_club'], 0, 2))) ?></div>
        <?php endif; ?>
        <div class="min-w-0">
          <p class="font-mono text-sm font-bold text-royal-700"><?= e($c['nomor_pendaftaran']) ?></p>
          <h2 id="club-info" class="font-display text-3xl font-extrabold uppercase leading-tight text-slate-900"><?= e($c['nama_club']) ?></h2>
          <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-600">
            <span class="rounded-md bg-slate-900 px-2 py-0.5 text-xs font-bold text-white"><?= e(category_label($c['kategori'])) ?></span>
            <?= status_badge($c['status']) ?>
            <span><?= (int) $c['jumlah_pemain'] ?> pemain</span>
          </p>
        </div>
      </div>
      <dl class="mt-5 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
        <?php foreach ([
            'Manager' => $c['nama_manager'], 'Pelatih' => $c['nama_pelatih'],
            'WhatsApp' => $c['whatsapp'], 'Email' => $c['email'] ?: '-',
            'Alamat' => $c['alamat'], 'Kampung/Kelurahan' => $c['kampung'] ?: '-',
            'Distrik' => $c['distrik'], 'Kabupaten' => $c['kabupaten'], 'Provinsi' => $c['provinsi'],
            'Terdaftar' => format_datetime($c['created_at']), 'Diperbarui' => format_datetime($c['updated_at']),
        ] as $k => $v): ?>
          <div><dt class="text-xs text-slate-500"><?= e($k) ?></dt><dd class="font-medium text-slate-900">
            <?php if ($k === 'WhatsApp' && whatsapp_link($v)): ?>
              <a class="text-royal-700 hover:underline" href="<?= e(whatsapp_link($v)) ?>" target="_blank" rel="noopener noreferrer"><?= e($v) ?></a>
            <?php else: ?><?= e($v) ?><?php endif; ?>
          </dd></div>
        <?php endforeach; ?>
      </dl>
    </section>

    <section class="rounded-2xl bg-white p-5 ring-1 ring-slate-200" aria-labelledby="club-status">
      <h2 id="club-status" class="font-display text-xl font-bold uppercase text-slate-900">Status Pendaftaran Club</h2>
      <div class="mt-3">
        <?php render_view('admin/status-control', ['target' => 'club', 'id' => $c['club_id'], 'current' => $c['status'], 'notes' => $c['catatan_admin']]); ?>
      </div>
    </section>
  </div>

  <section class="mt-6" aria-labelledby="player-list">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h2 id="player-list" class="font-display text-2xl font-bold uppercase text-slate-900">Pemain (<?= count($players) ?>)</h2>
      <?php if (!$sensitive): ?><p class="text-xs text-slate-500">NIK disamarkan & data orang tua disembunyikan sesuai hak akses Anda.</p><?php endif; ?>
    </div>
    <div class="mt-3 grid gap-4 xl:grid-cols-2">
      <?php foreach ($players as $i => $p): ?>
        <article id="<?= e($p['pemain_id']) ?>" class="scroll-mt-20 rounded-2xl bg-white p-4 ring-1 ring-slate-200">
          <div class="flex gap-4">
            <div class="w-24 shrink-0 sm:w-28">
              <?php if ($canDocs && $p['has_foto']): ?>
                <button type="button" class="doc-open block w-full" data-src="<?= e($doc('player', $p['pemain_id'], 'foto')) ?>" data-kind="image" data-title="Foto full body — <?= e($p['nama_lengkap']) ?>" data-download="<?= e($doc('player', $p['pemain_id'], 'foto', true)) ?>">
                  <img src="<?= e($doc('player', $p['pemain_id'], 'foto')) ?>" alt="Foto full body <?= e($p['nama_lengkap']) ?>" class="doc-thumb ring-1 ring-slate-200" loading="lazy">
                </button>
              <?php else: ?>
                <div class="doc-thumb flex items-center justify-center text-xs text-slate-400"><?= $p['has_foto'] ? 'Foto tersedia' : 'Tanpa foto' ?></div>
              <?php endif; ?>
            </div>
            <div class="min-w-0 flex-1">
              <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                  <p class="text-xs font-bold text-slate-500">#<?= $i + 1 ?> · No. <?= e($p['nomor_punggung']) ?> · <?= e($p['posisi_label']) ?></p>
                  <h3 class="truncate text-lg font-bold text-slate-900"><?= e($p['nama_lengkap']) ?></h3>
                </div>
                <?= status_badge($p['status_verifikasi']) ?>
              </div>
              <dl class="mt-2 grid grid-cols-1 gap-x-4 gap-y-1 text-xs sm:grid-cols-2">
                <div><dt class="inline text-slate-500">NIK:</dt> <dd class="inline font-mono font-semibold"><?= e($p['nik']) ?></dd></div>
                <div><dt class="inline text-slate-500">Lahir:</dt> <dd class="inline"><?= e($p['tempat_lahir']) ?>, <?= e(format_date_id($p['tanggal_lahir'])) ?></dd></div>
                <div><dt class="inline text-slate-500">JK:</dt> <dd class="inline"><?= e(GAMBASI_GENDERS[$p['jenis_kelamin']] ?? $p['jenis_kelamin']) ?></dd></div>
                <div><dt class="inline text-slate-500">Kategori:</dt> <dd class="inline"><?= e(category_label($p['kategori'])) ?></dd></div>
                <?php if ($sensitive): ?>
                  <div><dt class="inline text-slate-500">Ayah:</dt> <dd class="inline"><?= e($p['nama_ayah'] ?: '-') ?></dd></div>
                  <div><dt class="inline text-slate-500">Ibu:</dt> <dd class="inline"><?= e($p['nama_ibu'] ?: '-') ?></dd></div>
                  <div><dt class="inline text-slate-500">Wali:</dt> <dd class="inline"><?= e($p['nama_wali'] ?: '-') ?></dd></div>
                  <div><dt class="inline text-slate-500">WA Wali:</dt> <dd class="inline"><?= e($p['whatsapp_wali']) ?></dd></div>
                  <div class="sm:col-span-2"><dt class="inline text-slate-500">Alamat:</dt> <dd class="inline"><?= e($p['alamat']) ?></dd></div>
                <?php endif; ?>
              </dl>
              <?php if ($canDocs): ?>
                <div class="mt-3 flex flex-wrap gap-2">
                  <?php if ($p['has_akte']): ?>
                    <button type="button" class="doc-open btn-chip" data-src="<?= e($doc('player', $p['pemain_id'], 'akte')) ?>" data-kind="auto" data-title="Akte kelahiran — <?= e($p['nama_lengkap']) ?>" data-download="<?= e($doc('player', $p['pemain_id'], 'akte', true)) ?>">Lihat Akte</button>
                  <?php else: ?><span class="btn-chip btn-chip-danger">Akte tidak ada</span><?php endif; ?>
                  <?php if ($p['has_foto']): ?>
                    <button type="button" class="doc-open btn-chip" data-src="<?= e($doc('player', $p['pemain_id'], 'foto')) ?>" data-kind="image" data-title="Foto full body — <?= e($p['nama_lengkap']) ?>" data-download="<?= e($doc('player', $p['pemain_id'], 'foto', true)) ?>">Lihat Foto</button>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
          <div class="mt-4 border-t border-slate-100 pt-3">
            <?php render_view('admin/status-control', ['target' => 'player', 'id' => $p['pemain_id'], 'current' => $p['status_verifikasi'], 'notes' => $p['catatan_admin']]); ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>
<?php render_view('admin/doc-viewer'); ?>
<?php render_view('admin/footer'); ?>
