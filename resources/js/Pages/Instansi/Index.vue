<template>
    <Head title="Instansi" />
    <div class="d-flex justify-content-between align-items-start">
        <div><div class="page-title">Instansi</div><div class="page-subtitle">Instansi pusat dan daerah yang menyusun kebutuhan ASN.</div></div>
        <Link href="/instansi/create" class="btn btn-primary"><i class="fa fa-plus-circle me-1"></i> Tambah</Link>
    </div>
    <div class="card mb-3"><div class="card-body py-2">
        <form class="d-flex gap-2 flex-wrap" @submit.prevent="go">
            <input v-model="f.q" class="form-control form-control-sm w-auto" placeholder="Kode / nama…">
            <select v-model="f.jenis" class="form-select form-select-sm w-auto"><option value="">Semua jenis</option><option v-for="j in jenisOptions" :key="j.value" :value="j.value">{{ j.label }}</option></select>
            <button class="btn btn-sm btn-primary"><i class="fa fa-search"></i></button>
        </form>
    </div></div>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Kode</th><th>Nama</th><th>Jenis</th><th>Provinsi</th><th class="num">Unit</th><th class="num">Pegawai aktif</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="d in datas.data" :key="d.id">
                        <td>{{ d.kode }}</td><td><Link :href="`/monitoring/instansi/${d.id}`">{{ d.nama }}</Link></td>
                        <td>{{ jenisOptions.find(j => j.value === d.jenis)?.label }}</td><td>{{ d.provinsi }}</td>
                        <td class="num">{{ d.unit_kerjas_count }}</td><td class="num">{{ d.pegawais_count }}</td>
                        <td><span class="badge" :class="d.is_active ? 'bg-success' : 'bg-secondary'">{{ d.is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                        <td class="text-nowrap"><Link :href="`/unit-kerja?instansi_id=${d.id}`" class="btn btn-sm btn-light" title="Kelola organisasi"><i class="fa fa-sitemap"></i></Link>
                            <Link :href="`/instansi/${d.id}/edit`" class="btn btn-sm btn-light"><i class="fa fa-pencil"></i></Link>
                            <button class="btn btn-sm btn-light text-danger" @click="destroy(`/instansi/${d.id}`)"><i class="fa fa-trash"></i></button></td>
                    </tr>
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
import { destroy } from '../../confirm';

const props = defineProps({ datas: Object, filters: Object, jenisOptions: Array });
const f = reactive({ q: props.filters.q || '', jenis: props.filters.jenis || '' });
const go = () => router.get('/instansi', Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true, replace: true });
</script>
