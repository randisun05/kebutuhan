<template>
    <Head title="Perbandingan Kebutuhan JF" />
    <div class="page-title">Perbandingan Kebutuhan JF</div>
    <div class="page-subtitle">Rekomendasi, penetapan, ABK, dan bezetting dari berbagai sumber <b>berdampingan sebagai referensi</b>; tidak ada yang menjadi patokan dan tidak saling membatasi. Kolom kosong berarti <b>tidak ada data</b>, bukan nol.</div>

    <div v-if="pembinaOptions.length > 1" class="mb-2 d-flex align-items-center gap-2">
        <span class="small text-muted">Instansi pembina:</span>
        <select v-model="f.pembina_id" class="form-select form-select-sm w-auto" @change="router.get('/perbandingan-jf', { pembina_id: f.pembina_id }, { replace: true })"><option v-for="p in pembinaOptions" :key="p.id" :value="p.id">{{ p.nama }}</option></select>
    </div>
    <div v-else-if="pembinaOptions.length === 1" class="mb-2 small text-muted">Instansi pembina: <b>{{ pembinaOptions[0].nama }}</b></div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><StatCard label="Baris (instansi × jabatan)" :value="ringkasan.baris" icon="fa-th-list" color="#6b7280" /></div>
        <div class="col-6 col-lg-3"><StatCard label="Kebutuhan berjauhan" :value="ringkasan.berjauhan_kebutuhan" icon="fa-arrows-h" color="#b77900" :hint="`selisih > ${ambang}% antar sumber kebutuhan`" /></div>
        <div class="col-6 col-lg-3"><StatCard label="Bezetting berjauhan" :value="ringkasan.berjauhan_bezetting" icon="fa-arrows-h" color="#d03b3b" :hint="`selisih > ${ambang}% antar sumber bezetting`" /></div>
    </div>

    <div class="card mb-2"><div class="card-body py-2 d-flex flex-wrap gap-2">
        <input v-model="f.q" class="form-control form-control-sm w-auto" placeholder="Cari instansi..." @keyup.enter="go">
        <select v-model="f.jabatan" class="form-select form-select-sm w-auto" @change="go"><option value="">Semua jabatan</option><option v-for="j in jabatanOptions" :key="j" :value="j">{{ j }}</option></select>
        <select v-model="f.berjauhan" class="form-select form-select-sm w-auto" @change="go"><option value="">Semua baris</option><option value="kebutuhan">Kebutuhan berjauhan</option><option value="bezetting">Bezetting berjauhan</option></select>
        <button class="btn btn-sm btn-primary" @click="go"><i class="fa fa-search"></i></button>
    </div></div>

    <div class="card">
        <div class="table-responsive"><table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th rowspan="2">Instansi</th><th rowspan="2">Jabatan</th>
                    <th v-if="kebutuhan.length" :colspan="kebutuhan.length" class="text-center border-start">Kebutuhan</th>
                    <th v-if="bezetting.length" :colspan="bezetting.length" class="text-center border-start">Bezetting</th><th rowspan="2"></th>
                </tr>
                <tr>
                    <th v-for="s in kebutuhan" :key="s.kode" class="num border-start" :title="label(s.adapter)">{{ s.nama }}</th>
                    <th v-for="s in bezetting" :key="s.kode" class="num border-start" :title="label(s.adapter)">{{ s.nama }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="d in datas.data" :key="d.id">
                    <td>{{ d.instansi }} <span v-if="!d.dipetakan" class="badge bg-warning text-dark ms-1" title="Alias belum dipetakan ke referensi instansi BKN">belum dipetakan</span></td>
                    <td class="small">{{ d.jabatan }}</td>
                    <td v-for="(s, i) in kebutuhan" :key="s.kode" class="num" :class="[i === 0 ? 'border-start' : '', d.berjauhan.kebutuhan ? 'table-warning' : '']">{{ n(d.nilai[s.kode]) }}</td>
                    <td v-for="(s, i) in bezetting" :key="s.kode" class="num" :class="[i === 0 ? 'border-start' : '', d.berjauhan.bezetting ? 'table-danger' : '']">{{ n(d.nilai[s.kode]) }}</td>
                    <td class="text-end"><Link :href="`/perbandingan-jf/${d.id}`" class="btn btn-sm btn-light">Rincian <i class="fa fa-angle-right"></i></Link></td>
                </tr>
                <tr v-if="!datas.data.length"><td :colspan="3 + kebutuhan.length + bezetting.length" class="text-center text-muted py-4">Tidak ada data.</td></tr>
            </tbody>
        </table></div>
        <div class="card-body pt-2"><Pagination :links="datas.links" /></div>
    </div>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import StatCard from '../../Components/StatCard.vue';
import Pagination from '../../Components/Pagination.vue';
import { num } from '../../utils';

const props = defineProps({ datas: Object, sumber: Array, pembinaOptions: Array, pembinaId: Number, filters: Object, jabatanOptions: Array, ringkasan: Object, ambang: Number });
const f = reactive({ q: '', jabatan: '', berjauhan: '', ...props.filters });
const go = () => router.get('/perbandingan-jf', Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true, replace: true });
const kebutuhan = computed(() => props.sumber.filter((s) => s.jenis === 'kebutuhan'));
const bezetting = computed(() => props.sumber.filter((s) => s.jenis === 'bezetting'));
const n = (v) => (v === null || v === undefined ? '–' : num(v));
const label = (a) => ({ fakta: 'Impor Excel', abk: 'Dihitung langsung dari ABK SIMONKEB', pegawai: 'Dihitung langsung dari pegawai SIMONKEB', dashboard: 'API Dashboard' }[a] || a);
</script>
