@echo off
setlocal
set "SCRIPT_DIR=%~dp0"

powershell -ExecutionPolicy Bypass -File "%SCRIPT_DIR%start_pos_server_oneclick.ps1"
exit /b %errorlevel%
