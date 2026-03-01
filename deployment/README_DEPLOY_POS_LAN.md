# Deploy POS SaaS on LAN (Server + Front Cashier PC)

Dokumen ini untuk mode:
- **PC Server**: XAMPP + source code + MySQL.
- **PC Kasir depan**: browser/kiosk wrapper saja (tanpa XAMPP/DB lokal).

## 1. Struktur yang dipakai

- Server app URL: `http://<SERVER_IP>/ritel4/public/POS/`
- Login URL POS: `http://<SERVER_IP>/ritel4/public/POS/login.php`
- Health URL: `http://<SERVER_IP>/ritel4/public/POS/health.php`

## 2. Setup PC Server

1. Pastikan source ada di `C:\xampp\htdocs\ritel4`.
2. Jalankan skrip:
   - `deployment\server\start_pos_server.bat`
3. Cek URL LAN yang terdeteksi:
   - `deployment\server\get_lan_url.ps1`
4. (Admin) buka firewall HTTP:
   - `deployment\server\open_firewall_ports.ps1`
5. Uji dari browser server:
   - `http://127.0.0.1/ritel4/public/POS/health.php`

Jika butuh force bind Apache ke semua interface + allow access LAN, jalankan (Admin):
- `deployment\server\configure_apache_lan.ps1`

## 3. Setup PC Kasir Depan

1. Pastikan PC kasir dan server satu jaringan LAN.
2. Test akses:
   - `http://<SERVER_IP>/ritel4/public/POS/login.php`
3. Jalankan kiosk launcher:
   - `deployment\client\start_pos_kiosk.bat http://<SERVER_IP>/ritel4/public/POS/login.php`

Atau gunakan PowerShell:
- `deployment\client\start_pos_kiosk.ps1 -ServerUrl "http://<SERVER_IP>/ritel4/public/POS/login.php"`

## 4. Device Registration (wajib per device kasir)

Karena aplikasi pakai guard device:
- Buka `http://<SERVER_IP>/ritel4/public/device_register.php` pada PC kasir.
- Selesaikan registrasi sampai device terikat ke toko.
- Setelah itu login normal di URL POS.

## 5. Operasional harian

1. Server dinyalakan: start Apache + MySQL.
2. Kasir buka URL server (kiosk).
3. Monitoring cepat:
   - health endpoint `.../ritel4/public/POS/health.php`.
4. Backup rutin:
   - Menu POS > Maintenance > Backup.

## 5A. Mode 1 Klik (Disarankan)

### PC Server
1. Jalankan: `deployment\server\start_pos_server_oneclick.bat`
2. Script akan:
   - start Apache + MySQL,
   - cek health lokal,
   - tampilkan URL LAN POS.

### PC Kasir
1. Edit sekali file: `deployment\client\kiosk_config.ps1`
   - isi `$ServerUrl` dengan URL server LAN yang benar.
2. Jalankan: `deployment\client\start_pos_kiosk_oneclick.bat`
3. Jika server tidak bisa dijangkau, script menampilkan pesan troubleshooting yang jelas.

## 6. Catatan keamanan minimum

- Ganti password akun admin POS.
- Batasi akses LAN (jangan expose ke internet langsung).
- Jika butuh remote internet, gunakan reverse proxy + HTTPS + auth tambahan.
