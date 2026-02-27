@echo off
setlocal
dotnet restore
if errorlevel 1 exit /b 1

dotnet publish -c Release -r win-x64 --self-contained false
if errorlevel 1 exit /b 1

echo Publish selesai.
echo Cek folder:
echo   bin\Release\net8.0-windows\win-x64\publish
exit /b 0

