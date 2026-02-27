@echo off
setlocal

if "%~1"=="" (
  echo Usage:
  echo   start_pos_kiosk.bat http://SERVER_IP/ritel4/public/login.php
  exit /b 1
)

set "URL=%~1"
set "SCRIPT_DIR=%~dp0"

powershell -ExecutionPolicy Bypass -File "%SCRIPT_DIR%start_pos_kiosk.ps1" -ServerUrl "%URL%"
exit /b %errorlevel%
