# Start Local WP services for mjb.local when the Local app has not started them.
# Prefer starting the site from the Local app when possible (Site -> Start, SSL -> Trust).

$siteId = "cp2oegpc-"
$localRun = Join-Path $env:APPDATA "Local\run"
$siteRun = Join-Path $localRun $siteId
$routerNginx = Join-Path $localRun "router\nginx"
$services = Join-Path $env:APPDATA "Local\lightning-services"

function Test-PortListening {
    param([int]$Port)
    return (Test-NetConnection -ComputerName 127.0.0.1 -Port $Port -WarningAction SilentlyContinue).TcpTestSucceeded
}

if (-not (Test-PortListening 10004)) {
    Write-Host "Starting MariaDB on port 10004..."
    $mysqld = Join-Path $services "mariadb-10.4.32+1\bin\win32\bin\mysqld.exe"
    $cnf = Join-Path $siteRun "conf\mariadb\my.cnf"
    Start-Process -FilePath $mysqld -ArgumentList "--defaults-file=$cnf" -WindowStyle Hidden
    Start-Sleep -Seconds 3
}

if (-not (Test-PortListening 10005)) {
    Write-Host "Starting Apache on port 10005..."
    $httpd = Join-Path $services "apache-2.4.43+11\bin\win32\bin\httpd.exe"
    $conf = Join-Path $siteRun "conf\apache\apache2.conf"
    Start-Process -FilePath $httpd -ArgumentList "-f `"$conf`"" -WindowStyle Hidden
    Start-Sleep -Seconds 3
}

if (-not (Test-PortListening 443)) {
    Write-Host "Starting nginx router on ports 80/443..."
    $nginx = Join-Path $services "nginx-1.26.1+3\bin\win32\nginx.exe"
    Start-Process -FilePath $nginx -ArgumentList "-p `"$routerNginx`" -c conf/nginx.conf" -WorkingDirectory $routerNginx -WindowStyle Hidden
    Start-Sleep -Seconds 2
}

$checks = @{
    "MariaDB (10004)" = (Test-PortListening 10004)
    "Apache (10005)"  = (Test-PortListening 10005)
    "HTTP (80)"       = (Test-PortListening 80)
    "HTTPS (443)"     = (Test-PortListening 443)
}

$checks.GetEnumerator() | Sort-Object Name | ForEach-Object {
    $status = if ($_.Value) { "up" } else { "DOWN" }
    Write-Host ("{0}: {1}" -f $_.Key, $status)
}

if ($checks.Values -contains $false) {
    Write-Error "One or more services failed to start. Open the Local app and start 'Martin Orton Design' from there."
    exit 1
}

Write-Host ""
Write-Host "Site URLs:"
Write-Host "  https://mjb.local"
Write-Host "  https://mjb.local/jobs/"
Write-Host ""
Write-Host "If the browser warns about the certificate, run:"
Write-Host "  certutil -addstore -user Root `"$routerNginx\certs\mjb.local.crt`""