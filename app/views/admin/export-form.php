<?php
/**
 * Tombol export CSV. @var string $dataset clubs|players
 */
if (!admin_can('export')) {
    return;
}
?>
<form method="post" action="<?= e(url('/admin/export.php')) ?>" class="flex flex-wrap items-center gap-2">
  <?= csrf_field() ?>
  <input type="hidden" name="dataset" value="<?= e($dataset) ?>">
  <input type="hidden" name="kategori" value="<?= e($kategori ?? '') ?>">
  <input type="hidden" name="status" value="<?= e($status ?? '') ?>">
  <?php if ($dataset === 'players' && admin_can('export_sensitive')): ?>
    <label class="flex items-center gap-2 text-sm text-slate-700">
      <input type="checkbox" name="sensitive" value="1" class="h-4 w-4 rounded border-slate-300 text-royal-700">
      Sertakan data sensitif (NIK, orang tua/wali)
    </label>
  <?php endif; ?>
  <button type="submit" class="btn-secondary">
    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v12M7 11l5 5 5-5M4 20h16"/></svg>
    Export CSV
  </button>
</form>
