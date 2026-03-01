@echo off
setlocal

set "ADMIN_SERVER_URL=http://192.168.88.245/ritel4/public/admin/login.php"
cd /d "%~dp0admin-backoffice"

if not exist node_modules (
  call npm install
  if errorlevel 1 exit /b 1
)

call npm run build

