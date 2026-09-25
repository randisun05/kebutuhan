<template>
    <Head title="Peringatan Dini" />
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <div class="page-title">Sistem Peringatan Dini</div>
            <div class="page-subtitle">Deteksi otomatis setiap pagi. Peringatan tertutup sendiri ketika kondisinya sudah teratasi. Deteksi terakhir: {{ terakhirDeteksi ? waktu(terakhirDeteksi) : 'belum pernah' }}.</div>
        </div>
        <button v-if="canRun" class="btn btn-outline-primary btn-sm" @click="router.post('/peringatan/deteksi', {}, { preserveScroll: true })"><i class="fa fa-refresh me-1"></i> Deteksi sekarang</button>
    </div>

    <div class="row g-3 mb-3">
        <div v-for="t in tingkatList" :key="t.key" class="col-6 col-lg-3">
            <a href="#" class="text-decoration-none" @click.prevent="setFilter('tingkat', f.tingkat === t.key ? '' : t.key)">
                <div class="card stat-card h-100" :class="{ 'border border-2 border-dark': f.tingkat === t.key }">
                    <div class="card-body d-flex justify-content-between">
                        <div><div class="label">{{ t.label }}</div><div class="value" :style="{ color: t.color }">{{ perTingkat[t.key] || 0 }}</div></div>
                        <div class="icon" :style="{ background: t.color + '1a', color: t.color }"><i class="fa" :class="t.icon"></i></div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-3">
            <div class="card">
                <div class="card-header">Jenis peringatan</div>
                <div class="list-group list-group-flush">
                    <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between small" :class="{ active: !f.kode }" @click.prevent="setFilter('kode', '')">Semua <span>{{ Object.values(perKode).reduce((a, b) => a + b, 0) }}</span></a>
                    <a v-for="(l, k) in aturan" :key="k" href="#" class="list-group-item list-group-item-action d-flex justify-content-between small" :class="{ active: f.kode === k }" @click.prevent="setFilter('kode', k)">
                        {{ l }} <span class="badge bg-light text-dark border">{{ perKode[k] || 0 }}</span>
                    </a>
                </div>
            </div>
        </div>
        <div class="col-xl-9">
            <div class="card mb-2"><div class="card-body py-2 d-flex flex-wrap gap-2">
                <select v-if="instansiOptions.length > 1" v-model="f.instansi_id" class="form-select form-select-sm w-auto" @change="go">
                    <option value="">Semua instansi</option>
                    <option v-for="i in instansiOptions" :key="i.id" :value="i.id">{{ i.nama }}</option>
                </select>
                <select v-model="f.status" class="form-select form-select-sm w-auto" @change="go">
                    <option value="terbuka">Terbuka (aktif + ditindaklanjuti)</option>
                    <option value="aktif">Aktif</option>
                    <option value="ditindaklanjuti">Ditindaklanjuti</option>
                    <option value="selesai">Selesai</option>
                </select>
            </div></div>

            <div class="card">
                <ul class="list-group list-group-flush">
                    <li v-for="p in datas.data" :key="p.id" class="list-group-item">
                        <div class="d-flex justify-content-between gap-3">
                            <div class="flex-grow-1" style="min-width: 0">
                                <span class="badge me-1" :style="{ background: warna[p.tingkat] }"><i class="fa" :class="ikon[p.tingkat]"></i> {{ p.tingkat.toUpperCase() }}</span>
                                <span class="small text-muted">{{ aturan[p.kode] }}</span>
                                <div class="fw-semibold mt-1">{{ p.judul }}</div>
                                <div class="small">{{ p.pesan }}</div>
                                <div class="small text-muted mt-1">
                                    {{ p.instansi?.nama }} · terdeteksi sejak {{ tanggal(p.pertama_terdeteksi_at) }}
                                    <span v-if="p.status === 'selesai'"> · selesai {{ tanggal(p.selesai_at) }}</span>
                                </div>
                                <div v-if="p.catatan_tindak_lanjut" class="small mt-1 p-2 bg-light rounded"><i class="fa fa-reply me-1"></i> {{ p.catatan_tindak_lanjut }} <span class="text-muted">— {{ p.penangan?.name }}</span></div>
                            </div>
                            <div class="d-flex flex-column gap-1 align-items-end flex-shrink-0">
                                <span class="badge" :class="{ aktif: 'bg-danger', ditindaklanjuti: 'bg-warning text-dark', selesai: 'bg-success' }[p.status]">{{ p.status }}</span>
                                <Link v-if="p.url" :href="p.url" class="btn btn-sm btn-light">Buka <i class="fa fa-angle-right"></i></Link>
                                <button v-if="p.status !== 'selesai' && bolehTindak" class="btn btn-sm btn-outline-primary" @click="tindak(p)">Tindak lanjut</button>
                            </div>
                        </div>
                    </li>
                    <li v-if="!datas.data.length" class="list-group-item text-center text-muted py-4">Tidak ada peringatan.</li>
                </ul>
                <div class="card-body pt-0"><Pagination :links="datas.links" /></div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import Pagination from '../../Components/Pagination.vue';
import { tanggal, waktu } from '../../utils';

const props = defineProps({ datas: Object, filters: Object, aturan: Object, instansiOptions: Array, perTingkat: Object, perKode: Object, terakhirDeteksi: String, canRun: Boolean });

// warna status (bukan warna seri) selalu disertai ikon + label
const warna = { kritis: '#d03b3b', tinggi: '#ec835a', sedang: '#b77900', rendah: '#6b7280' };
const ikon = { kritis: 'fa-times-circle', tinggi: 'fa-exclamation-circle', sedang: 'fa-exclamation-triangle', rendah: 'fa-info-circle' };
const tingkatList = [
    { key: 'kritis', label: 'Kritis', color: warna.kritis, icon: ikon.kritis },
    { key: 'tinggi', label: 'Tinggi', color: warna.tinggi, icon: ikon.tinggi },
    { key: 'sedang', label: 'Sedang', color: warna.sedang, icon: ikon.sedang },
    { key: 'rendah', label: 'Rendah', color: warna.rendah, icon: ikon.rendah },
];

const f = reactive({ kode: props.filters.kode || '', tingkat: props.filters.tingkat || '', instansi_id: props.filters.instansi_id || '', status: props.filters.status || 'terbuka' });
const go = () => router.get('/peringatan', Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true, preserveScroll: true, replace: true });
const setFilter = (k, v) => { f[k] = v; go(); };
const bolehTindak = computed(() => usePage().props.auth.user.role !== 'pimpinan');

const tindak = async (p) => {
    const res = await Swal.fire({
        title: 'Catat tindak lanjut', text: p.judul, input: 'textarea', inputValue: p.catatan_tindak_lanjut || '',
        inputPlaceholder: 'Contoh: sudah diusulkan formasi 2027 / mutasi diproses SK …',
        inputValidator: (v) => (!v ? 'Catatan wajib diisi' : undefined),
        showCancelButton: true, confirmButtonText: 'Simpan', cancelButtonText: 'Batal',
    });
    if (res.isConfirmed) router.post(`/peringatan/${p.id}/tindak-lanjut`, { catatan: res.value }, { preserveScroll: true });
};
</script>
