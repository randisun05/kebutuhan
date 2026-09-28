<template>
    <Head :title="'Monitoring ' + instansi.nama" />

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <Link v-if="!isOperator" href="/monitoring" class="small"><i class="fa fa-angle-left"></i> Semua instansi</Link>
            <div class="page-title">{{ instansi.nama }}</div>
            <div class="page-subtitle">Breakdown kebutuhan vs existing per unit kerja. Angka "Total" sudah termasuk seluruh sub-unit di bawahnya.</div>
        </div>
        <LiveBadge :updated-at="updatedAt" @refresh="refresh" />
    </div>

    <MonitoringFilter :filters="filters" :jenis-options="jenisOptions" :url="`/monitoring/instansi/${instansi.id}`" :instansi-id="instansi.id" />

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-2"><StatCard label="Kebutuhan" :value="summary.kebutuhan" icon="fa-bullseye" color="#2a78d6" :hint="summary.jumlah_jabatan + ' posisi'" /></div>
        <div class="col-6 col-xl-2"><StatCard label="Existing" :value="summary.existing" icon="fa-users" color="#eb6834" :hint="'Pemenuhan ' + pct(summary.persentase)" /></div>
        <div class="col-6 col-xl-2"><StatCard label="Kurang" :value="summary.kurang" icon="fa-arrow-down" color="#d03b3b" value-class="text-kurang" /></div>
        <div class="col-6 col-xl-2"><StatCard label="Lebih (gemuk)" :value="summary.lebih" icon="fa-arrow-up" color="#b77900" value-class="text-lebih" /></div>
        <div class="col-6 col-xl-2"><StatCard label="Jabatan kosong" :value="summary.jabatan_kosong" icon="fa-circle-o" color="#6b7280" /></div>
        <div class="col-6 col-xl-2"><StatCard label="Pensiun ≤ 5 th" :value="summary.pensiun" icon="fa-hourglass-half" color="#4a3aa7" /></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header">Kurang vs Lebih per Unit Kerja (unit sendiri)</div>
                <div class="card-body">
                    <BarChart horizontal stacked :height="Math.max(260, chartUnits.length * 26)" :labels="chartUnits.map(u => u.nama)" :datasets="[
                        { label: 'Kurang', data: chartUnits.map(u => -u.own.kurang), color: COLORS.kurang },
                        { label: 'Lebih', data: chartUnits.map(u => u.own.lebih), color: COLORS.lebih },
                    ]" />
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header">Per Jenis Jabatan</div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Jenis</th><th class="num">Kebutuhan</th><th class="num">Existing</th><th class="num">Kurang</th><th class="num">Lebih</th><th>Kondisi</th></tr></thead>
                        <tbody>
                            <tr v-for="j in perJenis" :key="j.jenis">
                                <td>{{ j.label }}</td>
                                <td class="num">{{ num(j.kebutuhan) }}</td>
                                <td class="num">{{ num(j.existing) }}</td>
                                <td class="num text-kurang">{{ num(j.kurang) }}</td>
                                <td class="num text-lebih">{{ num(j.lebih) }}</td>
                                <td><Kondisi :status="j.kondisi" /></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Rekap per Unit Kerja</span>
            <div class="btn-group btn-group-sm">
                <button class="btn" :class="mode === 'total' ? 'btn-primary' : 'btn-outline-primary'" @click="mode = 'total'">Total (incl. sub-unit)</button>
                <button class="btn" :class="mode === 'own' ? 'btn-primary' : 'btn-outline-primary'" @click="mode = 'own'">Unit sendiri</button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="col-unit">Unit Kerja</th><th>Eselon</th><th class="num">Posisi</th><th class="num">Kebutuhan</th><th class="num">Existing</th>
                        <th class="num">Selisih</th><th class="num">Kurang</th><th class="num">Lebih</th><th class="num">Kosong</th>
                        <th class="num">Pensiun ≤5th</th><th class="num">Formasi</th><th>Pemenuhan</th><th>Kondisi</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="u in units" :key="u.id">
                        <td class="col-unit" :class="'unit-depth-' + Math.min(u.depth, 3)">
                            <i class="fa me-1 text-muted" :class="u.has_children ? 'fa-folder-open-o' : 'fa-file-o'"></i>
                            <Link :href="`/monitoring/unit/${u.id}`">{{ u.nama }}</Link>
                        </td>
                        <td>{{ u.eselon || '-' }}</td>
                        <td class="num">{{ num(u[mode].jumlah_jabatan) }}</td>
                        <td class="num">{{ num(u[mode].kebutuhan) }}</td>
                        <td class="num">{{ num(u[mode].existing) }}</td>
                        <td class="num fw-semibold" :class="u[mode].selisih < 0 ? 'text-kurang' : (u[mode].selisih > 0 ? 'text-lebih' : '')">{{ u[mode].selisih > 0 ? '+' : '' }}{{ num(u[mode].selisih) }}</td>
                        <td class="num text-kurang">{{ num(u[mode].kurang) }}</td>
                        <td class="num text-lebih">{{ num(u[mode].lebih) }}</td>
                        <td class="num">{{ num(u[mode].jabatan_kosong) }}</td>
                        <td class="num">{{ num(u[mode].pensiun) }}</td>
                        <td class="num">{{ num(u[mode].formasi) }}</td>
                        <td style="min-width: 100px">
                            <div class="small">{{ pct(u[mode].persentase) }}</div>
                            <div class="progress progress-thin"><div class="progress-bar" :class="barClass(u[mode].persentase)" :style="{ width: Math.min(u[mode].persentase || 0, 100) + '%' }"></div></div>
                        </td>
                        <td><Kondisi :status="u[mode].kondisi" /></td>
                        <td><Link :href="`/monitoring/unit/${u.id}`" class="btn btn-sm btn-light" title="Rincian per jabatan"><i class="fa fa-list"></i></Link></td>
                    </tr>
                    <tr v-if="!units.length"><td colspan="14" class="text-center text-muted py-4">Belum ada unit kerja.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage, usePoll } from '@inertiajs/vue3';
import StatCard from '../../Components/StatCard.vue';
import BarChart from '../../Components/BarChart.vue';
import Kondisi from '../../Components/Kondisi.vue';
import LiveBadge from '../../Components/LiveBadge.vue';
import MonitoringFilter from '../../Components/MonitoringFilter.vue';
import { num, pct, barClass, COLORS } from '../../utils';

const props = defineProps({ instansi: Object, filters: Object, jenisOptions: Array, summary: Object, units: Array, perJenis: Array, updatedAt: String });

const mode = ref('total');
const isOperator = computed(() => usePage().props.auth.user.role === 'operator_instansi');
const chartUnits = computed(() => props.units.filter((u) => u.own.kurang || u.own.lebih));

const LIVE = ['summary', 'units', 'perJenis', 'updatedAt'];
usePoll(30000, { only: LIVE });
const refresh = () => router.reload({ only: LIVE });
</script>
