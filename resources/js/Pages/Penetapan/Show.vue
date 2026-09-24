<template>
    <Head :title="'Penetapan ' + data.nomor_sk" />
    <div class="d-flex justify-content-between align-items-start no-print mb-3">
        <Link href="/penetapan" class="small"><i class="fa fa-angle-left"></i> Daftar penetapan</Link>
        <button class="btn btn-sm btn-outline-secondary" @click="print"><i class="fa fa-print me-1"></i> Cetak</button>
    </div>

    <div class="card">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <div class="fw-bold">LAMPIRAN KEPUTUSAN PENETAPAN KEBUTUHAN {{ data.usulan.jenis_asn === 'pppk' ? 'PPPK' : 'PNS' }}</div>
                <div>NOMOR {{ data.nomor_sk }} TANGGAL {{ tanggal(data.tanggal_sk).toUpperCase() }}</div>
                <div class="fw-semibold mt-2">{{ data.instansi.nama.toUpperCase() }} — TAHUN ANGGARAN {{ data.tahun }}</div>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead><tr><th>No</th><th>Unit Kerja</th><th>Jabatan</th><th>Kualifikasi Pendidikan</th><th class="num">Jumlah</th></tr></thead>
                    <tbody>
                        <tr v-for="(d, i) in data.usulan.details" :key="d.id">
                            <td>{{ i + 1 }}</td><td>{{ d.unit_kerja?.nama }}</td><td>{{ d.jabatan?.nama }}</td>
                            <td>{{ d.kualifikasi_pendidikan || '-' }}</td><td class="num">{{ num(d.jumlah_ditetapkan) }}</td>
                        </tr>
                    </tbody>
                    <tfoot><tr class="fw-bold"><td colspan="4" class="text-end">Jumlah</td><td class="num">{{ num(data.total_ditetapkan) }}</td></tr></tfoot>
                </table>
            </div>
            <div v-if="data.keterangan" class="mt-3 small">Keterangan: {{ data.keterangan }}</div>
            <div class="mt-4 small text-muted">Ditetapkan melalui SIMONKEB oleh {{ data.penetap?.name || '-' }} · Usulan {{ data.usulan.nomor }}</div>
        </div>
    </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { num, tanggal } from '../../utils';

defineProps({ data: Object });
const print = () => window.print();
</script>
