<template>
    <Head title="Log Audit" />
    <div class="page-title">Log Audit</div>
    <div class="page-subtitle">Jejak perubahan data master, organisasi, pegawai, ABK, dan pengguna. Perubahan alur usulan tercatat di riwayat masing-masing usulan.</div>

    <div class="card mb-3"><div class="card-body py-2">
        <form class="d-flex gap-2 flex-wrap" @submit.prevent="go">
            <select v-model="f.log" class="form-select form-select-sm w-auto" @change="go"><option value="">Semua kategori</option><option v-for="l in logOptions" :key="l" :value="l">{{ l }}</option></select>
            <select v-model="f.event" class="form-select form-select-sm w-auto" @change="go"><option value="">Semua aksi</option><option value="created">dibuat</option><option value="updated">diubah</option><option value="deleted">dihapus</option></select>
            <input v-model="f.q" class="form-control form-control-sm w-auto" placeholder="Cari nilai…">
            <button class="btn btn-sm btn-primary"><i class="fa fa-search"></i></button>
        </form>
    </div></div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead><tr><th>Waktu</th><th>Oleh</th><th>Kategori</th><th>Aksi</th><th>Data</th><th>Perubahan</th></tr></thead>
                <tbody>
                    <tr v-for="d in datas.data" :key="d.id">
                        <td class="small text-nowrap">{{ waktu(d.created_at) }}</td>
                        <td class="small">{{ d.causer }}</td>
                        <td><span class="badge bg-light text-dark border">{{ d.log_name }}</span></td>
                        <td class="small">{{ { created: 'dibuat', updated: 'diubah', deleted: 'dihapus' }[d.event] || d.description }}</td>
                        <td class="small">{{ d.subject }}</td>
                        <td class="small">
                            <div v-for="(v, k) in (d.perubahan.attributes || {})" :key="k">
                                <b>{{ k }}</b>: <span v-if="d.perubahan.old && k in d.perubahan.old" class="text-muted text-decoration-line-through">{{ d.perubahan.old[k] }}</span> {{ v }}
                            </div>
                            <div v-if="!d.perubahan.attributes && d.perubahan.old" class="text-muted">{{ d.perubahan.old }}</div>
                        </td>
                    </tr>
                    <tr v-if="!datas.data.length"><td colspan="6" class="text-center text-muted py-4">Belum ada log.</td></tr>
                </tbody>
            </table>
        </div>
        <div class="card-body pt-0"><Pagination :links="datas.links" /></div>
    </div>
</template>

<script setup>
import { reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import Pagination from '../../Components/Pagination.vue';
import { waktu } from '../../utils';

const props = defineProps({ datas: Object, filters: Object, logOptions: Array });
const f = reactive({ log: props.filters.log || '', event: props.filters.event || '', q: props.filters.q || '' });
const go = () => router.get('/audit', Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true, replace: true });
</script>
