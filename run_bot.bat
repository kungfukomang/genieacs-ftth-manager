@echo off
title GenieACS Telegram Bot Polling
:: Masuk ke drive C
c:
:: Masuk ke folder project
cd C:\xampp\htdocs\genieacs
echo ==========================================
echo Memulai Bot Telegram GenieACS (Polling)...
echo ==========================================
C:\xampp\php\php.exe telegram_poll.php
pause