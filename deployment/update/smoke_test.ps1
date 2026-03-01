param(
    [string]$BaseUrl = "http://127.0.0.1/ritel4"
)

$ErrorActionPreference = "Stop"

function Test-Endpoint {
    param(
        [string]$Name,
        [string]$Url,
        [int[]]$AcceptedStatus = @(200)
    )

    try {
        $resp = Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 15
        $code = [int]$resp.StatusCode
        if ($AcceptedStatus -contains $code) {
            Write-Host "[OK] $Name ($code) -> $Url"
            return $true
        }
        Write-Host "[FAIL] $Name unexpected status ($code) -> $Url"
        return $false
    } catch {
        Write-Host "[FAIL] $Name error -> $Url"
        Write-Host "       $($_.Exception.Message)"
        return $false
    }
}

$tests = @(
    @{ Name = "Health"; Url = "$BaseUrl/public/POS/health.php"; Accepted = @(200) },
    @{ Name = "POS Login"; Url = "$BaseUrl/public/POS/login.php"; Accepted = @(200) },
    @{ Name = "Admin Login"; Url = "$BaseUrl/public/admin/login.php"; Accepted = @(200) }
)

$allOk = $true
foreach ($t in $tests) {
    $ok = Test-Endpoint -Name $t.Name -Url $t.Url -AcceptedStatus $t.Accepted
    if (-not $ok) { $allOk = $false }
}

if ($allOk) {
    Write-Host "[DONE] Smoke test selesai, semua endpoint OK."
    exit 0
}

Write-Host "[DONE] Smoke test selesai, ada endpoint yang gagal."
exit 1

