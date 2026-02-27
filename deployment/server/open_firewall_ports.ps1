# Run as Administrator
param(
    [int]$HttpPort = 80
)

$ruleName = "POS SaaS HTTP Inbound $HttpPort"
$existing = Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue

if ($existing) {
    Write-Host "Firewall rule sudah ada: $ruleName"
    exit 0
}

New-NetFirewallRule `
    -DisplayName $ruleName `
    -Direction Inbound `
    -Action Allow `
    -Protocol TCP `
    -LocalPort $HttpPort `
    -Profile Private

Write-Host "Firewall rule berhasil dibuat: $ruleName"

