<template>
    <Head title="Dashboard Data" />
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <div class="page-title">Dashboard Data</div>
            <div class="page-subtitle">Tren, pergerakan, komposisi pegawai, pensiun, dan peta panas pemenuhan{{ instansiNama ? ' — ' + instansiNama : ' nasional' }}.</div>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <select v-if="instansiOptions.length > 1" class="form-select form-select-sm w-auto" :value="filters.instansi_id || ''" @change="router.get('/analitik', $event.target.value ? { instansi_id: $event.target.value } : {})">
                <option value="">Semua instansi</option>
                <option v-for="i in instansiOptions" :key="i.id" :value="i.id">{{ i.nama }}</option>
            </select>
            <LiveBadge :updated-at="updatedAt" @refresh="refresh" />
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-2"><StatCard label="Kebutuhan" :value="summary.kebutuhan" icon="fa-bullseye" color="#2a78d6" /></div>
        <div class="col-6 col-xl-2"><StatCard label="Existing" :value="summary.existing" icon="fa-users" color="#eb6834" :hint="'Pemenuhan ' + pct(summary.persentase)" /></div>
        <div class="col-6 col-xl-2"><StatCard label="Kurang" :value="summary.kurang" icon="fa-arrow-down" color="#d03b3b" value-class="text-kurang" /></div>
        <div class="col-6 col-xl-2"><StatCard label="Lebih" :value="summary.lebih" icon="fa-arrow-up" color="#b77900" value-class="text-lebih" /></div>
        <div class="col-6 col-xl-2"><StatCard label="Pensiun ≤ 5 th" :value="summary.pensiun" icon="fa-hourglass-half" color="#4a3aa7" /></div>
        <div class="col-6 col-xl-2">
            <Link href="/peringatan" class="text-decoration-none"><StatCard label="Peringatan terbuka" :value="totalPeringatan" icon="fa-exclamation-triangle" color="#d03b3b" :hint="(peringatanTingkat.kritis || 0) + ' kritis · ' + (peringatanTingkat.tinggi || 0) + ' tinggi'" /></Link>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between"><span>Tren kebutuhan vs existing</span><Link href="/histori" class="small">Histori <i class="fa fa-angle-right"></i></Link></div>
                <div class="card-body">
                    <LineChart :height="280" :labels="tren.map(t => t.label)" :datasets="[
                        { label: 'Kebutuhan (ABK)', data: tren.map(t => t.kebutuhan), color: COLORS.kebutuhan },
                        { label: 'Existing', data: tren.map(t => t.existing), color: COLORS.existing },
                    ]" />
                </div>
            </div>
        </div>
        <div class="col-xl-5">
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
        <div class="card-header">Peta panas % pemenuhan per {{ heatmap.mode === 'unit' ? 'unit kerja' : 'instansi' }} × jenis jabatan</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 heatmap">
                <thead><tr><th class="col-unit">{{ heatmap.mode === 'unit' ? 'Unit kerja' : 'Instansi' }}</th><th v-for="(l, k) in heatmap.kolom" :key="k" class="text-center">{{ l }}</th></tr></thead>
                <tbody>
                    <tr v-for="b in heatmap.baris" :key="b.id">
                        <td>
                            <Link v-if="heatmap.mode === 'instansi'" :href="`/analitik?instansi_id=${b.id}`">{{ b.nama }}</Link>
                            <Link v-else :href="`/monitoring/unit/${b.id}`">{{ b.nama }}</Link>
                        </td>
                        <td v-for="(s, k) in b.sel" :key="k" class="text-center" :style="sel(s.persen)" :title="`Existing ${s.existing} / kebutuhan ${s.kebutuhan}`">
                            {{ s.persen === null ? (s.existing ? 'tanpa ABK' : '–') : s.persen + '%' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-body small d-flex flex-wrap gap-3 align-items-center border-top">
            <span class="text-muted">Skala:</span>
            <span v-for="l in legenda" :key="l.label" class="d-inline-flex align-items-center gap-1"><span class="d-inline-block rounded" :style="{ width: '18px', height: '12px', background: l.bg }"></span>{{ l.label }}</span>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6 col-xl-4">
            <div class="card h-100"><div class="card-header">Pensiun per tahun (BUP)</div><div class="card-body">
                <BarChart :height="220" :labels="pensiunPerTahun.map(p => String(p.tahun))" :datasets="[{ label: 'Pegawai pensiun', data: pensiunPerTahun.map(p => p.jumlah), color: '#4a3aa7' }]" />
            </div></div>
        </div>
        <div class="col-lg-6 col-xl-4">
            <div class="card h-100"><div class="card-header">Komposisi per jenis jabatan</div><div class="card-body">
                <BarChart horizontal :height="220" :labels="komposisi.jenis.map(x => x.label)" :datasets="[{ label: 'Pegawai', data: komposisi.jenis.map(x => x.jumlah), color: COLORS.kebutuhan }]" />
            </div></div>
        </div>
        <div class="col-lg-6 col-xl-4">
            <div class="card h-100"><div class="card-header">Kelompok usia</div><div class="card-body">
                <BarChart :height="220" :labels="komposisi.usia.map(x => x.label)" :datasets="[{ label: 'Pegawai', data: komposisi.usia.map(x => x.jumlah), color: COLORS.kebutuhan }]" />
            </div></div>
        </div>
        <div class="col-lg-6 col-xl-4">
            <div class="card h-100"><div class="card-header">Golongan</div><div class="card-body">
                <BarChart horizontal :height="200" :labels="komposisi.golongan.map(x => x.label)" :datasets="[{ label: 'Pegawai', data: komposisi.golongan.map(x => x.jumlah), color: COLORS.kebutuhan }]" />
            </div></div>
        </div>
        <div class="col-lg-6 col-xl-4">
            <div class="card h-100"><div class="card-header">Pendidikan</div><div class="card-body">
                <BarChart horizontal :height="200" :labels="komposisi.pendidikan.map(x => x.label)" :datasets="[{ label: 'Pegawai', data: komposisi.pendidikan.map(x => x.jumlah), color: COLORS.kebutuhan }]" />
            </div></div>
        </div>
        <div class="col-lg-6 col-xl-4">
            <div class="card h-100"><div class="card-header">Status kepegawaian</div><div class="card-body">
                <div v-for="s in komposisi.status" :key="s.label" class="mb-3">
                    <div class="d-flex justify-content-between small"><span>{{ s.label }}</span><b>{{ num(s.jumlah) }} ({{ totalStatus ? Math.round(s.jumlah / totalStatus * 100) : 0 }}%)</b></div>
                    <div class="progress" style="height: 10px"><div class="progress-bar" :style="{ width: (totalStatus ? s.jumlah / totalStatus * 100 : 0) + '%', background: s.label === 'PNS' ? '#2a78d6' : '#eb6834' }"></div></div>
                </div>
            </div></div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import StatCard from '../../Components/StatCard.vue';
import BarChart from '../../Components/BarChart.vue';
import LineChart from '../../Components/LineChart.vue';
import LiveBadge from '../../Components/LiveBadge.vue';
import { num, pct, COLORS } from '../../utils';

const props = defineProps({ filters: Object, instansiOptions: Array, summary: Object, tren: Array, pergerakan: Array, komposisi: Object, pensiunPerTahun: Array, heatmap: Object, peringatanTingkat: Object, updatedAt: String });

const LIVE = ['summary', 'tren', 'pergerakan', 'komposisi', 'pensiunPerTahun', 'heatmap', 'peringatanTingkat', 'updatedAt', 'peringatan'];
usePoll(60000, { only: LIVE });
const refresh = () => router.reload({ only: LIVE });

const instansiNama = computed(() => props.instansiOptions.find((i) => i.id === Number(props.filters.instansi_id))?.nama);
const totalPeringatan = computed(() => Object.values(props.peringatanTingkat || {}).reduce((a, b) => a + Number(b), 0));
const totalStatus = computed(() => props.komposisi.status.reduce((a, b) => a + b.jumlah, 0));

// divergen: merah = kurang, abu netral = ideal (90–110%), biru = lebih; teks persen selalu tampil
const legenda = [
    { label: '< 50%', bg: '#e34948' }, { label: '50–89%', bg: '#f4b4b3' }, { label: '90–110% (ideal)', bg: '#f0efec' },
    { label: '111–150%', bg: '#a9c9ef' }, { label: '> 150%', bg: '#2a78d6' },
];
const sel = (p) => {
    if (p === null) return { color: '#898781' };
    if (p < 50) return { background: '#e34948', color: '#fff', fontWeight: 600 };
    if (p < 90) return { background: '#f4b4b3' };
    if (p <= 110) return { background: '#f0efec' };
    if (p <= 150) return { background: '#a9c9ef' };
    return { background: '#2a78d6', color: '#fff', fontWeight: 600 };
};
</script>
