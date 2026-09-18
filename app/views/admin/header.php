<?php
/**
 * Layout admin.
 * @var string $title
 * @var string $active
 */
$admin = $_SESSION['admin'] ?? null;
$menu = [
    'dashboard'    => ['Dashboard', '/admin/', 'view', 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10'],
    'clubs'        => ['Club', '/admin/clubs.php', 'view', 'M12 2l8 4v6c0 5-3.5 9-8 10-4.5-1-8-5-8-10V6l8-4z'],
    'players'      => ['Pemain', '/admin/players.php', 'view', 'M16 11a4 4 0 10-8 0 4 4 0 008 0zM4 21a8 8 0 0116 0'],
    'verification' => ['Verifikasi', '/admin/verification.php', 'update_status', 'M9 12l2 2 4-4M12 3a9 9 0 110 18 9 9 0 010-18z'],
    'documents'    => ['Dokumen', '/admin/documents.php', 'view_documents', 'M7 3h7l5 5v13H7V3zM14 3v5h5'],
    'logs'         => ['Log', '/admin/logs.php', 'view_logs', 'M4 6h16M4 12h16M4 18h10'],
    'settings'     => ['Pengaturan', '/admin/settings.php', 'view', 'M12 15a3 3 0 100-6 3 3 0 000 6zM19.4 15a1.7 1.7 0 00.3 1.8l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.8-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 11-4 0v-.1a1.7 1.7 0 00-1.1-1.5 1.7 1.7 0 00-1.8.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.8 1.7 1.7 0 00-1.5-1H3a2 2 0 110-4h.1a1.7 1.7 0 001.5-1.1 1.7 1.7 0 00-.3-1.8l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.8.3H9a1.7 1.7 0 001-1.5V3a2 2 0 114 0v.1a1.7 1.7 0 001 1.5 1.7 1.7 0 001.8-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.8V9a1.7 1.7 0 001.5 1H21a2 2 0 110 4h-.1a1.7 1.7 0 00-1.5 1z'],
];
?>
<!doctype html>
<html lang="id">
<head>
<?php render_view('partials/head', ['title' => $title . ' · Admin GAMBASI', 'description' => 'Panel admin GAMBASI Papua Selatan 2026']); ?>
<meta name="robots" content="noindex, nofollow">
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-800 antialiased">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2">Lewati ke konten</a>
<div class="lg:flex">
  <aside id="admin-sidebar" class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full bg-royal-950 text-white transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0" aria-label="Menu admin">
    <div class="brand-stripe" aria-hidden="true"></div>
    <div class="flex h-16 items-center gap-2.5 border-b border-white/10 px-5">
      <?= logo_img('h-11 w-auto', 'sm', '', true) ?>
      <div class="leading-none">
        <p class="font-display text-lg font-extrabold tracking-wide">GAMBASI</p>
        <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-gold-400">Panel Admin</p>
      </div>
    </div>
    <nav class="space-y-1 p-3">
      <?php foreach ($menu as $key => [$label, $href, $perm, $icon]):
          if ($admin && !in_array($perm, config('admin.roles')[$admin['role']] ?? [], true)) continue;
          $isActive = $key === ($active ?? ''); ?>
        <a href="<?= e(url($href)) ?>" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gold-400 <?= $isActive ? 'bg-white/10 text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
          <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="<?= e($icon) ?>"/></svg>
          <?= e($label) ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <?php if ($admin): ?>
    <div class="absolute inset-x-0 bottom-0 border-t border-white/10 p-4">
      <p class="truncate text-sm font-semibold"><?= e($admin['name']) ?></p>
      <p class="text-xs text-gold-400"><?= e($admin['role']) ?></p>
      <form method="post" action="<?= e(url('/admin/logout.php')) ?>" class="mt-3">
        <?= csrf_field() ?>
        <button type="submit" class="w-full rounded-lg border border-white/20 px-3 py-2 text-sm font-semibold text-white hover:bg-white/10">Logout</button>
      </form>
    </div>
    <?php endif; ?>
  </aside>
  <div id="admin-backdrop" class="fixed inset-0 z-30 hidden bg-black/40 lg:hidden"></div>

  <div class="min-w-0 flex-1">
    <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6">
      <button type="button" id="admin-menu-toggle" class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-slate-700 hover:bg-slate-100 lg:hidden" aria-controls="admin-sidebar" aria-expanded="false" aria-label="Buka menu admin">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
      </button>
      <h1 class="truncate font-display text-2xl font-bold uppercase tracking-wide text-slate-900"><?= e($title) ?></h1>
      <a href="<?= e(url('/')) ?>" target="_blank" rel="noopener" class="ml-auto hidden text-sm font-medium text-slate-500 hover:text-royal-700 sm:inline">Lihat situs ↗</a>
    </header>
    <main id="main" class="mx-auto max-w-7xl p-4 sm:p-6">
