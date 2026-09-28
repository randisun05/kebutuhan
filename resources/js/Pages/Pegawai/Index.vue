<template>
    <Head title="Data Pegawai" />
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div><div class="page-title">Data Pegawai Existing (Bezetting)</div><div class="page-subtitle">Setiap perubahan (tambah, mutasi, nonaktif) langsung tercermin di monitoring.</div></div>
        <div class="d-flex gap-2">
            <Link href="/pegawai/import" class="btn btn-outline-success"><i class="fa fa-file-excel-o me-1"></i> Impor Excel</Link>
            <Link :href="'/pegawai/create' + (f.instansi_id ? '?instansi_id=' + f.instansi_id : '')" class="btn btn-primary"><i class="fa fa-plus-circle me-1"></i> Tambah</Link>
        </div>
    </div>
    <div class="card mb-3"><div class="card-body py-2">
        <form class="d-flex gap-2 flex-wrap" @submit.prevent="go">
            <select v-if="instansiOptions.length > 1" v-model="f.instansi_id" class="form-select form-select-sm w-auto" @change="f.unit_kerja_id = ''; go()">
                <option value="">Semua instansi</option>
                <option v-for="i in instansiOptions" :key="i.id" :value="i.id">{{ i.nama }}</option>
            </select>
            <select v-if="unitOptions.length" v-model="f.unit_kerja_id" class="form-select form-select-sm w-auto" @change="go">
                <option value="">Semua unit</option>
                <option v-for="u in unitOptions" :key="u.id" :value="u.id">{{ '— '.repeat(u.depth) }}{{ u.nama }}</option>
            </select>
            <select v-model="f.jenis" class="form-select form-select-sm w-auto" @change="go"><option value="">Semua jenis jabatan</option><option v-for="j in jenisOptions" :key="j.value" :value="j.value">{{ j.label }}</option></select>
            <select v-model="f.aktif" class="form-select form-select-sm w-auto" @change="go"><option value="1">Aktif</option><option value="0">Nonaktif</option><option value="all">Semua</option></select>
            <div class="form-check form-switch align-self-center"><input v-model="f.pensiun" class="form-check-input" type="checkbox" id="pens" @change="go"><label for="pens" class="form-check-label small">Pensiun ≤ 5 th</label></div>
            <input v-model="f.q" class="form-control form-control-sm w-auto" placeholder="NIP / nama…">
            <button class="btn btn-sm btn-primary"><i class="fa fa-search"></i></button>
        </form>
    </div></div>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>NIP</th><th>Nama</th><th>Jabatan</th><th>Unit Kerja</th><th>Status</th><th>Gol.</th><th>TMT Pensiun</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="d in datas.data" :key="d.id" :class="{ 'text-muted': !d.is_active }">
                        <td class="small">{{ d.nip }}</td>
                        <td>{{ d.nama }}<div class="small text-muted" v-if="instansiOptions.length > 1">{{ d.instansi?.nama }}</div></td>
                        <td>{{ d.jabatan?.nama }}</td><td class="small">{{ d.unit_kerja?.nama }}</td>
                        <td class="text-uppercase small">{{ d.status_kepegawaian }} <span v-if="!d.is_active" class="badge bg-secondary">nonaktif</span></td>
                        <td>{{ d.golongan }}</td>
                        <td class="small">{{ tanggal(d.tmt_pensiun) }}</td>
                        <td class="text-nowrap"><Link :href="`/pegawai/${d.id}/edit`" class="btn btn-sm btn-light"><i class="fa fa-pencil"></i></Link>
                            <button class="btn btn-sm btn-light text-danger" @click="destroy(`/pegawai/${d.id}`)"><i class="fa fa-trash"></i></button></td>
                    </tr>
                    <tr v-if="!datas.data.length"><td colspan="8" class="text-center text-muted py-4">Tidak ada data pegawai.</td></tr>
                </tbody>
            </table>
        </div>
        <div class="card-body pt-0 d-flex justify-content-between align-items-center">
            <span class="small text-muted">Total {{ num(datas.total) }} pegawai</span>
            <Pagination :links="datas.links" />
        </div>
    </div>
</template>

<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Pagination from '../../Components/Pagination.vue';
import { destroy } from '../../confirm';
import { num, tanggal } from '../../utils';

const props = defineProps({ datas: Object, filters: Object, instansiOptions: Array, unitOptions: Array, jenisOptions: Array });
const f = reactive({
    instansi_id: props.filters.instansi_id || '', unit_kerja_id: props.filters.unit_kerja_id || '', jenis: props.filters.jenis || '',
    aktif: props.filters.aktif || '1', pensiun: props.filters.pensiun === '1' || props.filters.pensiun === true, q: props.filters.q || '',
});
const go = () => router.get('/pegawai', Object.fromEntries(Object.entries({ ...f, pensiun: f.pensiun ? 1 : '' }).filter(([, v]) => v)), { preserveState: true, replace: true });
</script>
