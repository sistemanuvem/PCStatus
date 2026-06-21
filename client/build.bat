@echo off
title Gerando PCStatusMonitor.exe
cd /d "%~dp0"

echo.
echo Verificando Python...
python --version > nul 2>&1
if errorlevel 1 (
    echo.
    echo  ERRO: Python nao encontrado.
    echo  Baixe em: https://www.python.org/downloads/
    echo  Na instalacao marque: Add Python to PATH
    echo.
    pause
    exit /b 1
)

echo OK - Instalando dependencias...
pip install --quiet --upgrade pip
pip install --quiet psutil requests wmi pywin32 pystray pillow nvidia-ml-py3 pyadl pyinstaller

echo OK - Compilando executavel...
if exist dist rmdir /s /q dist
if exist build rmdir /s /q build
if exist PCStatusMonitor.spec del PCStatusMonitor.spec

pyinstaller --onefile --noconsole --name PCStatusMonitor --hidden-import pystray._win32 --hidden-import win32timezone --hidden-import pythoncom --hidden-import win32com.client --hidden-import tkinter --hidden-import tkinter.ttk --collect-all pystray monitor.py

echo.
if exist dist\PCStatusMonitor.exe (
    echo  PRONTO! Executavel criado em:
    echo  %~dp0dist\PCStatusMonitor.exe
    echo.
    echo  Distribua apenas esse arquivo para os usuarios.
    explorer dist
) else (
    echo  ERRO ao compilar. Veja as mensagens acima.
)
pause
