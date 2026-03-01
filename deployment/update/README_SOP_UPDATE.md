# SOP Update 1 Klik (Server POS)

Folder ini berisi skrip update operasional agar perubahan kode lebih aman dan konsisten.

## Isi

- `deploy_sop.bat` : orkestrator utama (backup -> restart -> smoke test).
- `backup_db.bat` : backup MySQL DB POS ke file `.sql`.
- `restart_services.bat` : restart Apache + MySQL XAMPP.
- `smoke_test.ps1` : cek endpoint penting setelah update.

## Cara pakai cepat (disarankan)

Jalankan dari server:

```powershell
cd C:\xampp\htdocs\ritel4\deployment\update
powershell -ExecutionPolicy Bypass -File .\deploy_sop.ps1
```

## Opsi

```powershell
powershell -ExecutionPolicy Bypass -File .\deploy_sop.ps1 -SkipBackup
powershell -ExecutionPolicy Bypass -File .\deploy_sop.ps1 -BaseUrl http://127.0.0.1/ritel4
powershell -ExecutionPolicy Bypass -File .\deploy_sop.ps1 -BaseUrl http://192.168.88.245/ritel4
```

Jika butuh mode `.bat`, tetap bisa:
```bat
deploy_sop.bat --skip-backup --base-url http://127.0.0.1/ritel4
```

## Alur default yang dilakukan

1. Backup DB `hyeepos` ke folder `deployment\backups`.
2. Restart Apache + MySQL.
3. Smoke test:
   - `/public/POS/health.php`
   - `/public/POS/login.php`
   - `/public/admin/login.php`

## Catatan

- Jika kamu update file kode via Git/manual copy, lakukan sebelum `deploy_sop.bat`.
- Backup SQL hasil SOP wajib disimpan juga ke storage eksternal (opsional tapi disarankan).
