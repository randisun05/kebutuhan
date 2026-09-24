<template>
    <Head title="Unit Kerja" />
    <div class="d-flex justify-content-between align-items-start">
        <div><div class="page-title">Struktur Unit Kerja</div><div class="page-subtitle">Hierarki organisasi yang menjadi dasar breakdown monitoring per unit.</div></div>
        <Link :href="'/unit-kerja/create?instansi_id=' + (f.instansi_id || '')" class="btn btn-primary"><i class="fa fa-plus-circle me-1"></i> Tambah</Link>
    </div>
    <div class="card mb-3"><div class="card-body py-2">
        <form class="d-flex gap-2 flex-wrap" @submit.prevent="go">
            <select v-if="instansiOptions.length > 1" v-model="f.instansi_id" class="form-select form-select-sm w-auto" @change="go">
                <option v-for="i in instansiOptions" :key="i.id" :value="i.id">{{ i.nama }}</option>
            </select>
            <input v-model="f.q" class="form-control form-control-sm w-auto" placeholder="Nama unit…">
            <button class="btn btn-sm btn-primary"><i class="fa fa-search"></i></button>
        </form>
    </div></div>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Unit Kerja</th><th>Kode</th><th>Eselon</th><th class="num">Pegawai aktif</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="u in units" :key="u.id">
                        <td :class="'unit-depth-' + Math.min(u.depth, 3)"><i class="fa fa-angle-right text-muted me-1" v-if="u.depth"></i>{{ u.nama }}</td>
                        <td>{{ u.kode }}</td><td>{{ u.eselon || '-' }}</td><td class="num">{{ u.pegawai }}</td>
                        <td class="text-nowrap">
                            <Link :href="`/monitoring/unit/${u.id}`" class="btn btn-sm btn-light" title="Monitoring"><i class="fa fa-bar-chart"></i></Link>
                            <Link :href="`/unit-kerja/${u.id}/edit`" class="btn btn-sm btn-light"><i class="fa fa-pencil"></i></Link>
                            <button class="btn btn-sm btn-light text-danger" @click="destroy(`/unit-kerja/${u.id}`)"><i class="fa fa-trash"></i></button>
                        </td>
                    </tr>
                    <tr v-if="!units.length"><td colspan="5" class="text-center text-muted py-4">Belum ada unit kerja.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { destroy } from '../../confirm';

const props = defineProps({ units: Array, instansiOptions: Array, filters: Object });
const f = reactive({ instansi_id: props.filters.instansi_id || '', q: props.filters.q || '' });
const go = () => router.get('/unit-kerja', Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true, replace: true });
</script>
