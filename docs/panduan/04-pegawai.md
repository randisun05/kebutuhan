---
judul: Data pegawai existing
ringkas: Input manual, impor Excel, riwayat mutasi, dan status aktif pegawai.
bagian: Hulu — organisasi & data
urutan: 11
peran: [admin, operator_instansi]
menu: [/pegawai, /kualitas-data]
---

Data pegawai aktif adalah angka **existing** pada monitoring. Ada tiga cara memperbaruinya: **manual**, **impor Excel**, dan **sinkron SIASN** (lihat [Integrasi SIASN](/panduan/05-siasn)).

## Menambah atau mengubah pegawai

1. Buka **Data Pegawai (Existing)** → **Tambah**.
2. Pilih instansi, unit kerja, jabatan; isi NIP (18 digit), nama, status (PNS/PPPK), golongan, pendidikan.
3. Isi **tanggal lahir** — dipakai menghitung **TMT pensiun** otomatis dari batas usia pensiun (BUP) jabatan.
4. Untuk pegawai baru hasil seleksi, pilih **Diangkat dari formasi** (opsional). Jumlah *terisi* di [Pengisian Formasi](/panduan/09-penetapan) bertambah otomatis, dan berkurang lagi bila pegawai dinonaktifkan atau dihapus.
5. Saat mengubah unit/jabatan/status, isi **Keterangan perubahan** (misalnya nomor SK mutasi) agar tercatat di riwayat.

Pegawai yang pensiun/berhenti cukup **dinonaktifkan** (hilangkan centang *Aktif*), jangan dihapus, agar histori tetap utuh.

## Impor dari Excel

Menu **Impor Excel** → pilih instansi → unggah berkas dengan kolom `nip, nama, kode_unit, kode_jabatan, status_kepegawaian, golongan, pendidikan, tanggal_lahir, tmt_jabatan`. NIP yang sudah ada diperbarui (mutasi tercatat di riwayat), NIP baru ditambahkan. Baris yang gagal ditampilkan beserta alasannya.

## Riwayat pegawai

Di halaman ubah pegawai terdapat **Riwayat jabatan & mutasi**: masuk/aktif, keluar/nonaktif, mutasi unit, dan pergantian jabatan — lengkap dengan waktu, keterangan, dan siapa/sumber perubahannya (manual, impor, SIASN). Riwayat inilah yang dipakai [Histori Existing](/panduan/12-histori).

## Filter

Filter berdasarkan unit (termasuk sub-unit), jenis jabatan, status aktif, atau **Pensiun ≤ 5 th** untuk melihat pegawai yang segera mencapai BUP.

## Kualitas data

Menu **Kualitas Data** memeriksa otomatis pegawai aktif satu instansi dan menampilkan jumlah temuan beserta contohnya (klik ikon pensil untuk langsung memperbaiki):

| Pemeriksaan | Tingkat |
| --- | --- |
| NIP bukan 18 digit angka | Tinggi |
| Tanggal lahir kosong (pensiun dan proyeksi tidak terhitung) | Tinggi |
| Tanggal lahir tidak wajar (umur < 17 atau > 75 tahun) | Tinggi |
| Masih aktif padahal sudah melewati TMT pensiun | Tinggi |
| Ditempatkan di unit kerja nonaktif | Sedang |
| Memakai jabatan nonaktif | Sedang |
| Kemungkinan data ganda (nama dan tanggal lahir sama) | Sedang |
| Golongan atau pendidikan kosong | Rendah |
| PNS belum pernah atau lama tidak disinkron SIASN (bila integrasi aktif) | Rendah |

Pemeriksaan ini hanya informasi dan tidak memblokir fitur apa pun.

Setiap pemeriksaan menampilkan beberapa contoh. Klik **Lihat semua temuan** untuk daftar lengkap dengan halaman dan **filter unit kerja**, atau **Unduh semua temuan** untuk mengekspor seluruh temuan instansi ke Excel.

![Kualitas data](/img/panduan/kualitas-data.png)
