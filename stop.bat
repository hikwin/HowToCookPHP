@echo off
cd /d "%~dp0"
color 0A
echo ========================================
echo    HowToCookViewer Stopping Script
echo ========================================
echo.

echo Stopping PHP built-in server...
taskkill /F /IM php.exe 2>nul
if %errorlevel% equ 0 (
    echo.
    echo Server stopped successfully!
) else (
    echo.
    echo No running PHP server detected.
)
echo.
echo Press any key to exit...
pause >nul
