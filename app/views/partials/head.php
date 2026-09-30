<?php
/** @var string $title */
/** @var string $description */
$nonce = csp_nonce();
$useBuild = config('app.tailwind_mode') !== 'cdn' && is_file(GAMBASI_ROOT . '/public/assets/css/tailwind.css');
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description ?? config('event.kegiatan') . ' — ' . config('event.kompetisi')) ?>">
<meta name="theme-color" content="#0c1238">
<meta property="og:image" content="<?= e(preg_replace('#^(https?://[^/]+).*$#', '$1', (string) config('app.url')) . url('/assets/images/logo-gambasi.png')) ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="app-base" content="<?= e(base_path()) ?>">
<link rel="icon" href="<?= e(asset('icons/favicon-32.png')) ?>" type="image/png" sizes="32x32">
<link rel="icon" href="<?= e(asset('icons/favicon-64.png')) ?>" type="image/png" sizes="64x64">
<link rel="apple-touch-icon" href="<?= e(asset('icons/apple-touch-icon.png')) ?>">
<link rel="preload" as="image" href="<?= e(asset('images/logo-gambasi-sm.webp')) ?>" type="image/webp">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<?php if ($useBuild): ?>
<link rel="stylesheet" href="<?= e(asset('css/tailwind.css')) ?>">
<?php else: ?>
<script nonce="<?= e($nonce) ?>" src="https://cdn.tailwindcss.com"></script>
<script nonce="<?= e($nonce) ?>">
  tailwind.config = {
    theme: {
      extend: {
        fontFamily: {
          sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
          display: ['"Barlow Condensed"', 'Inter', 'sans-serif']
        },
        colors: {
          royal: { 50: '#eef2ff', 100: '#dde5ff', 200: '#bfcdff', 300: '#93a9fb', 400: '#6380f5', 500: '#3b5bec', 600: '#2542d6', 700: '#1d33b3', 800: '#1c2c8c', 900: '#1b276b', 950: '#0c1238' },
          flame: { 300: '#fca5b1', 400: '#f25a6e', 500: '#e3263f', 600: '#c41b33', 700: '#9f162a' },
          gold: { 300: '#fde68a', 400: '#fcd34d', 500: '#f5b400', 600: '#d99a00', 700: '#9a6700' },
          ink: { 900: '#0b1320', 950: '#060b14' }
        }
      }
    }
  };
</script>
<?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
