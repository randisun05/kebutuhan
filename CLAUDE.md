# SIMONKEB — catatan untuk pengembang & Claude

Aplikasi monitoring penyusunan kebutuhan ASN (Laravel 12, PHP 8.3, Inertia v2, Vue 3). Gambaran fitur ada di `README.md`.

## Konteks ekosistem

Aplikasi ini bagian dari ekosistem layanan digital Dit. JF Manajemen ASN (BKN). Baca `docs/KONTEKS-EKOSISTEM.md`
(tujuan T1–T5, arsitektur tujuh lapis, prinsip data, rambu keamanan dan AI) sebelum merancang fitur baru.
Asumsi (perlu diverifikasi tim): SIMONKEB berperan sebagai komponen **Penataan Kebutuhan** (tujuan T2: eksisting vs formasi vs ABK).
Prinsip yang berlaku di sini: NIP kunci tunggal, SIASN sumber kebenaran data kepegawaian, AI memberi rekomendasi dan manusia memutuskan.

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
- Fitur AI (Gemini) wajib lewat `App\Services\Ai\AsistenAi::riwayat()`: hasil disimpan di tabel `ai_riwayat` dan dipakai ulang; AI hanya dipanggil lagi bila pengguna meminta "Buat ulang" (`baru=1`). Jangan memanggil `GeminiClient` langsung dari controller, dan jangan membuat tabel riwayat AI baru.
- Modul Rekomendasi JF MASN bersifat **dinamis**: jangan menulis jabatan pokok, jenjang, sumber angka, nama sheet, atau huruf kolom Excel di dalam kode. Semuanya data (`jf_jabatan_pokok`, `jf_jenjang`, `sumber_data`, `impor_profil`, `instansi_alias`) yang dikelola lewat menu Referensi; angka disimpan berversi di `kebutuhan_fakta`.
- Isolasi instansi pembina: data modul Rekomendasi JF dimiliki satu `instansi_pembina` (lewat `jf_jabatan_pokok`, `sumber_data`, `impor_profil`). Setiap query/aksi yang menyentuh data itu harus dibatasi dengan `User::canAccessPembina()` / `RekomendasiJf::untukPembina()`, dan pengguna berperan `pembina` ditolak dari modul lain oleh `BatasiPembina`. Peran baru yang membatasi cakupan harus ditambahkan ke middleware itu.
- Jalankan `vendor/bin/pint` dan `php artisan test` sebelum push.

## Pengingat arsitektur (dari pemilik produk)

Dashboard = pusat pertukaran data antar-project (lihat `docs/KONTEKS-EKOSISTEM.md` §9a). Ingatkan pengguna bila merancang fitur yang
mengambil data project lain langsung (harus lewat Dashboard), atau fitur yang memuat data sendiri tetapi belum dikirim ke Dashboard.
Referensi baku (instansi, jabatan JF, jenjang) harus identik antar-project: jangan membuat varian lokal.
