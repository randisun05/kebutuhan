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

## Dua jalur kebutuhan

Setiap instansi memakai salah satu jalur, yang diatur administrator di **Ubah Instansi → Jalur kebutuhan**:

| Jalur | Dasar kebutuhan | Peringatan *ABK belum ada* |
| --- | --- | --- |
| **Standar (ABK)** | Hasil ABK final | Tetap dibuat |
| **Manual dari existing** | Kebutuhan sementara yang diisi manual | Tidak dibuat |

Kedua jalur memakai fitur yang sama (monitoring, usulan, proyeksi, redistribusi). Posisi yang sudah punya ABK final selalu memakai hasil ABK, apa pun jalur instansinya.

### Mengisi kebutuhan sementara secara massal

Administrator dan operator instansi dapat mengisi banyak posisi sekaligus:

- **Isi dari existing / formasi** — di halaman monitoring instansi (seluruh instansi) atau halaman rincian unit (unit itu, mengikuti pilihan *Termasuk sub-unit*). Pilih sumber angka:
  - **Jumlah pegawai existing**: pegawai aktif per jabatan, dengan dasar *Existing per tanggal ...*.
  - **Formasi yang sudah ditetapkan**: total *jumlah ditetapkan* per unit dan jabatan dari usulan berstatus *ditetapkan*, dengan dasar *Formasi ditetapkan per tanggal ...*.

  Posisi yang sudah punya ABK final atau kebutuhan sementara **tidak diubah**. Setelah itu ubah angkanya per posisi lewat ikon pensil.
- **Impor Excel** — klik **Template Excel**, isi kolom `kode_unit`, `kode_jabatan`, `jumlah`, dan `dasar`, lalu klik **Impor Excel**. Posisi yang sudah ada diperbarui; jumlah **0** menghapusnya. Baris yang gagal dilaporkan.

![Monitoring instansi: jalur kebutuhan dan isi massal](/img/panduan/monitoring-instansi.png)

## Kesiapan ABK

Halaman per instansi menampilkan **Kesiapan ABK**: persentase posisi yang memakai ABK final mutakhir, dibanding posisi dengan ABK lama, kebutuhan sementara, atau belum ada dasar sama sekali. Ini hanya informasi dan tidak menghalangi fitur apa pun.
