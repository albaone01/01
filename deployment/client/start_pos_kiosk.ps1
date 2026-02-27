param(
    [Parameter(Mandatory = $true)]
    [string]$ServerUrl,
    [switch]$Fullscreen
)

function Start-EdgeApp {
    param([string]$Url, [bool]$Full)
    $edgePaths = @(
        "$Env:ProgramFiles\Microsoft\Edge\Application\msedge.exe",
        "$Env:ProgramFiles(x86)\Microsoft\Edge\Application\msedge.exe"
    )
    $edge = $edgePaths | Where-Object { Test-Path $_ } | Select-Object -First 1
    if (-not $edge) { return $false }
    if ($Full) {
        Start-Process -FilePath $edge -ArgumentList "--kiosk $Url --edge-kiosk-type=fullscreen --no-first-run"
    } else {
        Start-Process -FilePath $edge -ArgumentList "--app=$Url --start-maximized --no-first-run"
    }
    return $true
}

function Start-ChromeApp {
    param([string]$Url)
    $chromePaths = @(
        "$Env:ProgramFiles\Google\Chrome\Application\chrome.exe",
        "$Env:ProgramFiles(x86)\Google\Chrome\Application\chrome.exe"
    )
    $chrome = $chromePaths | Where-Object { Test-Path $_ } | Select-Object -First 1
    if (-not $chrome) { return $false }
    Start-Process -FilePath $chrome -ArgumentList "--app=$Url --start-maximized --no-first-run"
    return $true
}

if (-not ($ServerUrl -match '^https?://')) {
    Write-Host "ServerUrl harus diawali http:// atau https://"
    exit 1
}

if (Start-EdgeApp -Url $ServerUrl -Full $Fullscreen.IsPresent) {
    Write-Host "Kiosk dijalankan via Microsoft Edge."
    exit 0
}

if (Start-ChromeApp -Url $ServerUrl) {
    Write-Host "Kiosk dijalankan via Google Chrome."
    exit 0
}

Write-Host "Browser Edge/Chrome tidak ditemukan."
exit 1

