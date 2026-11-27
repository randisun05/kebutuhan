<template>
    <Head title="Rekomendasi JF MASN" />
    <div class="page-title">Rekomendasi JF MASN</div>
    <div class="page-subtitle">Rekomendasi BKN dan penetapan KemenPANRB per instansi × jabatan pokok × jenjang, diimpor dari Excel rekap tim data. Angka bezetting di sini adalah <b>foto saat rekomendasi</b>, bukan kondisi sekarang.</div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg"><StatCard label="Instansi" :value="ringkasan.instansi" icon="fa-building" color="#2a78d6" /></div>
        <div class="col-6 col-lg"><StatCard label="Baris (instansi × jabatan)" :value="ringkasan.baris" icon="fa-th-list" color="#6b7280" /></div>
        <div class="col-6 col-lg"><StatCard label="Rekomendasi BKN selesai" :value="ringkasan.rekom_done" icon="fa-check" color="#198754" /></div>
        <div class="col-6 col-lg"><StatCard label="Ditetapkan KemenPANRB" :value="ringkasan.ditetapkan" icon="fa-gavel" color="#4a3aa7" /></div>
        <div v-if="bolehImpor" class="col-6 col-lg"><StatCard label="Instansi belum cocok" :value="ringkasan.belum_cocok" icon="fa-chain-broken" color="#b77900" /></div>
    </div>

    <div v-if="bolehImpor" class="card mb-3">
        <div class="card-body py-2 d-flex flex-wrap align-items-center gap-2 small">
            <span class="fw-semibold"><i class="fa fa-upload me-1"></i> Impor Excel rekap</span>
            <span class="text-muted me-auto">
                Sheet <code>Input Data (Instansi Baru)</code>. Pratinjau dulu sebelum disimpan; baris yang sudah ada diperbarui, bukan digandakan.
                <template v-if="imporTerakhir"> Impor terakhir: {{ waktu(imporTerakhir.created_at) }} oleh {{ imporTerakhir.user?.name || '-' }} ({{ imporTerakhir.jumlah_baris }} baris).</template>
            </span>
            <button class="btn btn-sm btn-primary" :disabled="proses" @click="berkas.click()"><i class="fa me-1" :class="proses ? 'fa-spinner fa-spin' : 'fa-file-excel-o'"></i> Pilih berkas .xlsx</button>
            <input ref="berkas" type="file" accept=".xlsx" class="d-none" @change="pilihBerkas">
        </div>
    </div>

    <div v-if="bolehImpor && belumCocok.length" class="card mb-3 border-warning">
        <div class="card-header">Instansi di Excel yang belum cocok dengan referensi BKN <span class="badge bg-warning text-dark ms-1">{{ belumCocok.length }}</span></div>
        <div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>Nama di Excel</th><th>Referensi instansi BKN</th><th></th></tr></thead>
            <tbody><tr v-for="b in belumCocok" :key="b.nama_instansi_normal">
                <td>{{ b.nama }}</td>
                <td><input v-model="pilihan[b.nama_instansi_normal]" class="form-control form-control-sm" list="ref-instansi" placeholder="Ketik untuk mencari..."></td>
                <td class="text-end"><button class="btn btn-sm btn-outline-primary" :disabled="!idRef(b.nama_instansi_normal)" @click="petakan(b)">Simpan</button></td>
            </tr></tbody>
        </table></div>
        <datalist id="ref-instansi"><option v-for="r in refOptions" :key="r.id" :value="r.nama"></option></datalist>
    </div>

    <div class="card mb-2"><div class="card-body py-2 d-flex flex-wrap gap-2">
        <input v-model="f.q" class="form-control form-control-sm w-auto" placeholder="Cari instansi..." @keyup.enter="go">
        <select v-model="f.jabatan_pokok" class="form-select form-select-sm w-auto" @change="go"><option value="">Semua jabatan</option><option v-for="j in jabatanOptions" :key="j" :value="j">{{ j }}</option></select>
        <select v-model="f.status_rekom" class="form-select form-select-sm w-auto" @change="go"><option value="">Semua status rekomendasi</option><option v-for="s in statusOptions" :key="s" :value="s">{{ s }}</option></select>
        <select v-model="f.status_menpan" class="form-select form-select-sm w-auto" @change="go"><option value="">Semua status Menpan</option><option value="ditetapkan">Ditetapkan</option><option value="belum">Belum ditetapkan</option></select>
        <button class="btn btn-sm btn-primary" @click="go"><i class="fa fa-search"></i></button>
    </div></div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Instansi</th><th>Jabatan</th><th>Status rekomendasi</th><th class="num">Bezetting saat rekom</th><th class="num">Rekom BKN</th><th class="num">Penetapan Menpan</th><th class="num">Selisih Menpan − BKN</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="d in datas.data" :key="d.id">
                        <td>{{ d.nama_instansi }} <span v-if="!d.ref_instansi_id" class="badge bg-warning text-dark ms-1" title="Belum cocok dengan referensi instansi BKN">belum cocok</span></td>
                        <td>{{ d.jabatan_pokok }}</td>
                        <td><span class="badge" :class="d.status_rekom === 'DONE' ? 'bg-success' : 'bg-secondary'">{{ d.status_rekom || '-' }}</span> <span v-if="d.jenis_rekom" class="small text-muted">{{ d.jenis_rekom }}</span></td>
                        <td class="num">{{ nilai(d.bezetting) }}</td><td class="num">{{ nilai(d.rekom_bkn) }}</td><td class="num">{{ nilai(d.penetapan_menpan) }}</td>
                        <td class="num" :class="selisih(d) < 0 ? 'text-kurang' : selisih(d) > 0 ? 'text-lebih' : ''">{{ d.rekom_bkn === null || d.penetapan_menpan === null ? '-' : selisih(d) }}</td>
                        <td class="text-end"><Link :href="`/rekomendasi-jf/${d.id}`" class="btn btn-sm btn-light">Rincian <i class="fa fa-angle-right"></i></Link></td>
                    </tr>
                    <tr v-if="!datas.data.length"><td colspan="8" class="text-center text-muted py-4">Belum ada data. <template v-if="bolehImpor">Impor berkas Excel rekap di atas.</template></td></tr>
                </tbody>
            </table>
        </div>
        <div class="card-body pt-2"><Pagination :links="datas.links" /></div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { Head, Link, router } from '@inertiajs/vue3';
import StatCard from '../../Components/StatCard.vue';
import Pagination from '../../Components/Pagination.vue';
import { escapeHtml } from '../../ai';
import { num, waktu } from '../../utils';

const props = defineProps({
    datas: Object, filters: Object, jabatanOptions: Array, statusOptions: Array, ringkasan: Object,
    belumCocok: { type: Array, default: () => [] }, refOptions: { type: Array, default: () => [] }, imporTerakhir: Object, bolehImpor: Boolean, batasUnggahMb: Number,
});

const f = reactive({ q: '', jabatan_pokok: '', status_rekom: '', status_menpan: '', ...props.filters });
const go = () => router.get('/rekomendasi-jf', Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true, replace: true });
const nilai = (v) => (v === null || v === undefined ? '-' : num(v));
const selisih = (d) => (d.penetapan_menpan ?? 0) - (d.rekom_bkn ?? 0);

const berkas = ref(null);
const proses = ref(false);

const pilihBerkas = async (e) => {
    const file = e.target.files[0];
    e.target.value = '';
    if (!file) return;
    // cek batas unggah server lebih dulu agar pesannya jelas (bukan galat umum)
    if (props.batasUnggahMb && file.size > props.batasUnggahMb * 1048576) {
        return Swal.fire({
            icon: 'error', title: 'Berkas melebihi batas unggah server',
            html: `<div class="small text-start">Berkas <b>${escapeHtml(file.name)}</b> berukuran ${(file.size / 1048576).toFixed(1)} MB, sedangkan batas unggah server saat ini <b>${props.batasUnggahMb} MB</b>.<br>Minta administrator server menaikkan <code>upload_max_filesize</code> dan <code>post_max_size</code> (php.ini) serta <code>client_max_body_size</code> (Nginx). Lihat <code>docs/DEPLOY.md</code>.</div>`,
        });
    }
    proses.value = true;
    const body = new FormData();
    body.append('file', file);
    let p;
    try {
        p = (await axios.post('/rekomendasi-jf/pratinjau', body)).data;
    } catch (err) {
        proses.value = false;
        return Swal.fire({ icon: 'error', title: 'Berkas tidak dapat dibaca', text: err.response?.data?.message || err.response?.data?.errors?.file?.[0] || 'Periksa berkas lalu coba lagi.' });
    }
    proses.value = false;

    const r = p.ringkas;
    const jab = Object.entries(r.per_jabatan).map(([k, v]) => `${escapeHtml(k)}: ${v}`).join(' · ');
    const tak = r.jumlah_tidak_cocok ? `<div class="text-warning mt-2"><b>${r.jumlah_tidak_cocok} instansi belum cocok</b> dengan referensi BKN (dapat dipetakan setelah impor): ${r.instansi_tidak_cocok.slice(0, 5).map(escapeHtml).join('; ')}${r.jumlah_tidak_cocok > 5 ? '…' : ''}</div>` : '';
    const per = p.peringatan.length ? `<details class="mt-2"><summary>${r.jumlah_peringatan} peringatan data</summary><ul class="mb-0 ps-3">${p.peringatan.map((x) => `<li>${escapeHtml(x)}</li>`).join('')}</ul></details>` : '';
    const ok = await Swal.fire({
        title: 'Pratinjau impor',
        html: `<div class="small text-start"><p class="mb-1"><b>${escapeHtml(p.nama_berkas)}</b></p>
            <p class="mb-1">${r.baris} baris dari ${r.instansi} instansi (${jab}). <b>${r.baru} baru</b>, <b>${r.diperbarui} diperbarui</b>, ${r.jumlah_surat} surat terbaca.</p>${tak}${per}
            <div class="text-muted mt-2">Lowongan dihitung ulang dari bezetting − rekomendasi BKN. Belum ada yang tersimpan.</div></div>`,
        width: 640, showCancelButton: true, confirmButtonText: 'Simpan impor', cancelButtonText: 'Batal',
    });
    if (ok.isConfirmed) router.post('/rekomendasi-jf/impor', { token: p.token, nama_berkas: p.nama_berkas }, { preserveScroll: true });
};

const pilihan = reactive({});
const idRef = (kunci) => props.refOptions.find((r) => r.nama === pilihan[kunci])?.id;
const petakan = (b) => router.post('/rekomendasi-jf/petakan-instansi', { nama_normal: b.nama_instansi_normal, ref_instansi_id: idRef(b.nama_instansi_normal) }, { preserveScroll: true });
</script>
