<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

if (admin_current()) {
    redirect(url('/admin/'));
}

$error = '';
$username = '';
$notice = $_SESSION['_flash_login'] ?? '';
unset($_SESSION['_flash_login']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $username = clean_text($_POST['username'] ?? '', 60);
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    if (!verify_csrf()) {
        $error = 'Sesi halaman kedaluwarsa. Silakan coba lagi.';
    } elseif ($username === '' || $password === '' || strlen($password) > 200) {
        $error = 'Username dan password wajib diisi.';
    } elseif (!admin_users()) {
        $error = 'Belum ada akun admin. Buat akun melalui perintah: php tools/create-admin.php';
    } else {
        $result = admin_attempt_login($username, $password);
        if ($result['ok']) {
            redirect(url('/admin/'));
        }
        $error = $result['message'];
        usleep(random_int(200000, 500000));
    }
}
header('Cache-Control: no-store');
?>
<!doctype html>
<html lang="id">
<head>
<?php render_view('partials/head', ['title' => 'Login Admin · GAMBASI Papua Selatan 2026', 'description' => 'Login panel admin']); ?>
<meta name="robots" content="noindex, nofollow">
</head>
<body class="min-h-screen bg-royal-950 font-sans antialiased">
<div class="hero-pitch fixed inset-0 opacity-40" aria-hidden="true"></div>
<main id="main" class="relative flex min-h-screen items-center justify-center px-4 py-10">
  <div class="w-full max-w-sm">
    <div class="mb-6 flex flex-col items-center text-center text-white">
      <?= logo_img('hero-logo h-40 w-auto', 'lg', 'Logo GAMBASI', true) ?>
      <p class="mt-3 font-display text-3xl font-extrabold tracking-wide">GAMBASI</p>
      <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gold-400">Panel Admin Panitia</p>
    </div>
    <form method="post" class="rounded-[1.75rem] bg-white p-6 shadow-2xl sm:p-8" novalidate>
      <h1 class="font-display text-2xl font-bold uppercase text-slate-900">Masuk</h1>
      <?php if ($notice): ?><p class="notice notice-info mt-4 text-sm" role="status"><?= e($notice) ?></p><?php endif; ?>
      <?php if ($error): ?><p class="notice notice-error mt-4 text-sm" role="alert"><?= e($error) ?></p><?php endif; ?>
      <?= csrf_field() ?>
      <div class="mt-5">
        <label for="username" class="field-label">Username</label>
        <input id="username" name="username" class="field-input" value="<?= e($username) ?>" required autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="60" autofocus>
      </div>
      <div class="mt-4">
        <label for="password" class="field-label">Password</label>
        <input id="password" name="password" type="password" class="field-input" required autocomplete="current-password" maxlength="200">
      </div>
      <button type="submit" class="btn-primary mt-6 w-full">Masuk</button>
      <p class="mt-4 text-center text-xs text-slate-500">Akses terbatas untuk panitia yang berwenang. Aktivitas login dicatat.</p>
    </form>
    <p class="mt-6 text-center"><a href="<?= e(url('/')) ?>" class="text-sm font-semibold text-white/70 hover:text-white">← Kembali ke situs</a></p>
  </div>
</main>
</body>
</html>
