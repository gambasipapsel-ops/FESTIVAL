<?php
/**
 * CLI: buat aset web dari logo resmi (PNG transparan).
 *   php tools/build-logo.php "C:\path\LOGO GAMBASI.png"
 * Hasil: public/assets/images/logo-gambasi.png (600px), logo-gambasi-sm.png (160px),
 *        public/assets/icons/favicon-32.png, favicon-64.png, apple-touch-icon.png (180), icon-512.png
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit;
}
$src = $argv[1] ?? '';
if (!is_file($src)) {
    fwrite(STDERR, "Penggunaan: php tools/build-logo.php <logo.png>\n");
    exit(1);
}
$root = dirname(__DIR__);
$img = imagecreatefrompng($src);
imagesavealpha($img, true);
$w = imagesx($img);
$h = imagesy($img);

// Bounding box area tidak transparan
$minx = $w; $miny = $h; $maxx = 0; $maxy = 0;
for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $w; $x++) {
        if (((imagecolorat($img, $x, $y) >> 24) & 127) < 110) {
            if ($x < $minx) $minx = $x;
            if ($x > $maxx) $maxx = $x;
            if ($y < $miny) $miny = $y;
            if ($y > $maxy) $maxy = $y;
        }
    }
}
$pad = 8;
$minx = max(0, $minx - $pad); $miny = max(0, $miny - $pad);
$maxx = min($w - 1, $maxx + $pad); $maxy = min($h - 1, $maxy + $pad);
$cw = $maxx - $minx + 1;
$ch = $maxy - $miny + 1;

function canvas(int $w, int $h, ?array $bg = null): GdImage
{
    $c = imagecreatetruecolor($w, $h);
    imagealphablending($c, false);
    imagesavealpha($c, true);
    $fill = $bg ? imagecolorallocatealpha($c, $bg[0], $bg[1], $bg[2], 0) : imagecolorallocatealpha($c, 0, 0, 0, 127);
    imagefilledrectangle($c, 0, 0, $w, $h, $fill);
    imagealphablending($c, true);
    return $c;
}

function save(GdImage $im, string $path): void
{
    imagealphablending($im, false);
    imagesavealpha($im, true);
    imagepng($im, $path, 9);
    printf("%-55s %4dx%-4d %6d KB\n", str_replace('\\', '/', $path), imagesx($im), imagesy($im), filesize($path) / 1024);
}

// Logo penuh (dipangkas)
foreach (['logo-gambasi.png' => 600, 'logo-gambasi-sm.png' => 160] as $name => $tw) {
    $th = (int) round($ch * $tw / $cw);
    $out = canvas($tw, $th);
    imagecopyresampled($out, $img, 0, 0, $minx, $miny, $tw, $th, $cw, $ch);
    save($out, "$root/public/assets/images/$name");
    if (function_exists('imagewebp')) {
        $webp = "$root/public/assets/images/" . basename($name, '.png') . '.webp';
        imagewebp($out, $webp, 85);
        printf("%-55s %4dx%-4d %6d KB\n", str_replace('\\', '/', $webp), $tw, $th, filesize($webp) / 1024);
    }
}

// Ikon persegi: area perisai + sayap (tanpa tulisan) agar terbaca di ukuran kecil
$iconTop = $miny;
$iconBottom = (int) ($miny + $ch * 0.83);
$ih = $iconBottom - $iconTop;
$side = max($cw, $ih);
$sx = $minx - (int) (($side - $cw) / 2);
$sy = $iconTop - (int) (($side - $ih) / 2);
$icons = [
    'favicon-32.png' => [32, null, 0],
    'favicon-64.png' => [64, null, 0],
    'apple-touch-icon.png' => [180, [11, 18, 56], 14],
    'icon-512.png' => [512, [11, 18, 56], 40],
];
foreach ($icons as $name => [$size, $bg, $margin]) {
    $out = canvas($size, $size, $bg);
    imagecopyresampled($out, $img, $margin, $margin, $sx, $sy, $size - 2 * $margin, $size - 2 * $margin, $side, $side);
    save($out, "$root/public/assets/icons/$name");
}
