@echo off
setlocal ENABLEDELAYEDEXPANSION

set "TOKEN="
if not "%~1"=="" set "TOKEN=%~1"
if "%TOKEN%"=="" if not "%BOT_TOKEN%"=="" set "TOKEN=%BOT_TOKEN%"

if "%TOKEN%"=="" (
  echo Enter Telegram bot token:
  set /p "TOKEN="
)

rem Pick Python launcher if available
set "PY=python"
where py >nul 2>&1 && set "PY=py"

echo Starting Python bot...
"%PY%" bot.py "%TOKEN%"

