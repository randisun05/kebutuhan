<template>
    <Head title="Pengisian Formasi" />
    <div class="page-title">Pengisian Formasi</div>
    <div class="page-subtitle">Lacak realisasi formasi yang sudah ditetapkan (hasil seleksi yang telah diangkat). Sisa formasi belum terisi tampil di monitoring dan proyeksi.</div>

    <div class="row g-3 mb-3">
        <div class="col-md-4"><StatCard label="Formasi ditetapkan" :value="ringkasan.ditetapkan" icon="fa-gavel" color="#2a78d6" /></div>
        <div class="col-md-4"><StatCard label="Sudah terisi" :value="ringkasan.terisi" icon="fa-user-plus" color="#0ca30c" :hint="persen + '% realisasi'" /></div>
        <div class="col-md-4"><StatCard label="Belum terisi" :value="ringkasan.ditetapkan - ringkasan.terisi" icon="fa-hourglass-half" color="#b77900" /></div>
    </div>

    <div class="card mb-3"><div class="card-body py-2">
        <form class="d-flex flex-wrap gap-2" @submit.prevent="go">
            <select v-if="instansiOptions.length > 1" v-model="f.instansi_id" class="form-select form-select-sm w-auto" @change="go">
                <option value="">Semua instansi</option>
                <option v-for="i in instansiOptions" :key="i.id" :value="i.id">{{ i.nama }}</option>
            </select>
            <select v-model="f.status" class="form-select form-select-sm w-auto" @change="go">
                <option value="">Semua</option><option value="belum">Belum terisi penuh</option><option value="penuh">Sudah terisi penuh</option>
            </select>
            <input v-model="f.tahun" type="number" class="form-control form-control-sm" style="width: 100px" placeholder="Tahun" @change="go">
            <button v-if="canEdit" type="button" class="btn btn-sm btn-primary ms-auto" :disabled="!dirty" @click="simpan"><i class="fa fa-save me-1"></i> Simpan perubahan</button>
        </form>
    </div></div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>SK / Usulan</th><th>Instansi</th><th class="col-unit">Unit</th><th class="col-jabatan">Jabatan</th><th class="num">Ditetapkan</th><th class="num" style="width: 120px">Terisi</th><th style="width: 140px">Realisasi</th><th>Diperbarui</th></tr></thead>
                <tbody>
                    <tr v-for="d in rows" :key="d.id">
                        <td class="small"><Link :href="`/usulan/${d.usulan_id}`">{{ d.usulan?.penetapan?.nomor_sk || d.usulan?.nomor }}</Link><div class="text-muted">{{ d.usulan?.tahun }} · {{ d.usulan?.jenis_asn?.toUpperCase() }}</div></td>
                        <td class="small">{{ d.usulan?.instansi?.nama }}</td>
                        <td class="small">{{ d.unit_kerja?.nama }}</td>
                        <td>{{ d.jabatan?.nama }}</td>
                        <td class="num">{{ d.jumlah_ditetapkan }}</td>
                        <td class="num">
                            <input v-if="canEdit" v-model.number="d.jumlah_terisi" type="number" min="0" :max="d.jumlah_ditetapkan" class="form-control form-control-sm text-end" @input="dirty = true">
                            <span v-else>{{ d.jumlah_terisi }}</span>
                        </td>
                        <td><div class="progress progress-thin"><div class="progress-bar" :class="d.jumlah_terisi >= d.jumlah_ditetapkan ? 'bg-success' : 'bg-warning'" :style="{ width: Math.min(100, d.jumlah_terisi / d.jumlah_ditetapkan * 100) + '%' }"></div></div></td>
                        <td class="small text-muted">{{ waktu(d.terisi_updated_at) }}</td>
                    </tr>
                    <tr v-if="!rows.length"><td colspan="8" class="text-center text-muted py-4">Belum ada formasi yang ditetapkan.</td></tr>
                </tbody>
            </table>
        </div>
        <div class="card-body pt-0"><Pagination :links="datas.links" /></div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Pagination from '../../Components/Pagination.vue';
import StatCard from '../../Components/StatCard.vue';
import { waktu } from '../../utils';

const props = defineProps({ datas: Object, filters: Object, instansiOptions: Array, ringkasan: Object, canEdit: Boolean });
const rows = ref(props.datas.data.map((d) => ({ ...d })));
watch(() => props.datas, (v) => { rows.value = v.data.map((d) => ({ ...d })); dirty.value = false; });
const dirty = ref(false);
const persen = computed(() => (props.ringkasan.ditetapkan ? Math.round((props.ringkasan.terisi / props.ringkasan.ditetapkan) * 100) : 0));
const f = reactive({ instansi_id: props.filters.instansi_id || '', status: props.filters.status || '', tahun: props.filters.tahun || '' });
const go = () => router.get('/formasi', Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true, replace: true });
const simpan = () => router.put('/formasi', { items: rows.value.map((d) => ({ id: d.id, jumlah_terisi: d.jumlah_terisi || 0 })) }, { preserveScroll: true });
</script>
