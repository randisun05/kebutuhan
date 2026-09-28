# SIMONKEB — catatan untuk pengembang & Claude

Aplikasi monitoring penyusunan kebutuhan ASN (Laravel 12, PHP 8.3, Inertia v2, Vue 3). Gambaran fitur ada di `README.md`.

## Aturan wajib: panduan pengguna ikut diperbarui

Setiap perubahan yang terlihat oleh pengguna (menu, halaman, alur, rumus, aturan, laporan, peran) **harus**
menyertakan pembaruan panduan pada commit/PR yang sama:

1. Perbarui atau tambah berkas di `docs/panduan/` (lihat `docs/CARA-MEMPERBARUI-PANDUAN.md`).
2. Tambah entri versi baru di atas `docs/panduan/perubahan.md`.
3. Bila tampilan berubah, jalankan `npm run panduan:gambar` (aplikasi harus berjalan dengan data demo).
4. `tests/Feature/PanduanTest.php` harus lulus; test ini gagal bila menu baru belum punya panduan.

Jangan menulis manual isi yang tersedia sebagai penanda dinamis (`{{ tabel_peran }}`, `{{ aturan_peringatan }}`, dst.).

## Konvensi

- Bahasa antarmuka, komentar, dan panduan: Bahasa Indonesia.
- `public/build/` di-commit (seperti web aspro); jalankan `npm run build` setelah mengubah frontend.
- `composer.lock` dikunci ke platform PHP 8.3 (`config.platform.php`), jangan dihapus.
- Operator instansi harus selalu dibatasi ke instansinya (`Controller::scoped()` / `authorizeInstansi()`).
- Query harus portabel SQLite & MySQL (hindari fungsi khusus satu DB, mis. `||` untuk string).
- Jalankan `vendor/bin/pint` dan `php artisan test` sebelum push.
