param(
    [string]$DbHost = "127.0.0.1",
    [int]$DbPort = 3306,
    [string]$DbName = "juvo",
    [string]$DbUser = "root",
    [string]$DbPass = "",
    [switch]$SkipNpm
)

$ErrorActionPreference = "Stop"
Set-Location -Path (Split-Path -Parent $MyInvocation.MyCommand.Path)

function Step($m) { Write-Host "`n==> $m" -ForegroundColor Cyan }
function Fail($m) { Write-Host "`nERROR: $m" -ForegroundColor Red; exit 1 }

foreach ($tool in @("php", "composer", "node", "npm")) {
    if (-not (Get-Command $tool -ErrorAction SilentlyContinue)) {
        Fail "$tool was not found on PATH. Install it first (Laragon bundles PHP, Composer, Node and MySQL)."
    }
}

$phpMajor = [int]((php -r "echo PHP_MAJOR_VERSION;"))
$phpMinor = [int]((php -r "echo PHP_MINOR_VERSION;"))
if ($phpMajor -lt 8 -or ($phpMajor -eq 8 -and $phpMinor -lt 1)) {
    Fail "PHP 8.1+ is required, found $(php -v | Select-Object -First 1)"
}

$extensions = php -m
foreach ($ext in @("mbstring", "openssl", "pdo_mysql", "fileinfo", "xml", "tokenizer", "ctype", "json", "curl")) {
    if ($extensions -notmatch "(?m)^$([regex]::Escape($ext))$") {
        Write-Host "WARNING: PHP extension '$ext' appears to be missing." -ForegroundColor Yellow
    }
}

Step "Installing PHP dependencies (composer install)"
composer install --no-interaction
if ($LASTEXITCODE -ne 0) { Fail "composer install failed" }

if (-not (Test-Path .env)) {
    Step "Creating .env from .env.example"
    Copy-Item .env.example .env
}

Step "Pointing .env at your local database ($DbHost`:$DbPort / $DbName)"
function Set-EnvValue($text, $key, $value) {
    $safe = ([string]$value).Replace('$', '$$')
    $updated = $text -replace "(?m)^$([regex]::Escape($key))=.*$", "$key=$safe"
    if ($updated -notmatch "(?m)^$([regex]::Escape($key))=") {
        $updated += "`n$key=$value`n"
    }
    return $updated
}
$content = Get-Content .env -Raw
$content = Set-EnvValue $content "DB_HOST" $DbHost
$content = Set-EnvValue $content "DB_PORT" $DbPort
$content = Set-EnvValue $content "DB_DATABASE" $DbName
$content = Set-EnvValue $content "DB_USERNAME" $DbUser
$content = Set-EnvValue $content "DB_PASSWORD" $DbPass
$content = Set-EnvValue $content "FLEETBASE_ENABLED" "false"
Set-Content -Path .env -Value $content -NoNewline

Step "Generating application key"
php artisan key:generate --force
if ($LASTEXITCODE -ne 0) { Fail "key:generate failed" }

Step "Running migrations"
php artisan migrate --force
if ($LASTEXITCODE -ne 0) { Fail "migrate failed" }

Step "Seeding demo data"
php artisan db:seed --force
if ($LASTEXITCODE -ne 0) { Fail "db:seed failed" }

Step "Linking public/storage (storefront images live here)"
php artisan storage:link
if (-not (Test-Path "public\storage")) {
    $public = (Resolve-Path "public").Path
    $storage = (Resolve-Path "storage\app\public").Path
    cmd /c mklink /J "$public\storage" "$storage" | Out-Null
    if (-not (Test-Path "public\storage")) { Fail "Could not create the storage link" }
}

if (-not $SkipNpm) {
    Step "Installing and building front-end assets"
    npm ci
    if ($LASTEXITCODE -ne 0) { Fail "npm ci failed" }
    npm run build
    if ($LASTEXITCODE -ne 0) { Fail "npm run build failed" }
}

Write-Host "`nDone. Start the app with:" -ForegroundColor Green
Write-Host "    php artisan serve" -ForegroundColor Green
Write-Host "Then open http://127.0.0.1:8000" -ForegroundColor Green
