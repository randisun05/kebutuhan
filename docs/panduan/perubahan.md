---
judul: Apa yang baru
ringkas: Catatan perubahan aplikasi per versi.
bagian: Bantuan
urutan: 99
peran: [semua]
menu: []
---

Halaman ini mencatat perubahan aplikasi. Membuka halaman ini menandai pembaruan sebagai sudah dibaca.

## v1.5.0 — 2026-10-28

- **Kebutuhan sementara**: posisi yang belum punya ABK final dapat diberi angka kebutuhan sementara (halaman rincian unit di Monitoring), sehingga monitoring, usulan, proyeksi, dan redistribusi tetap terhitung. Otomatis digantikan hasil ABK setelah difinalkan.
- **Tarik dari ABK** kini memakai ABK yang berlaku untuk tahun usulan dan mengurangkan formasi yang sudah ditetapkan tetapi belum terisi serta usulan lain yang sedang diproses, sehingga tidak ada usulan ganda. Opsi **kurangi dengan redistribusi** dari unit gemuk.
- Halaman usulan menampilkan **saran sistem** per jabatan dan kartu **Pemeriksaan terhadap kebutuhan** (melebihi saran, tanpa dasar, kesiapan ABK) untuk operator, BKN, dan KemenPANRB. Hanya penanda, tidak memblokir.
- **Pegawai dapat ditautkan ke formasi** yang ditetapkan; jumlah terisi di Pengisian Formasi bertambah otomatis.
- **Kesiapan ABK** per instansi di halaman monitoring.
- **Justifikasi AI** untuk usulan kebutuhan.

## v1.4.0 — 2026-10-07

- **Asisten AI** (Google Gemini): tombol **Buat draf** di form ABK mengisi uraian tugas dan informasi jabatan, kartu **Analisis AI** di monitoring per instansi, dan tombol **Saran AI** di Peringatan Dini.
- Hasil AI **disimpan di riwayat** dan dipakai lagi untuk permintaan yang sama; AI hanya dipanggil ulang lewat tombol **Buat ulang**.
- Administrator dapat memasang sampai 5 API key Gemini yang dipakai bergiliran; key yang kuotanya habis dilewati otomatis. Lihat [Asisten AI](/panduan/17-asisten-ai).

## v1.3.1 — 2026-09-28

- **Keamanan:** login SSO SIASN kini hanya mencocokkan akun berdasarkan **NIP** (email tidak lagi dipakai). Pastikan NIP semua pengguna SSO sudah terisi.
- **Keamanan:** filter unit kerja pada Histori, Laporan, Proyeksi, Data Pegawai, dan ABK kini hanya menerima unit milik instansi yang dipilih.
- Login email + password dapat dinyalakan/dimatikan administrator; bila dimatikan, form login manual dan lupa password tidak tampil.

## v1.3.0 — 2026-09-26

- **Panduan pengguna** di dalam aplikasi: tombol **Bantuan** di setiap halaman membuka bagian panduan yang sesuai, pencarian, unduh PDF, dan pemberitahuan "Apa yang baru".
- Tabel peran, akses menu, status & aksi usulan, jenis peringatan, ambang batas, jenis laporan, dan rumus ABK di panduan kini dibangkitkan otomatis dari aplikasi.

## v1.2.0 — 2026-09-25

- **Login SSO SIASN** untuk admin dan operator instansi.
- **Histori Existing**: rekam jejak bulanan, log perubahan, dan analisis unit/jabatan yang **tidak bergerak** dalam 1–24 bulan.
- **Peringatan Dini** dengan 10 jenis peringatan, tindak lanjut, dan penutupan otomatis.
- **Pusat Laporan** (9 jenis, Excel & PDF) dan **Dashboard Data** (tren, peta panas, komposisi pegawai).

## v1.1.0 — 2026-09-25

- **Integrasi SIASN**: sinkron unor dan data utama PNS.
- **Manajemen organisasi**: unit aktif/nonaktif, impor Excel, peta jabatan; riwayat mutasi pegawai.
- **Manajemen ABK lengkap**: informasi jabatan, volume per periode, ABK per tahun, salin tahun, impor/ekspor, PDF, efektivitas unit.
- **Proyeksi 5 tahun** dan **redistribusi pegawai**.
- Usulan: prioritas, estimasi anggaran, pengisian formasi, PDF penetapan, notifikasi.
- Keamanan: reset password, 2FA, log audit.

## v1.0.0 — 2026-09-24

- Rilis awal: data master, Anjab/ABK, usulan → verifikasi BKN → penetapan KemenPANRB, dan monitoring real-time kebutuhan vs existing.
