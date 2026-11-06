<template>
    <Head title="Kualitas Data" />
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <div class="page-title">Kualitas Data Existing</div>
            <div class="page-subtitle">Pemeriksaan otomatis data pegawai aktif agar angka existing, pensiun, dan proyeksi dapat dipercaya.</div>
        </div>
        <select v-if="instansiOptions.length > 1" class="form-select form-select-sm w-auto" :value="instansiId" @change="router.get('/kualitas-data', { instansi_id: $event.target.value })">
            <option v-for="i in instansiOptions" :key="i.id" :value="i.id">{{ i.nama }}</option>
        </select>
    </div>

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
            <div v-if="p.jumlah > p.contoh.length" class="text-muted mt-1">Menampilkan {{ p.contoh.length }} dari {{ num(p.jumlah) }} temuan.</div>
        </div>
    </div>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { num } from '../../utils';

defineProps({ instansiOptions: Array, instansiId: [Number, String], pemeriksaan: Array, totalTemuan: Number });
const tingkat = { tinggi: 'bg-danger', sedang: 'bg-warning text-dark', rendah: 'bg-secondary' };
</script>
