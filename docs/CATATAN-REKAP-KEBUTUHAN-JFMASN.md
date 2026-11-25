# Catatan Analisis: Rekap Kebutuhan JF MASN (Tim Data)

Sumber: `docs/Rekap Kebutuhan JFMASN.xlsx` (39 sheet). Catatan ini merangkum struktur dan isi berkas
sebagai bahan pengembangan SIMONKEB selanjutnya. Angka di bawah dihitung dari berkas per 25-11-2026.

## 1. Sheet inti: `Input Data (Instansi Baru)`

Sumber data paling lengkap; `Rekap`, `DB DASHBOARD`, dan `DATA` adalah turunannya.

- **1.930 baris**, **643 instansi**. Satu baris = satu pasangan *instansi × jabatan*.
- Jabatan: Analis SDM Aparatur, Pranata SDM Aparatur, Asesor SDM Aparatur (tiap instansi 3 baris),
  plus 1 baris Auditor Manajemen ASN.
- Jenis instansi (kode): KAB 1.245, KOT 279, KEMENT 120, PROV 114, LPNK 85, LNS 42, LN 24, KO 21.
- Pokja: POKJA 1 (417), POKJA 2 (429), POKJA 3 (501), POKJA 4 (583).

### Kolom (indeks 0-based)

| Kolom | Isi |
|---|---|
| 0–6 | NO, cek nomenklatur, instansi lama, nama instansi, jabatan, jenis instansi, pokja |
| 7–8 | Status rekomendasi (DONE 1.217, Belum Usul 709, Tidak Memenuhi Syarat 3, Dikembalikan 1), histori status |
| 9–13 | No/tgl surat rekom kebutuhan, link surat, no/tgl surat pengembalian |
| 14–16 | Jenis rekomendasi (Rekom baru 1.037, Rekom tambahan 179), bulan & tahun rekom (rumus) |
| 17–21 | **Bezetting** per jenjang (Pertama/Terampil, Muda/Mahir, Madya/Penyelia, Utama) + total |
| 22–26 | **Perhitungan formasi** per jenjang + total |
| 27–30 | **Lowongan** per jenjang = bezetting − perhitungan (negatif = masih kurang) |
| 31–32 | Ditetapkan sesuai bezetting (Ya/Tidak), catatan |
| 33–40 | Jumlah **penetapan Menpan** per jenjang (dua blok) |
| 41–48 | No/tgl surat penetapan Menpan, jabatan & nama penandatangan, bulan/tahun, status persetujuan Menpan, jumlah penetapan |
| 49–51 | Wilayah kerja, catatan, total lowongan |
| 52–56 | Selisih rekom BKN × penetapan Menpan per jenjang + total |
| 57–60 | Bulan/tahun pengembalian, link drive kuesioner, instansi LKJ 2025 |

Tahun rekomendasi: 2021 (3), 2022 (27), 2023 (347), 2024 (285), 2025 (341), 2026 (193).

### Alur bisnis yang tercermin

1. Instansi mengusulkan → **rekomendasi kebutuhan BKN** (atau dikembalikan / TMS).
2. Rekomendasi bisa **baru** atau **tambahan** (nomor surat lama tidak ditimpa, nomor baru ditambahkan).
3. **Penetapan Menpan** atas rekomendasi; dibandingkan dengan angka rekom BKN.

## 2. Sheet `Rekap` (turunan)

1.870 baris. Kolom: instansi, jabatan, jenis instansi (nama panjang: Kabupaten, Kota,
Kementerian/Lembaga, Provinsi), pokja, status konversi, status pengangkatan, status formasi kebutuhan,
status persetujuan Menpan, lalu blok bezetting / perhitungan / lowongan per jenjang, ditetapkan sesuai
bezetting, keterangan.

- Status persetujuan Menpan: Belum Usul 803, Tidak Ada JFK 417, On Progress 390, DONE 260.
- Status formasi: DONE 676, Tidak ada JFK 242, On Progress 115, Belum Usul 73, Tidak disetujui 6, kosong 709.

## 3. Sheet pendukung

| Sheet | Isi |
|---|---|
| `PANDUAN MENGISI` | Aturan input: isi lewat sheet Input Data; kolom "Rumus Otomatis" jangan diisi; rekom/penetapan baru menambah nomor (bukan menimpa) dan jenis diubah ke "Rekom Tambahan" |
| `resume 2025`, `resume 2026`, `all RESUME` | Rekap per bulan × jabatan, jumlah & persentase instansi sudah direkom / belum usul |
| `BEZETTING` | Bezetting per instansi × jabatan × jenjang (dari SIASN) |
| `REF` | Referensi instansi 2022 / 2024 / 2025: kode, jenis, pokja, wilayah kerja |
| `Rekom x Ukom` | Rekom × hasil uji kompetensi (lulus / tidak lulus / TMS) — **banyak `#REF!`, rusak** |
| `Input Data Lama`, `on process 2022`–`2025` | Data historis per tahun (struktur mirip Input Data) |
| `DB DASHBOARD`, `DATA`, `Pivot*`, `Sheet*` | Bahan dashboard & pivot |

## 4. Masalah kualitas data

- Status tidak konsisten penulisannya ("Tidak Ada JFK" vs "Tidak ada JFK", "Tidak Dapat Dihubungi" vs
  "Tidak dapat dihubungi").
- Sel kosong terisi `False`/`0` hasil rumus; 19 tahun rekom bernilai `#VALUE!`.
- Jenis instansi berbeda antar sheet (kode KAB/KEMENT vs nama panjang).
- `Rekom x Ukom` berisi referensi rusak.

## 5. Pemetaan ke SIMONKEB saat ini

| Excel | SIMONKEB | Catatan |
|---|---|---|
| Instansi | `instansis` | Perlu atribut **pokja**, **wilayah kerja**, jenis instansi kode BKN |
| Jabatan (3 JF MASN) | `jabatans` | Sudah bisa |
| Granularitas instansi × jabatan × jenjang | `usulan_details` per unit kerja × jabatan | Excel **tanpa unit kerja**, tapi **per jenjang** |
| Bezetting | `bezetting_snapshots` / existing | Perlu per jenjang |
| Perhitungan formasi | `kebutuhan_abk` | |
| Rekomendasi BKN (nomor, tgl, baru/tambahan, dikembalikan) | `usulans` status + `jumlah_rekomendasi` | Belum ada nomor surat rekom, jenis rekom, multi-rekom |
| Penetapan Menpan | `penetapans` (1 per usulan) | Excel bisa > 1 surat; perlu per jenjang & penandatangan |
| Status konversi / pengangkatan | — | Belum ada |
| Resume per bulan / pokja | `LaporanService` | Perlu laporan per pokja & per bulan rekom |

## 6. Opsi pengembangan (belum diputuskan)

1. Impor sebagai data historis rekomendasi & penetapan JF MASN.
2. Modul baru "Monitoring Rekomendasi JF MASN" dengan alur pokja seperti Excel.
3. Hanya acuan untuk menyesuaikan laporan yang ada.
