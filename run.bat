@echo off
cd /d "%~dp0"
echo ========================================
echo    HowToCookViewer Startup Script
echo ========================================
echo.

REM Set PHP path (default to global 'php' in PATH, or specify a custom path)
set PHP_PATH=php

REM Check if PHP is installed and available
"%PHP_PATH%" --version >nul 2>&1
if %errorlevel% neq 0 (
    echo Error: PHP command not found in your system PATH.
    echo If PHP is installed elsewhere, please modify the PHP_PATH variable in this run.bat script.
    echo.
    pause
    exit /b 1
)

echo Detected PHP version:
"%PHP_PATH%" --version
echo.

REM Check if database has been populated, if not, offer to sync
if not exist "data\howtocook.db" (
    echo [INFO] SQLite database does not exist. Running sync.php to clone and index recipes first...
    set /p "run_sync=Do you want to run recipe sync now? (y/n): "
    if /i "%run_sync%"=="y" (
        set GIT_LFS_SKIP_SMUDGE=1
        "%PHP_PATH%" sync.php
    )
)

echo Starting PHP built-in server...
echo Server address: http://localhost:8080
echo Press Ctrl+C to stop the server
echo.

REM Start PHP built-in server using the public directory as document root and router.php
"%PHP_PATH%" -S localhost:8080 -t public public/router.php

echo.
echo Server stopped
pause 