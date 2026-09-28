<template>
    <Head title="Laporan" />
    <div class="page-title">Pusat Laporan</div>
    <div class="page-subtitle">Pilih jenis laporan dan cakupan, lihat pratinjau, lalu unduh sebagai Excel atau PDF.</div>

    <div class="row g-3">
        <div class="col-xl-3">
            <div class="card">
                <div class="card-header">Jenis laporan</div>
                <div class="list-group list-group-flush">
                    <a v-for="(l, k) in jenisOptions" :key="k" href="#" class="list-group-item list-group-item-action small" :class="{ active: f.jenis === k }" @click.prevent="f.jenis = k; go()">{{ l }}</a>
                </div>
            </div>
        </div>
        <div class="col-xl-9">
            <div class="card mb-3"><div class="card-body py-2">
                <form class="d-flex flex-wrap gap-2 align-items-center" @submit.prevent="go">
                    <select v-if="instansiOptions.length > 1" v-model="f.instansi_id" class="form-select form-select-sm w-auto" @change="f.unit_kerja_id = ''; go()">
                        <option value="">Semua instansi</option>
                        <option v-for="i in instansiOptions" :key="i.id" :value="i.id">{{ i.nama }}</option>
                    </select>
                    <select v-if="unitOptions.length && pakai('unit')" v-model="f.unit_kerja_id" class="form-select form-select-sm w-auto" @change="go">
                        <option value="">Semua unit</option>
                        <option v-for="u in unitOptions" :key="u.id" :value="u.id">{{ '— '.repeat(u.depth) }}{{ u.nama }}</option>
                    </select>
                    <select v-if="pakai('jenis')" v-model="f.jenis_jabatan" class="form-select form-select-sm w-auto" @change="go">
                        <option value="">Semua jenis jabatan</option>
                        <option v-for="j in jenisJabatanOptions" :key="j.value" :value="j.value">{{ j.label }}</option>
                    </select>
                    <input v-if="pakai('tahun')" v-model="f.tahun" type="number" class="form-control form-control-sm" style="width: 130px" :placeholder="f.jenis === 'pensiun' ? 'Dalam … tahun' : 'Tahun'" @change="go">
                    <select v-if="f.jenis === 'tidak_bergerak'" v-model="f.bulan" class="form-select form-select-sm w-auto" @change="go">
                        <option v-for="b in [3, 6, 12, 24]" :key="b" :value="b">{{ b }} bulan</option>
                    </select>
                    <div class="ms-auto d-flex gap-2" v-if="hasil">
                        <a :href="'/laporan/unduh/xlsx?' + qs" class="btn btn-sm btn-success"><i class="fa fa-file-excel-o me-1"></i> Excel</a>
                        <a :href="'/laporan/unduh/pdf?' + qs" class="btn btn-sm btn-danger"><i class="fa fa-file-pdf-o me-1"></i> PDF</a>
                    </div>
                </form>
            </div></div>

            <div v-if="!hasil" class="card"><div class="card-body text-center text-muted py-5"><i class="fa fa-file-text-o fa-2x mb-2"></i><div>Pilih jenis laporan di sebelah kiri.</div></div></div>

            <div v-else class="card">
                <div class="card-header">
                    <div class="fw-semibold">{{ hasil.judul }}</div>
                    <div class="small text-muted fw-normal">{{ hasil.subjudul }} · {{ hasil.jumlah_baris }} baris<span v-if="hasil.jumlah_baris > hasil.baris.length"> (pratinjau {{ hasil.baris.length }} baris pertama, unduhan memuat semua)</span></div>
                </div>
                <div class="card-body py-2 border-bottom d-flex flex-wrap gap-3 small" v-if="Object.keys(hasil.angka || {}).length">
                    <span v-for="(v, k) in hasil.angka" :key="k"><span class="text-muted">{{ k.replaceAll('_', ' ') }}:</span> <b>{{ num(v) }}</b></span>
                </div>
                <div class="table-responsive" style="max-height: 70vh">
                    <table class="table table-sm table-hover mb-0">
                        <thead style="position: sticky; top: 0"><tr><th>#</th><th v-for="k in hasil.kolom" :key="k">{{ k }}</th></tr></thead>
                        <tbody>
                            <tr v-for="(row, i) in hasil.baris" :key="i">
                                <td class="text-muted">{{ i + 1 }}</td>
                                <td v-for="(v, j) in row" :key="j" :class="{ num: typeof v === 'number' }">{{ typeof v === 'number' ? num(v) : (v ?? '–') }}</td>
                            </tr>
                            <tr v-if="!hasil.baris.length"><td :colspan="hasil.kolom.length + 1" class="text-center text-muted py-4">Tidak ada data{{ f.jenis === 'rekap_unit' && !f.instansi_id ? ' — pilih instansi terlebih dahulu' : '' }}.</td></tr>
                        </tbody>
                        <tfoot v-if="hasil.total"><tr class="fw-bold table-light"><td></td><td v-for="(v, j) in hasil.total" :key="j" :class="{ num: typeof v === 'number' }">{{ typeof v === 'number' ? num(v) : v }}</td></tr></tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { num } from '../../utils';

const props = defineProps({ jenisOptions: Object, filters: Object, instansiOptions: Array, unitOptions: Array, jenisJabatanOptions: Array, hasil: Object });
const f = reactive({ jenis: props.filters.jenis || '', instansi_id: props.filters.instansi_id || '', unit_kerja_id: props.filters.unit_kerja_id || '',
    jenis_jabatan: props.filters.jenis_jabatan || '', tahun: props.filters.tahun || '', bulan: props.filters.bulan || 6 });
const clean = () => Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '' && v !== null));
const qs = computed(() => new URLSearchParams(clean()).toString());
const go = () => router.get('/laporan', clean(), { preserveState: true, replace: true });
const PAKAI = {
    unit: ['rekap_jabatan', 'posisi_bermasalah', 'pensiun', 'tidak_bergerak', 'rekap_instansi'],
    jenis: ['rekap_instansi', 'rekap_unit', 'rekap_jabatan', 'posisi_bermasalah', 'pensiun'],
    tahun: ['usulan_penetapan', 'pensiun', 'abk'],
};
const pakai = (k) => PAKAI[k].includes(f.jenis);
</script>
