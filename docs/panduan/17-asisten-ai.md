---
judul: Asisten AI
ringkas: Draf Anjab-ABK, analisis monitoring, dan saran tindak lanjut peringatan dengan AI (Google Gemini).
bagian: Monitoring & analitik
urutan: 45
peran: [admin, operator_instansi, verifikator_bkn, validator_kemenpan, pimpinan]
menu: []
---

SIMONKEB memakai AI (Google Gemini) untuk mempercepat pekerjaan yang biasanya memakan waktu. **Hasil AI selalu berupa saran atau draf**: tidak ada data yang tersimpan sebelum Anda memeriksa dan menekan **Simpan**.

Status saat ini: {{ status_ai }}

Tombol AI (ikon tongkat ajaib) hanya tampil bila administrator sudah mengaktifkan fitur ini.

## Hasil disimpan, tidak dibuat ulang terus

Setiap hasil AI **disimpan di riwayat** beserta waktu dan nama pembuatnya. Bila Anda (atau pengguna lain) meminta hal yang sama lagi — jabatan & unit yang sama di form ABK, filter yang sama di monitoring, atau peringatan yang sama — SIMONKEB **menampilkan hasil tersimpan** tanpa memanggil AI lagi, sehingga kuota tidak terbuang.

- Keterangan *"Diambil dari riwayat …"* menunjukkan kapan dan oleh siapa hasil itu dibuat.
- Bila datanya sudah berubah sejak hasil dibuat, muncul keterangan **data sudah berubah**.
- Klik **Buat ulang** hanya bila memang perlu hasil baru; hasil lama tetap tersimpan di riwayat.

## Draf Anjab-ABK

Untuk administrator dan operator instansi, di form **Manajemen ABK** → **Tambah/Ubah ABK**.

1. Pilih unit kerja dan **jabatan** lebih dulu.
2. (Opsional) Tulis catatan, misalnya *"fokus layanan perizinan online"*.
3. Klik **Buat draf**. Bila draf untuk jabatan, unit, dan catatan yang sama sudah ada di riwayat, pilih **Pakai draf ini** atau **Buat ulang**. Draf menambahkan:
   - uraian tugas beserta hasil kerja, **volume, periode, dan norma waktu perkiraan**;
   - ikhtisar jabatan dan unsur informasi jabatan **yang masih kosong** (isian yang sudah ada tidak ditimpa).
4. **Periksa dan sesuaikan** volume dan norma waktu dengan kondisi nyata unit Anda — angka dari AI hanya perkiraan dan langsung memengaruhi kebutuhan pegawai.
5. Simpan seperti biasa. Lihat [Manajemen ABK](/panduan/06-abk).

## Analisis monitoring

Di halaman monitoring satu instansi (klik nama instansi di **Monitoring Kebutuhan**), kartu **Analisis AI** → **Buat analisis** menghasilkan:

- ringkasan kondisi kebutuhan vs existing,
- masalah utama (unit/jabatan dengan kekurangan atau kelebihan terbesar),
- rekomendasi (redistribusi, usulan formasi, antisipasi pensiun, pemutakhiran ABK),
- prioritas tindakan 3 bulan ke depan.

Analisis dibuat dari angka di halaman tersebut, termasuk filter jenis jabatan dan nama jabatan yang sedang aktif. Analisis terakhir langsung tampil saat halaman dibuka; tombolnya berubah menjadi **Buat ulang**.

## Saran tindak lanjut peringatan

Di **Peringatan Dini**, tombol **Saran AI** pada setiap peringatan terbuka menampilkan penyebab yang mungkin, langkah tindak lanjut, dan contoh catatan (dari riwayat bila sudah pernah dibuat; tombol **Buat ulang** untuk saran baru). Klik **Catat tindak lanjut** untuk langsung menulis catatan. Lihat [Sistem peringatan dini](/panduan/13-peringatan).

## Batas pemakaian

AI memakai kuota gratis Google yang dibagi semua pengguna, sehingga:

- setiap pengguna dibatasi **6 permintaan per menit** dan **100 per hari**;
- administrator dapat memasang **sampai 5 API key**. Key dipakai **bergiliran**; key yang kuotanya habis otomatis dilewati dan dipakai lagi setelah pulih (rate limit per menit: sekitar 1 menit; kuota harian: setelah reset harian Google, sekitar pukul 14.00–15.00 WIB);
- bila semua key sedang habis, muncul pesan beserta perkiraan jam tersedia kembali.

## Untuk administrator: mengaktifkan AI

1. Buat API key di Google AI Studio (`aistudio.google.com/apikey`). Kuota gratis dihitung **per proyek Google**, jadi buat setiap key di **proyek/akun yang berbeda** agar kuotanya benar-benar bertambah.
2. Isi `GEMINI_API_KEY_1` sampai `GEMINI_API_KEY_5` di `.env` server (boleh kurang dari 5). Model diatur dengan `GEMINI_MODEL`.
3. Periksa dengan `php artisan ai:status --uji`. Perintah ini menampilkan setiap key (disamarkan), apakah sedang istirahat, alasannya, dan jumlah pemakaian hari ini. `--pulihkan` melepaskan semua key dari masa istirahat, misalnya setelah key diganti.
4. Untuk mematikan fitur tanpa menghapus key, isi `GEMINI_ENABLED=false`.

> Data yang dikirim ke Google hanya data agregat dan nama jabatan/unit yang diperlukan untuk menyusun jawaban; data pribadi pegawai (nama, NIP) tidak dikirim.
