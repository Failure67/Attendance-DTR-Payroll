@echo off
title RMCS Payroll System
color 0A

echo ========================================
echo   RMCS Payroll System - Starting...
echo ========================================
echo.

echo [Step 1/2] Starting Laravel Server...
start "Laravel Server" cmd /k "php artisan serve"

echo [Step 2/2] Waiting 6 seconds for server...
timeout /t 6 /nobreak > nul

echo [Step 3/2] Launching Application...
npx electron electron/main.js

echo.
echo ========================================
echo   Application Closed
echo ========================================
pause