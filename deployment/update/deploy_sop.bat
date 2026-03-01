@echo off
setlocal

set "SCRIPT_DIR=%~dp0"
set "BASE_URL=http://127.0.0.1/ritel4"
set "SKIP_BACKUP_ARG="

:parse_args
if "%~1"=="" goto args_done
if /I "%~1"=="--skip-backup" (
  set "SKIP_BACKUP_ARG=-SkipBackup"
  shift
  goto parse_args
)
if /I "%~1"=="--base-url" (
  shift
  if "%~1"=="" (
    echo [ERROR] --base-url butuh nilai URL.
    exit /b 1
  )
  set "BASE_URL=%~1"
  shift
  goto parse_args
)
echo [WARN] Argumen tidak dikenal: %~1
shift
goto parse_args

:args_done
powershell -ExecutionPolicy Bypass -File "%SCRIPT_DIR%deploy_sop.ps1" %SKIP_BACKUP_ARG% -BaseUrl "%BASE_URL%"
exit /b %errorlevel%
