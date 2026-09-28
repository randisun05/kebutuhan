---
judul: Sistem peringatan dini
ringkas: Jenis peringatan, tingkat, ambang batas, dan cara menindaklanjuti.
bagian: Monitoring & analitik
urutan: 43
peran: [semua]
menu: [/peringatan]
---

Sistem memeriksa kondisi setiap pagi dan memunculkan peringatan. Peringatan **tertutup otomatis** ketika kondisinya sudah teratasi.

![Peringatan dini](/img/panduan/peringatan.png)

## Jenis peringatan

{{ aturan_peringatan }}

## Tingkat

| Tingkat | Contoh |
| --- | --- |
| Kritis | Pemenuhan unit di bawah ambang kritis |
| Tinggi | Jabatan struktural kosong, unit kekurangan yang stagnan, usulan tertahan, sinkron SIASN gagal |
| Sedang | Jabatan fungsional/pelaksana kosong, unit gemuk, pensiun mendatang, data usang, formasi belum terisi |
| Rendah | ABK belum ada atau kedaluwarsa |

Peringatan kritis/tinggi yang baru dikirim sebagai **notifikasi** ke administrator dan operator instansi terkait.

## Ambang batas yang berlaku

{{ ambang_peringatan }}

## Menindaklanjuti

1. Klik kartu tingkat atau jenis peringatan di kiri untuk memfilter.
2. Klik **Buka** untuk menuju halaman terkait (monitoring unit, histori, usulan, dll.).
3. Klik **Tindak lanjut** dan tulis apa yang sudah dilakukan (misalnya "sudah diusulkan formasi 2027"). Status berubah menjadi *ditindaklanjuti*.
4. Setelah kondisinya teratasi, deteksi berikutnya menandai peringatan **selesai**.

Administrator dapat menjalankan deteksi kapan saja dengan tombol **Deteksi sekarang**.
