<template>
    <Head :title="'Peta Jabatan ' + instansi.nama" />
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <div class="page-title">Peta Jabatan</div>
            <div class="page-subtitle">{{ instansi.nama }} · susunan jabatan per unit dengan kelas jabatan, bezetting (B), kebutuhan ABK (K), dan selisih (+/−).</div>
        </div>
        <div class="d-flex gap-2 no-print">
            <select v-if="instansiOptions.length > 1" class="form-select form-select-sm w-auto" :value="instansi.id" @change="router.get('/peta-jabatan', { instansi_id: $event.target.value })">
                <option v-for="i in instansiOptions" :key="i.id" :value="i.id">{{ i.nama }}</option>
            </select>
            <button class="btn btn-sm btn-outline-secondary" @click="print"><i class="fa fa-print"></i> Cetak</button>
        </div>
    </div>

    <div v-for="u in units" :key="u.id" class="card mb-2" :style="{ marginLeft: Math.min(u.depth, 4) * 24 + 'px' }">
        <div class="card-header d-flex justify-content-between align-items-center py-2">
            <span><i class="fa fa-building-o me-1 text-muted"></i> {{ u.nama }} <span v-if="u.eselon" class="badge bg-light text-dark border ms-1">Eselon {{ u.eselon }}</span>
                <span v-if="!u.is_active" class="badge bg-secondary ms-1">nonaktif</span></span>
            <span class="small text-muted" v-if="u.jabatan.length">B {{ sum(u.jabatan, 'existing') }} · K {{ sum(u.jabatan, 'kebutuhan') }}</span>
        </div>
        <table v-if="u.jabatan.length" class="table table-sm mb-0">
            <thead><tr><th>Jabatan</th><th class="num" style="width: 70px">Kelas</th><th class="num" style="width: 70px">B</th><th class="num" style="width: 70px">K</th><th class="num" style="width: 70px">+/−</th><th style="width: 140px">Kondisi</th></tr></thead>
            <tbody>
                <tr v-for="j in u.jabatan" :key="j.jabatan_id">
                    <td>{{ j.jabatan_nama }} <span class="small text-muted">({{ j.jenis_label }})</span></td>
                    <td class="num">{{ j.kelas_jabatan || '-' }}</td>
                    <td class="num">{{ j.existing }}</td>
                    <td class="num">{{ j.kebutuhan }}</td>
                    <td class="num fw-semibold" :class="j.selisih < 0 ? 'text-kurang' : (j.selisih > 0 ? 'text-lebih' : '')">{{ j.selisih > 0 ? '+' : '' }}{{ j.selisih }}</td>
                    <td><Kondisi :status="j.status" /></td>
                </tr>
            </tbody>
        </table>
        <div v-else class="card-body py-2 small text-muted">Belum ada jabatan (ABK final / pegawai).</div>
    </div>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3';
import Kondisi from '../../Components/Kondisi.vue';

defineProps({ instansi: Object, instansiOptions: Array, units: Array });
const sum = (rows, key) => rows.reduce((t, r) => t + (r[key] || 0), 0);
const print = () => window.print();
</script>
