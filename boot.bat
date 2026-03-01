@echo off
cd /d "%~dp0"

:: --- Run git pull once after startup ---
echo Checking for updates after app start...
git pull
if errorlevel 1 (
    echo Could not pull latest code. Continuing anyway...
)

:: --- Start background loop for 12-hour git pulls ---
start "" cmd /c "call :GitUpdater"

:: --- Main crash-recovery loop (your old logic) ---
:loop
echo Starting stack...
php stack start

echo Running worker...
php stack run:worker

echo Crashed or stopped. Restarting in 5 seconds...
timeout /t 5 /nobreak

goto loop

:: --- Subroutine: Git updater ---
:GitUpdater
:gitloop
echo [%date% %time%] Waiting 12 hours before next git pull...
timeout /t 43200 /nobreak

echo [%date% %time%] Checking for updates...
git pull
if errorlevel 1 (
    echo [%date% %time%] Could not pull latest code. Continuing anyway...
)

goto gitloop
