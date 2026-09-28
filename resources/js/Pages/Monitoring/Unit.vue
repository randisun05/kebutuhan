<template>
    <Head :title="unit.nama" />

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <Link :href="`/monitoring/instansi/${unit.instansi_id}`" class="small"><i class="fa fa-angle-left"></i> {{ unit.instansi?.nama }}</Link>
            <div class="page-title">{{ unit.nama }}</div>
            <div class="page-subtitle">Rincian per jabatan<span v-if="unit.parent"> · induk: {{ unit.parent.nama }}</span></div>
        </div>
        <LiveBadge :updated-at="updatedAt" @refresh="refresh" />
    </div>

    <MonitoringFilter :filters="filters" :jenis-options="jenisOptions" :url="`/monitoring/unit/${unit.id}`" :extra="{ sub: filters.sub ? 1 : 0 }">
        <div class="form-check form-switch ms-1">
            <input class="form-check-input" type="checkbox" id="sub" :checked="filters.sub" @change="toggleSub">
            <label class="form-check-label small" for="sub">Termasuk sub-unit</label>
        </div>
    </MonitoringFilter>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><StatCard label="Kebutuhan" :value="summary.kebutuhan" icon="fa-bullseye" color="#2a78d6" /></div>
        <div class="col-6 col-lg-3"><StatCard label="Existing" :value="summary.existing" icon="fa-users" color="#eb6834" :hint="'Pemenuhan ' + pct(summary.persentase)" /></div>
        <div class="col-6 col-lg-3"><StatCard label="Kurang" :value="summary.kurang" icon="fa-arrow-down" color="#d03b3b" value-class="text-kurang" /></div>
        <div class="col-6 col-lg-3"><StatCard label="Lebih (gemuk)" :value="summary.lebih" icon="fa-arrow-up" color="#b77900" value-class="text-lebih" /></div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th><th v-if="filters.sub" class="col-unit">Unit Kerja</th><th class="col-jabatan">Jabatan</th><th>Jenis</th><th class="num">Kebutuhan (ABK)</th><th class="num">Existing</th>
                        <th class="num">Selisih</th><th class="num">Pensiun ≤5th</th><th class="num">Formasi ditetapkan</th><th>Pemenuhan</th><th>Kondisi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(p, i) in positions" :key="p.unit_kerja_id + '-' + p.jabatan_id">
                        <td>{{ i + 1 }}</td>
                        <td v-if="filters.sub" class="small">{{ p.unit_nama }}</td>
                        <td>{{ p.jabatan_nama }}</td>
                        <td class="small">{{ p.jenis_label }}</td>
                        <td class="num">{{ num(p.kebutuhan) }}</td>
                        <td class="num">{{ num(p.existing) }}</td>
                        <td class="num fw-semibold" :class="p.selisih < 0 ? 'text-kurang' : (p.selisih > 0 ? 'text-lebih' : '')">{{ p.selisih > 0 ? '+' : '' }}{{ num(p.selisih) }}</td>
                        <td class="num">{{ num(p.pensiun) }}</td>
                        <td class="num">{{ num(p.formasi) }}</td>
                        <td class="small">{{ pct(p.persentase) }}</td>
                        <td><Kondisi :status="p.status" /></td>
                    </tr>
                    <tr v-if="!positions.length"><td colspan="11" class="text-center text-muted py-4">Tidak ada posisi jabatan.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import StatCard from '../../Components/StatCard.vue';
import Kondisi from '../../Components/Kondisi.vue';
import LiveBadge from '../../Components/LiveBadge.vue';
import MonitoringFilter from '../../Components/MonitoringFilter.vue';
import { num, pct } from '../../utils';

const props = defineProps({ unit: Object, filters: Object, jenisOptions: Array, summary: Object, positions: Array, updatedAt: String });

const LIVE = ['summary', 'positions', 'updatedAt'];
usePoll(30000, { only: LIVE });
const refresh = () => router.reload({ only: LIVE });
const toggleSub = (e) => router.get(`/monitoring/unit/${props.unit.id}`, { ...props.filters, sub: e.target.checked ? 1 : 0 }, { preserveState: true, replace: true });
</script>
