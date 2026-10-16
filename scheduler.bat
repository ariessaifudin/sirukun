@echo off
cd /d "%~dp0"
set "SIRUKUN_PHP=%~dp0..\..\php\php.exe"
if not exist "%SIRUKUN_PHP%" set "SIRUKUN_PHP=C:\xampp\php\php.exe"
if not exist "%SIRUKUN_PHP%" (
  echo PHP XAMPP tidak ditemukan. Edit SIRUKUN_PHP pada scheduler.bat.
  exit /b 1
)
"%SIRUKUN_PHP%" artisan schedule:run >> storage\logs\scheduler.log 2>&1
