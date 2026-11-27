<template>
    <Head :title="data.alias.nama" />
    <Link href="/rekomendasi-jf" class="small"><i class="fa fa-angle-left"></i> Semua rekomendasi</Link>
    <div class="page-title">{{ data.alias.nama }}</div>
    <div class="page-subtitle">{{ data.jabatan_pokok.nama }} · {{ data.jabatan_pokok.rumpun }}
        <span v-if="data.pokja"> · {{ data.pokja }}</span><span v-if="data.wilayah_kerja"> · {{ data.wilayah_kerja }}</span></div>

    <div class="d-flex flex-wrap gap-2 mb-3 small">
        <span class="badge" :class="data.status_rekom === 'DONE' ? 'bg-success' : 'bg-secondary'">Rekomendasi: {{ data.status_rekom || '-' }}</span>
        <span v-if="data.jenis_rekom" class="badge bg-info text-dark">{{ data.jenis_rekom }}</span>
        <span class="badge" :class="data.status_menpan ? 'bg-primary' : 'bg-secondary'">Menpan: {{ data.status_menpan || 'belum ditetapkan' }}</span>
        <span v-if="!data.alias.ref_instansi" class="badge bg-warning text-dark">alias belum dipetakan ke referensi instansi BKN</span>
    </div>

    <div class="card mb-3">
        <div class="card-header">Perbandingan per jenjang</div>
        <div class="card-body py-2 small text-muted">Setiap sumber ditampilkan apa adanya dan tidak saling membatasi. Sel kosong berarti tidak ada data, bukan nol.<template v-if="lowonganAda"> Lowongan = bezetting − rekomendasi BKN (negatif berarti masih kurang).</template></div>
        <div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>Jenjang</th><th v-for="x in sumber" :key="x.kode" class="num">{{ x.nama }}</th><th v-if="lowonganAda" class="num">Lowongan</th></tr></thead>
            <tbody>
                <tr v-for="j in jenjang" :key="j.kode"><td>{{ j.nama }}</td>
                    <td v-for="x in sumber" :key="x.kode" class="num">{{ n(j.nilai[x.kode]) }}</td>
                    <td v-if="lowonganAda" class="num" :class="lowongan(j) < 0 ? 'text-kurang' : ''">{{ lowongan(j) === null ? '-' : n(lowongan(j)) }}</td></tr>
                <tr class="fw-semibold"><td>Total</td><td v-for="x in sumber" :key="x.kode" class="num">{{ jumlah(x.kode) }}</td><td v-if="lowonganAda" class="num">{{ n(jenjang.reduce((a, j) => a + (lowongan(j) ?? 0), 0)) }}</td></tr>
            </tbody>
        </table></div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Surat</div>
        <div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>Jenis</th><th>Nomor</th><th>Tanggal</th><th>Penandatangan</th><th></th></tr></thead>
            <tbody>
                <tr v-for="s in data.surats" :key="s.id"><td>{{ label[s.jenis] || s.jenis }}</td><td>{{ s.nomor }}</td><td>{{ s.tanggal ? tanggal(s.tanggal) : '-' }}</td>
                    <td class="small">{{ [s.penandatangan_nama, s.penandatangan_jabatan].filter(Boolean).join(' — ') || '-' }}</td>
                    <td class="text-end"><a v-if="s.tautan && /^https?:/.test(s.tautan)" :href="s.tautan" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-light">Buka tautan</a></td></tr>
                <tr v-if="!data.surats.length"><td colspan="5" class="text-center text-muted py-3">Belum ada surat tercatat.</td></tr>
            </tbody>
        </table></div>
    </div>

    <div v-if="riwayat.length" class="card mb-3">
        <div class="card-header">Riwayat perubahan angka <span class="badge bg-secondary ms-1">{{ riwayat.length }}</span></div>
        <div class="card-body py-2 small text-muted">Nilai lama yang digantikan oleh impor berikutnya (versi terbaru ada di tabel di atas).</div>
        <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Sumber</th><th>Jenjang</th><th class="num">Nilai lama</th><th>Dicatat</th></tr></thead>
            <tbody><tr v-for="(r, i) in riwayat" :key="i"><td>{{ r.sumber }}</td><td>{{ r.jenjang }}</td><td class="num">{{ n(r.nilai) }}</td><td class="small">{{ waktu(r.dicatat) }}</td></tr></tbody></table></div>
    </div>

    <div v-if="data.catatan" class="alert alert-light border small" style="white-space: pre-line">{{ data.catatan }}</div>
</template>

<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { num, tanggal, waktu } from '../../utils';

const props = defineProps({ data: Object, sumber: Array, jenjang: Array, riwayat: Array });
const label = { rekomendasi: 'Rekomendasi BKN', pengembalian: 'Pengembalian', penetapan_menpan: 'Penetapan KemenPANRB' };
const n = (v) => (v === null || v === undefined ? '-' : num(v));
const jumlah = (kode) => (props.jenjang.some((j) => j.nilai[kode] != null) ? num(props.jenjang.reduce((a, j) => a + (j.nilai[kode] ?? 0), 0)) : '-');
// lowongan hanya bila kedua sumber (bezetting saat rekom dan rekomendasi BKN) tersedia
const lowonganAda = computed(() => props.sumber.some((x) => x.kode === 'bezetting_rekom') && props.sumber.some((x) => x.kode === 'rekom_bkn'));
const lowongan = (j) => (j.nilai.bezetting_rekom == null || j.nilai.rekom_bkn == null ? null : j.nilai.bezetting_rekom - j.nilai.rekom_bkn);
</script>
