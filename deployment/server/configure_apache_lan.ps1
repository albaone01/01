# Run as Administrator
# Backup + patch Apache config so POS can be accessed from LAN.

$xampp = "C:\xampp"
$httpdConf = Join-Path $xampp "apache\conf\httpd.conf"
$httpdXamppConf = Join-Path $xampp "apache\conf\extra\httpd-xampp.conf"

if (!(Test-Path $httpdConf) -or !(Test-Path $httpdXamppConf)) {
    Write-Host "Config Apache tidak ditemukan di C:\xampp."
    exit 1
}

$stamp = Get-Date -Format "yyyyMMdd_HHmmss"
Copy-Item $httpdConf "$httpdConf.bak_$stamp" -Force
Copy-Item $httpdXamppConf "$httpdXamppConf.bak_$stamp" -Force

# 1) ensure Apache listens on all interfaces for port 80
$httpd = Get-Content $httpdConf -Raw
$httpd = $httpd -replace '(?m)^\s*Listen\s+127\.0\.0\.1:80\s*$', 'Listen 80'
if ($httpd -notmatch '(?m)^\s*Listen\s+80\s*$') {
    $httpd += "`r`nListen 80`r`n"
}
Set-Content -Path $httpdConf -Value $httpd -Encoding ASCII

# 2) allow LAN access to htdocs in xampp config
$xconf = Get-Content $httpdXamppConf -Raw
$xconf = $xconf -replace '(?m)^\s*Require\s+local\s*$', '    Require all granted'
Set-Content -Path $httpdXamppConf -Value $xconf -Encoding ASCII

Write-Host "Patch selesai. Restart Apache untuk apply perubahan."

