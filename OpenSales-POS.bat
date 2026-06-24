@echo off
chcp 65001 >nul
cd /d "%~dp0"

set "PHP=runtime\win\php.exe"
if not exist "%PHP%" set "PHP=php"
set "PORT=8090"

if not exist "storage\.installed" (
  "%PHP%" artisan migrate --force --no-interaction
  "%PHP%" artisan db:seed --force --no-interaction
  type nul > "storage\.installed"
)

start "" /min "%PHP%" artisan serve --host=127.0.0.1 --port=%PORT%
timeout /t 2 /nobreak >nul
start "" http://127.0.0.1:%PORT%

echo ========================================
echo   OpenSales POS ishlamoqda
echo   Brauzer: http://127.0.0.1:%PORT%
echo   Login: kassa / kassa
echo   To'xtatish: bu oynani yoping
echo ========================================
pause >nul
