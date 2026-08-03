@echo off
chcp 65001 > nul

echo ============================================
echo   中医商城 启动脚本
echo ============================================
echo.

rem ----- 检测 PHP -----
where php > nul 2>&1
if errorlevel 1 (
    if exist "D:\php-8.4\php.exe" (
        set "PHP_BIN=D:\php-8.4\php.exe"
        echo [PHP] 使用 D:\php-8.4\php.exe ^(未加入 PATH^)
    ) else (
        echo [ERROR] 未找到 PHP，请先安装 PHP 8.2+ 并加入 PATH
        pause
        exit /b 1
    )
) else (
    set "PHP_BIN=php"
    echo [PHP] 使用环境变量中的 PHP
)

rem ----- 检测包管理器 -----
where pnpm > nul 2>&1
if errorlevel 1 (
    where npm > nul 2>&1
    if errorlevel 1 (
        echo [ERROR] 未找到 pnpm 或 npm
        pause
        exit /b 1
    ) else (
        set "PM=npm"
        echo [PM]  使用 npm
    )
) else (
    set "PM=pnpm"
    echo [PM]  使用 pnpm
)
echo.

cd /d "%~dp0"
start "Backend-Laravel" cmd /k "%PHP_BIN% artisan serve --host=127.0.0.1 --port=8000"

timeout /t 3 /nobreak > nul

cd /d "%~dp0web"
start "Frontend-Vue" cmd /k "%PM% dev"

echo.
echo 两个窗口已打开,关闭对应窗口即可停止服务。
pause
