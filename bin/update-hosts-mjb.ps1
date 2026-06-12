#Requires -RunAsAdministrator
$hostsPath = "$env:SystemRoot\System32\drivers\etc\hosts"
$content = Get-Content $hostsPath -Raw

$replacement = @"
## Local - Start ##
::1 mjb.local #Local Site - Modern Job Board demo
127.0.0.1 mjb.local #Local Site - Modern Job Board demo
::1 www.mjb.local #Local Site - Modern Job Board demo
127.0.0.1 www.mjb.local #Local Site - Modern Job Board demo
::1 martin-orton-design.local #Reserved - portfolio site (separate Local site)
127.0.0.1 martin-orton-design.local #Reserved - portfolio site (separate Local site)
::1 www.martin-orton-design.local #Reserved - portfolio site (separate Local site)
127.0.0.1 www.martin-orton-design.local #Reserved - portfolio site (separate Local site)
## Local - End ##
"@

if ($content -match '## Local - Start ##') {
    $content = $content -replace '(?s)## Local - Start ##.*?## Local - End ##', $replacement
} else {
    $content = $content.TrimEnd() + "`r`n`r`n" + $replacement + "`r`n"
}

Set-Content -Path $hostsPath -Value $content
Write-Host "Hosts file updated."
Get-Content $hostsPath | Select-String "mjb.local|martin-orton-design.local"