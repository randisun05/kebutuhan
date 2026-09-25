<template>
    <Head title="Redistribusi Pegawai" />
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <div class="page-title">Saran Redistribusi Pegawai</div>
            <div class="page-subtitle">{{ instansi.nama }} · memindahkan kelebihan (gemuk) ke unit yang kekurangan pada jabatan yang sama, sebelum mengusulkan formasi baru.</div>
        </div>
        <div class="d-flex gap-2">
            <select v-if="instansiOptions.length > 1" class="form-select form-select-sm w-auto" :value="instansi.id" @change="go({ instansi_id: $event.target.value })">
                <option v-for="i in instansiOptions" :key="i.id" :value="i.id">{{ i.nama }}</option>
            </select>
            <select class="form-select form-select-sm w-auto" :value="filters.jenis || ''" @change="go({ jenis: $event.target.value })">
                <option value="">Semua jenis jabatan</option>
                <option v-for="j in jenisOptions" :key="j.value" :value="j.value">{{ j.label }}</option>
            </select>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6"><StatCard label="Pegawai dapat diredistribusi" :value="hasil.total_dipindah" icon="fa-exchange" color="#2a78d6" :hint="hasil.saran.length + ' saran perpindahan'" /></div>
        <div class="col-md-6"><StatCard label="Kekurangan tersisa (perlu formasi)" :value="hasil.total_sisa_kurang" icon="fa-user-plus" color="#d03b3b" value-class="text-kurang" hint="Tidak dapat ditutup dari redistribusi internal" /></div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">Saran perpindahan</div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th class="col-jabatan">Jabatan</th><th class="col-unit">Dari unit (lebih)</th><th></th><th class="col-unit">Ke unit (kurang)</th><th class="num">Jumlah</th></tr></thead>
                        <tbody>
                            <tr v-for="(s, i) in hasil.saran" :key="i">
                                <td>{{ s.jabatan_nama }}<div class="small text-muted">{{ s.jenis_label }}</div></td>
                                <td>{{ s.dari_unit }} <span class="small text-lebih">(+{{ s.dari_selisih }})</span></td>
                                <td><i class="fa fa-long-arrow-right text-muted"></i></td>
                                <td>{{ s.ke_unit }} <span class="small text-kurang">({{ s.ke_selisih }})</span></td>
                                <td class="num fw-bold">{{ s.jumlah }}</td>
                            </tr>
                            <tr v-if="!hasil.saran.length"><td colspan="5" class="text-center text-muted py-4">Tidak ada pasangan kelebihan–kekurangan pada jabatan yang sama.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card">
                <div class="card-header">Kekurangan yang tersisa</div>
                <ul class="list-group list-group-flush small">
                    <li v-for="(k, i) in hasil.sisa_kurang.slice(0, 30)" :key="i" class="list-group-item d-flex justify-content-between">
                        <span>{{ k.jabatan_nama }}<div class="text-muted">{{ k.unit_nama }}</div></span><b class="text-kurang">{{ k.jumlah }}</b>
                    </li>
                    <li v-if="!hasil.sisa_kurang.length" class="list-group-item text-muted">Tidak ada.</li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3';
import StatCard from '../../Components/StatCard.vue';

const props = defineProps({ instansi: Object, instansiOptions: Array, jenisOptions: Array, filters: Object, hasil: Object });
const go = (extra) => router.get('/redistribusi', Object.fromEntries(Object.entries({ instansi_id: props.instansi.id, jenis: props.filters.jenis, ...extra }).filter(([, v]) => v)));
</script>
