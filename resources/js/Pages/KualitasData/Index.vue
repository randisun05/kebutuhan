<template>
    <Head title="Kualitas Data" />
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <div class="page-title">Kualitas Data Existing</div>
            <div class="page-subtitle">Pemeriksaan otomatis data pegawai aktif agar angka existing, pensiun, dan proyeksi dapat dipercaya.</div>
        </div>
        <div class="d-flex gap-2">
            <select v-if="instansiOptions.length > 1" class="form-select form-select-sm w-auto" :value="instansiId" @change="router.get('/kualitas-data', { instansi_id: $event.target.value })">
                <option v-for="i in instansiOptions" :key="i.id" :value="i.id">{{ i.nama }}</option>
            </select>
            <a class="btn btn-sm btn-success" :href="`/kualitas-data/export?instansi_id=${instansiId}`"><i class="fa fa-file-excel-o me-1"></i> Unduh semua temuan</a>
        </div>
    </div>

    <!-- rincian lengkap satu jenis pemeriksaan -->
    <template v-if="rincian">
        <Link :href="`/kualitas-data?instansi_id=${instansiId}`" class="small"><i class="fa fa-angle-left"></i> Semua pemeriksaan</Link>
        <div class="card mt-2">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span>{{ rincian.judul }} <span class="badge ms-1" :class="tingkat[rincian.tingkat]">{{ rincian.tingkat }}</span></span>
                <select class="form-select form-select-sm w-auto" :value="filters.unit_id || ''" @change="saring($event.target.value)">
                    <option value="">Semua unit kerja</option>
                    <option v-for="u in unitOptions" :key="u.id" :value="u.id">{{ '— '.repeat(u.depth || 0) + u.nama }}</option>
                </select>
            </div>
            <div class="card-body py-2 small text-muted">{{ rincian.saran }} · {{ num(rincian.temuan.total) }} temuan</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>NIP</th><th>Nama</th><th>Unit</th><th>Keterangan</th><th></th></tr></thead>
                    <tbody>
                        <tr v-for="c in rincian.temuan.data" :key="c.id">
                            <td>{{ c.nip }}</td><td>{{ c.nama }}</td><td>{{ c.unit }}</td><td>{{ c.keterangan }}</td>
                            <td class="text-end"><Link :href="`/pegawai/${c.id}/edit`" class="btn btn-sm btn-light"><i class="fa fa-pencil"></i></Link></td>
                        </tr>
                        <tr v-if="!rincian.temuan.data.length"><td colspan="5" class="text-center text-muted py-3">Tidak ada temuan.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="card-body pt-2"><Pagination :links="rincian.temuan.links" /></div>
        </div>
    </template>

    <template v-else>
        <div class="alert py-2 small" :class="totalTemuan ? 'alert-warning' : 'alert-success'">
            <i class="fa me-1" :class="totalTemuan ? 'fa-exclamation-triangle' : 'fa-check-circle'"></i>
            <span v-if="totalTemuan">{{ num(totalTemuan) }} temuan pada {{ pemeriksaan.filter((p) => p.jumlah).length }} jenis pemeriksaan. Hanya informasi; tidak memblokir fitur apa pun.</span>
            <span v-else>Tidak ada temuan. Data pegawai aktif lolos semua pemeriksaan.</span>
        </div>

        <div class="card mb-3" v-for="p in pemeriksaan" :key="p.kode">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>{{ p.judul }} <span class="badge ms-1" :class="tingkat[p.tingkat]">{{ p.tingkat }}</span></span>
                <span class="fw-semibold" :class="p.jumlah ? 'text-danger' : 'text-success'">{{ p.jumlah ? num(p.jumlah) + ' temuan' : 'OK' }}</span>
            </div>
            <div v-if="p.jumlah" class="card-body py-2 small">
                <div class="text-muted mb-2">{{ p.saran }}</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead><tr><th>NIP</th><th>Nama</th><th>Unit</th><th>Keterangan</th><th></th></tr></thead>
                        <tbody>
                            <tr v-for="c in p.contoh" :key="c.id">
                                <td>{{ c.nip }}</td><td>{{ c.nama }}</td><td>{{ c.unit }}</td><td>{{ c.keterangan }}</td>
                                <td class="text-end"><Link :href="`/pegawai/${c.id}/edit`" class="btn btn-sm btn-light"><i class="fa fa-pencil"></i></Link></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="p.jumlah > p.contoh.length" class="mt-2">
                    <Link :href="`/kualitas-data?instansi_id=${instansiId}&kode=${p.kode}`" class="btn btn-sm btn-outline-primary">Lihat semua {{ num(p.jumlah) }} temuan</Link>
                </div>
            </div>
        </div>
    </template>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import Pagination from '../../Components/Pagination.vue';
import { num } from '../../utils';

const props = defineProps({
    instansiOptions: Array, instansiId: [Number, String], pemeriksaan: Array, totalTemuan: Number,
    rincian: Object, filters: Object, unitOptions: { type: Array, default: () => [] },
});
const tingkat = { tinggi: 'bg-danger', sedang: 'bg-warning text-dark', rendah: 'bg-secondary' };
const saring = (unitId) => router.get('/kualitas-data', { instansi_id: props.instansiId, kode: props.filters.kode, unit_id: unitId || undefined }, { preserveState: true });
</script>
