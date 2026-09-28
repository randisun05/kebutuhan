<template>
    <Head title="Efektivitas Unit" />
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <Link href="/anjab" class="small"><i class="fa fa-angle-left"></i> Manajemen ABK</Link>
            <div class="page-title">Efektivitas & Efisiensi Unit (EU / PEU)</div>
            <div class="page-subtitle">{{ instansi.nama }} · EU = Σ(beban kerja ÷ WKE) ÷ jumlah pegawai unit, dari ABK final terbaru.</div>
        </div>
        <select v-if="instansiOptions.length > 1" class="form-select form-select-sm w-auto" :value="instansi.id" @change="router.get('/anjab-rekap-unit', { instansi_id: $event.target.value })">
            <option v-for="i in instansiOptions" :key="i.id" :value="i.id">{{ i.nama }}</option>
        </select>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><StatCard label="Beban kerja (setara pegawai)" :value="total.beban_pegawai" icon="fa-briefcase" color="#2a78d6" /></div>
        <div class="col-6 col-lg-3"><StatCard label="Kebutuhan (ABK)" :value="total.kebutuhan" icon="fa-bullseye" color="#4a3aa7" /></div>
        <div class="col-6 col-lg-3"><StatCard label="Pegawai existing" :value="total.existing" icon="fa-users" color="#eb6834" /></div>
        <div class="col-6 col-lg-3"><StatCard label="EU instansi" :value="total.eu ?? '-'" icon="fa-tachometer" color="#0ca30c" :hint="total.peu ? 'PEU ' + total.peu.nilai + ' · ' + total.peu.label : ''" /></div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th class="col-unit">Unit Kerja</th><th class="num">Jabatan ber-ABK</th><th class="num">Beban (pegawai)</th><th class="num">Kebutuhan</th><th class="num">Existing</th><th class="num">EU</th><th>PEU</th></tr></thead>
                <tbody>
                    <tr v-for="u in units" :key="u.id">
                        <td class="col-unit" :class="'unit-depth-' + Math.min(u.depth, 3)">{{ u.nama }}</td>
                        <td class="num">{{ u.jabatan }}</td>
                        <td class="num">{{ u.beban_pegawai }}</td>
                        <td class="num">{{ u.kebutuhan }}</td>
                        <td class="num">{{ u.existing }}</td>
                        <td class="num fw-semibold">{{ u.eu ?? '-' }}</td>
                        <td><span v-if="u.peu" class="badge bg-light text-dark border">{{ u.peu.nilai }} · {{ u.peu.label }}</span><span v-else class="small text-muted">-</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-body small text-muted border-top">PEU: A &gt; 1,00 (sangat baik) · B 0,90–1,00 · C 0,70–0,89 · D 0,50–0,69 · E &lt; 0,50. EU di atas 1 berarti beban kerja melebihi kapasitas pegawai yang ada.</div>
    </div>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import StatCard from '../../Components/StatCard.vue';

defineProps({ instansi: Object, instansiOptions: Array, units: Array, total: Object });
</script>
