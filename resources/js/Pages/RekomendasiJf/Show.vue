<template>
    <Head :title="data.nama_instansi" />
    <Link href="/rekomendasi-jf" class="small"><i class="fa fa-angle-left"></i> Semua rekomendasi</Link>
    <div class="page-title">{{ data.nama_instansi }}</div>
    <div class="page-subtitle">{{ data.jabatan_pokok }} · {{ data.rumpun }}
        <span v-if="data.pokja"> · {{ data.pokja }}</span><span v-if="data.wilayah_kerja"> · {{ data.wilayah_kerja }}</span></div>

    <div class="d-flex flex-wrap gap-2 mb-3 small">
        <span class="badge" :class="data.status_rekom === 'DONE' ? 'bg-success' : 'bg-secondary'">Rekomendasi: {{ data.status_rekom || '-' }}</span>
        <span v-if="data.jenis_rekom" class="badge bg-info text-dark">{{ data.jenis_rekom }}</span>
        <span class="badge" :class="data.status_menpan ? 'bg-primary' : 'bg-secondary'">Menpan: {{ data.status_menpan || 'belum ditetapkan' }}</span>
        <span v-if="!data.ref_instansi" class="badge bg-warning text-dark">belum cocok dengan referensi instansi BKN</span>
    </div>

    <div class="card mb-3">
        <div class="card-header">Perbandingan per jenjang</div>
        <div class="card-body py-2 small text-muted">Bezetting adalah foto saat rekomendasi. Lowongan = bezetting − rekomendasi BKN (negatif berarti masih kurang). Tiga angka ini dipakai sebagai referensi berdampingan.</div>
        <div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>Jenjang</th><th class="num">Bezetting saat rekom</th><th class="num">Rekom BKN</th><th class="num">Penetapan Menpan</th><th class="num">Lowongan</th><th class="num">Menpan − BKN</th></tr></thead>
            <tbody>
                <tr v-for="j in jenjang" :key="j.kode"><td class="text-capitalize">{{ j.kode }}</td>
                    <td class="num">{{ n(j.bezetting_rekom) }}</td><td class="num">{{ n(j.rekom_bkn) }}</td><td class="num">{{ n(j.penetapan_menpan) }}</td>
                    <td class="num" :class="j.lowongan < 0 ? 'text-kurang' : ''">{{ n(j.lowongan) }}</td>
                    <td class="num" :class="j.selisih_menpan < 0 ? 'text-kurang' : j.selisih_menpan > 0 ? 'text-lebih' : ''">{{ n(j.selisih_menpan) }}</td></tr>
                <tr class="fw-semibold"><td>Total</td><td class="num">{{ jumlah('bezetting_rekom') }}</td><td class="num">{{ jumlah('rekom_bkn') }}</td><td class="num">{{ jumlah('penetapan_menpan') }}</td><td class="num">{{ jumlah('lowongan') }}</td><td class="num">{{ jumlah('selisih_menpan') }}</td></tr>
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

    <div v-if="data.catatan" class="alert alert-light border small" style="white-space: pre-line">{{ data.catatan }}</div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { num, tanggal } from '../../utils';

const props = defineProps({ data: Object, jenjang: Array });
const label = { rekomendasi: 'Rekomendasi BKN', pengembalian: 'Pengembalian', penetapan_menpan: 'Penetapan KemenPANRB' };
const n = (v) => (v === null || v === undefined ? '-' : num(v));
const jumlah = (k) => num(props.jenjang.reduce((a, j) => a + (j[k] ?? 0), 0));
</script>
