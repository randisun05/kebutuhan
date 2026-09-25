---
judul: Manajemen ABK (Analisis Jabatan & Beban Kerja)
ringkas: Menyusun, memfinalkan, menyalin, mengimpor, dan mencetak ABK sesuai PermenPANRB 1/2020.
bagian: Hulu — ABK & perencanaan
urutan: 20
peran: [semua]
menu: [/anjab, /anjab-rekap-unit]
---

ABK menentukan **berapa pegawai yang dibutuhkan** setiap jabatan di setiap unit. Hanya ABK berstatus **final** yang dipakai monitoring, proyeksi, dan usulan; bila ada beberapa tahun, yang dipakai adalah **ABK final tahun terbaru**.

## Rumus

Untuk setiap uraian tugas: **beban kerja = volume per tahun × norma waktu** (menit). Kebutuhan pegawai = **Σ beban kerja ÷ waktu kerja efektif**, dibulatkan (pecahan ≥ 0,5 ke atas).

{{ konstanta_abk }}

Volume boleh diisi per bulan/minggu/hari; aplikasi mengalikannya dengan pengali di atas agar setara per tahun.

## Menyusun ABK baru

1. Buka **Manajemen ABK** → **Tambah ABK**.
2. Pilih instansi, unit kerja, jabatan, dan **tahun ABK**.
3. **Tab 1 — Uraian Tugas & Beban Kerja**: tambahkan baris uraian tugas, hasil kerja, volume + periode, dan norma waktu (menit). Kebutuhan pegawai langsung terhitung di bawah tabel.
4. **Tab 2 — Informasi Jabatan**: ikhtisar dan unsur informasi jabatan (satu butir per baris):

{{ informasi_jabatan }}

5. **Tab 3 — Waktu Kerja & Proyeksi**: ubah waktu kerja efektif bila perlu (ada kalkulator hari × jam) dan isi **perkiraan pertumbuhan beban kerja per tahun (%)** untuk proyeksi 5 tahun.
6. Simpan sebagai **Draft**, periksa, lalu **Finalkan**.

![Form ABK](/img/panduan/abk-form.png)

## Memfinalkan dan membuka kembali

Klik ikon gembok (atau tombol **Finalkan** di halaman detail). ABK final mencatat siapa dan kapan memfinalkan. Klik lagi untuk mengembalikan ke draft — ABK draft dikeluarkan dari perhitungan kebutuhan.

## Menyalin ke tahun berikutnya

- Satu ABK: buka detail → **Salin ke tahun…**.
- Seluruh instansi: di daftar ABK klik **Salin Tahun**, pilih tahun asal (ABK final) dan tahun tujuan.

Salinan dibuat sebagai **draft** agar volume beban kerja bisa disesuaikan sebelum difinalkan.

## Impor dan ekspor

- **Impor**: kolom `kode_unit, kode_jabatan, tahun, uraian_tugas, hasil_kerja, volume, satuan_periode, norma_waktu`. Baris dengan unit + jabatan + tahun yang sama digabung menjadi satu ABK draft. ABK final tidak ditimpa.
- **Export**: rekap ABK sesuai filter ke Excel (beban kerja, kebutuhan, existing, EJ, PEJ).
- **PDF**: dokumen Informasi Jabatan & ABK per jabatan (ikon PDF).

## Efektivitas jabatan dan unit

**EJ** = beban kerja ÷ (pegawai existing × waktu kerja efektif), dikategorikan menjadi **PEJ**. Menu **Efektivitas Unit** menghitung **EU/PEU** per unit dari ABK final.

{{ kategori_pej }}

EJ/EU di atas 1 berarti beban kerja melebihi kapasitas pegawai yang ada; di bawah 0,5 berarti pegawai jauh melebihi beban kerja.
