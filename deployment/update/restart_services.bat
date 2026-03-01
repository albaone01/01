@echo off
setlocal

set "XAMPP_DIR=C:\xampp"
set "APACHE_STOP=%XAMPP_DIR%\apache_stop.bat"
set "MYSQL_STOP=%XAMPP_DIR%\mysql_stop.bat"
set "APACHE_START=%XAMPP_DIR%\apache_start.bat"
set "MYSQL_START=%XAMPP_DIR%\mysql_start.bat"

if exist "%APACHE_STOP%" (
  echo [INFO] Stopping Apache...
  call "%APACHE_STOP%" >nul 2>&1
)
if exist "%MYSQL_STOP%" (
  echo [INFO] Stopping MySQL...
  call "%MYSQL_STOP%" >nul 2>&1
)

timeout /t 2 /nobreak >nul

if not exist "%APACHE_START%" (
  echo [ERROR] File tidak ditemukan: %APACHE_START%
  exit /b 1
)
if not exist "%MYSQL_START%" (
  echo [ERROR] File tidak ditemukan: %MYSQL_START%
  exit /b 1
)

echo [INFO] Starting Apache...
call "%APACHE_START%" >nul 2>&1
echo [INFO] Starting MySQL...
call "%MYSQL_START%" >nul 2>&1

timeout /t 2 /nobreak >nul

echo [INFO] Service status (port 80 & 3306):
netstat -ano | findstr ":80 :3306"

exit /b 0

