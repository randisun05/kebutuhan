# SIMONKEB — Sistem Informasi Monitoring Penyusunan Kebutuhan ASN

Aplikasi untuk mengelola penyusunan kebutuhan ASN **dari hulu ke hilir**, mulai dari
analisis jabatan/beban kerja, pengajuan usulan instansi, verifikasi dan pertimbangan
teknis BKN, validasi KemenPANRB, sampai penetapan kebutuhan. Hasilnya dimonitor
**real-time** terhadap pegawai existing: berapa yang kosong/kurang dan berapa yang
gemuk/lebih, per instansi, per unit kerja (hierarki), dan per jabatan.

Stack mengikuti web aspro: **Laravel 12 · PHP 8.3 · Inertia.js · Vue 3 · Vite**,
Bootstrap 5, Font Awesome, SweetAlert2, Chart.js, maatwebsite/excel.

## Fitur

**Hulu: organisasi & data existing**
- Manajemen organisasi: instansi, unit kerja bertingkat, aktif/nonaktif, nama jabatan pimpinan, impor struktur dari Excel, peta jabatan (kelas, B, K, +/−) siap cetak.
- Data pegawai existing: input manual, impor Excel, atau **sinkron dari SIASN BKN**; setiap mutasi unit/ganti jabatan/keluar-masuk tercatat di riwayat pegawai.
- Integrasi SIASN: tarik unor (`referensi/ref-unor`) menjadi unit kerja beserta hierarkinya, dan data utama PNS (`pns/data-utama/{nip}`) untuk memperbarui unit, jabatan, golongan, pendidikan, tanggal lahir, dan status aktif. Bisa manual dari menu, via `php artisan siasn:sync`, atau terjadwal tiap malam.

**Hulu: manajemen ABK (PermenPANRB 1/2020)**
- Informasi jabatan lengkap: ikhtisar, kualifikasi, bahan & perangkat kerja, tanggung jawab, wewenang, korelasi, lingkungan kerja, risiko bahaya, syarat jabatan, prestasi yang diharapkan, kelas jabatan.
- Uraian tugas dengan volume **per tahun/bulan/minggu/hari** (disetahunkan ×1/×12/×50/×250 sesuai 1.250 jam = 104/25/5 jam) × norma waktu ÷ waktu kerja efektif (default 75.000 menit, ada kalkulator hari × jam).
- ABK per tahun (versi), salin ke tahun berikut (satuan atau seluruh instansi), draft → final (tercatat siapa & kapan), impor/ekspor Excel, cetak dokumen Anjab-ABK ke PDF.
- Efektivitas Jabatan (EJ/PEJ) dan Efektivitas Unit (EU/PEU) kategori A–E.
- **Proyeksi kebutuhan 5 tahun**: kebutuhan ABK × pertumbuhan beban kerja per tahun, dikurangi pensiun (BUP) per tahun, dikurangi formasi yang sudah ditetapkan tetapi belum terisi, menghasilkan rencana formasi per tahun dan estimasi belanja pegawai.
- Saran redistribusi pegawai dari unit gemuk ke unit kurang pada jabatan yang sama.

**Hilir: usulan → penetapan → pengisian**
- Usulan dengan prioritas per jabatan, estimasi anggaran, verifikasi & pertimbangan teknis BKN, validasi & penetapan KemenPANRB, PDF lampiran penetapan.
- Pelacakan pengisian formasi (realisasi hasil seleksi); sisa formasi belum terisi tampil di monitoring dan proyeksi.
- Notifikasi di aplikasi (lonceng) dan opsional email untuk setiap tahap alur.

**Keamanan**
- Login Laravel Fortify: lupa/reset password, ganti password, 2FA (aplikasi autentikator + kode pemulihan), 2FA wajib per peran (`WAJIB_2FA_PERAN`).
- Log audit perubahan data master, organisasi, pegawai, ABK, dan pengguna.

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
php artisan queue:work          # untuk sinkronisasi SIASN & email
php artisan schedule:work       # (opsional) sinkron SIASN terjadwal
```

### Integrasi SIASN

1. Ajukan akses web service SIASN ke BKN (kredensial APIM + SSO dan whitelist IP server).
2. Isi `SIASN_*` di `.env`, set `SIASN_ENABLED=true`, lalu uji dari menu **Integrasi SIASN → Uji koneksi**.
3. Isi *ID Instansi SIASN* pada data instansi, jalankan sinkron **unor**, lalu isi *ID jabatan SIASN* pada referensi jabatan (atau samakan nama jabatannya).
4. Jalankan sinkron **pegawai** (semua PNS aktif atau daftar NIP tertentu). Hasil dan baris yang gagal tercatat di riwayat sinkronisasi.

Struktur endpoint dan token mengikuti pola web service SIASN (APIM `Authorization` + SSO `Auth`) yang dipakai
pustaka integrasi Laravel yang beredar. Nama field respons dibaca toleran, tetapi **belum diuji terhadap server BKN
asli** karena membutuhkan kredensial; verifikasi sekali di mode `training` sebelum produksi.

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
