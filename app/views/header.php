<?php
/**
 * Header publik.
 * @var string $title
 * @var string $active  (home|daftar|cek)
 */
$active = $active ?? '';
$nav = [
    ['label' => 'Tentang', 'href' => url('/#tentang')],
    ['label' => 'Kategori', 'href' => url('/#kategori')],
    ['label' => 'Persyaratan', 'href' => url('/#persyaratan')],
    ['label' => 'Timeline', 'href' => url('/#timeline')],
    ['label' => 'FAQ', 'href' => url('/#faq')],
    ['label' => 'Cek Pendaftaran', 'href' => url('/cek-pendaftaran.php'), 'key' => 'cek'],
];
?>
<!doctype html>
<html lang="id" class="scroll-smooth">
<head>
<?php render_view('partials/head', ['title' => $title, 'description' => $description ?? null]); ?>
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-800 antialiased <?= e($bodyClass ?? '') ?>">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:font-semibold focus:text-royal-900 focus:shadow-lg">Lewati ke konten utama</a>

<header id="site-header" class="fixed inset-x-0 top-0 z-50 transition-colors duration-300 <?= $active === 'home' ? 'header-transparent' : 'header-solid' ?>">
  <div class="brand-stripe absolute inset-x-0 top-0" aria-hidden="true"></div>
  <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
    <a href="<?= e(url('/')) ?>" class="flex items-center gap-2.5 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gold-400" aria-label="Beranda GAMBASI Papua Selatan">
      <?= logo_img('mt-1 h-12 w-auto drop-shadow-lg', 'sm', '', true) ?>
      <span class="leading-none">
        <span class="text-gold-gradient block font-display text-xl font-extrabold tracking-wide">GAMBASI</span>
        <span class="block text-[10px] font-semibold uppercase tracking-[0.2em] text-gold-400">Papua Selatan 2026</span>
      </span>
    </a>

    <nav class="hidden items-center gap-1 lg:flex" aria-label="Navigasi utama">
      <?php foreach ($nav as $item): ?>
        <a href="<?= e($item['href']) ?>" class="rounded-lg px-3 py-2 text-sm font-medium text-white/85 transition hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gold-400 <?= ($item['key'] ?? '') === $active ? 'bg-white/10 text-white' : '' ?>"><?= e($item['label']) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('/daftar-sepakbola.php')) ?>" class="ml-2 rounded-xl bg-gold-500 px-4 py-2 text-sm font-bold uppercase tracking-wide text-ink-950 shadow-lg shadow-gold-500/20 transition hover:bg-gold-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white">Daftarkan Club</a>
    </nav>

    <button type="button" id="nav-toggle" class="inline-flex h-11 w-11 items-center justify-center rounded-xl text-white hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gold-400 lg:hidden" aria-controls="mobile-nav" aria-expanded="false" aria-label="Buka menu">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
    </button>
  </div>

  <nav id="mobile-nav" class="hidden border-t border-white/10 bg-royal-950/95 backdrop-blur lg:hidden" aria-label="Navigasi mobile">
    <div class="mx-auto flex max-w-7xl flex-col gap-1 px-4 py-3">
      <?php foreach ($nav as $item): ?>
        <a href="<?= e($item['href']) ?>" class="rounded-lg px-3 py-3 text-base font-medium text-white/90 hover:bg-white/10"><?= e($item['label']) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('/daftar-sepakbola.php')) ?>" class="mt-2 rounded-xl bg-gold-500 px-4 py-3 text-center text-base font-bold uppercase tracking-wide text-ink-950">Daftarkan Club</a>
    </div>
  </nav>
</header>
