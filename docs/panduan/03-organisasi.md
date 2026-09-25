---
judul: Organisasi, unit kerja, dan peta jabatan
ringkas: Menyusun struktur unit kerja bertingkat, impor Excel, aktif/nonaktif, dan mencetak peta jabatan.
bagian: Hulu — organisasi & data
urutan: 10
peran: [admin, operator_instansi]
menu: [/unit-kerja, /peta-jabatan, /instansi]
---

Struktur organisasi menjadi dasar ABK, monitoring per unit, dan peta jabatan.

## Menambah unit kerja

1. Buka **Organisasi & Unit Kerja**, pilih instansi (khusus administrator).
2. Klik **Tambah Unit**.
3. Isi **Unit induk** (kosongkan untuk unit tertinggi), **Kode** (dipakai untuk impor Excel), **Nama**, **Eselon**, dan **Nama jabatan pimpinan**.
4. **ID Unor SIASN** terisi otomatis bila unit disinkron dari SIASN; boleh diisi manual agar sinkron pegawai dapat memetakan unit.
5. Klik **Simpan**.

![Manajemen organisasi](/img/panduan/unit-kerja.png)

## Impor struktur dari Excel

Klik **Impor Excel**, unduh **Template**, isi kolom `kode, nama, kode_induk, eselon, nama_jabatan_pimpinan, siasn_unor_id`, lalu unggah. Unit dicocokkan berdasarkan **kode**: kode yang sudah ada diperbarui, kode baru ditambahkan. Kolom `kode_induk` membentuk hierarki, sehingga urutan baris bebas.

## Menonaktifkan unit

Klik ikon sakelar pada baris unit. Unit nonaktif tetap tersimpan (riwayat tidak hilang) dan ditandai *nonaktif*. Unit yang masih memiliki pegawai atau sub-unit tidak dapat dihapus — pindahkan pegawainya dulu atau nonaktifkan saja.

## Peta jabatan

Menu **Peta Jabatan** menampilkan setiap unit (bertingkat) beserta jabatannya: **kelas jabatan**, **B** (bezetting/existing), **K** (kebutuhan ABK), dan **+/−**. Klik **Cetak** untuk mencetak atau menyimpan sebagai PDF dari browser.

![Peta jabatan](/img/panduan/peta-jabatan.png)
