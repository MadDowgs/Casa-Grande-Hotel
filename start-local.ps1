# Casa Grande Hotel - one-command local run on Windows with XAMPP (MySQL) and PHP.
# Installs dependencies, prepares .env, creates the database, builds the tables, then starts
# the Payment API (port 8003) and the website (port 8000).
$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot
$php   = if (Test-Path 'C:\xampp\php\php.exe') { 'C:\xampp\php\php.exe' } else { 'php' }
$mysql = 'C:\xampp\mysql\bin\mysql.exe'

function Set-EnvValue($key, $value) {
    $lines = @(Get-Content .env)
    $pattern = "^\s*#?\s*$key="
    if ($lines -match $pattern) { $lines = $lines | ForEach-Object { if ($_ -match $pattern) { "$key=$value" } else { $_ } } }
    else { $lines += "$key=$value" }
    Set-Content .env $lines
}
function Run-Artisan {
    & $php artisan @args
    if ($LASTEXITCODE -ne 0) { Write-Host "Command failed: php artisan $args" -ForegroundColor Red; exit 1 }
}

Write-Host '1/5 Dependencies ...'
if (-not (Test-Path .\vendor\autoload.php)) {
    if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
        Write-Host 'Composer is not installed. Get it from https://getcomposer.org/download/ then run this again.' -ForegroundColor Red; exit 1
    }
    & composer install --no-interaction
    if ($LASTEXITCODE -ne 0) { Write-Host 'composer install failed' -ForegroundColor Red; exit 1 }
}

Write-Host '2/5 Environment ...'
if (-not (Test-Path .env)) { Copy-Item .env.example .env; Run-Artisan key:generate }
Set-EnvValue 'PAYMENT_API_URL' 'http://127.0.0.1:8003/api.php/charges'

Write-Host '3/5 Database (MySQL must be running in XAMPP) ...'
if (-not (Test-NetConnection -ComputerName 127.0.0.1 -Port 3306 -InformationLevel Quiet -WarningAction SilentlyContinue)) {
    Write-Host 'MySQL is not running. Start MySQL in the XAMPP Control Panel, then run this again.' -ForegroundColor Red; exit 1
}
if (Test-Path $mysql) { & $mysql -u root -e 'CREATE DATABASE IF NOT EXISTS casa_grande CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' }
else { Write-Host 'mysql.exe not found: create a database named casa_grande in phpMyAdmin.' -ForegroundColor Yellow }
Run-Artisan optimize:clear
Run-Artisan migrate --force
Run-Artisan db:seed --force

Write-Host '4/5 Payment API on http://127.0.0.1:8003 ...'
$paymentHealth = 'http://127.0.0.1:8003/api.php/health'
$paymentReady = $false
try {
    $paymentReady = (Invoke-RestMethod -Uri $paymentHealth -TimeoutSec 2).status -eq 'ok'
} catch {
    $paymentServer = Start-Process $php -ArgumentList '-S', '127.0.0.1:8003', '-t', "$PSScriptRoot\payment-api" -PassThru -WindowStyle Hidden
    for ($attempt = 0; $attempt -lt 15; $attempt++) {
        Start-Sleep -Seconds 1
        try {
            $paymentReady = (Invoke-RestMethod -Uri $paymentHealth -TimeoutSec 2).status -eq 'ok'
        } catch {
            if ($paymentServer.HasExited) { break }
        }
        if ($paymentReady) { break }
    }
}
if (-not $paymentReady) {
    Write-Host 'Payment API did not become ready on port 8003. Check that PHP is available and the port is free.' -ForegroundColor Red
    exit 1
}
Write-Host 'Payment API is ready.'

Write-Host '5/5 Website on http://127.0.0.1:8000 (Ctrl+C to stop) ...'
Start-Process powershell -WindowStyle Hidden -ArgumentList '-NoProfile', '-Command', 'Start-Sleep 4; Start-Process http://127.0.0.1:8000'
& $php artisan serve --host=127.0.0.1 --port=8000
