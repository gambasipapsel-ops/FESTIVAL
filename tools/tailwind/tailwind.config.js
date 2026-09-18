/**
 * Konfigurasi Tailwind untuk build produksi memakai Tailwind Standalone CLI (tanpa Node.js/npm).
 * Harus identik dengan konfigurasi CDN di app/views/partials/head.php.
 *
 *   tools\bin\tailwindcss.exe -c tools/tailwind/tailwind.config.js -i tools/tailwind/input.css -o public/assets/css/tailwind.css --minify
 */
module.exports = {
  content: [
    './app/**/*.php',
    './public/**/*.php',
    './public/assets/js/**/*.js'
  ],
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
