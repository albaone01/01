# Desktop Apps (Electron)

Folder ini berisi 2 wrapper Electron terpisah:

- `pos-kiosk` untuk kasir/POS.
- `admin-backoffice` untuk admin.

Keduanya hanya membuka URL server, backend tetap di server (XAMPP + MySQL).

## Prasyarat

- Node.js 18+ (disarankan 20+)
- Server POS sudah aktif di LAN

## 1) POS Kiosk App

```bash
cd desktop-apps/pos-kiosk
npm install
set POS_SERVER_URL=http://192.168.88.245/ritel4/public/POS/login.php
npm run start
```

Build exe:
```bash
set POS_SERVER_URL=http://192.168.88.245/ritel4/public/POS/login.php
npm run build
```

Output:
- `desktop-apps/pos-kiosk/dist/`

## 2) Admin Backoffice App

```bash
cd desktop-apps/admin-backoffice
npm install
set ADMIN_SERVER_URL=http://192.168.88.245/ritel4/public/admin/login.php
npm run start
```

Build exe:
```bash
set ADMIN_SERVER_URL=http://192.168.88.245/ritel4/public/admin/login.php
npm run build
```

Output:
- `desktop-apps/admin-backoffice/dist/`

## Catatan

- Untuk Windows PowerShell, ganti `set VAR=...` dengan:
  - `$env:VAR="..."`
- Kalau URL server berubah, update env var saat run/build.

