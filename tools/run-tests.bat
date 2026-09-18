@echo off
REM DEV-ONLY: jalankan seluruh test (backend Apps Script + integrasi PHP).
REM Node.js hanya dipakai untuk menjalankan kode .gs secara offline saat testing.
setlocal
cd /d "%~dp0.."
set "PHP_BIN=php"
set "NODE_BIN=node"
if exist "C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe" set "PHP_BIN=C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe"
for /d %%D in ("C:\laragon\bin\nodejs\node-*") do if exist "%%D\node.exe" set "NODE_BIN=%%D\node.exe"

echo === Apps Script backend tests ===
"%NODE_BIN%" tests\gas\run-tests.js || exit /b 1
echo.
echo === PHP integration tests ===
"%PHP_BIN%" tests\php\run-tests.php || exit /b 1
