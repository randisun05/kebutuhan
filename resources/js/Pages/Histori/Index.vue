<template>
    <Head title="Histori Data Existing" />
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <div class="page-title">Histori Perubahan Data Existing</div>
            <div class="page-subtitle">Lacak pergerakan pegawai (masuk, keluar, mutasi, ganti jabatan) dan temukan yang <b>tidak bergerak sama sekali</b> dalam periode tertentu.</div>
        </div>
        <a :href="'/histori/export?' + qs" class="btn btn-success btn-sm"><i class="fa fa-file-excel-o me-1"></i> Export analisis</a>
    </div>

    <div class="card mb-3"><div class="card-body py-2">
        <form class="d-flex flex-wrap gap-2 align-items-center" @submit.prevent="go">
            <select v-if="instansiOptions.length > 1" v-model="f.instansi_id" class="form-select form-select-sm w-auto" @change="f.unit_kerja_id = ''; f.level = f.instansi_id ? 'unit' : 'instansi'; go()">
                <option value="">Semua instansi</option>
                <option v-for="i in instansiOptions" :key="i.id" :value="i.id">{{ i.nama }}</option>
            </select>
            <select v-if="unitOptions.length" v-model="f.unit_kerja_id" class="form-select form-select-sm w-auto" @change="go">
                <option value="">Semua unit</option>
                <option v-for="u in unitOptions" :key="u.id" :value="u.id">{{ '— '.repeat(u.depth) }}{{ u.nama }}</option>
            </select>
            <div class="btn-group btn-group-sm">
                <button v-for="b in [1, 3, 6, 12, 24]" :key="b" type="button" class="btn" :class="Number(f.bulan) === b ? 'btn-primary' : 'btn-outline-primary'" @click="f.bulan = b; go()">{{ b }} bln</button>
            </div>
            <select v-model="f.level" class="form-select form-select-sm w-auto" @change="go">
                <option value="instansi">Per instansi</option>
                <option value="unit">Per unit kerja</option>
                <option value="posisi">Per jabatan (posisi)</option>
            </select>
        </form>
    </div></div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl"><StatCard :label="labelLevel + ' dipantau'" :value="ringkasan.total" icon="fa-list" color="#2a78d6" /></div>
        <div class="col-6 col-xl"><StatCard :label="'Tidak bergerak ' + f.bulan + ' bln'" :value="ringkasan.diam" icon="fa-pause-circle" color="#6b7280" :hint="ringkasan.total ? Math.round(ringkasan.diam / ringkasan.total * 100) + '% dari total' : ''" /></div>
        <div class="col-6 col-xl"><StatCard label="Diam & kekurangan" :value="ringkasan.diam_kurang" icon="fa-exclamation-circle" color="#d03b3b" value-class="text-kurang" hint="Kekurangan tanpa tindak lanjut" /></div>
        <div class="col-6 col-xl"><StatCard label="Diam & bermasalah" :value="ringkasan.diam_bermasalah" icon="fa-warning" color="#b77900" hint="Kurang, kosong, atau gemuk" /></div>
        <div class="col-6 col-xl"><StatCard :label="'Peristiwa ' + f.bulan + ' bln'" :value="ringkasan.total_pergerakan" icon="fa-exchange" color="#4a3aa7" hint="Masuk, keluar, mutasi, ganti jabatan" /></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">Tren kebutuhan vs existing (12 bulan)</div>
                <div class="card-body">
                    <LineChart :labels="tren.map(t => t.label)" :datasets="[
                        { label: 'Kebutuhan (ABK)', data: tren.map(t => t.kebutuhan), color: COLORS.kebutuhan },
                        { label: 'Existing', data: tren.map(t => t.existing), color: COLORS.existing },
                    ]" :height="280" />
                    <div class="small text-muted mt-1" v-if="tren.some(t => !t.ada_data)">Titik kosong = belum ada rekam jejak pada bulan tersebut (rekam jejak diambil otomatis setiap hari).</div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">Pergerakan pegawai per bulan</div>
                <div class="card-body">
                    <BarChart stacked :height="280" :labels="pergerakan.map(p => p.label)" :datasets="[
                        { label: 'Masuk', data: pergerakan.map(p => p.masuk), color: '#2a78d6' },
                        { label: 'Keluar', data: pergerakan.map(p => p.keluar), color: '#eb6834' },
                        { label: 'Mutasi unit', data: pergerakan.map(p => p.mutasi_unit), color: '#1baf7a' },
                        { label: 'Ganti jabatan', data: pergerakan.map(p => p.ganti_jabatan), color: '#eda100' },
                    ]" />
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>{{ f.tampil === 'diam' ? 'Tidak bergerak sama sekali' : 'Semua' }} — {{ labelLevel.toLowerCase() }}, {{ f.bulan }} bulan terakhir</span>
            <div class="btn-group btn-group-sm">
                <button class="btn" :class="f.tampil === 'diam' ? 'btn-dark' : 'btn-outline-dark'" @click="f.tampil = 'diam'; go()">Hanya yang diam</button>
                <button class="btn" :class="f.tampil === 'semua' ? 'btn-dark' : 'btn-outline-dark'" @click="f.tampil = 'semua'; go()">Semua</button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="col-unit">{{ labelLevel }}</th><th class="num">Kebutuhan</th><th class="num">Existing {{ f.bulan }} bln lalu</th><th class="num">Existing kini</th>
                        <th class="num">Perubahan</th><th class="num">Selisih</th><th class="num">Pergerakan</th><th>Terakhir bergerak</th><th>Kondisi</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in analisis" :key="r.key" :class="{ 'table-warning': r.pergerakan === 0 && ['kurang', 'kosong'].includes(r.kondisi) }">
                        <td>{{ r.nama }}<div v-if="r.induk" class="small text-muted">{{ r.induk }}</div></td>
                        <td class="num">{{ num(r.kebutuhan) }}</td>
                        <td class="num">{{ r.existing_awal === null ? '–' : num(r.existing_awal) }}</td>
                        <td class="num">{{ num(r.existing) }}</td>
                        <td class="num" :class="{ 'text-muted': !r.perubahan }">{{ r.perubahan === null ? '–' : (r.perubahan > 0 ? '+' : '') + r.perubahan }}</td>
                        <td class="num fw-semibold" :class="r.selisih < 0 ? 'text-kurang' : (r.selisih > 0 ? 'text-lebih' : '')">{{ r.selisih > 0 ? '+' : '' }}{{ r.selisih }}</td>
                        <td class="num">
                            <span v-if="r.pergerakan" class="fw-semibold">{{ r.pergerakan }}</span>
                            <span v-else class="kondisi kondisi-tanpa_abk"><i class="fa fa-pause"></i> 0</span>
                        </td>
                        <td class="small">{{ r.terakhir_bergerak ? tanggal(r.terakhir_bergerak) + ' (' + lama(r.terakhir_bergerak) + ')' : 'belum pernah' }}</td>
                        <td><Kondisi :status="r.kondisi" /></td>
                        <td>
                            <Link v-if="r.level === 'instansi'" :href="`/histori?instansi_id=${r.instansi_id}&level=unit&bulan=${f.bulan}`" class="btn btn-sm btn-light" title="Rinci per unit"><i class="fa fa-search-plus"></i></Link>
                            <Link v-else :href="`/monitoring/unit/${r.unit_kerja_id}`" class="btn btn-sm btn-light" title="Monitoring unit"><i class="fa fa-bar-chart"></i></Link>
                        </td>
                    </tr>
                    <tr v-if="!analisis.length"><td colspan="10" class="text-center text-muted py-4">{{ f.tampil === 'diam' ? 'Semua ' + labelLevel.toLowerCase() + ' mengalami pergerakan dalam periode ini.' : 'Tidak ada data.' }}</td></tr>
                </tbody>
            </table>
        </div>
        <div class="card-body small text-muted border-top">Pergerakan = peristiwa pegawai yang menyentuh objek (pegawai masuk/keluar, mutasi dari/ke unit, ganti jabatan), baik dari input manual, impor, maupun sinkron SIASN. Baris kuning: kekurangan tetapi tidak ada pergerakan.</div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>Log perubahan data existing ({{ f.bulan }} bulan terakhir)</span>
            <select v-model="f.jenis" class="form-select form-select-sm w-auto" @change="go">
                <option value="">Semua jenis</option>
                <option v-for="(l, k) in jenisRiwayat" :key="k" :value="k">{{ l }}</option>
            </select>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead><tr><th>Waktu</th><th>Pegawai</th><th>Peristiwa</th><th>Dari</th><th>Ke</th><th>Keterangan</th><th>Sumber / oleh</th></tr></thead>
                <tbody>
                    <tr v-for="l in log.data" :key="l.id">
                        <td class="small text-nowrap">{{ waktu(l.created_at) }}</td>
                        <td><Link v-if="bolehPegawai" :href="`/pegawai/${l.pegawai_id}/edit`">{{ l.nama }}</Link><span v-else>{{ l.nama }}</span><div class="small text-muted">{{ l.nip }}</div></td>
                        <td><span class="badge" :class="badgeJenis[l.jenis]">{{ jenisRiwayat[l.jenis] || l.jenis }}</span></td>
                        <td class="small">{{ l.dari_unit ? l.dari_unit + ' · ' + l.dari_jabatan : '–' }}</td>
                        <td class="small">{{ l.ke_unit ? l.ke_unit + ' · ' + l.ke_jabatan : '–' }}</td>
                        <td class="small text-muted">{{ l.keterangan }}</td>
                        <td class="small">{{ l.sumber }}<span v-if="l.oleh"> · {{ l.oleh }}</span></td>
                    </tr>
                    <tr v-if="!log.data.length"><td colspan="7" class="text-center text-muted py-4">Tidak ada perubahan dalam periode ini.</td></tr>
                </tbody>
            </table>
        </div>
        <div class="card-body pt-0"><Pagination :links="log.links" /></div>
    </div>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import StatCard from '../../Components/StatCard.vue';
import BarChart from '../../Components/BarChart.vue';
import LineChart from '../../Components/LineChart.vue';
import Kondisi from '../../Components/Kondisi.vue';
import Pagination from '../../Components/Pagination.vue';
import { num, tanggal, waktu, COLORS } from '../../utils';

const props = defineProps({ filters: Object, instansiOptions: Array, unitOptions: Array, jenisRiwayat: Object, tren: Array, pergerakan: Array, analisis: Array, ringkasan: Object, log: Object });

const f = reactive({ ...props.filters, instansi_id: props.filters.instansi_id || '', unit_kerja_id: props.filters.unit_kerja_id || '', jenis: props.filters.jenis || '' });
const clean = () => Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '' && v !== null && v !== undefined));
const qs = computed(() => new URLSearchParams(clean()).toString());
const go = () => router.get('/histori', clean(), { preserveState: true, preserveScroll: true, replace: true });
const labelLevel = computed(() => ({ instansi: 'Instansi', unit: 'Unit kerja', posisi: 'Jabatan' })[f.level]);
const bolehPegawai = ['admin', 'operator_instansi'].includes(usePage().props.auth.user.role);
const badgeJenis = { masuk: 'bg-primary', keluar: 'bg-secondary', mutasi_unit: 'bg-success', ganti_jabatan: 'bg-warning text-dark' };
const lama = (d) => {
    const bulan = Math.floor((Date.now() - new Date(d)) / (30.44 * 864e5));
    return bulan < 1 ? 'bulan ini' : bulan + ' bln lalu';
};
</script>
