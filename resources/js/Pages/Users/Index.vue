<template>
    <Head title="Pengguna" />
    <div class="d-flex justify-content-between align-items-start">
        <div><div class="page-title">Pengguna</div><div class="page-subtitle">Admin, verifikator BKN, validator KemenPANRB, operator instansi, dan pimpinan.</div></div>
        <Link href="/users/create" class="btn btn-primary"><i class="fa fa-plus-circle me-1"></i> Tambah</Link>
    </div>
    <div class="card mb-3"><div class="card-body py-2">
        <form class="d-flex gap-2 flex-wrap" @submit.prevent="go">
            <input v-model="f.q" class="form-control form-control-sm w-auto" placeholder="Nama / email / NIP…">
            <select v-model="f.role" class="form-select form-select-sm w-auto"><option value="">Semua peran</option><option v-for="r in roleOptions" :key="r.value" :value="r.value">{{ r.label }}</option></select>
            <button class="btn btn-sm btn-primary"><i class="fa fa-search"></i></button>
        </form>
    </div></div>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Nama</th><th>Email</th><th>NIP</th><th>Peran</th><th>Instansi</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="d in datas.data" :key="d.id">
                        <td>{{ d.name }}</td><td>{{ d.email }}</td><td class="small">{{ d.nip || '-' }}</td><td>{{ d.role_label }}</td>
                        <td class="small">{{ d.instansi?.nama || '-' }}</td>
                        <td><span class="badge" :class="d.is_active ? 'bg-success' : 'bg-secondary'">{{ d.is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                        <td class="text-nowrap"><Link :href="`/users/${d.id}/edit`" class="btn btn-sm btn-light"><i class="fa fa-pencil"></i></Link>
                            <button class="btn btn-sm btn-light text-danger" @click="destroy(`/users/${d.id}`)"><i class="fa fa-trash"></i></button></td>
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

const props = defineProps({ datas: Object, filters: Object, roleOptions: Array });
const f = reactive({ q: props.filters.q || '', role: props.filters.role || '' });
const go = () => router.get('/users', Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true, replace: true });
</script>
