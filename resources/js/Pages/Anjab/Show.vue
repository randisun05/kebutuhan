<template>
    <Head :title="'ABK ' + data.jabatan.nama" />
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <Link href="/anjab" class="small"><i class="fa fa-angle-left"></i> Daftar ABK</Link>
            <div class="page-title">{{ data.jabatan.nama }}</div>
            <div class="page-subtitle">{{ data.unit_kerja.nama }} · {{ data.instansi.nama }} · Tahun {{ data.tahun }}
                <span class="badge ms-1" :class="data.status === 'final' ? 'bg-success' : 'bg-secondary'">{{ data.status }}</span></div>
        </div>
        <div v-if="canEdit" class="d-flex gap-2 no-print">
            <button class="btn btn-sm btn-outline-secondary" @click="print"><i class="fa fa-print"></i> Cetak</button>
            <Link :href="`/anjab/${data.id}/edit`" class="btn btn-sm btn-warning"><i class="fa fa-pencil"></i> Ubah</Link>
            <button class="btn btn-sm btn-outline-danger" @click="hapus"><i class="fa fa-trash"></i></button>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><StatCard label="Kebutuhan (ABK)" :value="data.kebutuhan" icon="fa-bullseye" color="#2a78d6" :hint="'Hasil hitung ' + data.kebutuhan_hitung" /></div>
        <div class="col-6 col-lg-3"><StatCard label="Existing" :value="data.existing" icon="fa-users" color="#eb6834" /></div>
        <div class="col-6 col-lg-3"><StatCard label="Selisih" :value="(data.selisih > 0 ? '+' : '') + data.selisih" icon="fa-balance-scale" color="#6b7280" :value-class="data.selisih < 0 ? 'text-kurang' : (data.selisih > 0 ? 'text-lebih' : '')" /></div>
        <div class="col-6 col-lg-3"><StatCard label="Efektivitas Jabatan" :value="data.ej ?? '-'" icon="fa-tachometer" color="#4a3aa7" :hint="data.pej ? 'PEJ ' + data.pej.nilai + ' (' + data.pej.label + ')' : 'Belum ada pegawai'" /></div>
    </div>

    <div class="card mb-3" v-if="data.ikhtisar_jabatan">
        <div class="card-header">Ikhtisar Jabatan</div>
        <div class="card-body">{{ data.ikhtisar_jabatan }}</div>
    </div>

    <div class="card">
        <div class="card-header">Uraian Tugas & Perhitungan Beban Kerja (WKE {{ num(data.waktu_kerja_efektif) }} menit)</div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>#</th><th>Uraian tugas</th><th>Hasil kerja</th><th class="num">Volume</th><th class="num">Norma waktu</th><th class="num">Beban kerja</th><th class="num">Pegawai</th></tr></thead>
                <tbody>
                    <tr v-for="(t, i) in data.uraian_tugas" :key="t.id">
                        <td>{{ i + 1 }}</td><td>{{ t.uraian_tugas }}</td><td>{{ t.hasil_kerja }}</td>
                        <td class="num">{{ num(t.volume) }}</td><td class="num">{{ num(t.norma_waktu) }}</td>
                        <td class="num">{{ num(t.volume * t.norma_waktu) }}</td>
                        <td class="num">{{ (t.volume * t.norma_waktu / data.waktu_kerja_efektif).toFixed(2) }}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="fw-semibold"><td colspan="5" class="text-end">Jumlah</td><td class="num">{{ num(data.total_beban_kerja) }}</td><td class="num">{{ data.kebutuhan_hitung }}</td></tr>
                    <tr class="table-primary fw-bold"><td colspan="6" class="text-end">Kebutuhan pegawai</td><td class="num">{{ data.kebutuhan }}</td></tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import StatCard from '../../Components/StatCard.vue';
import { num } from '../../utils';

const props = defineProps({ data: Object, canEdit: Boolean });
const print = () => window.print();
const hapus = async () => {
    const ok = await Swal.fire({ icon: 'warning', title: 'Hapus ABK ini?', showCancelButton: true, confirmButtonText: 'Hapus', cancelButtonText: 'Batal' });
    if (ok.isConfirmed) router.delete(`/anjab/${props.data.id}`);
};
</script>
