<?php
/**
 * Tautan media sosial resmi + email.
 * @var string $variant dark|light
 * @var bool   $withLabel tampilkan nama akun
 */
$variant = $variant ?? 'dark';
$withLabel = $withLabel ?? false;
$email = (string) config('info.kontak_email');
$icons = [
    'facebook'  => 'M13.5 21v-7.5h2.53l.38-2.94H13.5V8.69c0-.85.24-1.43 1.46-1.43h1.56V4.63a21 21 0 00-2.27-.12c-2.25 0-3.79 1.37-3.79 3.9v2.17H7.9v2.94h2.56V21h3.04z',
    'instagram' => 'M12 7.3a4.7 4.7 0 100 9.4 4.7 4.7 0 000-9.4zm0 7.75a3.05 3.05 0 110-6.1 3.05 3.05 0 010 6.1zm5.98-7.94a1.1 1.1 0 11-2.2 0 1.1 1.1 0 012.2 0zM21.1 8.2c-.07-1.48-.4-2.8-1.49-3.88-1.08-1.08-2.4-1.42-3.88-1.5-1.53-.08-6.12-.08-7.65 0-1.48.07-2.8.41-3.88 1.49S2.78 6.71 2.7 8.19c-.09 1.53-.09 6.12 0 7.65.07 1.48.41 2.8 1.5 3.88 1.08 1.08 2.39 1.42 3.87 1.5 1.53.08 6.12.08 7.65 0 1.48-.07 2.8-.41 3.88-1.5 1.08-1.08 1.42-2.4 1.5-3.88.08-1.53.08-6.11 0-7.64zm-1.98 9.28a3.13 3.13 0 01-1.76 1.76c-1.22.49-4.11.37-5.46.37s-4.24.11-5.46-.37a3.13 3.13 0 01-1.76-1.76c-.48-1.22-.37-4.11-.37-5.46s-.11-4.24.37-5.46A3.13 3.13 0 016.44 4.8c1.22-.48 4.11-.37 5.46-.37s4.24-.11 5.46.37a3.13 3.13 0 011.76 1.76c.49 1.22.37 4.11.37 5.46s.12 4.24-.37 5.46z',
    'tiktok'    => 'M16.6 5.82A4.28 4.28 0 0115.54 3h-3.09v12.4a2.59 2.59 0 01-2.59 2.5 2.6 2.6 0 01-2.6-2.6 2.6 2.6 0 013.37-2.49V9.66a5.73 5.73 0 00-.77-.05A5.7 5.7 0 004.16 15.3 5.7 5.7 0 009.86 21a5.7 5.7 0 005.7-5.7V9.01a7.35 7.35 0 004.3 1.38V7.3a4.3 4.3 0 01-3.26-1.48z',
    'email'     => 'M3 5h18a1 1 0 011 1v12a1 1 0 01-1 1H3a1 1 0 01-1-1V6a1 1 0 011-1zm17 2.24l-7.4 5.6a1 1 0 01-1.2 0L4 7.24V17h16V7.24zM5.03 7l6.97 5.27L18.97 7H5.03z',
];
$items = [];
foreach (config('social', []) as $key => $s) {
    if (!empty($s['url']) && preg_match('#^https://#', $s['url'])) {
        $items[] = ['key' => $key, 'label' => $s['label'], 'handle' => $s['handle'], 'href' => $s['url'], 'external' => true];
    }
}
if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $items[] = ['key' => 'email', 'label' => 'Email', 'handle' => $email, 'href' => 'mailto:' . $email, 'external' => false];
}
$btn = $variant === 'dark'
    ? 'bg-white/10 text-white ring-1 ring-white/15 hover:bg-gold-500 hover:text-ink-950'
    : 'bg-white text-royal-800 ring-1 ring-slate-200 hover:bg-royal-700 hover:text-white';
?>
<ul class="flex flex-wrap gap-2 <?= $withLabel ? 'flex-col sm:flex-row' : '' ?>" aria-label="Media sosial dan email resmi">
  <?php foreach ($items as $it): ?>
    <li>
      <a href="<?= e($it['href']) ?>"<?= $it['external'] ? ' target="_blank" rel="noopener noreferrer"' : '' ?>
         class="inline-flex items-center gap-2 rounded-xl transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gold-400 <?= $btn ?> <?= $withLabel ? 'px-3 py-2 text-sm font-semibold' : 'h-10 w-10 justify-center' ?>"
         aria-label="<?= e($it['label'] . ' ' . $it['handle']) ?>" title="<?= e($it['label'] . ': ' . $it['handle']) ?>">
        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="<?= e($icons[$it['key']]) ?>"/></svg>
        <?php if ($withLabel): ?><span class="break-all"><?= e($it['handle']) ?></span><?php endif; ?>
      </a>
    </li>
  <?php endforeach; ?>
</ul>
