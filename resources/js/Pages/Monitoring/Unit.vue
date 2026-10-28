<template>
    <Head :title="unit.nama" />

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <Link :href="`/monitoring/instansi/${unit.instansi_id}`" class="small"><i class="fa fa-angle-left"></i> {{ unit.instansi?.nama }}</Link>
            <div class="page-title">{{ unit.nama }}</div>
            <div class="page-subtitle">Rincian per jabatan<span v-if="unit.parent"> · induk: {{ unit.parent.nama }}</span></div>
        </div>
        <LiveBadge :updated-at="updatedAt" @refresh="refresh" />
    </div>

    <MonitoringFilter :filters="filters" :jenis-options="jenisOptions" :url="`/monitoring/unit/${unit.id}`" :extra="{ sub: filters.sub ? 1 : 0 }">
        <div class="form-check form-switch ms-1">
            <input class="form-check-input" type="checkbox" id="sub" :checked="filters.sub" @change="toggleSub">
            <label class="form-check-label small" for="sub">Termasuk sub-unit</label>
        </div>
    </MonitoringFilter>

    <div v-if="summary.posisi_sementara || summary.tanpa_abk" class="alert alert-light border small py-2">
        <i class="fa fa-info-circle me-1"></i>
        <span v-if="summary.posisi_sementara">{{ summary.posisi_sementara }} posisi memakai <b>kebutuhan sementara</b> karena belum memiliki ABK final. </span>
        <span v-if="summary.tanpa_abk">{{ summary.tanpa_abk }} posisi berpegawai belum punya dasar kebutuhan<template v-if="bolehAtur">; isi kebutuhan sementara agar tetap terhitung</template>.</span>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><StatCard label="Kebutuhan" :value="summary.kebutuhan" icon="fa-bullseye" color="#2a78d6" /></div>
        <div class="col-6 col-lg-3"><StatCard label="Existing" :value="summary.existing" icon="fa-users" color="#eb6834" :hint="'Pemenuhan ' + pct(summary.persentase)" /></div>
        <div class="col-6 col-lg-3"><StatCard label="Kurang" :value="summary.kurang" icon="fa-arrow-down" color="#d03b3b" value-class="text-kurang" /></div>
        <div class="col-6 col-lg-3"><StatCard label="Lebih (gemuk)" :value="summary.lebih" icon="fa-arrow-up" color="#b77900" value-class="text-lebih" /></div>
    </div>

    <div class="card">
        <div v-if="bolehAtur" class="card-header d-flex justify-content-between align-items-center">
            <span class="small text-muted">Kebutuhan dari ABK final; posisi tanpa ABK dapat diberi kebutuhan sementara.</span>
            <button class="btn btn-sm btn-outline-primary" @click="aturSementara()"><i class="fa fa-plus me-1"></i> Kebutuhan sementara</button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th><th v-if="filters.sub" class="col-unit">Unit Kerja</th><th class="col-jabatan">Jabatan</th><th>Jenis</th><th class="num">Kebutuhan</th><th class="num">Existing</th>
                        <th class="num">Selisih</th><th class="num">Pensiun ≤5th</th><th class="num">Formasi ditetapkan</th><th>Pemenuhan</th><th>Kondisi</th><th v-if="bolehAtur"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(p, i) in positions" :key="p.unit_kerja_id + '-' + p.jabatan_id">
                        <td>{{ i + 1 }}</td>
                        <td v-if="filters.sub" class="small">{{ p.unit_nama }}</td>
                        <td>{{ p.jabatan_nama }}</td>
                        <td class="small">{{ p.jenis_label }}</td>
                        <td class="num">
                            {{ num(p.kebutuhan) }}
                            <span v-if="p.sumber === 'sementara'" class="badge bg-warning text-dark ms-1" :title="'Kebutuhan sementara' + (sementaraDari(p)?.dasar ? ': ' + sementaraDari(p).dasar : '')">sementara</span>
                        </td>
                        <td class="num">{{ num(p.existing) }}</td>
                        <td class="num fw-semibold" :class="p.selisih < 0 ? 'text-kurang' : (p.selisih > 0 ? 'text-lebih' : '')">{{ p.selisih > 0 ? '+' : '' }}{{ num(p.selisih) }}</td>
                        <td class="num">{{ num(p.pensiun) }}</td>
                        <td class="num">{{ num(p.formasi) }}</td>
                        <td class="small">{{ pct(p.persentase) }}</td>
                        <td><Kondisi :status="p.status" /></td>
                        <td v-if="bolehAtur">
                            <button v-if="p.sumber !== 'abk'" class="btn btn-sm btn-light" title="Atur kebutuhan sementara" @click="aturSementara(p)"><i class="fa fa-pencil"></i></button>
                        </td>
                    </tr>
                    <tr v-if="!positions.length"><td colspan="12" class="text-center text-muted py-4">Tidak ada posisi jabatan.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import StatCard from '../../Components/StatCard.vue';
import Kondisi from '../../Components/Kondisi.vue';
import LiveBadge from '../../Components/LiveBadge.vue';
import MonitoringFilter from '../../Components/MonitoringFilter.vue';
import { num, pct } from '../../utils';
import { escapeHtml } from '../../ai';
import Swal from 'sweetalert2';

const props = defineProps({
    unit: Object, filters: Object, jenisOptions: Array, summary: Object, positions: Array, updatedAt: String,
    sementara: { type: Object, default: () => ({}) }, bolehAtur: Boolean, jabatanOptions: { type: Array, default: () => [] },
});

const sementaraDari = (p) => props.sementara[p.unit_kerja_id + '-' + p.jabatan_id];
// isi/ubah kebutuhan sementara; jumlah 0 menghapusnya
const aturSementara = async (p = null) => {
    const ada = p ? sementaraDari(p) : null;
    const opsiJabatan = props.jabatanOptions.map((j) => `<option value="${j.id}">${escapeHtml(j.nama)}</option>`).join('');
    const res = await Swal.fire({
        title: 'Kebutuhan sementara',
        html: `<div class="text-start small">
            <p class="text-muted">Dipakai selama posisi belum memiliki ABK final, lalu otomatis digantikan hasil ABK.</p>
            <label class="form-label">Jabatan</label>
            ${p ? `<div class="fw-semibold mb-2">${escapeHtml(p.jabatan_nama)}${props.filters.sub ? ' · ' + escapeHtml(p.unit_nama) : ''}</div>`
                : `<select id="ks-jabatan" class="form-select form-select-sm mb-2"><option value="">- pilih -</option>${opsiJabatan}</select>`}
            <label class="form-label">Jumlah kebutuhan (0 = hapus)</label>
            <input id="ks-jumlah" type="number" min="0" class="form-control form-control-sm mb-2" value="${ada?.jumlah ?? p?.existing ?? ''}">
            <label class="form-label">Dasar / keterangan</label>
            <input id="ks-dasar" maxlength="255" class="form-control form-control-sm" placeholder="Contoh: peta jabatan 2024, kesepakatan pimpinan" value="${escapeHtml(ada?.dasar ?? '')}">
        </div>`,
        showCancelButton: true, confirmButtonText: 'Simpan', cancelButtonText: 'Batal',
        preConfirm: () => {
            const jabatan = p ? p.jabatan_id : document.getElementById('ks-jabatan').value;
            const jumlah = document.getElementById('ks-jumlah').value;
            if (!jabatan) return Swal.showValidationMessage('Pilih jabatan');
            if (jumlah === '' || Number(jumlah) < 0) return Swal.showValidationMessage('Isi jumlah kebutuhan');
            return { unit_kerja_id: p ? p.unit_kerja_id : props.unit.id, jabatan_id: jabatan, jumlah: Number(jumlah), dasar: document.getElementById('ks-dasar').value };
        },
    });
    if (res.isConfirmed) router.post('/kebutuhan-sementara', res.value, { preserveScroll: true });
};

const LIVE = ['summary', 'positions', 'updatedAt'];
usePoll(30000, { only: LIVE });
const refresh = () => router.reload({ only: LIVE });
const toggleSub = (e) => router.get(`/monitoring/unit/${props.unit.id}`, { ...props.filters, sub: e.target.checked ? 1 : 0 }, { preserveState: true, replace: true });
</script>
