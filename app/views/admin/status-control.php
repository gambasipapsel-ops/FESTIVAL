<?php
/**
 * Kontrol update status + catatan.
 * @var string $target club|player
 * @var string $id
 * @var string $current
 * @var string $notes
 */
$canUpdate = admin_can('update_status');
$canNote = admin_can('add_note');
$uid = 'sc-' . e($id);
?>
<div class="status-control space-y-3" data-target="<?= e($target) ?>" data-id="<?= e($id) ?>">
  <?php if ($canUpdate): ?>
    <div class="flex flex-wrap gap-1.5" role="group" aria-label="Ubah status">
      <?php foreach (config('status') as $code => $meta): ?>
        <button type="button" data-status="<?= e($code) ?>" aria-pressed="<?= $code === $current ? 'true' : 'false' ?>"
                class="status-btn rounded-lg px-2.5 py-1.5 text-xs font-bold ring-1 ring-inset transition <?= $code === $current ? 'bg-slate-900 text-white ring-slate-900' : 'bg-white text-slate-700 ring-slate-300 hover:bg-slate-50' ?>">
          <?= e($code) ?>
        </button>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if ($canUpdate || $canNote): ?>
    <div>
      <label for="<?= $uid ?>-note" class="sr-only">Catatan admin</label>
      <textarea id="<?= $uid ?>-note" class="field-input note-input text-sm" rows="2" maxlength="500" placeholder="Catatan (wajib untuk REVISION/REJECTED)"></textarea>
      <?php if ($canNote): ?>
        <button type="button" class="note-btn btn-chip mt-2">Simpan catatan saja</button>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if ($notes !== ''): ?>
    <details class="rounded-xl bg-slate-50 p-3 text-xs text-slate-700 ring-1 ring-slate-200" <?= $target === 'club' ? 'open' : '' ?>>
      <summary class="cursor-pointer font-semibold">Riwayat catatan</summary>
      <pre class="mt-2 whitespace-pre-wrap font-sans"><?= e($notes) ?></pre>
    </details>
  <?php endif; ?>
</div>
