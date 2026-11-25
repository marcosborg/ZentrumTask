@echo off
setlocal
cd /d "%~dp0.."
set "APP_ENV=local"
set "APP_URL=http://127.0.0.1:8098"
set "APP_DEBUG=false"
set "DB_MODE=sandbox"
set "DB_HOST_SANDBOX=127.0.0.1"
set "DB_PORT_SANDBOX=3306"
set "FCM_SERVICE_ACCOUNT=disabled-in-local-preview"
set "MAIL_MAILER=log"
set "QUEUE_CONNECTION=sync"
set "CACHE_STORE=file"
set "SESSION_DRIVER=file"
set "PHP=C:\xampp\php\php.exe"
if exist bootstrap\cache\config.php (
  echo A configuracao Laravel esta em cache. Limpe a cache local antes de iniciar.
  exit /b 1
)
if /I "%~1"=="--copiar-aws" goto copiar
set "DB_CONNECTION=sqlite"
set "DB_DATABASE=%CD%\storage\app\private\ads-preview.sqlite"
if not exist "%DB_DATABASE%" type nul > "%DB_DATABASE%"
goto iniciar
:copiar
set "DB_CONNECTION=mysql"
set "DB_DATABASE_SANDBOX=zentrum_ads_preview_%RANDOM%_%RANDOM%"
echo A copiar a base AWS para uma base separada no XAMPP: %DB_DATABASE_SANDBOX%
"%PHP%" artisan db:replicate production sandbox --no-interaction
if errorlevel 1 goto falha
:iniciar
"%PHP%" artisan migrate --no-interaction
if errorlevel 1 goto falha
"%PHP%" "%~dp0zentrum-ads-preview.php"
if errorlevel 1 goto falha
exit /b 0
:falha
echo Nao foi possivel preparar a pre-visualizacao.
pause
exit /b 1
