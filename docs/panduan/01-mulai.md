---
judul: Mulai menggunakan SIMONKEB
ringkas: Login (akun atau SSO SIASN), tampilan utama, menu, bantuan, dan notifikasi.
bagian: Dasar
urutan: 1
peran: [semua]
menu: [/dashboard, /login]
---

SIMONKEB (Sistem Informasi Monitoring Penyusunan Kebutuhan ASN) mengelola penyusunan kebutuhan ASN **dari hulu ke hilir**: data organisasi dan pegawai existing → Analisis Jabatan & Analisis Beban Kerja (ABK) → proyeksi → usulan instansi → pertimbangan teknis BKN → penetapan KemenPANRB → pengisian formasi, lalu memonitor kondisi kebutuhan vs existing secara real-time.

## Masuk ke aplikasi

Ada dua cara masuk:

1. **Email dan password** — isi email dan password akun SIMONKEB, lalu klik **Masuk**. Lupa password? Klik **Lupa password?** dan ikuti tautan yang dikirim ke email.
2. **SSO SIASN** — klik **Masuk dengan SSO SIASN**, masuk dengan akun SIASN Anda di halaman BKN, lalu Anda otomatis kembali ke SIMONKEB. Tombol ini hanya muncul bila administrator sudah mengaktifkan SSO.

> Login SSO hanya berhasil bila **NIP Anda sudah terdaftar** sebagai pengguna SIMONKEB. Bila muncul pesan "belum terdaftar", minta administrator menambahkan akun dengan NIP Anda.

Bila akun Anda memakai autentikasi dua faktor (2FA), setelah password Anda akan diminta kode 6 digit dari aplikasi autentikator. Login lewat SSO SIASN tidak meminta 2FA lagi karena BKN sudah memakai MFA.

![Halaman login](/img/panduan/login.png)

## Tampilan utama

Setelah masuk, Anda berada di **Dashboard**:

- **Kotak peringatan penting** (merah) — peringatan kritis/tinggi terbaru dari sistem peringatan dini.
- **Kartu angka** — kebutuhan (hasil ABK final), existing, kekurangan, kelebihan (gemuk), jabatan kosong, dan pensiun ≤ 5 tahun.
- **Grafik** kebutuhan vs existing per instansi dan kurang vs lebih per jenis jabatan.
- **Progres usulan** per status dan **penetapan terbaru**.

Angka di dashboard diperbarui otomatis setiap 30 detik (lihat tanda *Real-time · diperbarui …*). Klik ikon ↻ untuk memperbarui saat itu juga.

![Dashboard](/img/panduan/dashboard.png)

## Menu dan navigasi

Menu di sisi kiri dikelompokkan sesuai alur kerja: **Monitoring**, **Hulu: Organisasi & Data**, **Hulu: ABK & Perencanaan**, **Hilir: Usulan s.d. Penetapan**, dan **Referensi**. Menu yang tampil menyesuaikan peran Anda — lihat [Peran dan hak akses](/panduan/02-peran).

Di pojok kanan atas:

- **Bantuan (?)** — membuka bagian panduan yang sesuai dengan halaman yang sedang Anda buka.
- **Lonceng** — notifikasi (usulan yang perlu tindakan, peringatan penting). Klik notifikasi untuk langsung membuka halamannya.
- **Nama Anda** — keamanan akun (ganti password, 2FA) dan **Keluar**.

Badge angka di menu **Usulan Kebutuhan** menunjukkan usulan yang menunggu tindakan Anda, dan badge merah di **Peringatan Dini** menunjukkan peringatan kritis/tinggi yang masih aktif.

## Istilah penting

| Istilah | Arti |
| --- | --- |
| Kebutuhan | Jumlah pegawai yang dibutuhkan menurut ABK **final** tahun terbaru |
| Existing / bezetting | Jumlah pegawai aktif saat ini |
| Posisi | Kombinasi unit kerja × jabatan, satuan terkecil perhitungan |
| Kurang / kosong | Existing < kebutuhan / existing = 0 padahal dibutuhkan |
| Lebih (gemuk) | Existing > kebutuhan |
| Formasi belum terisi | Formasi yang sudah ditetapkan tetapi belum diisi hasil seleksi |
