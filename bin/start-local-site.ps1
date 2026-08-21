# Start Local WP services for mjb.local when the Local app has not started them.
# Prefer starting the site from the Local app when possible (Site -> Start, SSL -> Trust).

$siteId = "cp2oegpc-"
$localRun = Join-Path $env:APPDATA "Local\run"
$siteRun = Join-Path $localRun $siteId
$routerNginx = Join-Path $localRun "router\nginx"
$services = Join-Path $env:APPDATA "Local\lightning-services"

function Test-PortOpen {
    param([int]$Port)
    try {
        $client = New-Object System.Net.Sockets.TcpClient
        $iar = $client.BeginConnect("127.0.0.1", $Port, $null, $null)
        $ok = $iar.AsyncWaitHandle.WaitOne(800, $false)
        if (-not $ok) { $client.Close(); return $false }
        $client.EndConnect($iar) | Out-Null
        $client.Close()
        return $true
    } catch { return $false }
}

if (-not (Test-PortOpen 10004)) {
    Write-Host "Starting MariaDB on port 10004..."
    $mysqld = Join-Path $services "mariadb-10.4.32+1\bin\win32\bin\mysqld.exe"
    $cnf = Join-Path $siteRun "conf\mariadb\my.cnf"
    Start-Process -FilePath $mysqld -ArgumentList "--defaults-file=$cnf" -WindowStyle Hidden
    Start-Sleep -Seconds 3
}

if (-not (Test-PortOpen 10005)) {
    Write-Host "Starting Apache on port 10005..."
    $httpd = Join-Path $services "apache-2.4.43+11\bin\win32\bin\httpd.exe"
    $conf = Join-Path $siteRun "conf\apache\apache2.conf"
    Start-Process -FilePath $httpd -ArgumentList "-f `"$conf`"" -WindowStyle Hidden
    Start-Sleep -Seconds 3
}

if (-not (Test-PortOpen 443)) {
    Write-Host "Starting nginx router on ports 80/443..."
    $nginx = Join-Path $services "nginx-1.26.1+3\bin\win32\nginx.exe"
    Start-Process -FilePath $nginx -ArgumentList "-p `"$routerNginx`" -c conf/nginx.conf" -WorkingDirectory $routerNginx -WindowStyle Hidden
    Start-Sleep -Seconds 2
}

$checks = @{
    "MariaDB (10004)" = (Test-PortOpen 10004)
    "Apache (10005)"  = (Test-PortOpen 10005)
    "HTTP (80)"       = (Test-PortOpen 80)
    "HTTPS (443)"     = (Test-PortOpen 443)
}

$checks.GetEnumerator() | Sort-Object Name | ForEach-Object {
    $status = if ($_.Value) { "up" } else { "DOWN" }
    Write-Host ("{0}: {1}" -f $_.Key, $status)
}

if ($checks.Values -contains $false) {
    Write-Error "One or more services failed to start. Open the Local app and start the 'mjb' site from there."
    exit 1
}

Write-Host ""
Write-Host "Site URLs:"
Write-Host "  https://mjb.local"
Write-Host "  https://mjb.local/jobs/"
Write-Host ""
Write-Host "If the browser warns about the certificate, run:"
Write-Host "  certutil -addstore -user Root `"$routerNginx\certs\mjb.local.crt`""