<template>
    <Head title="Pedoman & Regulasi" />
    <div class="page-title">Pedoman Penyusunan Kebutuhan ASN</div>
    <div class="page-subtitle">Ringkasan dasar hukum dan metode yang dipakai aplikasi. Untuk bunyi pasal yang mengikat, rujuk naskah resmi pada JDIH KemenPANRB / BKN.</div>

    <div class="row g-3">
        <div class="col-xl-7">
            <div class="card mb-3">
                <div class="card-header"><i class="fa fa-book me-1"></i> Dasar Hukum</div>
                <ul class="list-group list-group-flush">
                    <li v-for="r in regulasi" :key="r.nama" class="list-group-item">
                        <div class="fw-semibold">{{ r.nama }}</div>
                        <div class="small text-muted">{{ r.isi }}</div>
                    </li>
                </ul>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="fa fa-random me-1"></i> Alur Hulu ke Hilir dalam Aplikasi</div>
                <div class="card-body">
                    <ol class="mb-0">
                        <li v-for="a in alur" :key="a.judul" class="mb-2"><b>{{ a.judul }}</b> — <span class="text-muted">{{ a.pelaku }}</span><div class="small">{{ a.isi }}</div></li>
                    </ol>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card mb-3">
                <div class="card-header"><i class="fa fa-calculator me-1"></i> Rumus Analisis Beban Kerja</div>
                <div class="card-body">
                    <p class="mb-2">Beban kerja per uraian tugas = <b>volume hasil kerja per tahun × norma waktu</b> (menit).</p>
                    <p class="mb-2">Kebutuhan pegawai = <b>Σ beban kerja ÷ waktu kerja efektif</b>.</p>
                    <p class="mb-2">Waktu kerja efektif = <b>1.250 jam/tahun ({{ num(wke) }} menit)</b>. Hasil dibulatkan: pecahan ≥ 0,5 ke atas.</p>
                    <p class="mb-0">Efektivitas Jabatan (EJ) = beban kerja ÷ (jumlah pegawai existing × waktu kerja efektif).</p>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Prestasi Efektivitas Jabatan (PEJ)</div>
                <table class="table table-sm mb-0">
                    <thead><tr><th>Nilai EJ</th><th>PEJ</th><th>Kategori</th></tr></thead>
                    <tbody>
                        <tr><td>&gt; 1,00</td><td>A</td><td>Sangat baik</td></tr>
                        <tr><td>0,90 – 1,00</td><td>B</td><td>Baik</td></tr>
                        <tr><td>0,70 – 0,89</td><td>C</td><td>Cukup</td></tr>
                        <tr><td>0,50 – 0,69</td><td>D</td><td>Sedang</td></tr>
                        <tr><td>&lt; 0,50</td><td>E</td><td>Kurang</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="card mb-3">
                <div class="card-header">Definisi Kondisi pada Monitoring</div>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item"><Kondisi status="kurang" /> existing lebih kecil dari kebutuhan ABK final.</li>
                    <li class="list-group-item"><Kondisi status="kosong" /> jabatan dibutuhkan tetapi belum ada pegawai sama sekali.</li>
                    <li class="list-group-item"><Kondisi status="lebih" /> existing melebihi kebutuhan (gemuk), kandidat redistribusi.</li>
                    <li class="list-group-item"><Kondisi status="ideal" /> tingkat pemenuhan agregat 90–110%.</li>
                    <li class="list-group-item"><Kondisi status="tanpa_abk" /> ada pegawai tetapi jabatan belum memiliki ABK final.</li>
                    <li class="list-group-item">Proyeksi pensiun dihitung dari tanggal lahir + BUP jabatan dalam {{ horizon }} tahun ke depan (satu siklus perencanaan).</li>
                </ul>
            </div>

            <div class="card">
                <div class="card-header">Batas Usia Pensiun (BUP)</div>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item">58 tahun: pejabat administrasi, JF ahli muda, ahli pertama, dan keterampilan.</li>
                    <li class="list-group-item">60 tahun: pejabat pimpinan tinggi dan JF ahli madya.</li>
                    <li class="list-group-item">65 tahun: JF ahli utama.</li>
                    <li class="list-group-item text-muted">Nilai BUP dapat diatur per jabatan pada menu Referensi Jabatan.</li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import Kondisi from '../../Components/Kondisi.vue';
import { num } from '../../utils';

defineProps({ wke: Number, horizon: Number });

const regulasi = [
    { nama: 'UU Nomor 20 Tahun 2023 tentang Aparatur Sipil Negara', isi: 'Kebutuhan ASN (PNS dan PPPK) ditetapkan secara nasional berdasarkan perencanaan kebutuhan instansi; menjadi payung manajemen ASN.' },
    { nama: 'PP Nomor 11 Tahun 2017 jo. PP Nomor 17 Tahun 2020 tentang Manajemen PNS', isi: 'Instansi menyusun kebutuhan jumlah dan jenis jabatan PNS berdasarkan analisis jabatan dan analisis beban kerja, untuk jangka 5 tahun yang dirinci per 1 tahun berdasarkan prioritas. Menteri PANRB menetapkan kebutuhan dengan memperhatikan pendapat Menteri Keuangan dan pertimbangan teknis Kepala BKN.' },
    { nama: 'PP Nomor 49 Tahun 2018 tentang Manajemen PPPK', isi: 'Penyusunan dan penetapan kebutuhan PPPK mengikuti pola yang sama: berdasarkan analisis jabatan dan beban kerja, ditetapkan oleh Menteri PANRB.' },
    { nama: 'Peraturan Menteri PANRB Nomor 1 Tahun 2020 tentang Pedoman Analisis Jabatan dan Analisis Beban Kerja', isi: 'Metode Anjab dan ABK: uraian tugas, hasil kerja, norma waktu, waktu kerja efektif, perhitungan kebutuhan pegawai, dan efektivitas jabatan.' },
    { nama: 'Peraturan Menteri PANRB Nomor 1 Tahun 2023 tentang Jabatan Fungsional', isi: 'Kebutuhan jabatan fungsional dihitung berdasarkan beban kerja dan ditetapkan dalam rangka penataan jabatan.' },
    { nama: 'Peraturan Kepala BKN Nomor 19 Tahun 2011 tentang Pedoman Umum Penyusunan Kebutuhan PNS', isi: 'Pedoman teknis perhitungan kebutuhan berdasarkan beban kerja, termasuk memperhitungkan pegawai yang akan pensiun.' },
];

const alur = [
    { judul: 'Struktur organisasi & data existing', pelaku: 'Operator instansi', isi: 'Memutakhirkan unit kerja dan data pegawai (bezetting), termasuk tanggal lahir untuk proyeksi pensiun. Bisa impor Excel.' },
    { judul: 'Anjab & ABK', pelaku: 'Operator instansi', isi: 'Menyusun uraian tugas, volume, dan norma waktu per jabatan per unit. ABK yang difinalkan menjadi angka kebutuhan resmi.' },
    { judul: 'Penyusunan usulan', pelaku: 'Operator instansi', isi: 'Rincian ditarik otomatis dari ABK (kekurangan + proyeksi pensiun) lalu disesuaikan, dilengkapi surat pengantar, kemudian diajukan.' },
    { judul: 'Verifikasi & pertimbangan teknis', pelaku: 'BKN', isi: 'Memverifikasi usulan, mengisi jumlah rekomendasi per jabatan, atau mengembalikan/menolak dengan catatan.' },
    { judul: 'Validasi & penetapan', pelaku: 'KemenPANRB', isi: 'Memvalidasi rekomendasi BKN, menetapkan jumlah per jabatan beserta nomor dan tanggal SK. Lampiran penetapan dapat dicetak.' },
    { judul: 'Monitoring real-time', pelaku: 'Semua peran', isi: 'Dashboard membandingkan kebutuhan ABK dengan existing per instansi, per unit kerja, dan per jabatan, dan diperbarui otomatis setiap 30 detik.' },
];
</script>
