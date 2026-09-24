<template>
    <Head title="Dashboard" />

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <div class="page-title">Dashboard Monitoring Kebutuhan ASN</div>
            <div class="page-subtitle">Perbandingan kebutuhan hasil ABK (final) dengan pegawai existing, serta progres usulan hingga penetapan.</div>
        </div>
        <LiveBadge :updated-at="updatedAt" @refresh="refresh" />
    </div>

    <div class="card mb-3">
        <div class="card-body py-2 d-flex flex-wrap gap-2 align-items-center">
            <i class="fa fa-filter text-muted"></i>
            <select v-if="instansiOptions.length > 1" v-model="filter.instansi_id" class="form-select form-select-sm w-auto" @change="apply">
                <option value="">Semua instansi</option>
                <option v-for="i in instansiOptions" :key="i.id" :value="i.id">{{ i.nama }}</option>
            </select>
            <select v-model="filter.jenis" class="form-select form-select-sm w-auto" @change="apply">
                <option value="">Semua jenis jabatan</option>
                <option v-for="j in jenisOptions" :key="j.value" :value="j.value">{{ j.label }}</option>
            </select>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-2"><StatCard label="Kebutuhan (ABK)" :value="summary.kebutuhan" icon="fa-bullseye" color="#2a78d6" :hint="summary.jumlah_jabatan + ' posisi jabatan'" /></div>
        <div class="col-6 col-xl-2"><StatCard label="Existing" :value="summary.existing" icon="fa-users" color="#eb6834" :hint="'Pemenuhan ' + pct(summary.persentase)" /></div>
        <div class="col-6 col-xl-2"><StatCard label="Kekurangan" :value="summary.kurang" icon="fa-arrow-down" color="#d03b3b" value-class="text-kurang" :hint="summary.jabatan_kurang + ' posisi kurang'" /></div>
        <div class="col-6 col-xl-2"><StatCard label="Kelebihan (gemuk)" :value="summary.lebih" icon="fa-arrow-up" color="#b77900" value-class="text-lebih" :hint="summary.jabatan_lebih + ' posisi lebih'" /></div>
        <div class="col-6 col-xl-2"><StatCard label="Jabatan kosong" :value="summary.jabatan_kosong" icon="fa-circle-o" color="#6b7280" hint="Dibutuhkan, belum terisi" /></div>
        <div class="col-6 col-xl-2"><StatCard label="Pensiun ≤ 5 th" :value="summary.pensiun" icon="fa-hourglass-half" color="#4a3aa7" :hint="'Formasi ditetapkan: ' + num(summary.formasi)" /></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">
                    <span>Kebutuhan vs Existing per Instansi</span>
                    <Link href="/monitoring" class="small">Detail <i class="fa fa-angle-right"></i></Link>
                </div>
                <div class="card-body">
                    <BarChart :labels="perInstansi.map(r => r.nama)" :datasets="[
                        { label: 'Kebutuhan (ABK)', data: perInstansi.map(r => r.kebutuhan), color: COLORS.kebutuhan },
                        { label: 'Existing', data: perInstansi.map(r => r.existing), color: COLORS.existing },
                    ]" />
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">
                    <span>Progres Usulan Kebutuhan</span>
                    <Link href="/usulan" class="small">Lihat <i class="fa fa-angle-right"></i></Link>
                </div>
                <ul class="list-group list-group-flush">
                    <li v-for="u in usulan" :key="u.status" class="list-group-item d-flex justify-content-between align-items-center">
                        <Link :href="`/usulan?status=${u.status}`" class="text-decoration-none text-reset">
                            <span class="badge me-2" :class="'bg-' + u.color">&nbsp;</span>{{ u.label }}
                        </Link>
                        <span class="fw-semibold num">{{ num(u.jumlah) }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header">Instansi dengan ketidakseimbangan terbesar</div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>Instansi</th><th class="num">Kebutuhan</th><th class="num">Existing</th><th class="num">Kurang</th><th class="num">Lebih</th><th>Pemenuhan</th><th>Kondisi</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in perInstansi.slice(0, 10)" :key="r.id">
                                <td><Link :href="`/monitoring/instansi/${r.id}`">{{ r.nama }}</Link></td>
                                <td class="num">{{ num(r.kebutuhan) }}</td>
                                <td class="num">{{ num(r.existing) }}</td>
                                <td class="num text-kurang">{{ num(r.kurang) }}</td>
                                <td class="num text-lebih">{{ num(r.lebih) }}</td>
                                <td style="min-width: 110px">
                                    <div class="small">{{ pct(r.persentase) }}</div>
                                    <div class="progress progress-thin"><div class="progress-bar" :class="barClass(r.persentase)" :style="{ width: Math.min(r.persentase || 0, 100) + '%' }"></div></div>
                                </td>
                                <td><Kondisi :status="r.kondisi" /></td>
                            </tr>
                            <tr v-if="!perInstansi.length"><td colspan="7" class="text-center text-muted py-4">Belum ada data.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card mb-3">
                <div class="card-header">Kurang vs Lebih per Jenis Jabatan</div>
                <div class="card-body">
                    <BarChart horizontal stacked :height="260" :labels="perJenis.map(r => r.label)" :datasets="[
                        { label: 'Kurang', data: perJenis.map(r => -r.kurang), color: COLORS.kurang },
                        { label: 'Lebih', data: perJenis.map(r => r.lebih), color: COLORS.lebih },
                    ]" />
                </div>
            </div>
            <div class="card">
                <div class="card-header">Penetapan terbaru</div>
                <ul class="list-group list-group-flush">
                    <li v-for="p in penetapanTerbaru" :key="p.id" class="list-group-item">
                        <Link :href="`/penetapan/${p.id}`" class="fw-semibold">{{ p.nomor_sk }}</Link>
                        <div class="small text-muted">{{ p.instansi?.nama }} · {{ tanggal(p.tanggal_sk) }} · {{ num(p.total_ditetapkan) }} formasi</div>
                    </li>
                    <li v-if="!penetapanTerbaru.length" class="list-group-item text-muted small">Belum ada penetapan.</li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive } from 'vue';
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import StatCard from '../../Components/StatCard.vue';
import BarChart from '../../Components/BarChart.vue';
import Kondisi from '../../Components/Kondisi.vue';
import LiveBadge from '../../Components/LiveBadge.vue';
import { num, pct, tanggal, barClass, COLORS } from '../../utils';

const props = defineProps({
    filters: Object, instansiOptions: Array, jenisOptions: Array, summary: Object,
    perInstansi: Array, perJenis: Array, usulan: Array, penetapanTerbaru: Array, updatedAt: String,
});

const LIVE = ['summary', 'perInstansi', 'perJenis', 'usulan', 'penetapanTerbaru', 'updatedAt', 'inbox'];
usePoll(30000, { only: LIVE });

const filter = reactive({ instansi_id: props.filters.instansi_id || '', jenis: props.filters.jenis || '' });
const apply = () => router.get('/dashboard', Object.fromEntries(Object.entries(filter).filter(([, v]) => v)), { preserveState: true, replace: true });
const refresh = () => router.reload({ only: LIVE });
</script>
