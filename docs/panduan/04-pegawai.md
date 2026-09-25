---
judul: Data pegawai existing
ringkas: Input manual, impor Excel, riwayat mutasi, dan status aktif pegawai.
bagian: Hulu — organisasi & data
urutan: 11
peran: [admin, operator_instansi]
menu: [/pegawai]
---

Data pegawai aktif adalah angka **existing** pada monitoring. Ada tiga cara memperbaruinya: **manual**, **impor Excel**, dan **sinkron SIASN** (lihat [Integrasi SIASN](/panduan/05-siasn)).

## Menambah atau mengubah pegawai

1. Buka **Data Pegawai (Existing)** → **Tambah**.
2. Pilih instansi, unit kerja, jabatan; isi NIP (18 digit), nama, status (PNS/PPPK), golongan, pendidikan.
3. Isi **tanggal lahir** — dipakai menghitung **TMT pensiun** otomatis dari batas usia pensiun (BUP) jabatan.
4. Saat mengubah unit/jabatan/status, isi **Keterangan perubahan** (misalnya nomor SK mutasi) agar tercatat di riwayat.

Pegawai yang pensiun/berhenti cukup **dinonaktifkan** (hilangkan centang *Aktif*), jangan dihapus, agar histori tetap utuh.

## Impor dari Excel

Menu **Impor Excel** → pilih instansi → unggah berkas dengan kolom `nip, nama, kode_unit, kode_jabatan, status_kepegawaian, golongan, pendidikan, tanggal_lahir, tmt_jabatan`. NIP yang sudah ada diperbarui (mutasi tercatat di riwayat), NIP baru ditambahkan. Baris yang gagal ditampilkan beserta alasannya.

## Riwayat pegawai

Di halaman ubah pegawai terdapat **Riwayat jabatan & mutasi**: masuk/aktif, keluar/nonaktif, mutasi unit, dan pergantian jabatan — lengkap dengan waktu, keterangan, dan siapa/sumber perubahannya (manual, impor, SIASN). Riwayat inilah yang dipakai [Histori Existing](/panduan/12-histori).

## Filter

Filter berdasarkan unit (termasuk sub-unit), jenis jabatan, status aktif, atau **Pensiun ≤ 5 th** untuk melihat pegawai yang segera mencapai BUP.
