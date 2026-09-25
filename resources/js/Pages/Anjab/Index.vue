<template>
    <Head title="Manajemen ABK" />
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <div class="page-title">Manajemen Anjab & ABK</div>
            <div class="page-subtitle">ABK <b>final</b> tahun terbaru per jabatan menjadi dasar kebutuhan pada monitoring, proyeksi 5 tahun, dan usulan.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <Link :href="'/anjab-rekap-unit' + q" class="btn btn-outline-primary"><i class="fa fa-tachometer me-1"></i> Efektivitas Unit</Link>
            <Link :href="'/proyeksi' + q" class="btn btn-outline-primary"><i class="fa fa-line-chart me-1"></i> Proyeksi 5 Th</Link>
            <a :href="'/anjab-export?' + qs" class="btn btn-outline-success"><i class="fa fa-file-excel-o me-1"></i> Export</a>
            <template v-if="canEdit">
                <button class="btn btn-outline-success" @click="panel = panel === 'import' ? '' : 'import'"><i class="fa fa-upload me-1"></i> Impor</button>
                <button class="btn btn-outline-secondary" @click="panel = panel === 'salin' ? '' : 'salin'"><i class="fa fa-copy me-1"></i> Salin Tahun</button>
                <Link :href="'/anjab/create' + q" class="btn btn-primary"><i class="fa fa-plus-circle me-1"></i> Tambah ABK</Link>
            </template>
        </div>
    </div>

    <div v-if="panel === 'import'" class="card mb-3 border-success"><div class="card-body">
        <form class="row g-2 align-items-end" @submit.prevent="imp.post('/anjab-import', { onSuccess: () => (panel = '') })">
            <div class="col-md-3"><label class="form-label small">Instansi</label>
                <select v-model="imp.instansi_id" class="form-select form-select-sm"><option v-for="i in instansiOptions" :key="i.id" :value="i.id">{{ i.nama }}</option></select></div>
            <div class="col-md-5"><label class="form-label small">Berkas (kode_unit, kode_jabatan, tahun, uraian_tugas, hasil_kerja, volume, satuan_periode, norma_waktu)</label>
                <input type="file" accept=".xlsx,.xls,.csv" class="form-control form-control-sm" @input="imp.file = $event.target.files[0]">
                <div class="text-danger small">{{ imp.errors.file }}</div></div>
            <div class="col-md-auto"><button class="btn btn-sm btn-success" :disabled="imp.processing">Impor</button></div>
            <div class="col-md-auto"><a href="/anjab-template" class="btn btn-sm btn-light"><i class="fa fa-download"></i> Template</a></div>
            <div class="col-12 small text-muted">Baris dengan unit + jabatan + tahun yang sama digabung menjadi satu ABK (draft). ABK yang sudah final tidak ditimpa.</div>
        </form>
    </div></div>

    <div v-if="panel === 'salin'" class="card mb-3"><div class="card-body">
        <form class="row g-2 align-items-end" @submit.prevent="salin.post('/anjab-duplicate', { onSuccess: () => (panel = '') })">
            <div class="col-md-4"><label class="form-label small">Instansi</label>
                <select v-model="salin.instansi_id" class="form-select form-select-sm"><option v-for="i in instansiOptions" :key="i.id" :value="i.id">{{ i.nama }}</option></select></div>
            <div class="col-md-2"><label class="form-label small">Dari tahun (ABK final)</label><input v-model.number="salin.dari_tahun" type="number" class="form-control form-control-sm"></div>
            <div class="col-md-2"><label class="form-label small">Ke tahun</label><input v-model.number="salin.ke_tahun" type="number" class="form-control form-control-sm"></div>
            <div class="col-md-auto"><button class="btn btn-sm btn-primary" :disabled="salin.processing">Salin semua</button></div>
            <div class="col-12 text-danger small">{{ salin.errors.ke_tahun }}</div>
        </form>
    </div></div>
    <div v-if="importErrors?.length" class="alert alert-warning small"><ul class="mb-0"><li v-for="(e, i) in importErrors" :key="i">{{ e }}</li></ul></div>

    <div class="row g-3 mb-3">
        <div class="col-4"><StatCard label="ABK (sesuai filter)" :value="ringkasan.total" icon="fa-file-text-o" color="#2a78d6" /></div>
        <div class="col-4"><StatCard label="Sudah final" :value="ringkasan.final" icon="fa-lock" color="#0ca30c" :hint="ringkasan.total ? Math.round(ringkasan.final / ringkasan.total * 100) + '% dari total' : ''" /></div>
        <div class="col-4"><StatCard label="Kebutuhan (ABK final)" :value="ringkasan.kebutuhan" icon="fa-bullseye" color="#4a3aa7" /></div>
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
                <select v-model="f.tahun" class="form-select form-select-sm w-auto" @change="go">
                    <option value="">Semua tahun</option>
                    <option v-for="t in tahunOptions" :key="t" :value="t">{{ t }}</option>
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
                    <tr><th class="col-jabatan">Jabatan</th><th class="col-unit">Unit Kerja</th><th>Tahun</th><th class="num">Beban (jam)</th><th class="num">Hitung</th><th class="num">Kebutuhan</th><th class="num">Existing</th><th class="num">Selisih</th><th>EJ / PEJ</th><th>Status</th><th></th></tr>
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
                        <td class="small text-nowrap">{{ d.ej ?? '-' }} <span v-if="d.pej" class="badge bg-light text-dark border">{{ d.pej.nilai }} · {{ d.pej.label }}</span></td>
                        <td><span class="badge" :class="d.status === 'final' ? 'bg-success' : 'bg-secondary'">{{ d.status }}</span></td>
                        <td class="text-nowrap">
                            <a :href="`/anjab/${d.id}/pdf`" class="btn btn-sm btn-light" title="PDF"><i class="fa fa-file-pdf-o"></i></a>
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
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import Pagination from '../../Components/Pagination.vue';
import StatCard from '../../Components/StatCard.vue';
import { num } from '../../utils';

const props = defineProps({ datas: Object, filters: Object, instansiOptions: Array, unitOptions: Array, tahunOptions: Array, canEdit: Boolean, ringkasan: Object });
const f = reactive({ instansi_id: props.filters.instansi_id || '', unit_kerja_id: props.filters.unit_kerja_id || '', status: props.filters.status || '', q: props.filters.q || '', tahun: props.filters.tahun || '' });
const clean = () => Object.fromEntries(Object.entries(f).filter(([, v]) => v));
const qs = computed(() => new URLSearchParams(clean()).toString());
const q = computed(() => (f.instansi_id ? '?instansi_id=' + f.instansi_id : ''));
const go = () => router.get('/anjab', clean(), { preserveState: true, replace: true });
const toggle = (d) => router.post(`/anjab/${d.id}/status`, {}, { preserveScroll: true });

const panel = ref('');
const defInstansi = props.filters.instansi_id || props.instansiOptions[0]?.id;
const imp = useForm({ instansi_id: defInstansi, file: null });
const tahunIni = new Date().getFullYear();
const salin = useForm({ instansi_id: defInstansi, dari_tahun: tahunIni, ke_tahun: tahunIni + 1 });
const importErrors = computed(() => usePage().props.session.importErrors);
</script>
