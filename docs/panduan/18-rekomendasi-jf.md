---
judul: Rekomendasi JF MASN
ringkas: Rekomendasi BKN dan penetapan KemenPANRB per instansi, jabatan, dan jenjang dari Excel rekap tim data.
bagian: Hilir — usulan s.d. penetapan
urutan: 32
peran: [admin, operator_instansi, verifikator_bkn, validator_kemenpan, pimpinan]
menu: [/rekomendasi-jf]
---

Menu **Rekomendasi JF MASN** menyimpan rekomendasi kebutuhan dari BKN dan penetapan dari KemenPANRB untuk jabatan fungsional manajemen ASN, dengan rincian **per instansi × jabatan pokok × jenjang**. Datanya berasal dari Excel rekap yang disusun tim data, bukan dari usulan di aplikasi ini.

Angka dari beberapa sumber ditampilkan **berdampingan sebagai referensi**, tanpa satu pun dijadikan patokan dan tanpa saling membatasi. Kolomnya mengikuti **daftar sumber** di menu [Master JF & Sumber](/panduan/19-master-jf), sehingga sumber baru muncul sendiri. Bawaannya:

| Sumber | Arti |
| --- | --- |
| **Bezetting saat rekomendasi** | Jumlah pejabat pada saat rekomendasi disusun. Ini **foto**, bukan kondisi sekarang. |
| **Rekomendasi BKN** | Jumlah rekomendasi BKN (kolom *Perhitungan formasi* di Excel). |
| **Penetapan KemenPANRB** | Jumlah yang ditetapkan KemenPANRB. |

**Lowongan** = bezetting − rekomendasi BKN (negatif berarti masih kurang) dan **selalu dihitung ulang** oleh aplikasi; kolom lowongan dan total di Excel tidak dipakai. Sel yang kosong berarti **tidak ada data**, bukan nol.

## Jabatan dan jenjang

Jabatan pokok dan jenjang yang berlaku adalah data yang dikelola administrator (bawaan: Analis, Asesor, dan Auditor dengan jenjang Pertama, Muda, Madya, Utama; Pranata dengan Terampil, Mahir, Penyelia). Blok empat kolom di Excel dibaca menurut **posisi kolom** tiap jenjang; angka pada posisi yang tidak punya jenjang (mis. kolom keempat Pranata) diabaikan dan dilaporkan sebagai peringatan.

## Melihat data

Halaman utama menampilkan satu baris per instansi × jabatan beserta status rekomendasi (mis. *DONE*, *Belum Usul*), angka tiap sumber, dan selisih antara dua sumber kebutuhan pertama (bawaan: Menpan − BKN). Saring menurut nama instansi, jabatan, status rekomendasi, atau status penetapan. Klik **Rincian** untuk melihat angka per jenjang, daftar surat (nomor, tanggal, penandatangan, tautan), catatan, dan **riwayat perubahan angka**: nilai lama yang digantikan impor berikutnya tetap tersimpan. Pokja dan wilayah kerja, bila ada di Excel, disimpan pada alias instansi dan bersifat opsional.

Operator instansi hanya melihat instansinya sendiri (yang sudah dipetakan ke referensi instansi BKN); peran lain melihat seluruh instansi.

## Impor dari Excel (administrator)

1. Pilih **profil impor** bila ada lebih dari satu, lalu klik **Pilih berkas .xlsx**. Yang dibaca hanya sheet dan kolom yang ditentukan di [profil impor](/panduan/19-master-jf) (bawaan: sheet `Input Data (Instansi Baru)`); sheet turunan, pivot, dan sheet rusak diabaikan.
2. Baca **pratinjau**: jumlah baris dan instansi, berapa yang **baru** dan **diperbarui**, jumlah surat, instansi yang belum cocok, serta peringatan data. **Belum ada yang tersimpan** pada tahap ini.
3. Klik **Simpan impor**. Baris yang sudah ada (instansi × jabatan yang sama) **diperbarui**, bukan digandakan, sehingga berkas dapat diimpor ulang setiap kali tim data memperbaruinya. Angka disimpan **berversi**: hanya angka yang berubah mendapat versi baru, nilai lama tetap tercatat di riwayat, dan **sel yang dikosongkan tidak menghapus** nilai sebelumnya. Daftar surat diganti sesuai berkas terbaru.

Yang ikut dirapikan saat impor:

- Satu sel berisi beberapa nomor dan tanggal surat (bertanda "- ") diurai menjadi **satu surat per baris**. Tanggal dalam bentuk teks (mis. *13 Oktober 2025*) dikenali; bila jumlah nomor dan tanggal berbeda, keduanya dipasangkan berurutan dan dilaporkan.
- **Sel rumus dibaca dari nilai tersimpannya.** Banyak kolom isian di Excel sumber (bezetting, perhitungan formasi, nomor dan tanggal penetapan) berisi rumus pencarian dari sheet lain; yang diimpor adalah hasil hitung terakhir yang tersimpan di berkas. Bila berkas belum pernah dihitung ulang di Excel, sel semacam itu terbaca kosong dan pratinjau memberi peringatan; buka dan simpan ulang berkas di Excel lalu impor lagi. Kolom turunan (bulan/tahun, total, status Menpan, selisih) tidak dipakai karena dihitung ulang oleh aplikasi; status *ditetapkan* ditentukan dari adanya angka atau surat penetapan.
- Baris tanpa angka (mis. *Belum Usul*) tetap dicatat agar cakupan instansi terlihat.

### Instansi yang belum cocok

Nama instansi di Excel dicocokkan dengan **referensi instansi BKN** setelah dinormalkan (mis. *Kab.* dibaca *Kabupaten*). Yang belum cocok ditandai **belum dipetakan**; administrator memetakannya di menu **Alias Instansi** ([panduan](/panduan/19-master-jf)). Pemetaan itu **dipertahankan** pada impor berikutnya, dan instansi yang berganti nama cukup ditambah aliasnya.

> Data ini per instansi. Rekomendasi BKN per unit kerja dan pembandingan dengan ABK serta bezetting terkini akan menyusul setelah sumber datanya dipetakan.

![Rekomendasi JF MASN](/img/panduan/rekomendasi-jf.png)
