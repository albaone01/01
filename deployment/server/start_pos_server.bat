@echo off
setlocal

set "XAMPP_DIR=C:\xampp"
set "APACHE_START=%XAMPP_DIR%\apache_start.bat"
set "MYSQL_START=%XAMPP_DIR%\mysql_start.bat"

if not exist "%APACHE_START%" (
  echo [ERROR] File tidak ditemukan: %APACHE_START%
  exit /b 1
)

if not exist "%MYSQL_START%" (
  echo [ERROR] File tidak ditemukan: %MYSQL_START%
  exit /b 1
)

echo Menjalankan Apache...
call "%APACHE_START%"
echo Menjalankan MySQL...
call "%MYSQL_START%"

echo.
echo Server POS dijalankan. Gunakan script get_lan_url.ps1 untuk melihat URL LAN.
exit /b 0

