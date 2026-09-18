<?php
/**
 * Form filter admin (auto-submit dengan debounce).
 * @var string $q
 * @var string $kategori
 * @var string $status
 * @var string $placeholder
 * @var array  $extra  [name => [label, options, value]]
 */
$statusOptions = array_map(fn($m) => $m['label'], config('status'));
?>
<form method="get" class="auto-filter grid gap-3 rounded-2xl bg-white p-4 ring-1 ring-slate-200 sm:grid-cols-2 lg:grid-cols-4" role="search">
  <div class="sm:col-span-2 lg:col-span-1">
    <label for="f-q" class="field-label">Cari</label>
    <input id="f-q" name="q" type="search" value="<?= e($q) ?>" class="field-input" placeholder="<?= e($placeholder) ?>" data-debounce>
  </div>
  <div>
    <label for="f-kategori" class="field-label">Kategori</label>
    <select id="f-kategori" name="kategori" class="field-input"><?= select_options(config('event.kategori'), $kategori) ?></select>
  </div>
  <div>
    <label for="f-status" class="field-label">Status</label>
    <select id="f-status" name="status" class="field-input"><?= select_options($statusOptions, $status) ?></select>
  </div>
  <?php foreach (($extra ?? []) as $name => [$label, $options, $value]): ?>
    <div>
      <label for="f-<?= e($name) ?>" class="field-label"><?= e($label) ?></label>
      <select id="f-<?= e($name) ?>" name="<?= e($name) ?>" class="field-input"><?= select_options($options, $value) ?></select>
    </div>
  <?php endforeach; ?>
  <div class="flex items-end gap-2">
    <button type="submit" class="btn-primary flex-1">Terapkan</button>
    <a href="?" class="btn-secondary">Reset</a>
  </div>
</form>
