# Cara memperbarui panduan pengguna

Panduan pengguna SIMONKEB tersimpan di `docs/panduan/*.md` dan tampil di aplikasi (menu **Panduan Pengguna**,
tombol **Bantuan** di setiap halaman, dan unduhan PDF). Panduan ikut ter-deploy bersama kode, jadi
**setiap perubahan fitur wajib disertai pembaruan panduan dalam commit/PR yang sama.**

## Yang otomatis

Bagian berikut dibangkitkan dari kode saat halaman dibuka, jadi **jangan ditulis manual**. Pakai penandanya:

| Penanda | Sumber |
| --- | --- |
| `{{ tabel_peran }}` | `App\Enums\Role` |
| `{{ akses_menu }}` | `resources/js/Components/Sidebar.vue` |
| `{{ status_usulan }}`, `{{ aksi_usulan }}` | `App\Enums\UsulanStatus`, `App\Services\UsulanWorkflow` |
| `{{ aturan_peringatan }}`, `{{ ambang_peringatan }}` | `App\Services\PeringatanService::ATURAN`, `config/simonkeb.php` |
| `{{ jenis_laporan }}` | `App\Services\LaporanService::JENIS` |
| `{{ konstanta_abk }}`, `{{ kategori_pej }}`, `{{ informasi_jabatan }}`, `{{ jenis_jabatan }}`, `{{ prioritas_usulan }}` | `App\Support\Referensi` |
| `{{ versi }}` | judul teratas `docs/panduan/perubahan.md` |

Penanda baru ditambahkan di `App\Services\PanduanService::blokDinamis()`.

## Yang dijaga test (CI gagal bila dilanggar)

`tests/Feature/PanduanTest.php` memastikan:

1. **Setiap menu di sidebar punya halaman panduan** (path menu tercantum di front matter `menu` salah satu berkas).
2. Semua penanda dikenal dan terganti, peran di front matter valid.
3. Semua gambar yang dirujuk ada di `public/img/panduan`.
4. Semua tautan antarhalaman `/panduan/...` menuju halaman yang ada.
5. `perubahan.md` memiliki versi `vX.Y.Z` yang valid.

## Langkah saat mengubah fitur

1. Ubah/tambah berkas di `docs/panduan/`. Format front matter:

   ```yaml
   ---
   judul: Judul halaman
   ringkas: Satu kalimat ringkasan.
   bagian: Monitoring & analitik   # pengelompokan di daftar panduan
   urutan: 40                      # urutan tampil
   peran: [semua]                  # atau [admin, operator_instansi, ...]
   menu: [/monitoring]             # path menu yang dijelaskan (dipakai tombol Bantuan)
   ---
   ```

2. Tambahkan entri di **atas** `docs/panduan/perubahan.md` dengan versi baru (`## vX.Y.Z — YYYY-MM-DD`).
   Pengguna yang belum membacanya akan melihat pemberitahuan "Ada pembaruan aplikasi".
   Naikkan versi: *patch* untuk perbaikan, *minor* untuk fitur baru, *major* untuk perubahan alur besar.
3. Bila tampilan berubah, perbarui tangkapan layar:

   ```bash
   php artisan migrate:fresh --seed && php artisan serve
   APP_URL=http://127.0.0.1:8000 npm run panduan:gambar
   ```

   Daftar gambar ada di `scripts/panduan-gambar.mjs`.
4. Jalankan `php artisan test` sebelum push.
