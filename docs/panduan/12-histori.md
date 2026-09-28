---
judul: Histori data existing dan analisis "tidak bergerak"
ringkas: Melacak perubahan data existing dan menemukan unit/jabatan yang tidak bergerak dalam periode tertentu.
bagian: Monitoring & analitik
urutan: 42
peran: [semua]
menu: [/histori]
---

Menu **Histori Existing** menjawab pertanyaan seperti *"selama 6 bulan terakhir, unit mana yang tidak bergerak sama sekali?"*.

![Histori existing](/img/panduan/histori.png)

## Mengatur analisis

1. Pilih **instansi** (dan unit bila perlu).
2. Pilih **periode**: 1, 3, 6, 12, atau 24 bulan.
3. Pilih **tingkat**: per instansi, per unit kerja, atau per jabatan (posisi).
4. Pilih **Hanya yang diam** (default) atau **Semua**.

## Membaca tabel

| Kolom | Arti |
| --- | --- |
| Existing N bln lalu | Existing pada awal periode, dari rekam jejak bulanan |
| Existing kini | Existing saat ini |
| Perubahan | Selisih existing kini dengan awal periode |
| Pergerakan | Jumlah peristiwa pegawai (masuk, keluar, mutasi dari/ke, ganti jabatan) dalam periode |
| Terakhir bergerak | Tanggal peristiwa terakhir yang menyentuh objek tersebut |
| Kondisi | Kurang, kosong, lebih, sesuai, atau belum ada ABK saat ini |

Baris **kuning** = objek yang kekurangan tetapi tidak ada pergerakan sama sekali — prioritas tindak lanjut. Klik ikon kaca pembesar pada tingkat instansi untuk menelusuri unitnya. **Export analisis** mengunduh tabel ke Excel.

## Kartu ringkasan dan grafik

Kartu menampilkan jumlah objek dipantau, yang tidak bergerak, yang diam dan kekurangan, yang diam dan bermasalah, serta total peristiwa. Grafik menampilkan tren 12 bulan dan pergerakan per bulan.

## Log perubahan

Bagian bawah memuat log peristiwa pegawai (waktu, pegawai, jenis, dari, ke, keterangan, sumber/oleh) dan bisa difilter per jenis.

> Rekam jejak diambil otomatis setiap hari. Pergerakan dihitung dari riwayat pegawai; data yang pertama kali diimpor tercatat sebagai "masuk" pada tanggal impor.
