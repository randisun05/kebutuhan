<template>
    <Head title="Usulan Kebutuhan" />
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <div class="page-title">Usulan Kebutuhan ASN</div>
            <div class="page-subtitle">Alur: Instansi mengajukan → Verifikasi & pertimbangan teknis BKN → Validasi KemenPANRB → Penetapan.</div>
        </div>
        <Link v-if="canCreate" href="/usulan/create" class="btn btn-primary"><i class="fa fa-plus-circle me-1"></i> Buat Usulan</Link>
    </div>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a href="#" class="nav-link" :class="{ active: !filters.inbox }" @click.prevent="go({ inbox: '' })">Semua</a></li>
        <li class="nav-item"><a href="#" class="nav-link" :class="{ active: filters.inbox }" @click.prevent="go({ inbox: 1 })">
            Perlu tindakan <span v-if="$page.props.inbox" class="badge bg-warning text-dark">{{ $page.props.inbox }}</span></a></li>
    </ul>

    <div class="card mb-3">
        <div class="card-body py-2">
            <form class="d-flex flex-wrap gap-2" @submit.prevent="go({})">
                <input v-model="f.q" class="form-control form-control-sm w-auto" placeholder="Nomor / perihal…">
                <select v-if="instansiOptions.length > 1" v-model="f.instansi_id" class="form-select form-select-sm w-auto">
                    <option value="">Semua instansi</option>
                    <option v-for="i in instansiOptions" :key="i.id" :value="i.id">{{ i.nama }}</option>
                </select>
                <select v-model="f.status" class="form-select form-select-sm w-auto">
                    <option value="">Semua status</option>
                    <option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <input v-model="f.tahun" type="number" class="form-control form-control-sm" style="width: 100px" placeholder="Tahun">
                <button class="btn btn-sm btn-primary"><i class="fa fa-search"></i> Cari</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>Nomor</th><th>Instansi</th><th>Perihal</th><th>Tahun</th><th>ASN</th><th class="num">Diusulkan</th><th class="num">Rekomendasi BKN</th><th class="num">Ditetapkan</th><th>Status</th><th>Diperbarui</th></tr>
                </thead>
                <tbody>
                    <tr v-for="d in datas.data" :key="d.id">
                        <td><Link :href="`/usulan/${d.id}`" class="fw-semibold">{{ d.nomor }}</Link></td>
                        <td>{{ d.instansi?.nama }}</td>
                        <td>{{ d.perihal }}</td>
                        <td>{{ d.tahun }}</td>
                        <td class="text-uppercase small">{{ d.jenis_asn }}</td>
                        <td class="num">{{ num(d.total_usul || 0) }}</td>
                        <td class="num">{{ d.total_rekomendasi === null ? '-' : num(d.total_rekomendasi) }}</td>
                        <td class="num">{{ d.total_ditetapkan === null ? '-' : num(d.total_ditetapkan) }}</td>
                        <td><span class="badge" :class="'bg-' + d.status_color">{{ d.status_label }}</span></td>
                        <td class="small text-muted">{{ waktu(d.updated_at) }}</td>
                    </tr>
                    <tr v-if="!datas.data.length"><td colspan="10" class="text-center text-muted py-4">Belum ada usulan.</td></tr>
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
import { num, waktu } from '../../utils';

const props = defineProps({ datas: Object, filters: Object, statusOptions: Array, instansiOptions: Array, canCreate: Boolean });
const f = reactive({ q: props.filters.q || '', status: props.filters.status || '', tahun: props.filters.tahun || '', instansi_id: props.filters.instansi_id || '', inbox: props.filters.inbox || '' });
const go = (extra) => {
    Object.assign(f, extra);
    router.get('/usulan', Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true, replace: true });
};
</script>
