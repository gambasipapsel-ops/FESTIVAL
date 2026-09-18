<?php
/**
 * Tombol "Tanya Admin via WhatsApp".
 * @var string $context  konteks tambahan pada pesan (tanpa data sensitif)
 * @var string $label
 * @var string $variant  solid|outline
 * @var string $id
 */
$link = admin_whatsapp_link($context ?? '');
if ($link === '') {
    return;
}
$variant = $variant ?? 'solid';
$cls = $variant === 'outline'
    ? 'border border-[#25D366] bg-white text-[#128C7E] hover:bg-[#25D366]/10'
    : 'bg-[#25D366] text-white shadow-lg shadow-[#25D366]/25 hover:bg-[#1ebe5b]';
?>
<a href="<?= e($link) ?>" target="_blank" rel="noopener noreferrer"<?= !empty($id) ? ' id="' . e($id) . '"' : '' ?>
   class="inline-flex min-h-[3rem] shrink-0 items-center whitespace-nowrap justify-center gap-2 rounded-xl px-5 py-3 text-sm font-bold transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#25D366]/40 <?= $cls ?>">
  <?php render_view('partials/wa-icon', ['class' => 'h-5 w-5']); ?>
  <span><?= e($label ?? 'Tanya Admin via WhatsApp') ?></span>
</a>
