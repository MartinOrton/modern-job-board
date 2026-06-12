param(
    [string]$WordPressRoot = "C:\Users\marti\4Mation Digital\mjb-local-test",
    [string]$PluginSource = "C:\Users\marti\4Mation Digital\modern-job-board"
)

$destination = Join-Path $WordPressRoot "wp-content\plugins\modern-job-board"

if (-not (Test-Path $WordPressRoot)) {
    Write-Error "WordPress root not found: $WordPressRoot"
    exit 1
}

if (-not (Test-Path $PluginSource)) {
    Write-Error "Plugin source not found: $PluginSource"
    exit 1
}

$robocopyArgs = @(
    $PluginSource,
    $destination,
    "/MIR",
    "/XD", ".git", "vendor", "node_modules", "website", ".phpunit.result.cache",
    "/XF", ".gitignore"
)

Write-Host "Syncing plugin to $destination"
& robocopy @robocopyArgs | Out-Null

if ($LASTEXITCODE -ge 8) {
    Write-Error "Robocopy failed with exit code $LASTEXITCODE"
    exit $LASTEXITCODE
}

Write-Host "Plugin synced successfully."
Write-Host "Run demo seed:"
Write-Host "  php `"$PluginSource\bin\seed-demo.php`" `"$WordPressRoot`""