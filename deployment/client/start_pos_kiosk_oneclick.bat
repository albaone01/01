@echo off
setlocal
set "SCRIPT_DIR=%~dp0"

echo [POS KIOSK] Menjalankan launcher...
powershell -NoProfile -ExecutionPolicy Bypass -File "%SCRIPT_DIR%start_pos_kiosk_oneclick.ps1"
set "EC=%errorlevel%"

if not "%EC%"=="0" (
  echo.
  echo [POS KIOSK] Launcher gagal. Kode error: %EC%
  echo Cek file kiosk_config.ps1, koneksi LAN, dan status server POS.
  echo.
  pause
)

exit /b %EC%
