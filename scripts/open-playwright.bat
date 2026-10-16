@echo off
cd /d "%~dp0.."
title SIS Playwright
call npm run e2e:ui
pause
