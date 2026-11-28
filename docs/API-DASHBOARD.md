# Menghubungkan API Dashboard (bezetting JF)

SIMONKEB dapat menarik angka bezetting JF kondisi kini dari API aplikasi Dashboard. Spesifikasi API belum final, jadi
semua yang mungkin berbeda (alamat, token, nama parameter, nama field) diatur lewat `.env` tanpa mengubah kode
(`config/dashboardjf.php`).

## Kontrak yang diharapkan (bawaan)

`GET {DASHBOARD_JF_BASE_URL}{DASHBOARD_JF_ENDPOINT}?pembina=BKN&per=2026-11-01&page=1&per_page=500`

```json
{
  "per": "2026-11-01",
  "meta": { "last_page": 3 },
  "data": [
    { "instansi_siasn_id": "A8ACA7...", "instansi_kode": "...", "instansi_nama": "Kementerian X",
      "jabatan": "Analis SDM Aparatur", "jenjang": "Muda", "jumlah": 12 }
  ]
}
```

- `per` (query) opsional; tanpa itu API memberi data terbaru dan menyebutkan tanggalnya di field `per`.
- Baris ganda untuk instansi × jabatan × jenjang yang sama dijumlahkan.
- Jabatan dan jenjang dicocokkan dengan master (menu Referensi JF) lewat **kode atau nama**. Yang tidak dikenal
  **tidak dibuat otomatis**; dilaporkan agar master tetap terkurasi.
- Instansi dicocokkan lewat ID SIASN, kode, lalu nama (alias). Alias baru dibuat bila belum ada.

## Langkah menghubungkan

1. Isi `.env`: `DASHBOARD_JF_ENABLED=true`, `DASHBOARD_JF_BASE_URL`, `DASHBOARD_JF_TOKEN`. Bila header autentikasi
   berbeda: `DASHBOARD_JF_AUTH_HEADER` (mis. `X-Api-Key`) dan `DASHBOARD_JF_AUTH_SKEMA` (kosongkan bila tanpa "Bearer").
2. Bila nama field berbeda, sesuaikan `DASHBOARD_JF_FIELD_*` dan `DASHBOARD_JF_PARAM_*` (lihat `config/dashboardjf.php`).
3. Aktifkan sumber **Bezetting Dashboard** (menu Referensi JF → Sumber Data).
4. Uji tanpa menulis data: `php artisan jf:tarik-dashboard --uji` (menampilkan jumlah baris dan yang tak cocok).
5. Tarik data: `php artisan jf:tarik-dashboard` (opsi `--pembina=KODE`, `--per=TANGGAL`).
6. Jadwal harian 02:30: `DASHBOARD_JF_TERJADWAL=true` (butuh `schedule:run` aktif).

Angka disimpan berversi di `kebutuhan_fakta`: versi baru hanya dibuat bila nilainya berubah, sehingga riwayat terjaga.
Hasilnya langsung tampil berdampingan dengan sumber lain di halaman **Perbandingan Kebutuhan JF**.

## Pesan kesalahan umum

| Pesan | Penyebab |
|---|---|
| belum aktif | `DASHBOARD_JF_ENABLED` atau `DASHBOARD_JF_BASE_URL` kosong |
| menolak akses (401/403) | token atau header autentikasi salah |
| tidak memuat field "data" | struktur respons berbeda; atur `DASHBOARD_JF_FIELD_DATA` |
| belum punya sumber ber-adapter dashboard | tambahkan sumber di menu Referensi JF |
