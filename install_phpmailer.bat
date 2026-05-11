@echo off
echo ========================================
echo PHPMailer Installation Script
echo ========================================
echo.

REM Check if composer is available
where composer >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo [OK] Composer found!
    echo.
    echo Installing PHPMailer via Composer...
    composer require phpmailer/phpmailer
    echo.
    echo Installation complete!
) else (
    echo [WARNING] Composer not found.
    echo.
    echo Please choose an option:
    echo 1. Install Composer from https://getcomposer.org/download/
    echo 2. Or manually download PHPMailer from:
    echo    https://github.com/PHPMailer/PHPMailer/releases
    echo    Extract and copy the 'src' folder to: vendor\phpmailer\
    echo.
)

pause


