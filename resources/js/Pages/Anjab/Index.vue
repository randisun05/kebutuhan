<template>
    <Head title="Anjab & ABK" />
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <div class="page-title">Analisis Jabatan & Analisis Beban Kerja</div>
            <div class="page-subtitle">Kebutuhan pegawai = Σ(volume × norma waktu) ÷ waktu kerja efektif. Hanya ABK berstatus <b>final</b> yang dihitung di monitoring & usulan.</div>
        </div>
        <Link v-if="canEdit" :href="'/anjab/create' + (f.instansi_id ? '?instansi_id=' + f.instansi_id : '')" class="btn btn-primary"><i class="fa fa-plus-circle me-1"></i> Tambah ABK</Link>
    </div>

    <div class="card mb-3">
        <div class="card-body py-2">
            <form class="d-flex flex-wrap gap-2" @submit.prevent="go">
                <select v-if="instansiOptions.length > 1" v-model="f.instansi_id" class="form-select form-select-sm w-auto" @change="f.unit_kerja_id = ''; go()">
                    <option value="">Semua instansi</option>
                    <option v-for="i in instansiOptions" :key="i.id" :value="i.id">{{ i.nama }}</option>
                </select>
                <select v-if="unitOptions.length" v-model="f.unit_kerja_id" class="form-select form-select-sm w-auto" @change="go">
                    <option value="">Semua unit</option>
                    <option v-for="u in unitOptions" :key="u.id" :value="u.id">{{ '— '.repeat(u.depth) }}{{ u.nama }}</option>
                </select>
                <select v-model="f.status" class="form-select form-select-sm w-auto" @change="go">
                    <option value="">Semua status</option>
                    <option value="draft">Draft</option>
                    <option value="final">Final</option>
                </select>
                <input v-model="f.q" class="form-control form-control-sm w-auto" placeholder="Nama jabatan…">
                <button class="btn btn-sm btn-primary"><i class="fa fa-search"></i></button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>Jabatan</th><th>Unit Kerja</th><th>Tahun</th><th class="num">Beban kerja (jam)</th><th class="num">Hitung</th><th class="num">Kebutuhan</th><th class="num">Existing</th><th class="num">Selisih</th><th>EJ / PEJ</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    <tr v-for="d in datas.data" :key="d.id">
                        <td><Link :href="`/anjab/${d.id}`" class="fw-semibold">{{ d.jabatan?.nama }}</Link><div class="small text-muted" v-if="instansiOptions.length > 1">{{ d.instansi?.nama }}</div></td>
                        <td class="small">{{ d.unit_kerja?.nama }}</td>
                        <td>{{ d.tahun }}</td>
                        <td class="num">{{ num(Math.round(d.total_beban_kerja / 60)) }}</td>
                        <td class="num">{{ d.kebutuhan_hitung }}</td>
                        <td class="num fw-semibold">{{ num(d.kebutuhan) }}</td>
                        <td class="num">{{ num(d.existing) }}</td>
                        <td class="num" :class="d.selisih < 0 ? 'text-kurang' : (d.selisih > 0 ? 'text-lebih' : '')">{{ d.selisih > 0 ? '+' : '' }}{{ d.selisih }}</td>
                        <td class="small">{{ d.ej ?? '-' }} <span v-if="d.pej" class="badge bg-light text-dark border">{{ d.pej.nilai }} · {{ d.pej.label }}</span></td>
                        <td><span class="badge" :class="d.status === 'final' ? 'bg-success' : 'bg-secondary'">{{ d.status }}</span></td>
                        <td class="text-nowrap">
                            <template v-if="canEdit">
                                <button class="btn btn-sm btn-light" :title="d.status === 'final' ? 'Kembalikan ke draft' : 'Finalkan'" @click="toggle(d)"><i class="fa" :class="d.status === 'final' ? 'fa-unlock' : 'fa-lock'"></i></button>
                                <Link :href="`/anjab/${d.id}/edit`" class="btn btn-sm btn-light"><i class="fa fa-pencil"></i></Link>
                            </template>
                        </td>
                    </tr>
                    <tr v-if="!datas.data.length"><td colspan="11" class="text-center text-muted py-4">Belum ada data ABK.</td></tr>
                </tbody>
            </table>
        </div>
        <div class="card-body pt-0"><Pagination :links="datas.links" /></div>
    </div>
</template>

<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Pagination from '../../Components/Pagination.vue';
import { num } from '../../utils';

const props = defineProps({ datas: Object, filters: Object, instansiOptions: Array, unitOptions: Array, canEdit: Boolean });
const f = reactive({ instansi_id: props.filters.instansi_id || '', unit_kerja_id: props.filters.unit_kerja_id || '', status: props.filters.status || '', q: props.filters.q || '' });
const go = () => router.get('/anjab', Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true, replace: true });
const toggle = (d) => router.post(`/anjab/${d.id}/status`, {}, { preserveScroll: true });
</script>
