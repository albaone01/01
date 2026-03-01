@echo off
setlocal

set "XAMPP_DIR=C:\xampp"
set "MYSQLDUMP=%XAMPP_DIR%\mysql\bin\mysqldump.exe"
set "DB_NAME=hyeepos"
set "DB_USER=root"
set "DB_PASS="
set "DB_HOST=127.0.0.1"
set "DB_PORT=3306"

set "TARGET_DIR=%~1"
if "%TARGET_DIR%"=="" set "TARGET_DIR=%~dp0..\backups"

if not exist "%TARGET_DIR%" mkdir "%TARGET_DIR%"

if not exist "%MYSQLDUMP%" (
  echo [ERROR] mysqldump tidak ditemukan: %MYSQLDUMP%
  exit /b 1
)

for /f %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyyMMdd_HHmmss"') do set "TS=%%i"
set "OUTFILE=%TARGET_DIR%\backup_%DB_NAME%_%TS%.sql"

echo [INFO] Backup database %DB_NAME% ...
"%MYSQLDUMP%" -h %DB_HOST% -P %DB_PORT% -u %DB_USER% %DB_NAME% > "%OUTFILE%"
if errorlevel 1 (
  echo [ERROR] Backup database gagal.
  exit /b 1
)

echo [OK] Backup selesai: %OUTFILE%
exit /b 0

