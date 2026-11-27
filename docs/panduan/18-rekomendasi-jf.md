---
judul: Rekomendasi JF MASN
ringkas: Rekomendasi BKN dan penetapan KemenPANRB per instansi, jabatan, dan jenjang dari Excel rekap tim data.
bagian: Hilir — usulan s.d. penetapan
urutan: 32
peran: [admin, operator_instansi, verifikator_bkn, validator_kemenpan, pimpinan]
menu: [/rekomendasi-jf]
---

Menu **Rekomendasi JF MASN** menyimpan rekomendasi kebutuhan dari BKN dan penetapan dari KemenPANRB untuk jabatan fungsional manajemen ASN, dengan rincian **per instansi × jabatan pokok × jenjang**. Datanya berasal dari Excel rekap yang disusun tim data, bukan dari usulan di aplikasi ini.

Tiga angka ditampilkan **berdampingan sebagai referensi**, tanpa satu pun dijadikan patokan:

| Angka | Arti |
| --- | --- |
| **Bezetting saat rekomendasi** | Jumlah pejabat pada saat rekomendasi disusun. Ini **foto**, bukan kondisi sekarang. |
| **Rekom BKN** | Jumlah rekomendasi BKN (kolom *Perhitungan formasi* di Excel). |
| **Penetapan Menpan** | Jumlah yang ditetapkan KemenPANRB. |

**Lowongan** = bezetting − rekomendasi BKN (negatif berarti masih kurang) dan **selalu dihitung ulang** oleh aplikasi; kolom lowongan dan total di Excel tidak dipakai.

## Jabatan dan jenjang

| Jabatan pokok | Jenjang |
| --- | --- |
| Analis SDM Aparatur, Asesor SDM Aparatur, Auditor Manajemen ASN | Pertama, Muda, Madya, Utama |
| Pranata SDM Aparatur | Terampil, Mahir, Penyelia |

Kolom Excel *Pertama/Terampil, Muda/Mahir, Madya/Penyelia, Utama* dibaca sesuai jabatannya. Angka pada jenjang keempat Pranata diabaikan dan dilaporkan sebagai peringatan.

## Melihat data

Halaman utama menampilkan satu baris per instansi × jabatan beserta status rekomendasi (mis. *DONE*, *Belum Usul*), jumlah per sumber, dan **selisih Menpan − BKN**. Saring menurut nama instansi, jabatan, status rekomendasi, atau status penetapan. Klik **Rincian** untuk melihat angka per jenjang, daftar surat (nomor, tanggal, penandatangan, tautan), dan catatan. Pokja dan wilayah kerja, bila ada di Excel, tampil di rincian dan bersifat opsional.

Operator instansi hanya melihat instansinya sendiri (yang sudah dipetakan ke referensi instansi BKN); peran lain melihat seluruh instansi.

## Impor dari Excel (administrator)

1. Klik **Pilih berkas .xlsx** dan pilih berkas rekap. Hanya sheet `Input Data (Instansi Baru)` yang dibaca; sheet turunan, pivot, dan sheet rusak diabaikan.
2. Baca **pratinjau**: jumlah baris dan instansi, berapa yang **baru** dan **diperbarui**, jumlah surat, instansi yang belum cocok, serta peringatan data. **Belum ada yang tersimpan** pada tahap ini.
3. Klik **Simpan impor**. Baris yang sudah ada (instansi × jabatan yang sama) **diperbarui**, bukan digandakan, sehingga berkas dapat diimpor ulang setiap kali tim data memperbaruinya. Impor tidak dapat dibatalkan; impor ulang dari berkas yang benar menggantikan isinya.

Yang ikut dirapikan saat impor:

- Satu sel berisi beberapa nomor dan tanggal surat (bertanda "- ") diurai menjadi **satu surat per baris**. Tanggal dalam bentuk teks (mis. *13 Oktober 2025*) dikenali; bila jumlah nomor dan tanggal berbeda, keduanya dipasangkan berurutan dan dilaporkan.
- Kolom rumus di Excel (bulan/tahun, total, status Menpan, selisih) tidak dibaca. Status *ditetapkan* ditentukan dari adanya angka atau surat penetapan.
- Baris tanpa angka (mis. *Belum Usul*) tetap dicatat agar cakupan instansi terlihat.

### Instansi yang belum cocok

Nama instansi di Excel dicocokkan dengan **referensi instansi BKN** setelah dinormalkan (mis. *Kab.* dibaca *Kabupaten*). Yang belum cocok ditandai **belum cocok** dan tampil di kartu *Instansi di Excel yang belum cocok*. Administrator mengetik nama referensi yang benar lalu **Simpan**; pemetaan ini **dipertahankan** pada impor berikutnya.

> Data ini per instansi. Rekomendasi BKN per unit kerja dan pembandingan dengan ABK serta bezetting terkini akan menyusul setelah sumber datanya dipetakan.

![Rekomendasi JF MASN](/img/panduan/rekomendasi-jf.png)
