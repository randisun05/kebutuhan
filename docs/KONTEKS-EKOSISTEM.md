# Konteks Proyek: Ekosistem Layanan Digital Direktorat JF Manajemen ASN (BKN)

File ini memberi konteks bagi Claude Code saat mengembangkan aplikasi di ekosistem ini.
Bacalah seluruhnya sebelum merancang, menulis kode, atau mengusulkan perubahan.

## 1. Peran Anda

Anda membantu Pranata Komputer di Direktorat Jabatan Fungsional Manajemen ASN (Dit. JF MASN), BKN,
mengembangkan ekosistem aplikasi yang melayani tujuan organisasi dan Matriks Peran Hasil (MPPH) direktorat.
Bertindaklah sebagai software engineer senior yang paham SPBE, keamanan informasi pemerintah, dan manajemen ASN.

## 2. Organisasi dan mandat

- **Dasar tusi:** Peraturan BKN 1/2025 tentang OTK BKN, Pasal 74 (tugas) dan Pasal 75 huruf a–f (fungsi).
  Kegiatannya: rumusan kebijakan teknis, NSPK, pembinaan, pembinaan dan supervisi, konsultasi, serta pemantauan, evaluasi, dan pelaporan.
  Objeknya: pengelolaan JF, perencanaan kebutuhan, pengembangan kompetensi dan karier, standardisasi–akreditasi–sertifikasi, dan fasilitasi.
- **Mandat instansi pembina:** PermenPANRB 1/2023 Pasal 47 (19 tugas instansi pembina, termasuk pengembangan sistem informasi JF).
- **JF yang dibina:** Analis SDM Aparatur, Pranata SDM Aparatur, Asesor SDM Aparatur, Auditor Manajemen ASN.
- **Acuan operasional:** SE Kepala BKN 1/2026 (panduan uji kompetensi JF MASN); KepKa BKN 41/2026
  (Kamus Kompetensi Teknis JF MASN: 20 kompetensi, masing-masing 5 level, belum dipetakan per jenjang).
- Nomor dan isi peraturan di atas berasal dari analisis sebelumnya. Jangan menambah klaim regulasi baru tanpa sumber; tulis "perlu diverifikasi".

## 3. Tujuan organisasi

Kalimat tujuan: *Pejabat JF Manajemen ASN menjadi penggerak sistem merit di setiap instansi.*

| Kode | Tujuan | Artinya | Indikator MPPH 2026 |
| --- | --- | --- | --- |
| T1 | Acuan yang jelas | Kebijakan dan NSPK JF MASN tersedia dan dipakai instansi | Rumusan kebijakan teknis (100%) |
| T2 | Jumlah yang tepat | Kebutuhan dan formasi JF MASN tersusun di setiap instansi | Instansi menyusun kebutuhan dan pengembangan karier JF MASN (100%) |
| T3 | Kompetensi yang terjamin | Pejabat tersertifikasi dan kompetensinya berkembang | Inovasi INSIGHT (1); pemetaan kompetensi Ahli Madya; fasilitasi ukom |
| T4 | Instansi yang terdampingi | Instansi mendapat pembinaan sesuai kebutuhannya | Instansi mendapat pembinaan sesuai kebutuhan (100%) |
| T5 | Keputusan berbasis data | Kondisi JF MASN terpantau dan menjadi dasar keputusan | Belum ada indikator sendiri |

Kriteria instansi sasaran penataan (MPPH): belum punya JF MASN atau kurang dari 5 pejabat; rutin mengusulkan ukom dengan kelulusan di bawah 50%; jumlah JF MASN terbanyak.

**Siklus pembinaan:** Menetapkan aturan (T1) → Menjamin mutu (T3) → Membina kompetensi (T2, T3) → Mendampingi instansi (T4) → Mengukur (T5) → hasil pengukuran kembali memperbaiki aturan.

## 4. Arsitektur tujuh lapis (dari bawah ke atas)

| Lapis | Komponen |
| --- | --- |
| 1. Sumber | SIASN (API BKN), SSO (login terintegrasi SIASN), NSPK (JDIH BKN) |
| 2. Data terpadu | Data JF: profil terpadu per NIP |
| 3. Pengetahuan | Knowledge Base |
| 4. Layanan AI | AI (layanan bersama) |
| 5. Aplikasi layanan | RICT → OLCT → INSIGHT (layanan inti); Penataan Kebutuhan, Layanan Pendukung |
| 6. Analitik dan keputusan | Dashboard |
| 7. Tujuan organisasi | T1–T5 dan indikator MPPH |

**Prinsip yang wajib dijaga:**
1. **Data naik, arahan turun.** Aplikasi menulis datanya ke Data JF; Dashboard membaca dari Data JF, bukan langsung dari tiap aplikasi.
2. **SIASN adalah sumber kebenaran data kepegawaian.** Jangan menyalin master data ASN secara permanen; gunakan cache atau snapshot yang jelas tujuannya.
3. **NIP adalah kunci tunggal** di semua aplikasi.
4. **AI adalah satu layanan bersama**, tidak dibangun terpisah di tiap aplikasi.
5. **Satu akun (SSO)** dengan hak akses bertingkat: terbuka, instansi, strategis. Peran dan cakupan instansi sebaiknya dikelola terpusat, bukan divalidasi ulang di tiap aplikasi.

## 5. Rancangan per komponen (Sumber → Dipakai oleh → Fitur)

**Data JF**
- Sumber: API JF Instansi Pembina (SIASN); referensi instansi; RICT (data pendaftaran); OLCT (hasil dan skor per kompetensi)
- Dipakai oleh: Dashboard, RICT, OLCT, INSIGHT, Penataan Kebutuhan, Layanan Pendukung, AI
- Fitur: profil terpadu per NIP; data kepegawaian dari SIASN; skor ujian per kompetensi; riwayat kenaikan dan perpindahan; sinkronisasi rutin dan cek kualitas data

**Knowledge Base**
- Sumber: JDIH (NSPK); notula; masukan pakar; sumber internet terkurasi
- Dipakai oleh: OLCT (pembuatan soal), AI, Layanan Pendukung
- Fitur: repositori regulasi berversi; klasifikasi sumber (resmi, internal, pakar, internet); tanya-jawab dengan rujukan (RAG, sudah berjalan); bahan pembuatan soal

**AI**
- Sumber: Knowledge Base; Data JF; prompt generatif
- Dipakai oleh: Dashboard, RICT, OLCT, INSIGHT, Penataan Kebutuhan, Layanan Pendukung
- Fitur: asisten tanya-jawab (RAG); pembuatan soal; pengecekan jawaban; analisa laporan; rekomendasi pengembangan kompetensi; proyeksi kebutuhan; agentic AI dengan persetujuan manusia

**Penataan Kebutuhan**
- Sumber: ABK; Data JF (baseline eksisting); data rekomendasi
- Dipakai oleh: Dashboard; RICT dan OLCT (alert ketersediaan kebutuhan, untuk OLCT opsional); INSIGHT
- Fitur: eksisting vs formasi vs ABK (saat ini masih AB, menuju ABK); instansi sasaran penataan otomatis; rekomendasi kebutuhan JF; alert ketersediaan kebutuhan

**Layanan Pendukung (portal)**
- Sumber: Data JF; Knowledge Base
- Dipakai oleh: Dashboard (data layanan pembinaan)
- Fitur (draf): helpdesk dan konsultasi; coaching clinic; bimtek; sosialisasi; PTSP; informasi kegiatan. Fitur portal lain belum didokumentasikan.

**RICT (registrasi uji kompetensi, sudah live)**
- Sumber: SIASN (data calon peserta); SSO; Data JF; Penataan Kebutuhan (alert ketersediaan kebutuhan)
- Dipakai oleh: OLCT (peserta terverifikasi diteruskan untuk ujian); Data JF; Dashboard
- Fitur: registrasi peserta uji kompetensi; peserta diteruskan ke OLCT. Rincian fitur internal belum didokumentasikan.

**OLCT (pelaksanaan uji kompetensi sampai sertifikat, sudah live)**
- Sumber: RICT (peserta terverifikasi); Data JF; AI; Knowledge Base; Penataan Kebutuhan (alert, opsional)
- Dipakai oleh: Data JF (hasil dan skor per kompetensi); INSIGHT; Dashboard
- Fitur: alur ujian dari awal sampai akhir (belum didokumentasikan)
- **Pengembangan yang sedang berlangsung:**
  - hybrid proctoring: face recognition;
  - computer vision sebagai pemicu indikasi kecurangan;
  - interview asinkron;
  - interview daring via Zoom SDK;
  - AI untuk pembuatan soal, pengecekan jawaban, dan analisa laporan.

**INSIGHT (inovasi MPPH 2026: pemetaan dan rencana pengembangan kompetensi)**
- Sumber: Data JF; OLCT (skor per kompetensi); AI; Knowledge Base; Penataan Kebutuhan
- Dipakai oleh: Dashboard; Layanan Pendukung
- Fitur: profil kompetensi per pejabat; gap kompetensi vs standar jenjang; rencana pengembangan kompetensi; rekomendasi diklat dan layanan. Fitur di luar manajemen OLCT belum didokumentasikan.
- Ruang lingkup 2026: pemetaan kompetensi jenjang Ahli Madya. Rancang agar mudah diperluas ke jenjang lain.

**Dashboard**
- Sumber: Data JF; semua aplikasi (melalui Data JF)
- Dipakai oleh: pimpinan; Pokja; instansi (akses bertingkat)
- Fitur: analitik; rekomendasi keputusan; capaian MPPH; instansi sasaran penataan; peringatan dini; menampilkan hasil agentic AI dari lapis AI

## 6. Dua syarat data yang paling penting

1. **Skor OLCT disimpan per kompetensi**, dengan kode yang mengacu ke Kamus Kompetensi Teknis (KepKa BKN 41/2026), bukan hanya nilai akhir.
2. **Profil terpadu per NIP** di Data JF yang menggabungkan data SIASN dan hasil ukom.

Tanpa keduanya, INSIGHT dan Dashboard hanya bisa merekap, tidak bisa menganalisis gap kompetensi.

## 7. Rambu keamanan dan AI

- Ikuti standar keamanan SPBE/BSSN dan UU 27/2022 tentang Pelindungan Data Pribadi: enkripsi saat transit dan saat disimpan, audit log, MFA untuk admin, prinsip hak akses minimum.
- Aplikasi dibangun di atas platform, data induk, dan standar keamanan Deputi Sistem Informasi dan Digitalisasi BKN. Direktorat adalah pemilik proses bisnis.
- **AI memberi rekomendasi, manusia yang memutuskan.** Kelulusan, sanksi kecurangan (termasuk flag dari face recognition dan computer vision), status akreditasi, dan rekomendasi formasi ditetapkan pejabat berwenang.
- Data pribadi, rekaman proctoring, dan bank soal tidak dikirim ke layanan AI publik; diproses di infrastruktur pemerintah.
- Jawaban AI tentang aturan wajib menyertakan rujukan pasal atau dokumen. Sumber internet terkurasi hanya untuk wawasan tambahan, bukan dasar jawaban normatif.
- Proctoring dan interview: peserta diberi tahu bila AI dipakai, rekaman disimpan terbatas sesuai kebijakan retensi, dan ada mekanisme keberatan yang ditinjau manusia.

## 8. Hal yang masih terbuka

- Rincian fitur internal RICT dan alur OLCT dari awal sampai akhir.
- Fitur INSIGHT di luar manajemen OLCT; daftar lengkap fitur portal.
- Akreditasi (penyelenggara ukom mandiri dan pelatihan fungsional) belum masuk MPPH 2026 dan belum punya sistem.
- Pembagian peran Penataan Kebutuhan dengan platform Direktorat Perencanaan Kebutuhan ASN.

## 9. Cara bekerja

- **Jangan menebak isi internal RICT, OLCT, atau aplikasi lain.** Baca kode di repositori terlebih dahulu. Jika belum jelas, tanyakan sebelum mengubah.
- Kaitkan setiap usulan fitur atau perubahan ke komponen di bagian 5, lapis di bagian 4, dan tujuan T1–T5 atau indikator MPPH yang dilayaninya.
- Pertahankan arah aliran data: aplikasi → Data JF → Dashboard. Jangan membuat integrasi langsung aplikasi-ke-dashboard.
- Untuk integrasi antaraplikasi, gunakan API terdokumentasi (OpenAPI) dan event; NIP sebagai kunci.
- Gunakan bahasa Indonesia yang lugas untuk dokumentasi, komentar penjelas, dan pesan ke pengguna, kecuali konvensi repositori berkata lain.
- Tandai dengan jelas mana fakta dari kode atau dokumen, mana asumsi.

## 10. Informasi repositori (isi oleh tim)

- Stack teknologi: [lengkapi]
- Struktur repositori dan aplikasi yang ada di dalamnya: [lengkapi]
- Cara menjalankan, menguji, dan deploy: [lengkapi]
- Lingkungan (dev, staging, produksi) dan aturan perubahan: [lengkapi]
