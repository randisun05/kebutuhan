# SIMONKEB — Sistem Informasi Monitoring Penyusunan Kebutuhan ASN

Aplikasi untuk mengelola penyusunan kebutuhan ASN **dari hulu ke hilir**, mulai dari
analisis jabatan/beban kerja, pengajuan usulan instansi, verifikasi dan pertimbangan
teknis BKN, validasi KemenPANRB, sampai penetapan kebutuhan. Hasilnya dimonitor
**real-time** terhadap pegawai existing: berapa yang kosong/kurang dan berapa yang
gemuk/lebih, per instansi, per unit kerja (hierarki), dan per jabatan.

Stack mengikuti web aspro: **Laravel 12 · PHP 8.3 · Inertia.js · Vue 3 · Vite**,
Bootstrap 5, Font Awesome, SweetAlert2, Chart.js, maatwebsite/excel.

## Alur (hulu → hilir)

| # | Tahap | Pelaku | Menu |
|---|-------|--------|------|
| 1 | Struktur organisasi & data pegawai existing (bezetting), bisa impor Excel | Operator instansi | Struktur Unit Kerja, Data Pegawai |
| 2 | Anjab & ABK: uraian tugas × volume × norma waktu ÷ waktu kerja efektif (75.000 menit) | Operator instansi | Anjab & ABK |
| 3 | Usulan: rincian ditarik otomatis dari ABK final (kekurangan + proyeksi pensiun 5 th), lalu **diajukan** | Operator instansi | Usulan Kebutuhan |
| 4 | Verifikasi & **pertimbangan teknis** (jumlah rekomendasi per jabatan) | Verifikator BKN | Usulan Kebutuhan |
| 5 | Validasi & **penetapan** (jumlah ditetapkan, nomor/tanggal SK, berkas SK) | Validator KemenPANRB | Usulan Kebutuhan, Penetapan |
| 6 | Monitoring kebutuhan vs existing, diperbarui otomatis tiap 30 detik | Semua peran | Dashboard, Monitoring |

Status usulan: `draft → diajukan → verifikasi_bkn → pertimbangan_teknis → validasi_kemenpan → ditetapkan`,
dengan cabang `dikembalikan` (perbaikan, catatan wajib) dan `ditolak`. Transisi dijaga
server-side di `App\Services\UsulanWorkflow` (peran, tahap, dan row lock agar tidak diproses ganda).
Jumlah rekomendasi tidak boleh melebihi usulan, dan jumlah ditetapkan tidak boleh melebihi rekomendasi.

## Logika monitoring

`App\Services\MonitoringService` menghitung langsung dari database, tanpa cache:

- **Posisi** = unit kerja × jabatan. Kebutuhan = ABK berstatus *final*; existing = pegawai aktif.
- Kurang/lebih dihitung **per posisi** lalu dijumlahkan, sehingga kelebihan di satu unit
  tidak menutupi kekurangan di unit lain.
- Indikator: kurang, lebih (gemuk), jabatan kosong (dibutuhkan tetapi 0 pegawai), belum ada ABK,
  proyeksi pensiun 5 tahun (tanggal lahir + BUP jabatan), dan formasi yang sudah ditetapkan.
- Rekap per unit memuat angka unit itu sendiri dan **total termasuk seluruh sub-unit**.
- Export Excel per posisi tersedia di halaman monitoring.

Rangkuman dasar hukum (UU 20/2023, PP 11/2017 jo. PP 17/2020, PP 49/2018,
PermenPANRB 1/2020 tentang Anjab-ABK, PermenPANRB 1/2023, Perka BKN 19/2011), rumus ABK,
kategori PEJ, dan BUP ada di menu **Pedoman & Regulasi**.

## Peran

| Peran | Akses |
|-------|-------|
| `admin` | Semua menu, referensi instansi/jabatan/pengguna, bisa menjalankan semua tahap |
| `verifikator_bkn` | Monitoring nasional, verifikasi & pertimbangan teknis |
| `validator_kemenpan` | Monitoring nasional, validasi & penetapan |
| `operator_instansi` | Hanya data instansinya: unit kerja, pegawai, ABK, usulan |
| `pimpinan` | Monitoring (baca saja) |

## Instalasi

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
# atur DB_* di .env (MySQL/MariaDB untuk produksi; default sqlite)
php artisan migrate --seed      # --seed mengisi data demo
npm run build                   # atau `npm run dev` saat pengembangan
php artisan serve
```

Akun demo (password `password`):

| Email | Peran |
|-------|-------|
| admin@simonkeb.test | Administrator |
| bkn@simonkeb.test | Verifikator BKN |
| kemenpan@simonkeb.test | Validator KemenPANRB |
| pimpinan@simonkeb.test | Pimpinan |
| kab-sjt@simonkeb.test, kot-mdn@simonkeb.test, prov-nsn@simonkeb.test, kl-adm@simonkeb.test | Operator instansi |

Data demo berisi 4 instansi dengan kondisi berbeda (seimbang, kekurangan, kelebihan) dan
usulan di berbagai tahap (draft, diajukan, pertimbangan teknis, ditetapkan).

## Pengujian

```bash
php artisan test
```

Test mencakup perhitungan monitoring (kurang/lebih per posisi, akumulasi sub-unit,
proyeksi pensiun, filter), alur usulan lengkap sampai penetapan, pengembalian,
pembatasan akses antarinstansi, dan hak akses per peran.

## Catatan

- `public/build/` di-commit seperti pada web aspro; jalankan `npm run build` setelah mengubah frontend.
- Berkas surat pengantar dan SK disimpan di disk privat (`storage/app/private`) dan diunduh melalui
  route yang memeriksa hak akses.
