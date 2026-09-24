<template>
    <Head :title="data.nomor" />

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <Link href="/usulan" class="small"><i class="fa fa-angle-left"></i> Daftar usulan</Link>
            <div class="page-title">{{ data.perihal }}</div>
            <div class="page-subtitle">
                {{ data.nomor }} · {{ data.instansi.nama }} · Tahun {{ data.tahun }} · {{ data.jenis_asn.toUpperCase() }}
                <span class="badge ms-1" :class="'bg-' + data.status_color">{{ data.status_label }}</span>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a v-if="data.surat_pengantar" :href="`/usulan/${data.id}/berkas/surat-pengantar`" class="btn btn-light btn-sm"><i class="fa fa-file-pdf-o me-1"></i> Surat pengantar</a>
            <template v-if="editable">
                <Link :href="`/usulan/${data.id}/edit`" class="btn btn-warning btn-sm"><i class="fa fa-pencil me-1"></i> Ubah</Link>
                <button v-if="data.status === 'draft'" class="btn btn-outline-danger btn-sm" @click="hapus"><i class="fa fa-trash"></i></button>
            </template>
        </div>
    </div>

    <!-- tahapan alur -->
    <div class="card mb-3">
        <div class="card-body">
            <div v-if="data.status === 'ditolak'" class="alert alert-danger mb-3"><i class="fa fa-ban me-1"></i> Usulan ditolak. {{ data.catatan_terakhir }}</div>
            <div v-else-if="data.status === 'dikembalikan'" class="alert alert-warning mb-3"><i class="fa fa-undo me-1"></i> Dikembalikan untuk perbaikan: <b>{{ data.catatan_terakhir }}</b></div>
            <div class="stepper">
                <div v-for="(s, i) in steps" :key="s" class="step" :data-no="i + 1" :class="{ done: step > i + 1 || step === 6, current: step === i + 1 && step !== 6 }">{{ s }}</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-9">
            <!-- rincian jabatan -->
            <div class="card mb-3">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span>Rincian Jabatan yang Diusulkan</span>
                    <div v-if="editable" class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary" @click="tarikAbk"><i class="fa fa-download me-1"></i> Tarik dari ABK</button>
                        <button class="btn btn-sm btn-primary" :disabled="!dirty" @click="simpanRincian"><i class="fa fa-save me-1"></i> Simpan rincian</button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="col-unit">Unit Kerja</th><th class="col-jabatan">Jabatan</th>
                                <th class="num" title="Kebutuhan hasil ABK saat usulan disusun">ABK</th>
                                <th class="num">Existing</th><th class="num">Pensiun ≤5th</th>
                                <th class="num">Diusulkan</th>
                                <th class="num">Rekomendasi BKN</th>
                                <th class="num">Ditetapkan</th>
                                <th>Kualifikasi / Ket.</th><th v-if="editable"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="d in rows" :key="d.id">
                                <td class="small">{{ d.unit_kerja?.nama }}</td>
                                <td>{{ d.jabatan?.nama }}</td>
                                <td class="num">{{ num(d.kebutuhan_abk) }}</td>
                                <td class="num">{{ num(d.existing) }}</td>
                                <td class="num">{{ num(d.proyeksi_pensiun) }}</td>
                                <td class="num">
                                    <input v-if="editable" v-model.number="d.jumlah_usul" type="number" min="0" class="form-control form-control-sm text-end" style="width: 80px" :class="{ 'border-warning': d.jumlah_usul > maxWajar(d) }" :title="d.jumlah_usul > maxWajar(d) ? 'Melebihi kekurangan + proyeksi pensiun' : ''" @input="dirty = true">
                                    <span v-else>{{ num(d.jumlah_usul) }}</span>
                                </td>
                                <td class="num">
                                    <input v-if="can('rekomendasi')" v-model.number="jumlah[d.id]" type="number" min="0" :max="d.jumlah_usul" class="form-control form-control-sm text-end" style="width: 80px" :class="{ 'is-invalid': errors['details.' + d.id] }">
                                    <span v-else>{{ d.jumlah_rekomendasi ?? '-' }}</span>
                                </td>
                                <td class="num">
                                    <input v-if="can('tetapkan')" v-model.number="jumlah[d.id]" type="number" min="0" :max="d.jumlah_rekomendasi" class="form-control form-control-sm text-end" style="width: 80px" :class="{ 'is-invalid': errors['details.' + d.id] }">
                                    <span v-else class="fw-semibold">{{ d.jumlah_ditetapkan ?? '-' }}</span>
                                </td>
                                <td class="small">
                                    <template v-if="editable">
                                        <input v-model="d.kualifikasi_pendidikan" class="form-control form-control-sm mb-1" placeholder="Kualifikasi" @input="dirty = true">
                                        <input v-model="d.keterangan" class="form-control form-control-sm" placeholder="Keterangan" @input="dirty = true">
                                    </template>
                                    <template v-else>{{ d.kualifikasi_pendidikan }}<div class="text-muted">{{ d.keterangan }}</div></template>
                                </td>
                                <td v-if="editable"><button class="btn btn-sm btn-light text-danger" @click="hapusRincian(d)"><i class="fa fa-times"></i></button></td>
                            </tr>
                            <tr v-if="!rows.length"><td colspan="10" class="text-center text-muted py-4">Belum ada rincian. Gunakan "Tarik dari ABK" atau tambah manual.</td></tr>
                        </tbody>
                        <tfoot v-if="rows.length">
                            <tr class="fw-semibold">
                                <td colspan="2">Total</td>
                                <td class="num">{{ num(sum('kebutuhan_abk')) }}</td>
                                <td class="num">{{ num(sum('existing')) }}</td>
                                <td class="num">{{ num(sum('proyeksi_pensiun')) }}</td>
                                <td class="num">{{ num(sum('jumlah_usul')) }}</td>
                                <td class="num">{{ can('rekomendasi') ? num(sumInput) : num(sum('jumlah_rekomendasi')) }}</td>
                                <td class="num">{{ can('tetapkan') ? num(sumInput) : num(sum('jumlah_ditetapkan')) }}</td>
                                <td :colspan="editable ? 2 : 1"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- tambah rincian manual -->
                <div v-if="editable" class="card-body border-top">
                    <form class="row g-2 align-items-end" @submit.prevent="tambahRincian">
                        <div class="col-md-4">
                            <label class="form-label small">Unit kerja</label>
                            <select v-model="baru.unit_kerja_id" class="form-select form-select-sm" :class="{ 'is-invalid': baru.errors.unit_kerja_id }">
                                <option value="">- pilih -</option>
                                <option v-for="u in unitOptions" :key="u.id" :value="u.id">{{ '— '.repeat(u.depth) }}{{ u.nama }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Jabatan</label>
                            <select v-model="baru.jabatan_id" class="form-select form-select-sm" :class="{ 'is-invalid': baru.errors.jabatan_id }">
                                <option value="">- pilih -</option>
                                <option v-for="j in jabatanOptions" :key="j.id" :value="j.id">{{ j.nama }}</option>
                            </select>
                            <div class="invalid-feedback">{{ baru.errors.jabatan_id }}</div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Jumlah</label>
                            <input v-model.number="baru.jumlah_usul" type="number" min="1" class="form-control form-control-sm" :class="{ 'is-invalid': baru.errors.jumlah_usul }">
                        </div>
                        <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100" :disabled="baru.processing"><i class="fa fa-plus"></i> Tambah</button></div>
                    </form>
                </div>
            </div>

            <!-- form penetapan -->
            <div v-if="can('tetapkan')" class="card mb-3 border-success">
                <div class="card-header text-success"><i class="fa fa-gavel me-1"></i> Data Penetapan Kebutuhan</div>
                <div class="card-body row g-3">
                    <div class="col-md-5">
                        <label class="form-label">Nomor SK / Keputusan</label>
                        <input v-model="sk.nomor_sk" class="form-control" :class="{ 'is-invalid': errors.nomor_sk }">
                        <div class="invalid-feedback">{{ errors.nomor_sk }}</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tanggal SK</label>
                        <input v-model="sk.tanggal_sk" type="date" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Berkas SK (PDF)</label>
                        <input type="file" accept="application/pdf" class="form-control" @input="sk.file_sk = $event.target.files[0]">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Keterangan</label>
                        <textarea v-model="sk.keterangan" rows="2" class="form-control"></textarea>
                    </div>
                </div>
            </div>

            <div v-if="data.penetapan" class="card mb-3 border-success">
                <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <div class="fw-semibold text-success"><i class="fa fa-check-circle me-1"></i> Ditetapkan: {{ data.penetapan.nomor_sk }}</div>
                        <div class="small text-muted">{{ tanggal(data.penetapan.tanggal_sk) }} · {{ num(data.penetapan.total_ditetapkan) }} formasi · oleh {{ data.penetapan.penetap?.name }}</div>
                    </div>
                    <div class="d-flex gap-2">
                        <a v-if="data.penetapan.file_sk" :href="`/usulan/${data.id}/berkas/sk`" class="btn btn-sm btn-light"><i class="fa fa-file-pdf-o"></i> SK</a>
                        <Link :href="`/penetapan/${data.penetapan.id}`" class="btn btn-sm btn-success">Lampiran penetapan</Link>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3">
            <div class="card mb-3" v-if="actions.length">
                <div class="card-header">Tindakan</div>
                <div class="card-body d-grid gap-2">
                    <button v-for="a in actions" :key="a.aksi" class="btn btn-sm" :class="btnClass(a.aksi)" :disabled="busy" @click="jalankan(a)">
                        <i class="fa me-1" :class="btnIcon(a.aksi)"></i> {{ a.label }}
                    </button>
                    <div v-if="can('rekomendasi') || can('tetapkan')" class="form-text">Isi kolom jumlah pada tabel rincian sebelum menekan tombol. Nilai tidak boleh melebihi tahap sebelumnya.</div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Informasi</div>
                <div class="card-body small">
                    <div class="mb-1"><span class="text-muted">Dibuat oleh:</span> {{ data.pembuat?.name || '-' }}</div>
                    <div class="mb-1"><span class="text-muted">Diajukan:</span> {{ waktu(data.diajukan_at) }}</div>
                    <div class="mb-1"><span class="text-muted">Ditetapkan:</span> {{ waktu(data.ditetapkan_at) }}</div>
                    <div v-if="data.keterangan" class="mt-2">{{ data.keterangan }}</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Riwayat</div>
                <div class="card-body">
                    <ul class="timeline">
                        <li v-for="log in data.logs" :key="log.id">
                            <div class="small fw-semibold">{{ label(log.ke_status) }}</div>
                            <div class="small text-muted">{{ log.user?.name }} · {{ waktu(log.created_at) }}</div>
                            <div v-if="log.catatan" class="small mt-1 fst-italic">"{{ log.catatan }}"</div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { num, tanggal, waktu } from '../../utils';

const props = defineProps({ data: Object, step: Number, actions: Array, editable: Boolean, unitOptions: Array, jabatanOptions: Array });

const steps = ['Penyusunan', 'Diajukan', 'Verifikasi BKN', 'Pertimbangan Teknis', 'Validasi KemenPANRB', 'Ditetapkan'];
const STATUS = {
    draft: 'Draft', diajukan: 'Diajukan', verifikasi_bkn: 'Verifikasi BKN', pertimbangan_teknis: 'Pertimbangan Teknis BKN',
    validasi_kemenpan: 'Validasi KemenPANRB', ditetapkan: 'Ditetapkan', dikembalikan: 'Dikembalikan', ditolak: 'Ditolak',
};
const label = (s) => STATUS[s] || s;

const rows = ref(props.data.details.map((d) => ({ ...d })));
const dirty = ref(false);
const busy = ref(false);
const errors = computed(() => usePage().props.errors || {});

const can = (aksi) => props.actions.some((a) => a.aksi === aksi);
const sum = (key) => rows.value.reduce((t, d) => t + (Number(d[key]) || 0), 0);
const maxWajar = (d) => Math.max(0, d.kebutuhan_abk - d.existing + d.proyeksi_pensiun);

// input jumlah untuk rekomendasi (default = diusulkan) atau penetapan (default = rekomendasi)
const jumlah = reactive(Object.fromEntries(props.data.details.map((d) => [d.id, can('tetapkan') ? d.jumlah_rekomendasi : d.jumlah_usul])));
const sumInput = computed(() => Object.values(jumlah).reduce((t, v) => t + (Number(v) || 0), 0));

// sinkronkan state lokal setiap kali server mengirim data terbaru
watch(() => props.data.details, (details) => {
    rows.value = details.map((d) => ({ ...d }));
    dirty.value = false;
    for (const d of details) jumlah[d.id] = can('tetapkan') ? d.jumlah_rekomendasi : d.jumlah_usul;
});

const sk = reactive({ nomor_sk: '', tanggal_sk: new Date().toISOString().slice(0, 10), keterangan: '', file_sk: null });

const baru = useForm({ unit_kerja_id: '', jabatan_id: '', jumlah_usul: 1 });
const tambahRincian = () => baru.post(`/usulan/${props.data.id}/details`, { preserveScroll: true, onSuccess: () => baru.reset() });

const simpanRincian = () => router.put(`/usulan/${props.data.id}/details`, {
    details: rows.value.map((d) => ({ id: d.id, jumlah_usul: d.jumlah_usul || 0, kualifikasi_pendidikan: d.kualifikasi_pendidikan, keterangan: d.keterangan })),
}, { preserveScroll: true, onSuccess: () => (dirty.value = false) });

const tarikAbk = async () => {
    const ok = await Swal.fire({ icon: 'question', title: 'Tarik rincian dari ABK?', text: 'Jabatan yang kekurangan pegawai akan ditambahkan/diperbarui dengan jumlah = kekurangan + proyeksi pensiun 5 tahun.', showCancelButton: true, confirmButtonText: 'Ya, tarik', cancelButtonText: 'Batal' });
    if (ok.isConfirmed) router.post(`/usulan/${props.data.id}/tarik-abk`, {}, { preserveScroll: true });
};

const hapusRincian = async (d) => {
    const ok = await Swal.fire({ icon: 'warning', title: 'Hapus rincian?', text: d.jabatan?.nama, showCancelButton: true, confirmButtonText: 'Hapus', cancelButtonText: 'Batal' });
    if (ok.isConfirmed) router.delete(`/usulan/${props.data.id}/details/${d.id}`, { preserveScroll: true });
};

const hapus = async () => {
    const ok = await Swal.fire({ icon: 'warning', title: 'Hapus usulan ini?', showCancelButton: true, confirmButtonText: 'Hapus', cancelButtonText: 'Batal' });
    if (ok.isConfirmed) router.delete(`/usulan/${props.data.id}`);
};

const btnClass = (a) => ({ tolak: 'btn-danger', kembalikan: 'btn-outline-warning', tetapkan: 'btn-success', ajukan: 'btn-primary' }[a] || 'btn-primary');
const btnIcon = (a) => ({ ajukan: 'fa-paper-plane', mulai_verifikasi: 'fa-search', rekomendasi: 'fa-check-square-o', mulai_validasi: 'fa-search', tetapkan: 'fa-gavel', kembalikan: 'fa-undo', tolak: 'fa-ban' }[a]);

const jalankan = async (a) => {
    if (a.aksi === 'ajukan' && dirty.value) {
        return Swal.fire({ icon: 'info', title: 'Simpan rincian dulu', text: 'Ada perubahan rincian yang belum disimpan.' });
    }

    const perluCatatan = ['kembalikan', 'tolak'].includes(a.aksi);
    const res = await Swal.fire({
        icon: perluCatatan ? 'warning' : 'question',
        title: a.label + '?',
        input: 'textarea',
        inputLabel: perluCatatan ? 'Catatan (wajib)' : 'Catatan (opsional)',
        inputValidator: (v) => (perluCatatan && !v ? 'Catatan wajib diisi' : undefined),
        showCancelButton: true, confirmButtonText: 'Ya, lanjutkan', cancelButtonText: 'Batal',
    });
    if (!res.isConfirmed) return;

    const payload = { aksi: a.aksi, catatan: res.value || '' };
    if (['rekomendasi', 'tetapkan'].includes(a.aksi)) payload.details = { ...jumlah };
    if (a.aksi === 'tetapkan') Object.assign(payload, sk);

    busy.value = true;
    router.post(`/usulan/${props.data.id}/aksi`, payload, {
        preserveScroll: true,
        forceFormData: a.aksi === 'tetapkan',
        onFinish: () => (busy.value = false),
    });
};
</script>
