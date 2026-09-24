<template>
    <Head title="Monitoring Kebutuhan" />

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <div class="page-title">Monitoring Kebutuhan per Instansi</div>
            <div class="page-subtitle">Kekurangan dan kelebihan dihitung per posisi (unit kerja × jabatan) lalu dijumlahkan, sehingga kelebihan di satu unit tidak menutupi kekurangan di unit lain.</div>
        </div>
        <LiveBadge :updated-at="updatedAt" @refresh="refresh" />
    </div>

    <MonitoringFilter :filters="filters" :jenis-options="jenisOptions" url="/monitoring" :extra="{ sort: filters.sort }" />

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><StatCard label="Kebutuhan" :value="summary.kebutuhan" icon="fa-bullseye" color="#2a78d6" /></div>
        <div class="col-6 col-lg-3"><StatCard label="Existing" :value="summary.existing" icon="fa-users" color="#eb6834" :hint="'Pemenuhan ' + pct(summary.persentase)" /></div>
        <div class="col-6 col-lg-3"><StatCard label="Kurang" :value="summary.kurang" icon="fa-arrow-down" color="#d03b3b" value-class="text-kurang" :hint="summary.jabatan_kosong + ' posisi kosong'" /></div>
        <div class="col-6 col-lg-3"><StatCard label="Lebih (gemuk)" :value="summary.lebih" icon="fa-arrow-up" color="#b77900" value-class="text-lebih" :hint="summary.jabatan_lebih + ' posisi gemuk'" /></div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Kurang vs Lebih per Instansi</div>
        <div class="card-body">
            <BarChart horizontal stacked :height="Math.max(220, rows.length * 38)" :labels="rows.map(r => r.nama)" :datasets="[
                { label: 'Kurang', data: rows.map(r => -r.kurang), color: COLORS.kurang },
                { label: 'Lebih', data: rows.map(r => r.lebih), color: COLORS.lebih },
            ]" />
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Rekap per Instansi</span>
            <select class="form-select form-select-sm w-auto" :value="filters.sort" @change="sort($event.target.value)">
                <option value="kurang">Urut: kekurangan terbesar</option>
                <option value="lebih">Urut: kelebihan terbesar</option>
                <option value="jabatan_kosong">Urut: jabatan kosong terbanyak</option>
                <option value="persentase">Urut: persentase pemenuhan</option>
                <option value="kebutuhan">Urut: kebutuhan</option>
                <option value="existing">Urut: existing</option>
            </select>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th><th>Instansi</th><th class="num">Posisi</th><th class="num">Kebutuhan</th><th class="num">Existing</th>
                        <th class="num">Selisih</th><th class="num">Kurang</th><th class="num">Lebih</th><th class="num">Kosong</th>
                        <th class="num">Pensiun ≤5th</th><th class="num">Formasi ditetapkan</th><th>Pemenuhan</th><th>Kondisi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(r, i) in rows" :key="r.id">
                        <td>{{ i + 1 }}</td>
                        <td><Link :href="`/monitoring/instansi/${r.id}`" class="fw-semibold">{{ r.nama }}</Link><div class="small text-muted">{{ r.kode }}</div></td>
                        <td class="num">{{ num(r.jumlah_jabatan) }}</td>
                        <td class="num">{{ num(r.kebutuhan) }}</td>
                        <td class="num">{{ num(r.existing) }}</td>
                        <td class="num fw-semibold" :class="r.selisih < 0 ? 'text-kurang' : (r.selisih > 0 ? 'text-lebih' : '')">{{ r.selisih > 0 ? '+' : '' }}{{ num(r.selisih) }}</td>
                        <td class="num text-kurang">{{ num(r.kurang) }}</td>
                        <td class="num text-lebih">{{ num(r.lebih) }}</td>
                        <td class="num">{{ num(r.jabatan_kosong) }}</td>
                        <td class="num">{{ num(r.pensiun) }}</td>
                        <td class="num">{{ num(r.formasi) }}</td>
                        <td style="min-width: 110px">
                            <div class="small">{{ pct(r.persentase) }}</div>
                            <div class="progress progress-thin"><div class="progress-bar" :class="barClass(r.persentase)" :style="{ width: Math.min(r.persentase || 0, 100) + '%' }"></div></div>
                        </td>
                        <td><Kondisi :status="r.kondisi" /></td>
                    </tr>
                    <tr v-if="!rows.length"><td colspan="13" class="text-center text-muted py-4">Tidak ada data.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import StatCard from '../../Components/StatCard.vue';
import BarChart from '../../Components/BarChart.vue';
import Kondisi from '../../Components/Kondisi.vue';
import LiveBadge from '../../Components/LiveBadge.vue';
import MonitoringFilter from '../../Components/MonitoringFilter.vue';
import { num, pct, barClass, COLORS } from '../../utils';

const props = defineProps({ filters: Object, jenisOptions: Array, summary: Object, rows: Array, updatedAt: String });

const LIVE = ['summary', 'rows', 'updatedAt'];
usePoll(30000, { only: LIVE });
const refresh = () => router.reload({ only: LIVE });
const sort = (value) => router.get('/monitoring', { ...props.filters, sort: value }, { preserveState: true, replace: true });
</script>
