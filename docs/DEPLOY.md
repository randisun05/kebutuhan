# Panduan deploy SIMONKEB (produksi)

Dokumen untuk administrator server. Panduan pengguna ada di dalam aplikasi (menu **Panduan Pengguna**).

## 1. Persyaratan

- PHP 8.3 (ekstensi: mbstring, xml, curl, zip, gd, pdo_mysql), Composer, Node.js 20+ (hanya bila membangun ulang frontend).
- MySQL 8 / MariaDB 10.6+ (produksi). SQLite hanya untuk pengembangan.
- Web server (Nginx/Apache) dengan document root `public/`.
- Cron, dan Supervisor (atau systemd) untuk antrean.

## 2. Pemasangan pertama

```bash
git clone <repo> simonkeb && cd simonkeb
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
# isi .env (lihat bagian 3), lalu:
php artisan migrate --force
php artisan db:seed --force        # HANYA lingkungan demo/uji; jangan di produksi
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

`public/build/` sudah di-commit; jalankan `npm ci && npm run build` hanya bila mengubah frontend.

## 3. Pengaturan `.env` yang wajib

| Variabel | Keterangan |
| --- | --- |
| `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` | Jangan pernah `APP_DEBUG=true` di produksi. |
| `DB_*` | Koneksi MySQL/MariaDB. |
| `QUEUE_CONNECTION=database` | Sinkron SIASN dan notifikasi berjalan lewat antrean. |
| `LOGIN_MANUAL=false` | Disarankan di produksi (hanya SSO SIASN). Nyalakan sementara untuk uji. |
| `WAJIB_2FA_PERAN` | Peran yang wajib 2FA lokal, mis. `admin`. |
| `MAIL_*`, `NOTIFIKASI_EMAIL` | Bila notifikasi email dinyalakan. |
| `SIASN_*` | Kredensial dari BKN (APIM, SSO, OIDC) dan `SIASN_ENABLED=true`. Lihat `config/siasn.php`. |
| `GEMINI_API_KEY_1..5` | Opsional, untuk fitur Asisten AI. |
| `PERINGATAN_*` | Ambang peringatan dini (lihat panduan **Peringatan Dini**). |

Simpan `.env` di luar repository dan jangan membagikannya. Kredensial tidak boleh masuk Git.

## 4. Proses latar (wajib)

**Scheduler** (cron, setiap menit):

```cron
* * * * * cd /var/www/simonkeb && php artisan schedule:run >> /dev/null 2>&1
```

Jadwal bawaan: sinkron SIASN 01.00 (bila `SIASN_SYNC_TERJADWAL=true`), snapshot histori 23.30, deteksi peringatan 06.00, dan detak scheduler tiap menit.

**Worker antrean** (Supervisor), contoh `/etc/supervisor/conf.d/simonkeb-worker.conf`:

```ini
[program:simonkeb-worker]
command=php /var/www/simonkeb/artisan queue:work --sleep=3 --tries=1 --max-time=3600
user=www-data
autostart=true
autorestart=true
stopwaitsecs=3600
numprocs=1
```

Setelah deploy ulang jalankan `php artisan queue:restart` agar worker memuat kode baru.

## 5. Pemantauan

`GET /health` (tanpa login) mengembalikan JSON tanpa data sensitif:

```json
{"status":"ok","waktu":"...","versi":"v1.7.0 ...","database":"ok","scheduler":"ok","antrean":{"menunggu":0,"gagal":0}}
```

- HTTP **503** bila database tidak dapat diakses.
- `status: perlu_perhatian` bila scheduler `terlambat`/`belum_berjalan` (cron mati) atau ada job antrean gagal.
- Arahkan uptime monitor ke `/health` dan beri peringatan bila bukan `status: ok`.

Log aplikasi: `storage/logs/`. Job gagal: `php artisan queue:failed`.

## 6. Pencadangan

- **Database**: dump harian (`mysqldump --single-transaction`), simpan minimal 14 hari di lokasi terpisah, dan **uji pemulihan** berkala.
- **Berkas unggahan**: cadangkan `storage/app` (surat pengantar, berkas SK).
- Data yang paling berharga: ABK final, usulan dan penetapan, riwayat pegawai, histori bulanan, log audit.

## 7. Pembaruan versi

```bash
php artisan down
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
php artisan up
```

Baca `docs/panduan/perubahan.md` untuk catatan tiap versi (mis. migrasi yang menambah kolom).

## 8. Daftar periksa keamanan

- [ ] `APP_DEBUG=false`, HTTPS aktif, cookie `SESSION_SECURE_COOKIE=true`.
- [ ] `LOGIN_MANUAL=false`; NIP seluruh pengguna SSO sudah terisi dan benar.
- [ ] 2FA wajib untuk peran administrator.
- [ ] Hak tulis hanya pada `storage/` dan `bootstrap/cache/`.
- [ ] `php artisan db:seed` **tidak** dijalankan di produksi (akun demo berpassword `password`).
- [ ] Whitelist IP server di BKN untuk web service SIASN.
