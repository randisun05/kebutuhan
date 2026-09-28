<template>
    <Head title="Proyeksi Kebutuhan" />
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <div class="page-title">Proyeksi Kebutuhan 5 Tahun</div>
            <div class="page-subtitle">{{ instansi.nama }} · berbasis ABK final terbaru, pertumbuhan beban kerja, dan pegawai yang mencapai BUP. Rencana formasi = tambahan kekurangan per tahun.</div>
        </div>
        <a :href="'/proyeksi/export?' + qs" class="btn btn-success btn-sm"><i class="fa fa-file-excel-o me-1"></i> Export Excel</a>
    </div>

    <div class="card mb-3"><div class="card-body py-2">
        <form class="d-flex flex-wrap gap-2" @submit.prevent="go">
            <select v-if="instansiOptions.length > 1" v-model="f.instansi_id" class="form-select form-select-sm w-auto" @change="f.unit_kerja_id = ''; go()">
                <option v-for="i in instansiOptions" :key="i.id" :value="i.id">{{ i.nama }}</option>
            </select>
            <select v-model="f.unit_kerja_id" class="form-select form-select-sm w-auto" @change="go">
                <option value="">Semua unit</option>
                <option v-for="u in unitOptions" :key="u.id" :value="u.id">{{ '— '.repeat(u.depth) }}{{ u.nama }}</option>
            </select>
            <select v-model="f.jenis" class="form-select form-select-sm w-auto" @change="go">
                <option value="">Semua jenis jabatan</option>
                <option v-for="j in jenisOptions" :key="j.value" :value="j.value">{{ j.label }}</option>
            </select>
            <div class="input-group input-group-sm w-auto"><span class="input-group-text">Mulai tahun</span>
                <input v-model.number="f.mulai" type="number" class="form-control" style="width: 90px" @change="go"></div>
        </form>
    </div></div>

    <div class="card mb-3">
        <div class="card-header">Rekap per tahun</div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th></th><th v-for="t in hasil.tahun" :key="t" class="num">{{ t }}</th></tr></thead>
                <tbody>
                    <tr><td>Kebutuhan (ABK)</td><td v-for="t in hasil.tahun" :key="t" class="num">{{ num(hasil.total[t].kebutuhan) }}</td></tr>
                    <tr><td>Existing akhir tahun (setelah pensiun)</td><td v-for="t in hasil.tahun" :key="t" class="num">{{ num(hasil.total[t].existing) }}</td></tr>
                    <tr><td>Pensiun kumulatif</td><td v-for="t in hasil.tahun" :key="t" class="num">{{ num(hasil.total[t].pensiun) }}</td></tr>
                    <tr><td>Kekurangan</td><td v-for="t in hasil.tahun" :key="t" class="num text-kurang">{{ num(hasil.total[t].kekurangan) }}</td></tr>
                    <tr><td>Kelebihan</td><td v-for="t in hasil.tahun" :key="t" class="num text-lebih">{{ num(hasil.total[t].kelebihan) }}</td></tr>
                    <tr class="table-primary fw-bold"><td>Rencana formasi</td><td v-for="t in hasil.tahun" :key="t" class="num">{{ num(hasil.total[t].rencana) }}</td></tr>
                    <tr><td>Estimasi tambahan belanja pegawai (Rp/th)</td><td v-for="t in hasil.tahun" :key="t" class="num small">{{ hasil.total[t].anggaran ? num(hasil.total[t].anggaran) : '-' }}</td></tr>
                </tbody>
            </table>
        </div>
        <div class="card-body small text-muted border-top">Estimasi belanja hanya terhitung untuk jabatan yang diisi "estimasi belanja pegawai" pada referensi jabatan.</div>
    </div>

    <div class="card">
        <div class="card-header">Rincian per jabatan ({{ hasil.rows.length }} posisi)</div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th rowspan="2" class="col-unit">Unit</th><th rowspan="2" class="col-jabatan">Jabatan</th><th rowspan="2" class="num">K / B kini</th><th rowspan="2" class="num">Formasi belum terisi</th>
                        <th v-for="t in hasil.tahun" :key="t" colspan="2" class="text-center">{{ t }}</th><th rowspan="2" class="num">Total rencana</th>
                    </tr>
                    <tr><template v-for="t in hasil.tahun" :key="t"><th class="num small">K/B</th><th class="num small">Rencana</th></template></tr>
                </thead>
                <tbody>
                    <tr v-for="r in hasil.rows" :key="r.anjab_id">
                        <td class="small">{{ r.unit_nama }}</td>
                        <td><Link :href="`/anjab/${r.anjab_id}`">{{ r.jabatan_nama }}</Link><div class="small text-muted">ABK {{ r.tahun_abk }}<span v-if="r.pertumbuhan"> · +{{ r.pertumbuhan }}%/th</span></div></td>
                        <td class="num">{{ r.kebutuhan_saat_ini }} / {{ r.existing_saat_ini }}</td>
                        <td class="num">{{ r.formasi_belum_terisi || '-' }}</td>
                        <template v-for="t in hasil.tahun" :key="t">
                            <td class="num small" :class="{ 'text-kurang': r.tahun[t].kekurangan }">{{ r.tahun[t].kebutuhan }}/{{ r.tahun[t].existing }}</td>
                            <td class="num fw-semibold">{{ r.tahun[t].rencana || '' }}</td>
                        </template>
                        <td class="num fw-bold">{{ r.total_rencana }}</td>
                    </tr>
                    <tr v-if="!hasil.rows.length"><td :colspan="5 + hasil.tahun.length * 2" class="text-center text-muted py-4">Belum ada ABK final untuk dihitung.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { num } from '../../utils';

const props = defineProps({ instansi: Object, instansiOptions: Array, unitOptions: Array, jenisOptions: Array, filters: Object, hasil: Object });
const f = reactive({ instansi_id: props.instansi.id, unit_kerja_id: props.filters.unit_kerja_id || '', jenis: props.filters.jenis || '', mulai: props.filters.mulai || props.hasil.tahun[0] });
const clean = () => Object.fromEntries(Object.entries(f).filter(([, v]) => v));
const qs = computed(() => new URLSearchParams(clean()).toString());
const go = () => router.get('/proyeksi', clean(), { preserveState: true, replace: true });
</script>
