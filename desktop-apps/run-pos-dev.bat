@echo off
setlocal

set "POS_SERVER_URL=http://192.168.88.245/ritel4/public/POS/login.php"

cd /d "%~dp0pos-kiosk"
if not exist node_modules (
  call npm install
  if errorlevel 1 exit /b 1
)
call npm run start

