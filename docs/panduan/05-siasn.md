---
judul: Integrasi SIASN
ringkas: Menarik unit organisasi (unor) dan data utama PNS dari SIASN BKN.
bagian: Hulu — organisasi & data
urutan: 12
peran: [admin, operator_instansi]
menu: [/siasn]
---

Integrasi SIASN memperbarui data existing langsung dari BKN sehingga tidak perlu input ulang.

> Integrasi aktif setelah administrator mengisi kredensial web service SIASN (dari BKN) di konfigurasi server. Status koneksi terlihat di kartu **Status koneksi**.

![Integrasi SIASN](/img/panduan/siasn.png)

## Urutan sinkronisasi yang disarankan

1. **Isi ID Instansi SIASN** pada data instansi (administrator) agar hanya unor instansi Anda yang ditarik.
2. **Sinkron unit organisasi (unor)** — unor SIASN dibuat/diperbarui menjadi unit kerja lengkap dengan hierarkinya.
3. **Petakan jabatan** — isi *ID jabatan SIASN* di referensi jabatan, atau pastikan nama jabatannya sama persis dengan SIASN.
4. **Sinkron data pegawai** — kosongkan kolom NIP untuk semua PNS aktif instansi, atau tempel daftar NIP tertentu.

Kartu **Pemetaan kode** menunjukkan berapa unit dan jabatan yang sudah terpetakan.

## Apa yang diperbarui

Untuk setiap NIP: nama, unit kerja, jabatan, golongan, pendidikan, tanggal lahir, TMT jabatan, dan status aktif (pegawai berstatus pensiun/berhenti/wafat di SIASN otomatis dinonaktifkan). Mutasi hasil sinkron tercatat di riwayat pegawai dengan keterangan *Sinkronisasi SIASN*.

Pegawai **baru** hanya ditambahkan bila unor dan jabatannya sudah terpetakan; bila belum, NIP tersebut dicatat sebagai gagal beserta alasannya.

## Riwayat sinkronisasi

Setiap proses tercatat: waktu, jenis, status (berjalan/selesai/gagal), jumlah berhasil & gagal, serta daftar pesan per NIP (klik tombol daftar). Sinkron yang gagal juga memunculkan [peringatan dini](/panduan/13-peringatan).

Administrator dapat mengaktifkan **sinkron otomatis setiap malam**.
