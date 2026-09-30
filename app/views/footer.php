<?php
/** Footer publik. @var array $scripts */
$ev = config('event');
$wa = admin_whatsapp_link();
?>
<footer class="bg-royal-950 text-slate-300">
  <div class="brand-stripe" aria-hidden="true"></div>
  <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-3 lg:px-8">
    <div>
      <div class="flex items-center gap-3">
        <?= logo_img('h-20 w-auto', 'sm', 'Logo GAMBASI') ?>
        <div>
          <p class="font-display text-2xl font-extrabold tracking-wide text-white"><?= e($ev['nama']) ?></p>
          <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gold-400"><?= e($ev['wilayah']) ?></p>
        </div>
      </div>
      <p class="mt-4 text-sm leading-relaxed text-slate-400"><?= e($ev['kegiatan']) ?><br><?= e($ev['kompetisi']) ?></p>
      <p class="mt-3 text-sm italic text-slate-400">“<?= e($ev['tema']) ?>”</p>
      <p class="mt-6 text-xs font-bold uppercase tracking-widest text-gold-400">Ikuti kami</p>
      <div class="mt-3"><?php render_view('partials/social-links', ['variant' => 'dark']); ?></div>
    </div>

    <div>
      <h2 class="font-display text-lg font-bold uppercase tracking-wider text-white">Informasi Event</h2>
      <dl class="mt-4 space-y-3 text-sm">
        <div><dt class="text-slate-500">Tanggal</dt><dd class="font-semibold text-slate-200"><?= e($ev['tanggal']) ?></dd></div>
        <div><dt class="text-slate-500">Lokasi</dt><dd class="font-semibold text-slate-200"><?= e($ev['lokasi']) ?></dd></div>
        <div><dt class="text-slate-500">Kategori</dt><dd class="font-semibold text-slate-200">U-10 &amp; U-12</dd></div>
      </dl>
    </div>

    <div>
      <h2 class="font-display text-lg font-bold uppercase tracking-wider text-white">Kontak Panitia</h2>
      <ul class="mt-4 space-y-3 text-sm">
        <?php if (has_info('kontak_nama')): ?><li class="font-semibold text-slate-200"><?= e(config('info.kontak_nama')) ?></li><?php endif; ?>
        <li>
          <span class="text-slate-500">WhatsApp:</span>
          <?php if ($wa): ?>
            <a class="font-semibold text-gold-400 hover:underline" href="<?= e($wa) ?>" target="_blank" rel="noopener noreferrer"><?= e(config('info.kontak_whatsapp')) ?></a>
          <?php else: ?><span class="text-slate-300">Akan diumumkan panitia</span><?php endif; ?>
        </li>
        <?php foreach (admin_contacts() as $c): ?>
          <li>
            <span class="text-slate-500">Admin <?= e($c['nama']) ?>:</span>
            <a class="font-semibold text-gold-400 hover:underline" href="<?= e($c['link']) ?>" target="_blank" rel="noopener noreferrer"><?= e($c['nomor']) ?></a>
          </li>
        <?php endforeach; ?>
        <li>
          <span class="text-slate-500">Email:</span>
          <?php if (has_info('kontak_email')): ?>
            <a class="font-semibold text-gold-400 hover:underline" href="mailto:<?= e(config('info.kontak_email')) ?>"><?= e(config('info.kontak_email')) ?></a>
          <?php else: ?><span class="text-slate-300">Akan diumumkan panitia</span><?php endif; ?>
        </li>
        <?php foreach (config('social', []) as $s): ?>
          <li>
            <span class="text-slate-500"><?= e($s['label']) ?>:</span>
            <a class="font-semibold text-gold-400 hover:underline" href="<?= e($s['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($s['handle']) ?></a>
          </li>
        <?php endforeach; ?>
        <li><span class="text-slate-500">Sekretariat:</span> <span class="text-slate-300"><?= e(info_or_tba('sekretariat')) ?></span></li>
      </ul>
      <div class="mt-6 flex flex-wrap gap-2">
        <a href="<?= e(url('/daftar-sepakbola.php')) ?>" class="rounded-lg bg-gold-500 px-4 py-2 text-sm font-bold text-ink-950 hover:bg-gold-400">Daftar Club</a>
        <a href="<?= e(url('/cek-pendaftaran.php')) ?>" class="rounded-lg border border-white/20 px-4 py-2 text-sm font-semibold text-white hover:bg-white/10">Cek Pendaftaran</a>
      </div>
    </div>
  </div>
  <div class="border-t border-white/10">
    <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-6 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
      <p>&copy; 2026 <?= e($ev['nama']) ?> · <?= e($ev['kompetisi']) ?></p>
      <p>Data peserta dilindungi dan hanya diakses panitia yang berwenang.</p>
    </div>
  </div>
</footer>

<?php if ($wa): ?>
<a href="<?= e($wa) ?>" target="_blank" rel="noopener noreferrer" id="wa-float" class="wa-float"
   aria-label="Tanya admin via WhatsApp <?= e(admin_whatsapp_display()) ?>">
  <?php render_view('partials/wa-icon', ['class' => 'h-7 w-7']); ?>
  <span class="wa-float-label">Tanya Admin</span>
</a>
<?php endif; ?>

<div id="toast-root" class="pointer-events-none fixed inset-x-0 bottom-4 z-[80] flex flex-col items-center gap-2 px-4 sm:bottom-6 sm:items-end sm:px-6" aria-live="polite" aria-atomic="true"></div>

<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php foreach (($scripts ?? []) as $src): ?>
<script src="<?= e(asset($src)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
