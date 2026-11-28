<template>
    <Head :title="data.instansi" />
    <Link href="/perbandingan-jf" class="small"><i class="fa fa-angle-left"></i> Semua perbandingan</Link>
    <div class="page-title">{{ data.instansi }}</div>
    <div class="page-subtitle">{{ data.jabatan }}<span v-if="data.pokja"> · {{ data.pokja }}</span> · <Link :href="`/rekomendasi-jf/${data.id}`">rincian rekomendasi</Link></div>

    <div v-if="!data.dipetakan" class="alert alert-warning small">Nama instansi ini belum dipetakan ke referensi instansi BKN (menu Alias Instansi), sehingga sumber yang dihitung langsung dari SIMONKEB (ABK dan pegawai) tidak dapat dikaitkan.</div>
    <div v-else-if="r.instansi_lokal === null" class="alert alert-light border small">Instansi ini belum dikaitkan ke instansi di aplikasi, jadi ABK dan pegawai SIMONKEB tidak tersedia. Kaitkan lewat referensi instansi BKN.</div>
    <div v-else-if="!r.jabatan_terkait" class="alert alert-light border small">Belum ada jabatan SIMONKEB yang dikaitkan ke <b>{{ data.jabatan }}</b> (form Jabatan), sehingga ABK dan pegawai tidak dapat disejajarkan.</div>

    <div class="card mb-3">
        <div class="card-header">Per jenjang</div>
        <div class="card-body py-2 small text-muted">Setiap sumber berdiri sendiri. Sel kosong = tidak ada data. Selisih antar sumber sejenis lebih dari {{ ambang }}% ditandai pada daftar.</div>
        <div class="table-responsive"><table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th rowspan="2">Jenjang</th>
                    <th v-if="kebutuhan.length" :colspan="kebutuhan.length" class="text-center border-start">Kebutuhan</th>
                    <th v-if="bezetting.length" :colspan="bezetting.length" class="text-center border-start">Bezetting</th>
                </tr>
                <tr><th v-for="s in kebutuhan" :key="s.kode" class="num border-start">{{ s.nama }}</th><th v-for="s in bezetting" :key="s.kode" class="num border-start">{{ s.nama }}</th></tr>
            </thead>
            <tbody>
                <tr v-for="j in r.jenjang" :key="j.kode"><td>{{ j.nama }}</td>
                    <td v-for="(s, i) in kebutuhan" :key="s.kode" class="num" :class="i === 0 ? 'border-start' : ''">{{ n(j.nilai[s.kode]) }}</td>
                    <td v-for="(s, i) in bezetting" :key="s.kode" class="num" :class="i === 0 ? 'border-start' : ''">{{ n(j.nilai[s.kode]) }}</td></tr>
                <tr class="fw-semibold"><td>Total</td>
                    <td v-for="(s, i) in kebutuhan" :key="s.kode" class="num" :class="i === 0 ? 'border-start' : ''">{{ total(s.kode) }}</td>
                    <td v-for="(s, i) in bezetting" :key="s.kode" class="num" :class="i === 0 ? 'border-start' : ''">{{ total(s.kode) }}</td></tr>
            </tbody>
        </table></div>
    </div>

    <div v-if="r.unit.length" class="card mb-3">
        <div class="card-header">Per unit kerja (data SIMONKEB) <span class="badge bg-secondary ms-1">{{ r.unit.length }}</span></div>
        <div class="card-body py-2 small text-muted">ABK final terbaru dan pegawai aktif per unit untuk jabatan yang dikaitkan ke {{ data.jabatan }}. Rekomendasi dan penetapan hanya tersedia per instansi.</div>
        <div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>Unit kerja</th><th>Jabatan</th><th class="num">ABK</th><th class="num">Pegawai aktif</th></tr></thead>
            <tbody><tr v-for="(u, i) in r.unit" :key="i"><td>{{ u.unit }}</td><td class="small">{{ u.jabatan }}</td><td class="num">{{ n(u.abk) }}</td><td class="num">{{ n(u.pegawai) }}</td></tr></tbody>
        </table></div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { num } from '../../utils';

const props = defineProps({ data: Object, r: Object, ambang: Number });
const kebutuhan = computed(() => props.r.sumber.filter((s) => s.jenis === 'kebutuhan'));
const bezetting = computed(() => props.r.sumber.filter((s) => s.jenis === 'bezetting'));
const n = (v) => (v === null || v === undefined ? '–' : num(v));
const total = (kode) => (props.r.jenjang.some((j) => j.nilai[kode] != null) ? num(props.r.jenjang.reduce((a, j) => a + (j.nilai[kode] ?? 0), 0)) : '–');
</script>
