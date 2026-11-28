---
judul: Perbandingan kebutuhan JF
ringkas: Rekomendasi BKN, penetapan KemenPANRB, ABK, dan bezetting dari beberapa sumber berdampingan sebagai referensi.
bagian: Hilir — usulan s.d. penetapan
urutan: 33
peran: [admin, operator_instansi, verifikator_bkn, validator_kemenpan, pimpinan, pembina]
menu: [/perbandingan-jf]
---

Halaman **Perbandingan Kebutuhan JF** menaruh beberapa sumber angka **berdampingan sebagai referensi** untuk satu instansi dan satu jabatan pokok JF. Tidak ada sumber yang menjadi patokan dan tidak ada yang saling membatasi; sumber yang berbeda memang boleh berbeda. **Kolom kosong (–) berarti tidak ada data**, bukan nol.

## Sumber

Kolom mengikuti **sumber aktif** milik instansi pembina (menu [Master JF & Sumber](/panduan/19-master-jf)). Bawaan untuk BKN:

| Kelompok | Sumber | Asal nilai |
| --- | --- | --- |
| Kebutuhan | Rekomendasi BKN, Penetapan KemenPANRB | Impor Excel rekap tim data |
| Kebutuhan | ABK SIMONKEB | Dihitung langsung: ABK final terbaru per unit kerja, dijumlahkan per instansi |
| Bezetting | Bezetting saat rekomendasi | Impor Excel (foto saat rekomendasi disusun) |
| Bezetting | Pegawai aktif SIMONKEB | Dihitung langsung: pegawai aktif per jabatan (kondisi kini) |
| Bezetting | Bezetting API Dashboard | Ditarik dari API Dashboard (nonaktif sampai dikonfigurasi; lihat panduan server) |

Sumber **ABK** dan **pegawai** SIMONKEB hanya terisi bila (1) nama instansi sudah dipetakan ke referensi instansi BKN yang dikaitkan ke instansi di aplikasi, dan (2) jabatan SIMONKEB-nya sudah **dikaitkan ke JF MASN** di form Jabatan ([Referensi jabatan](/panduan/16-administrasi)). Jabatan tanpa kaitan diabaikan.

## Membaca daftar

Satu baris = satu instansi × jabatan pokok, dengan jumlah semua jenjang per sumber. Sumber **sejenis** dibandingkan sesamanya: kebutuhan dengan kebutuhan, bezetting dengan bezetting. Bila selisih terbesar melampaui **ambang** (bawaan 20% dari nilai terbesar), selnya diberi warna (kuning untuk kebutuhan, merah untuk bezetting) sebagai tanda **berjauhan**: itu undangan untuk memeriksa, bukan penilaian benar atau salah. Saring menurut instansi, jabatan, atau hanya yang berjauhan.

## Rincian

Klik **Rincian** untuk melihat perbandingan **per jenjang** dan, bila ada data SIMONKEB, **per unit kerja** (ABK dan pegawai aktif). Rekomendasi dan penetapan hanya tersedia per instansi, sehingga tidak dirinci per unit.

## Siapa melihat apa

- **Operator Instansi Pembina**: hanya data pembinanya.
- **Operator instansi**: hanya instansinya sendiri.
- **Administrator dan peran lain**: semua pembina (pilih lewat *Instansi pembina*).

## Menghubungkan bezetting dari API Dashboard

Sumber **Bezetting Dashboard** nonaktif sampai API Dashboard dihubungkan oleh administrator sistem: isi `DASHBOARD_JF_*` di `.env`, aktifkan sumbernya di **Referensi JF → Sumber Data**, lalu jalankan `php artisan jf:tarik-dashboard --uji` untuk menguji. Langkah lengkap dan format data ada di `docs/API-DASHBOARD.md`. Setelah aktif, kolomnya muncul berdampingan dengan sumber lain di halaman ini.
