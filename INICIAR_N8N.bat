@echo off
chcp 65001 >nul
title n8n - Automatizaciones NAVISSI
color 0B
cls
echo ==========================================
echo   n8n - Motor de Automatizaciones
echo ==========================================
echo.
echo Abriendo n8n en http://localhost:5678
echo (la primera vez te pedira crear una cuenta de propietario: correo y clave, es solo local, en tu maquina)
echo.
start "" http://localhost:5678
"%APPDATA%\npm\n8n.cmd" start
pause
