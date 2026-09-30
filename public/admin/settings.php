<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

require_admin('view');

$flash = null;
$errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_admin('settings');
    if (!verify_csrf()) {
        $flash = ['error', 'Sesi halaman kedaluwarsa. Silakan coba lagi.'];
    } else {
        $keys = ['REGISTRATION_OPEN', 'MIN_AGE_U10', 'MAX_AGE_U10', 'MIN_AGE_U12', 'MAX_AGE_U12',
            'AGE_REFERENCE_DATE', 'MIN_PLAYERS', 'MAX_PLAYERS'];
        $input = [];
        foreach ($keys as $k) {
            $input[$k] = is_string($_POST[$k] ?? null) ? trim($_POST[$k]) : '';
        }
        $input['REGISTRATION_OPEN'] = isset($_POST['REGISTRATION_OPEN']) ? 'true' : 'false';
        $res = gas_admin('admin_update_settings', ['settings' => $input]);
        if (!empty($res['success'])) {
            gas_public_config_clear();
            $_SESSION['_flash_settings'] = 'Pengaturan tersimpan.';
            redirect(url('/admin/settings.php'));
        }
        $flash = ['error', $res['message'] ?? 'Gagal menyimpan pengaturan.'];
        $errors = $res['errors'] ?? [];
    }
}
if (!empty($_SESSION['_flash_settings'])) {
    $flash = ['success', $_SESSION['_flash_settings']];
    unset($_SESSION['_flash_settings']);
}

$res = gas_admin('admin_get_settings');
$d = $res['data'] ?? [];
$s = $d['settings'] ?? [];
$canEdit = !empty($d['can_edit']) && admin_can('settings');
$apiConfigured = config('api.url') !== '' && config('api.secret') !== '';

render_view('admin/header', ['title' => 'Pengaturan', 'active' => 'settings']);
?>
<?php if ($flash): ?>
  <div class="notice <?= $flash[0] === 'success' ? 'notice-info' : 'notice-error' ?> mb-4" role="<?= $flash[0] === 'success' ? 'status' : 'alert' ?>">
    <?= e($flash[1]) ?>
    <?php if ($errors): ?><ul class="mt-2 list-disc pl-5 text-sm"><?php foreach ($errors as $er): ?><li><?= e($er['field'] . ': ' . $er['message']) ?></li><?php endforeach; ?></ul><?php endif; ?>
  </div>
<?php endif; ?>

<div class="grid gap-5 lg:grid-cols-3">
  <section class="rounded-2xl bg-white p-5 ring-1 ring-slate-200" aria-labelledby="health-title">
    <h2 id="health-title" class="font-display text-xl font-bold uppercase text-slate-900">Status Sistem</h2>
    <ul class="mt-3 space-y-2 text-sm">
      <?php
      $checks = [
          'Konfigurasi API (PHP .env)' => $apiConfigured,
          'Koneksi Apps Script' => !empty($res['success']),
          'Google Sheets' => !empty($d['health']['spreadsheet']),
          'Google Drive' => !empty($d['health']['drive']),
          'Akun penyimpanan resmi' => !empty($d['storage']['verified']),
          'Aturan usia U-10' => !empty($d['age_rules']['U10']),
          'Aturan usia U-12' => !empty($d['age_rules']['U12']),
      ];
      foreach ($checks as $label => $ok): ?>
        <li class="flex items-center justify-between gap-3">
          <span><?= e($label) ?></span>
          <span class="rounded-full px-2 py-0.5 text-xs font-bold <?= $ok ? 'bg-royal-100 text-royal-800' : 'bg-rose-100 text-rose-700' ?>"><?= $ok ? 'OK' : 'Belum' ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
    <dl class="mt-4 space-y-1 border-t border-slate-100 pt-3 text-xs text-slate-500">
      <div>Environment Apps Script: <strong><?= e($d['environment'] ?? '-') ?></strong></div>
      <div>Versi backend: <strong><?= e($d['version'] ?? '-') ?></strong></div>
      <div>Environment PHP: <strong><?= e(config('app.env')) ?></strong></div>
    </dl>
    <?php if (empty($res['success'])): ?><div class="mt-3"><?= api_error_notice($res) ?></div><?php endif; ?>
  </section>

  <section class="rounded-2xl bg-white p-5 ring-1 ring-slate-200 lg:col-span-2" aria-labelledby="reg-title">
    <h2 id="reg-title" class="font-display text-xl font-bold uppercase text-slate-900">Ketentuan Pendaftaran</h2>
    <p class="mt-1 text-sm text-slate-600">Isi sesuai regulasi resmi panitia. Usia dihitung dalam tahun penuh pada <em>tanggal acuan usia</em>. Selama aturan usia kosong, sistem menolak pendaftaran.</p>
    <?php if (!empty($res['success'])): ?>
    <form method="post" class="mt-5 space-y-5">
      <?= csrf_field() ?>
      <fieldset <?= $canEdit ? '' : 'disabled' ?> class="space-y-5">
        <label class="flex items-center gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200">
          <input type="checkbox" name="REGISTRATION_OPEN" value="true" class="h-5 w-5 rounded border-slate-300 text-royal-700" <?= !empty($d['registration_open']) ? 'checked' : '' ?>>
          <span><span class="font-semibold text-slate-900">Pendaftaran dibuka</span><br><span class="text-sm text-slate-500">Jika tidak dicentang, formulir tidak dapat dikirim.</span></span>
        </label>
        <?php $win = registration_window(); ?>
        <p class="rounded-xl px-4 py-3 text-sm ring-1 <?= $win === 'open' ? 'bg-royal-50 text-royal-800 ring-royal-200' : 'bg-amber-50 text-amber-900 ring-amber-200' ?>">
          Jadwal resmi (otomatis): dibuka <strong><?= e(info_or_tba('pendaftaran_buka')) ?></strong>, ditutup <strong><?= e(info_or_tba('pendaftaran_tutup')) ?></strong>.
          Saat ini: <strong><?= e(['before' => 'belum masuk jadwal', 'open' => 'dalam jadwal', 'after' => 'jadwal sudah lewat'][$win]) ?></strong>.
          Formulir hanya bisa dikirim bila saklar di atas aktif <em>dan</em> dalam jadwal. Jadwal diubah lewat <code>PENDAFTARAN_BUKA_ISO</code> / <code>PENDAFTARAN_TUTUP_ISO</code> di file <code>.env</code>.
        </p>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label for="AGE_REFERENCE_DATE" class="field-label">Tanggal acuan usia</label>
            <input type="date" id="AGE_REFERENCE_DATE" name="AGE_REFERENCE_DATE" value="<?= e($s['AGE_REFERENCE_DATE'] ?? '') ?>" class="field-input">
          </div>
          <div></div>
          <?php foreach (['U10' => 'U-10', 'U12' => 'U-12'] as $code => $label): ?>
            <div>
              <label for="MIN_AGE_<?= $code ?>" class="field-label">Usia minimum <?= $label ?> (tahun)</label>
              <input type="number" min="0" max="99" id="MIN_AGE_<?= $code ?>" name="MIN_AGE_<?= $code ?>" value="<?= e($s['MIN_AGE_' . $code] ?? '') ?>" class="field-input">
            </div>
            <div>
              <label for="MAX_AGE_<?= $code ?>" class="field-label">Usia maksimum <?= $label ?> (tahun)</label>
              <input type="number" min="0" max="99" id="MAX_AGE_<?= $code ?>" name="MAX_AGE_<?= $code ?>" value="<?= e($s['MAX_AGE_' . $code] ?? '') ?>" class="field-input">
            </div>
          <?php endforeach; ?>
          <div>
            <label for="MIN_PLAYERS" class="field-label">Minimal pemain per club</label>
            <input type="number" min="1" max="40" id="MIN_PLAYERS" name="MIN_PLAYERS" value="<?= e($s['MIN_PLAYERS'] ?? '') ?>" class="field-input" placeholder="Default 1">
          </div>
          <div>
            <label for="MAX_PLAYERS" class="field-label">Maksimal pemain per club</label>
            <input type="number" min="1" max="40" id="MAX_PLAYERS" name="MAX_PLAYERS" value="<?= e($s['MAX_PLAYERS'] ?? '') ?>" class="field-input" placeholder="Batas teknis 40">
          </div>
        </div>
      </fieldset>
      <?php if ($canEdit): ?>
        <button type="submit" class="btn-primary">Simpan Pengaturan</button>
      <?php else: ?>
        <p class="text-sm text-slate-500">Hanya SUPERADMIN yang dapat mengubah pengaturan.</p>
      <?php endif; ?>
    </form>
    <?php endif; ?>
  </section>

  <?php if (!empty($d['storage'])): $st = $d['storage']; ?>
  <section class="rounded-2xl bg-white p-5 ring-1 ring-slate-200 lg:col-span-3" aria-labelledby="storage-title">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h2 id="storage-title" class="font-display text-xl font-bold uppercase text-slate-900">Penyimpanan Data (Google Drive)</h2>
        <p class="mt-1 text-sm text-slate-600">Database dan seluruh dokumen peserta disimpan secara privat di Google Drive akun resmi panitia.</p>
      </div>
      <a href="<?= e($st['drive_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn-secondary">Buka Google Drive ↗</a>
    </div>
    <?php if (!empty($st['mismatch'])): ?>
      <div class="notice notice-error mt-4" role="alert">
        <strong>Akun penyimpanan salah.</strong> Apps Script berjalan sebagai <strong><?= e($st['running_as']) ?></strong>, bukan <strong><?= e($st['owner_email']) ?></strong>.
        Pendaftaran baru diblokir sampai Apps Script di-deploy ulang dari akun resmi.
      </div>
    <?php elseif (empty($st['verified'])): ?>
      <div class="notice notice-warn mt-4" role="status">Email akun Apps Script tidak dapat diverifikasi. Pastikan project dibuat & di-deploy dari <strong><?= e($st['owner_email']) ?></strong>.</div>
    <?php endif; ?>
    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
      <div class="rounded-xl bg-slate-50 p-3"><dt class="text-xs text-slate-500">Akun resmi</dt><dd class="font-semibold text-slate-900"><?= e($st['owner_email']) ?></dd></div>
      <div class="rounded-xl bg-slate-50 p-3"><dt class="text-xs text-slate-500">Apps Script berjalan sebagai</dt><dd class="font-semibold <?= !empty($st['verified']) ? 'text-emerald-700' : 'text-rose-700' ?>"><?= e($st['running_as'] ?: '(tidak terdeteksi)') ?></dd></div>
      <div class="rounded-xl bg-slate-50 p-3"><dt class="text-xs text-slate-500">Spreadsheet</dt><dd class="font-semibold text-slate-900"><?= e($st['spreadsheet_name']) ?></dd></div>
      <div class="rounded-xl bg-slate-50 p-3"><dt class="text-xs text-slate-500">Folder Drive</dt><dd class="font-semibold text-slate-900"><?= e($st['root_folder_name']) ?></dd></div>
    </dl>
    <p class="mt-3 text-xs text-slate-500">Buka Google Drive dengan login sebagai <?= e($st['owner_email']) ?> untuk melihat folder & spreadsheet. Jangan ubah pengaturan berbagi menjadi “Anyone with the link”.</p>
  </section>
  <?php endif; ?>

  <section class="rounded-2xl bg-white p-5 ring-1 ring-slate-200 lg:col-span-3" aria-labelledby="info-title">
    <h2 id="info-title" class="font-display text-xl font-bold uppercase text-slate-900">Informasi Publik (file .env)</h2>
    <p class="mt-1 text-sm text-slate-600">Kontak panitia, biaya, dan jadwal diatur di file <code>.env</code> server. Nilai kosong ditampilkan sebagai “Akan diumumkan panitia”.</p>
    <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach (array_filter(config('info'), 'is_string') as $k => $v): ?>
        <div class="rounded-xl bg-slate-50 p-3"><dt class="font-mono text-xs text-slate-500"><?= e($k) ?></dt><dd class="font-semibold <?= $v === '' ? 'text-slate-400' : 'text-slate-900' ?>"><?= e($v !== '' ? $v : '(belum diisi)') ?></dd></div>
      <?php endforeach; ?>
    </dl>
  </section>
</div>
<?php render_view('admin/footer'); ?>
