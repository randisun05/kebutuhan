---
judul: Master JF, sumber, alias instansi, dan profil impor
ringkas: Mengelola jabatan pokok, jenjang, sumber angka, alias instansi, dan tata letak Excel sebagai data.
bagian: Referensi
urutan: 95
peran: [admin, pembina]
menu: [/jf-master, /alias-instansi, /impor-profil]
---

Modul [Rekomendasi JF MASN](/panduan/18-rekomendasi-jf) tidak menulis jabatan, jenjang, sumber angka, atau tata letak Excel di dalam kode. Semuanya **data** yang dikelola administrator lewat menu **Referensi**, sehingga JF baru, jenjang baru, sumber baru, atau perubahan susunan kolom Excel tidak memerlukan perubahan aplikasi.

## Instansi pembina dan hak akses

Setiap jabatan pokok, sumber angka, dan profil impor **dimiliki satu instansi pembina**. Administrator mengelola daftar pembina di tab **Instansi pembina** (kode, nama, status) dan membuat pengguna berperan **Operator Instansi Pembina** di menu Pengguna dengan memilih instansi pembinanya (wajib). Pengguna pembina hanya dapat:

- melihat dan mengubah jabatan pokok, sumber angka, dan profil impor **milik pembinanya** (data pembina lain ditolak di server, walau alamatnya diketik langsung);
- mengimpor Excel dengan profil milik pembinanya;
- membuka modul rekomendasi, master, profil impor, panduan, dan akunnya. **Modul operasional lain tertutup** untuk peran ini.

**Jenjang** (milik bersama), **Alias Instansi**, dan **Instansi pembina** hanya dapat dikelola administrator, karena memengaruhi semua pembina. Kode sumber boleh sama di pembina berbeda; profil impor hanya dapat memakai sumber milik pembinanya.

## Master JF & Sumber

Menu **Master JF & Sumber** (pilih instansi pembina di atas halaman bila ada lebih dari satu) punya tab berikut.

- **Jabatan pokok** — kode, nama, rumpun (keahlian atau keterampilan), urutan, dan status. Ikon **atur jenjang** menetapkan jenjang yang berlaku untuk jabatan itu beserta **posisi kolom** pada blok angka di Excel (1 = kolom pertama blok, dst.). Posisi harus unik per jabatan.
- **Jenjang** — kode, nama, rumpun, urutan, dan status.
- **Sumber angka** — kode, nama, jenis (kebutuhan atau bezetting), tingkat rinci (per instansi atau per unit), sifat (foto saat dokumen atau data hidup), dan asal nilai (saat ini selalu impor). Sumber aktif mendapat kolom di daftar rekomendasi dan dapat dipakai pada profil impor. Saat ini semua sumber berasal dari impor Excel; asal lain (ABK, pegawai, API Dashboard) akan ditambahkan bersama halaman perbandingan, dan baru muncul sebagai pilihan setelah berfungsi.

![Master JF & Sumber](/img/panduan/master-jf.png)

Aturan pengamanan data:

- **Kode** hanya huruf kecil, angka, `-`, dan `_`, dan harus unik.
- Item yang **sudah dipakai** (jabatan dengan rekomendasi, jenjang yang punya angka atau terpasang pada jabatan, sumber yang punya angka) **tidak dapat dihapus**; nonaktifkan saja. Kode sumber yang sudah punya data tidak dapat diubah karena dipakai profil impor.
- Jenjang yang sudah punya angka untuk suatu jabatan tidak dapat dilepas dari jabatan itu.
- Setiap perubahan tercatat di **Log Audit**.

## Alias Instansi

Menu **Alias Instansi** memuat nama instansi sebagaimana tertulis di sumber data dan pemetaannya ke **referensi instansi BKN**. Beberapa alias boleh menuju satu instansi, sehingga instansi yang berganti nama atau ditulis berbeda tetap menyatu.

- Cari nama, atau centang **hanya yang belum dipetakan**.
- **Ubah** untuk memilih referensi instansi (ketik nama lalu pilih dari daftar), serta mengisi pokja dan wilayah kerja yang bersifat opsional.
- **Tambah alias** untuk mendaftarkan nama lama atau penulisan lain sebelum impor berikutnya. Nama yang sama setelah dinormalkan ditolak.
- Alias yang sudah dipakai data rekomendasi tidak dapat dihapus.

![Alias Instansi](/img/panduan/alias-instansi.png)

## Profil Impor

Menu **Profil Impor** menyimpan tata letak berkas Excel sumber. Bila tim data menyisipkan atau memindahkan kolom, ubah profil, bukan kode.

- **Umum**: kode, nama, nama sheet, dan status aktif.
- **Kolom isian**: huruf kolom Excel (mis. `D`, `AX`) untuk nama instansi dan jabatan (wajib) serta jenis instansi, pokja, status rekomendasi, jenis rekomendasi, wilayah kerja, dan sesuai bezetting. Kolom catatan boleh lebih dari satu.
- **Blok angka**: satu blok per sumber, dengan kolom awal (jenjang pertama), lebar, dan kolom awal cadangan bila blok utama kosong. Semua sumber aktif dapat dipilih.
- **Surat**: untuk tiap jenis surat, kolom nomor, tanggal, tautan, dan penandatangan. Satu sel boleh memuat beberapa surat.

Profil divalidasi saat disimpan (huruf kolom, sumber dikenal, minimal satu blok). Pratinjau impor menampilkan peringatan bila jabatan di berkas belum ada di master atau bila angka berada pada posisi jenjang yang tidak berlaku.

![Profil Impor](/img/panduan/impor-profil.png)
