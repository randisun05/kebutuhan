---
judul: Usulan kebutuhan (pengajuan s.d. penetapan)
ringkas: Menyusun usulan, alur verifikasi BKN, validasi dan penetapan KemenPANRB.
bagian: Hilir — usulan s.d. penetapan
urutan: 30
peran: [semua]
menu: [/usulan]
---

## Alur dan status

{{ status_usulan }}

Setiap tindakan hanya dapat dilakukan pada status dan oleh peran tertentu:

{{ aksi_usulan }}

Semua tindakan tercatat di **Riwayat** usulan beserta catatan dan pelakunya. Pihak yang perlu bertindak menerima **notifikasi**.

## Operator instansi: menyusun dan mengajukan

1. **Usulan Kebutuhan** → **Buat Usulan**: pilih instansi, tahun anggaran, periode, jenis ASN, perihal; unggah surat pengantar (PDF).
2. Di halaman usulan klik **Tarik dari ABK**: jabatan yang kekurangan ditambahkan otomatis dengan jumlah = **saran sistem** (lihat di bawah) dan prioritas awal. Centang *Kurangi dengan redistribusi* bila pegawai dari unit gemuk akan dipindahkan lebih dulu; keterangan rincian mencatat unit asalnya.
3. Sesuaikan **jumlah**, **prioritas**, kualifikasi pendidikan, dan keterangan; tambah jabatan manual bila perlu. Klik **Simpan rincian**. Angka yang melebihi saran sistem ditandai kuning.
4. Klik **Ajukan Usulan**.

### Saran sistem

Rumus yang sama dipakai saat menarik rincian dan saat memeriksa usulan (operator, BKN, dan KemenPANRB melihat angka yang sama):

> **saran** = kebutuhan − existing + pensiun 5 tahun − formasi yang sudah ditetapkan tetapi belum terisi − usulan lain yang sedang diproses *(− pegawai yang dapat dipindahkan dari unit gemuk, bila redistribusi dipilih)*

- **Kebutuhan** diambil dari ABK final yang berlaku untuk **tahun usulan** (ABK tahun itu atau sebelumnya). Bila posisi belum punya ABK final, dipakai **kebutuhan sementara** (ditandai *sem.*) — lihat [Monitoring](/panduan/10-monitoring).
- Arahkan kursor ke angka saran untuk melihat rinciannya.

### Pemeriksaan terhadap kebutuhan

Kartu **Pemeriksaan terhadap kebutuhan** di kanan menunjukkan rincian yang **melebihi saran sistem**, rincian **tanpa dasar kebutuhan** (belum ada ABK final maupun kebutuhan sementara), dan **kesiapan ABK** instansi. Semua ini **hanya informasi**: usulan tetap dapat disimpan, diajukan, diverifikasi, dan ditetapkan. Saat menekan tombol tindakan, aplikasi menampilkan peringatan bila masih ada rincian yang ditandai. Pemeriksaan tidak ditampilkan lagi setelah usulan ditetapkan atau ditolak.

Bila fitur AI aktif, kartu **Justifikasi AI** menyusun draf alasan per jabatan untuk surat pengantar; klik **Salin teks** untuk menyalinnya. Lihat [Asisten AI](/panduan/17-asisten-ai).

Prioritas yang tersedia:

{{ prioritas_usulan }}

Bila usulan **dikembalikan**, perbaiki sesuai catatan lalu ajukan lagi.

![Detail usulan](/img/panduan/usulan.png)

## Verifikator BKN

1. Buka usulan dari tab **Perlu tindakan**, klik **Mulai Verifikasi**.
2. Isi kolom **Rekomendasi BKN** per jabatan (tidak boleh melebihi jumlah usul). Kolom **Saran sistem** dan kartu **Pemeriksaan** membantu menilai apakah angka usulan didukung ABK.
3. Klik **Beri Pertimbangan Teknis**, atau **Kembalikan untuk Perbaikan** / **Tolak Usulan** dengan catatan wajib.

## Validator KemenPANRB

1. Klik **Mulai Validasi**.
2. Isi kolom **Ditetapkan** per jabatan (tidak boleh melebihi rekomendasi BKN).
3. Isi **nomor SK, tanggal SK**, unggah berkas SK (PDF), lalu klik **Tetapkan Kebutuhan**.

Estimasi belanja pegawai (diusulkan, rekomendasi, ditetapkan) tampil di sisi kanan sebagai bahan pertimbangan anggaran.
