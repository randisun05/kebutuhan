<template>
    <Head title="Organisasi & Unit Kerja" />
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div><div class="page-title">Manajemen Organisasi</div><div class="page-subtitle">Struktur unit kerja per instansi yang menjadi dasar ABK, peta jabatan, dan breakdown monitoring.</div></div>
        <div class="d-flex flex-wrap gap-2">
            <Link :href="'/peta-jabatan?instansi_id=' + (f.instansi_id || '')" class="btn btn-outline-primary"><i class="fa fa-sitemap me-1"></i> Peta Jabatan</Link>
            <button class="btn btn-outline-success" @click="showImport = !showImport"><i class="fa fa-file-excel-o me-1"></i> Impor Excel</button>
            <Link :href="'/unit-kerja/create?instansi_id=' + (f.instansi_id || '')" class="btn btn-primary"><i class="fa fa-plus-circle me-1"></i> Tambah Unit</Link>
        </div>
    </div>

    <div v-if="showImport" class="card mb-3 border-success">
        <div class="card-body">
            <form class="row g-2 align-items-end" @submit.prevent="imp.post('/unit-kerja/import', { onSuccess: () => (showImport = false) })">
                <div class="col-md-5">
                    <label class="form-label small">Berkas Excel (kode, nama, kode_induk, eselon, nama_jabatan_pimpinan, siasn_unor_id)</label>
                    <input type="file" accept=".xlsx,.xls,.csv" class="form-control form-control-sm" @input="imp.file = $event.target.files[0]">
                    <div class="text-danger small" v-if="imp.errors.file">{{ imp.errors.file }}</div>
                </div>
                <div class="col-md-auto"><button class="btn btn-sm btn-success" :disabled="imp.processing"><i class="fa fa-upload"></i> Impor ke {{ instansiNama }}</button></div>
                <div class="col-md-auto"><a href="/unit-kerja/template" class="btn btn-sm btn-light"><i class="fa fa-download"></i> Template</a></div>
            </form>
        </div>
    </div>
    <div v-if="importErrors?.length" class="alert alert-warning small"><ul class="mb-0"><li v-for="(e, i) in importErrors" :key="i">{{ e }}</li></ul></div>

    <div class="card mb-3"><div class="card-body py-2">
        <form class="d-flex gap-2 flex-wrap" @submit.prevent="go">
            <select v-if="instansiOptions.length > 1" v-model="f.instansi_id" class="form-select form-select-sm w-auto" @change="go">
                <option v-for="i in instansiOptions" :key="i.id" :value="i.id">{{ i.nama }}</option>
            </select>
            <input v-model="f.q" class="form-control form-control-sm w-auto" placeholder="Nama unit…">
            <button class="btn btn-sm btn-primary"><i class="fa fa-search"></i></button>
            <span class="ms-auto small text-muted align-self-center">{{ units.length }} unit · {{ units.filter(u => !u.is_active).length }} nonaktif</span>
        </form>
    </div></div>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th class="col-unit">Unit Kerja</th><th>Kode</th><th>Eselon</th><th>Jabatan pimpinan</th><th>ID Unor SIASN</th><th class="num">Pegawai aktif</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="u in units" :key="u.id" :class="{ 'text-muted': !u.is_active }">
                        <td class="col-unit" :class="'unit-depth-' + Math.min(u.depth, 3)"><i class="fa fa-angle-right text-muted me-1" v-if="u.depth"></i>{{ u.nama }}</td>
                        <td class="small">{{ u.kode }}</td><td>{{ u.eselon || '-' }}</td>
                        <td class="small">{{ u.nama_jabatan_pimpinan || '-' }}</td>
                        <td class="small">{{ u.siasn_unor_id || '-' }}</td>
                        <td class="num">{{ u.pegawai }}</td>
                        <td><span class="badge" :class="u.is_active ? 'bg-success' : 'bg-secondary'">{{ u.is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                        <td class="text-nowrap">
                            <Link :href="`/monitoring/unit/${u.id}`" class="btn btn-sm btn-light" title="Monitoring"><i class="fa fa-bar-chart"></i></Link>
                            <button class="btn btn-sm btn-light" :title="u.is_active ? 'Nonaktifkan' : 'Aktifkan'" @click="router.post(`/unit-kerja/${u.id}/toggle`, {}, { preserveScroll: true })"><i class="fa" :class="u.is_active ? 'fa-toggle-on text-success' : 'fa-toggle-off'"></i></button>
                            <Link :href="`/unit-kerja/${u.id}/edit`" class="btn btn-sm btn-light"><i class="fa fa-pencil"></i></Link>
                            <button class="btn btn-sm btn-light text-danger" @click="destroy(`/unit-kerja/${u.id}`)"><i class="fa fa-trash"></i></button>
                        </td>
                    </tr>
                    <tr v-if="!units.length"><td colspan="8" class="text-center text-muted py-4">Belum ada unit kerja. Tambah manual, impor Excel, atau sinkron unor dari SIASN.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { destroy } from '../../confirm';

const props = defineProps({ units: Array, instansiOptions: Array, filters: Object });
const f = reactive({ instansi_id: props.filters.instansi_id || '', q: props.filters.q || '' });
const go = () => router.get('/unit-kerja', Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true, replace: true });
const showImport = ref(false);
const imp = useForm({ instansi_id: props.filters.instansi_id, file: null });
const instansiNama = computed(() => props.instansiOptions.find((i) => i.id === Number(f.instansi_id))?.nama);
const importErrors = computed(() => usePage().props.session.importErrors);
</script>
