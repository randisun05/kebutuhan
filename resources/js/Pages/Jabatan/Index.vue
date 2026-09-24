<template>
    <Head title="Jabatan" />
    <div class="d-flex justify-content-between align-items-start">
        <div><div class="page-title">Referensi Jabatan</div><div class="page-subtitle">JPT, administrator, pengawas, pelaksana, dan jabatan fungsional beserta BUP.</div></div>
        <Link href="/jabatan/create" class="btn btn-primary"><i class="fa fa-plus-circle me-1"></i> Tambah</Link>
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
                <thead><tr><th>Kode</th><th>Nama jabatan</th><th>Jenis</th><th>Jenjang</th><th class="num">Kelas</th><th class="num">BUP</th><th>Kualifikasi</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="d in datas.data" :key="d.id">
                        <td class="small">{{ d.kode }}</td><td>{{ d.nama }}</td><td>{{ d.jenis_label }}</td><td>{{ d.jenjang || '-' }}</td>
                        <td class="num">{{ d.kelas_jabatan || '-' }}</td><td class="num">{{ d.bup }}</td><td class="small">{{ d.kualifikasi_pendidikan }}</td>
                        <td><span class="badge" :class="d.is_active ? 'bg-success' : 'bg-secondary'">{{ d.is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                        <td class="text-nowrap"><Link :href="`/jabatan/${d.id}/edit`" class="btn btn-sm btn-light"><i class="fa fa-pencil"></i></Link>
                            <button class="btn btn-sm btn-light text-danger" @click="destroy(`/jabatan/${d.id}`)"><i class="fa fa-trash"></i></button></td>
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
const go = () => router.get('/jabatan', Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true, replace: true });
</script>
