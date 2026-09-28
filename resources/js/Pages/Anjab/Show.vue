<template>
    <Head :title="'ABK ' + data.jabatan.nama" />
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <Link href="/anjab" class="small"><i class="fa fa-angle-left"></i> Daftar ABK</Link>
            <div class="page-title">{{ data.jabatan.nama }}</div>
            <div class="page-subtitle">{{ data.unit_kerja.nama }} · {{ data.instansi.nama }} · Tahun {{ data.tahun }}
                <span class="badge ms-1" :class="data.status === 'final' ? 'bg-success' : 'bg-secondary'">{{ data.status }}</span>
                <span v-if="data.finalizer" class="small"> · difinalkan {{ data.finalizer.name }} {{ waktu(data.finalized_at) }}</span></div>
        </div>
        <div class="d-flex flex-wrap gap-2 no-print">
            <a :href="`/anjab/${data.id}/pdf`" class="btn btn-sm btn-outline-danger"><i class="fa fa-file-pdf-o"></i> PDF</a>
            <template v-if="canEdit">
                <button class="btn btn-sm btn-outline-success" @click="toggle"><i class="fa" :class="data.status === 'final' ? 'fa-unlock' : 'fa-lock'"></i> {{ data.status === 'final' ? 'Kembalikan ke draft' : 'Finalkan' }}</button>
                <button class="btn btn-sm btn-outline-primary" @click="salin"><i class="fa fa-copy"></i> Salin ke tahun…</button>
                <Link :href="`/anjab/${data.id}/edit`" class="btn btn-sm btn-warning"><i class="fa fa-pencil"></i> Ubah</Link>
                <button class="btn btn-sm btn-outline-danger" @click="hapus"><i class="fa fa-trash"></i></button>
            </template>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><StatCard label="Kebutuhan (ABK)" :value="data.kebutuhan" icon="fa-bullseye" color="#2a78d6" :hint="'Hasil hitung ' + data.kebutuhan_hitung" /></div>
        <div class="col-6 col-lg-3"><StatCard label="Existing" :value="data.existing" icon="fa-users" color="#eb6834" /></div>
        <div class="col-6 col-lg-3"><StatCard label="Selisih" :value="(data.selisih > 0 ? '+' : '') + data.selisih" icon="fa-balance-scale" color="#6b7280" :value-class="data.selisih < 0 ? 'text-kurang' : (data.selisih > 0 ? 'text-lebih' : '')" /></div>
        <div class="col-6 col-lg-3"><StatCard label="Efektivitas Jabatan" :value="data.ej ?? '-'" icon="fa-tachometer" color="#4a3aa7" :hint="data.pej ? 'PEJ ' + data.pej.nilai + ' (' + data.pej.label + ')' : 'Belum ada pegawai'" /></div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card mb-3">
                <div class="card-header">Uraian Tugas & Beban Kerja (WKE {{ num(data.waktu_kerja_efektif) }} menit)</div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>#</th><th>Uraian tugas</th><th>Hasil kerja</th><th class="num">Volume</th><th class="num">Norma</th><th class="num">Beban/th</th><th class="num">Pegawai</th></tr></thead>
                        <tbody>
                            <tr v-for="(t, i) in data.uraian_tugas" :key="t.id">
                                <td>{{ i + 1 }}</td><td>{{ t.uraian_tugas }}</td><td>{{ t.hasil_kerja }}</td>
                                <td class="num">{{ num(t.volume) }} <span class="small text-muted">{{ periodeLabel[t.satuan_periode] }}</span></td>
                                <td class="num">{{ num(t.norma_waktu) }}</td>
                                <td class="num">{{ num(beban(t)) }}</td>
                                <td class="num">{{ (beban(t) / data.waktu_kerja_efektif).toFixed(2) }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="fw-semibold"><td colspan="5" class="text-end">Jumlah</td><td class="num">{{ num(data.total_beban_kerja) }}</td><td class="num">{{ data.kebutuhan_hitung }}</td></tr>
                            <tr class="table-primary fw-bold"><td colspan="6" class="text-end">Kebutuhan pegawai</td><td class="num">{{ data.kebutuhan }}</td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Informasi Jabatan</div>
                <div class="card-body">
                    <div class="mb-3"><div class="small text-muted">Ikhtisar jabatan</div>{{ data.ikhtisar_jabatan || '-' }}</div>
                    <div class="row">
                        <div v-for="(meta, key) in terisi" :key="key" class="col-md-6 mb-3">
                            <div class="small text-muted">{{ meta.label }}</div>
                            <ul v-if="Array.isArray(data.informasi[key])" class="mb-0 ps-3"><li v-for="(x, i) in data.informasi[key]" :key="i">{{ x }}</li></ul>
                            <div v-else>{{ data.informasi[key] }}</div>
                        </div>
                        <div v-if="!Object.keys(terisi).length" class="text-muted small">Informasi jabatan (kualifikasi, bahan/perangkat kerja, tanggung jawab, wewenang, korelasi, syarat jabatan) belum diisi.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-header">Proyeksi kebutuhan (pertumbuhan {{ data.pertumbuhan_beban }}%/th)</div>
                <table class="table table-sm mb-0">
                    <tbody><tr v-for="p in proyeksi" :key="p.tahun"><td>{{ p.tahun }}</td><td class="num fw-semibold">{{ p.kebutuhan }}</td></tr></tbody>
                </table>
                <div class="card-body small"><Link :href="`/proyeksi?instansi_id=${data.instansi_id}&unit_kerja_id=${data.unit_kerja_id}`">Proyeksi lengkap dengan pensiun <i class="fa fa-angle-right"></i></Link></div>
            </div>
            <div class="card">
                <div class="card-header">Versi ABK posisi ini</div>
                <ul class="list-group list-group-flush">
                    <li v-for="v in versi" :key="v.id" class="list-group-item d-flex justify-content-between" :class="{ 'fw-semibold': v.id === data.id }">
                        <Link :href="`/anjab/${v.id}`">Tahun {{ v.tahun }}</Link>
                        <span><span class="badge me-1" :class="v.status === 'final' ? 'bg-success' : 'bg-secondary'">{{ v.status }}</span> K = {{ v.kebutuhan }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import StatCard from '../../Components/StatCard.vue';
import { num, waktu } from '../../utils';

const props = defineProps({ data: Object, canEdit: Boolean, informasiFields: Object, periodeLabel: Object, periodePerTahun: Object, proyeksi: Array, versi: Array });
const beban = (t) => t.volume * (props.periodePerTahun[t.satuan_periode] || 1) * t.norma_waktu;
const terisi = computed(() => Object.fromEntries(Object.entries(props.informasiFields).filter(([k]) => {
    const v = props.data.informasi?.[k];
    return Array.isArray(v) ? v.length : !!v;
})));
const toggle = () => router.post(`/anjab/${props.data.id}/status`, {}, { preserveScroll: true });
const salin = async () => {
    const res = await Swal.fire({ title: 'Salin ABK ke tahun', input: 'number', inputValue: props.data.tahun + 1, showCancelButton: true, confirmButtonText: 'Salin', cancelButtonText: 'Batal' });
    if (res.isConfirmed) router.post(`/anjab/${props.data.id}/duplicate`, { tahun: res.value });
};
const hapus = async () => {
    const ok = await Swal.fire({ icon: 'warning', title: 'Hapus ABK ini?', showCancelButton: true, confirmButtonText: 'Hapus', cancelButtonText: 'Batal' });
    if (ok.isConfirmed) router.delete(`/anjab/${props.data.id}`);
};
</script>
