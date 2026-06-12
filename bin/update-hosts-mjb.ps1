#Requires -RunAsAdministrator
$hostsPath = "$env:SystemRoot\System32\drivers\etc\hosts"
$content = Get-Content $hostsPath -Raw

$replacement = @"
## Local - Start ##
::1 mjb.local #Local Site
127.0.0.1 mjb.local #Local Site
::1 www.mjb.local #Local Site
127.0.0.1 www.mjb.local #Local Site
## Local - End ##
"@

if ($content -match '## Local - Start ##') {
    $content = $content -replace '(?s)## Local - Start ##.*?## Local - End ##', $replacement
} else {
    $content = $content.TrimEnd() + "`r`n`r`n" + $replacement + "`r`n"
}

Set-Content -Path $hostsPath -Value $content
Write-Host "Hosts file updated for mjb.local"
Get-Content $hostsPath | Select-String "mjb.local"