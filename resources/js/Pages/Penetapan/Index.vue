<template>
    <Head title="Penetapan Kebutuhan" />
    <div class="page-title">Penetapan Kebutuhan ASN</div>
    <div class="page-subtitle">Hasil akhir alur usulan: kebutuhan jumlah dan jenis jabatan yang telah ditetapkan.</div>

    <div class="row g-3 mb-3">
        <div v-for="r in rekap" :key="r.tahun" class="col-6 col-md-3 col-xl-2">
            <StatCard :label="'Tahun ' + r.tahun" :value="Number(r.total)" icon="fa-gavel" color="#0ca30c" :hint="r.jumlah_sk + ' SK penetapan'" />
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body py-2">
            <form class="d-flex flex-wrap gap-2" @submit.prevent="go">
                <input v-model="f.q" class="form-control form-control-sm w-auto" placeholder="Nomor SK…">
                <select v-if="instansiOptions.length > 1" v-model="f.instansi_id" class="form-select form-select-sm w-auto">
                    <option value="">Semua instansi</option>
                    <option v-for="i in instansiOptions" :key="i.id" :value="i.id">{{ i.nama }}</option>
                </select>
                <input v-model="f.tahun" type="number" class="form-control form-control-sm" style="width: 100px" placeholder="Tahun">
                <button class="btn btn-sm btn-primary"><i class="fa fa-search"></i> Cari</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Nomor SK</th><th>Tanggal</th><th>Instansi</th><th>Usulan</th><th>ASN</th><th>Tahun</th><th class="num">Formasi ditetapkan</th></tr></thead>
                <tbody>
                    <tr v-for="d in datas.data" :key="d.id">
                        <td><Link :href="`/penetapan/${d.id}`" class="fw-semibold">{{ d.nomor_sk }}</Link></td>
                        <td>{{ tanggal(d.tanggal_sk) }}</td>
                        <td>{{ d.instansi?.nama }}</td>
                        <td><Link :href="`/usulan/${d.usulan_id}`" class="small">{{ d.usulan?.nomor }}</Link></td>
                        <td class="text-uppercase small">{{ d.usulan?.jenis_asn }}</td>
                        <td>{{ d.tahun }}</td>
                        <td class="num fw-semibold">{{ num(d.total_ditetapkan) }}</td>
                    </tr>
                    <tr v-if="!datas.data.length"><td colspan="7" class="text-center text-muted py-4">Belum ada penetapan.</td></tr>
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
import StatCard from '../../Components/StatCard.vue';
import { num, tanggal } from '../../utils';

const props = defineProps({ datas: Object, filters: Object, instansiOptions: Array, rekap: Array });
const f = reactive({ q: props.filters.q || '', tahun: props.filters.tahun || '', instansi_id: props.filters.instansi_id || '' });
const go = () => router.get('/penetapan', Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true, replace: true });
</script>
