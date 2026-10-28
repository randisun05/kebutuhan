---
judul: Monitoring kebutuhan vs existing
ringkas: Membaca kondisi kosong, kurang, gemuk per instansi, unit, dan jabatan secara real-time.
bagian: Monitoring & analitik
urutan: 40
peran: [semua]
menu: [/monitoring]
---

## Cara membaca angka

Satuan perhitungan adalah **posisi** (unit kerja × jabatan). **Kebutuhan** diambil dari ABK final terbaru; bila posisi belum punya ABK final, dipakai **kebutuhan sementara** bila ada. Kekurangan dan kelebihan dihitung per posisi lalu dijumlahkan — kelebihan di satu unit **tidak** menutupi kekurangan di unit lain.

| Kondisi | Arti |
| --- | --- |
| Kurang | Existing di bawah kebutuhan |
| Kosong | Dibutuhkan tetapi belum ada pegawai |
| Lebih (gemuk) | Existing melebihi kebutuhan |
| Ideal | Pemenuhan agregat 90–110% |
| Belum ada ABK | Ada pegawai tetapi jabatannya belum punya ABK final |

## Tiga tingkat rincian

1. **Monitoring Kebutuhan** — rekap per instansi, bisa diurutkan (kekurangan terbesar, kelebihan terbesar, jabatan kosong, persentase).
2. Klik nama instansi — rekap **per unit kerja** bertingkat. Tombol **Total (incl. sub-unit)** / **Unit sendiri** mengganti cara penjumlahan.
3. Klik nama unit — rincian **per jabatan**, dengan opsi *Termasuk sub-unit*.

Pada halaman per instansi, kartu **Analisis AI** (bila diaktifkan) merangkum kondisi, masalah utama, dan rekomendasi tindakan dari angka di halaman tersebut. Lihat [Asisten AI](/panduan/17-asisten-ai).

Semua halaman monitoring diperbarui otomatis setiap 30 detik dan bisa difilter jenis jabatan, kondisi, dan nama jabatan. **Export Excel** mengunduh rincian per posisi.

![Monitoring per instansi](/img/panduan/monitoring.png)

## Kebutuhan sementara

Agar perhitungan tidak menunggu ABK selesai, administrator dan operator instansi dapat mengisi **kebutuhan sementara** di halaman rincian unit (klik nama unit):

1. Klik **Kebutuhan sementara** (posisi baru) atau ikon pensil pada baris jabatan yang belum punya ABK final.
2. Isi **jumlah** dan **dasar** (misalnya peta jabatan tahun lalu atau kesepakatan pimpinan). Isi **0** untuk menghapus.
3. Angka langsung dipakai di monitoring, usulan, proyeksi, dan redistribusi, dan ditandai *sementara*.

Begitu ABK posisi itu **difinalkan**, hasil ABK otomatis menggantikan kebutuhan sementara. Peringatan *ABK belum ada* tetap muncul sampai ABK selesai disusun.

## Kesiapan ABK

Halaman per instansi menampilkan **Kesiapan ABK**: persentase posisi yang memakai ABK final mutakhir, dibanding posisi dengan ABK lama, kebutuhan sementara, atau belum ada dasar sama sekali. Ini hanya informasi dan tidak menghalangi fitur apa pun.
