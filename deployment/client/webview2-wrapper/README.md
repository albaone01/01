# POS Kiosk Wrapper (WebView2)

Wrapper desktop untuk membuka POS server URL tanpa install XAMPP/DB di PC kasir.

## Prasyarat

- Windows 10/11
- .NET SDK 8
- WebView2 Runtime (umumnya sudah ada via Microsoft Edge)

## Konfigurasi URL Server

Edit file:
- `appsettings.json`

Contoh:
```json
{
  "serverUrl": "http://192.168.1.10/public/login.php"
}
```

## Build / Publish

```powershell
dotnet restore
dotnet publish -c Release -r win-x64 --self-contained false
```

Output ada di:
- `bin\Release\net8.0-windows\win-x64\publish\`

Jalankan:
- `POSKioskWrapper.exe`

## Hotkey

- `Ctrl + Shift + R` untuk reload halaman POS.

