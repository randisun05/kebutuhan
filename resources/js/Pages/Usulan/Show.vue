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
                                <th class="num" title="Kebutuhan (ABK final atau kebutuhan sementara) saat rincian disusun">Kebutuhan</th>
                                <th class="num">Existing</th><th class="num">Pensiun ≤5th</th>
                                <th v-if="pemeriksaan" class="num" title="Saran sistem dari kondisi terkini: kebutuhan − existing + pensiun − formasi belum terisi − usulan lain yang diproses">Saran sistem</th>
                                <th>Prioritas</th>
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
                                <td class="num">
                                    {{ num(d.kebutuhan_abk) }}
                                    <span v-if="d.sumber_kebutuhan === 'sementara'" class="badge bg-warning text-dark" title="Kebutuhan sementara (belum ada ABK final)">sem.</span>
                                </td>
                                <td class="num">{{ num(d.existing) }}</td>
                                <td class="num">{{ num(d.proyeksi_pensiun) }}</td>
                                <td v-if="pemeriksaan" class="num" :title="rincianSaran(d)">
                                    <template v-if="periksa(d)?.sumber">{{ num(periksa(d).saran) }}<i v-if="periksa(d).redistribusi_masuk" class="fa fa-random text-info ms-1" title="Ada pegawai yang dapat dipindahkan dari unit gemuk"></i></template>
                                    <span v-else class="badge bg-light text-muted border" title="Belum ada ABK final maupun kebutuhan sementara">tanpa dasar</span>
                                </td>
                                <td>
                                    <select v-if="editable" v-model.number="d.prioritas" class="form-select form-select-sm" style="width: 95px" @change="dirty = true">
                                        <option v-for="(l, k) in prioritasOptions" :key="k" :value="Number(k)">{{ l }}</option>
                                    </select>
                                    <span v-else class="badge" :class="{ 1: 'bg-danger', 2: 'bg-warning text-dark', 3: 'bg-secondary' }[d.prioritas]">{{ prioritasOptions[d.prioritas] }}</span>
                                </td>
                                <td class="num">
                                    <input v-if="editable" v-model.number="d.jumlah_usul" type="number" min="0" class="form-control form-control-sm text-end" style="width: 80px" :class="{ 'border-warning': melebihi(d, d.jumlah_usul) }" :title="melebihi(d, d.jumlah_usul) ? 'Melebihi saran sistem' : ''" @input="dirty = true">
                                    <span v-else>{{ num(d.jumlah_usul) }}</span>
                                    <div v-if="melebihi(d, d.jumlah_usul)" class="small text-warning-emphasis"><i class="fa fa-exclamation-triangle"></i> +{{ d.jumlah_usul - periksa(d).saran }}</div>
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
                            <tr v-if="!rows.length"><td colspan="12" class="text-center text-muted py-4">Belum ada rincian. Gunakan "Tarik dari ABK" atau tambah manual.</td></tr>
                        </tbody>
                        <tfoot v-if="rows.length">
                            <tr class="fw-semibold">
                                <td colspan="2">Total</td>
                                <td class="num">{{ num(sum('kebutuhan_abk')) }}</td>
                                <td class="num">{{ num(sum('existing')) }}</td>
                                <td class="num">{{ num(sum('proyeksi_pensiun')) }}</td>
                                <td v-if="pemeriksaan" class="num">{{ num(totalSaran) }}</td>
                                <td></td>
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

            <div v-if="pemeriksaan" class="card mb-3" :class="pemeriksaan.jumlah_melebihi || pemeriksaan.tanpa_dasar ? 'border-warning' : ''">
                <div class="card-header"><i class="fa fa-balance-scale me-1"></i> Pemeriksaan terhadap kebutuhan</div>
                <div class="card-body small">
                    <div v-if="pemeriksaan.jumlah_melebihi" class="text-warning-emphasis mb-1"><i class="fa fa-exclamation-triangle"></i> {{ pemeriksaan.jumlah_melebihi }} rincian melebihi saran sistem (total +{{ num(pemeriksaan.total_lebih) }}).</div>
                    <div v-if="pemeriksaan.tanpa_dasar" class="text-warning-emphasis mb-1"><i class="fa fa-question-circle"></i> {{ pemeriksaan.tanpa_dasar }} rincian tanpa ABK final maupun kebutuhan sementara.</div>
                    <div v-if="!pemeriksaan.jumlah_melebihi && !pemeriksaan.tanpa_dasar && rows.length" class="text-success mb-1"><i class="fa fa-check"></i> Semua rincian sesuai saran sistem.</div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between"><span>Kesiapan ABK instansi</span><b>{{ pemeriksaan.kesiapan.persen ?? '-' }}%</b></div>
                    <div class="progress progress-thin my-1"><div class="progress-bar" :class="(pemeriksaan.kesiapan.persen ?? 0) >= 80 ? 'bg-success' : 'bg-warning'" :style="{ width: (pemeriksaan.kesiapan.persen ?? 0) + '%' }"></div></div>
                    <div class="text-muted">
                        {{ pemeriksaan.kesiapan.mutakhir }} posisi ABK mutakhir (≥ {{ pemeriksaan.kesiapan.min_tahun }}),
                        {{ pemeriksaan.kesiapan.lama }} ABK lama, {{ pemeriksaan.kesiapan.sementara }} kebutuhan sementara,
                        {{ pemeriksaan.kesiapan.belum }} belum ada dasar — dari {{ pemeriksaan.kesiapan.total }} posisi.
                    </div>
                    <div class="text-muted mt-1">Hanya informasi; tidak menghalangi pengajuan maupun verifikasi.</div>
                </div>
            </div>

            <div v-if="$page.props.ai" class="card mb-3 border-info">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fa fa-magic text-info me-1"></i> Justifikasi AI</span>
                    <button class="btn btn-sm btn-info text-white" :disabled="ai.proses || !rows.length" @click="justifikasi(!!ai.html)">
                        <i class="fa" :class="ai.proses ? 'fa-spinner fa-spin' : 'fa-magic'"></i> {{ ai.html ? 'Buat ulang' : 'Buat' }}
                    </button>
                </div>
                <div class="card-body small">
                    <div v-if="ai.error" class="text-danger">{{ ai.error }}</div>
                    <template v-else-if="ai.html">
                        <div class="ai-hasil" v-html="ai.html"></div>
                        <div class="text-muted mt-2">{{ infoRiwayat(ai.riwayat) }}</div>
                        <button class="btn btn-sm btn-light mt-1" @click="salin"><i class="fa fa-copy me-1"></i> Salin teks</button>
                    </template>
                    <div v-else class="text-muted">Draf alasan/justifikasi per jabatan dari data kebutuhan, existing, pensiun, dan pemeriksaan di halaman ini, untuk surat pengantar.</div>
                </div>
            </div>

            <div class="card mb-3" v-if="anggaran.adaData">
                <div class="card-header">Estimasi belanja pegawai / tahun</div>
                <div class="card-body small">
                    <div class="d-flex justify-content-between"><span>Diusulkan</span><b>Rp {{ num(anggaran.usul) }}</b></div>
                    <div class="d-flex justify-content-between" v-if="anggaran.rekomendasi !== null"><span>Rekomendasi BKN</span><b>Rp {{ num(anggaran.rekomendasi) }}</b></div>
                    <div class="d-flex justify-content-between" v-if="anggaran.ditetapkan !== null"><span>Ditetapkan</span><b>Rp {{ num(anggaran.ditetapkan) }}</b></div>
                    <div class="text-muted mt-1">Dari estimasi belanja per jabatan pada referensi; bahan pertimbangan anggaran.</div>
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
import axios from 'axios';
import { num, tanggal, waktu } from '../../utils';
import { infoRiwayat } from '../../ai';

const props = defineProps({ data: Object, step: Number, actions: Array, editable: Boolean, unitOptions: Array, jabatanOptions: Array, prioritasOptions: Object, pemeriksaan: Object });

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
// saran sistem dari kondisi terkini (SaranUsulanService); hanya penanda, tidak membatasi isian
const periksa = (d) => props.pemeriksaan?.rincian?.[d.id];
const melebihi = (d, nilai) => !!periksa(d)?.sumber && Number(nilai) > periksa(d).saran;
const totalSaran = computed(() => rows.value.reduce((t, d) => t + (periksa(d)?.saran || 0), 0));
const rincianSaran = (d) => {
    const p = periksa(d);
    if (!p) return '';
    return `Kebutuhan ${p.kebutuhan}${p.sumber === 'sementara' ? ' (sementara)' : ''} − existing ${p.existing} + pensiun ${p.pensiun}`
        + ` − formasi belum terisi ${p.formasi} − usulan lain diproses ${p.usulan_proses} = ${p.saran}`
        + (p.redistribusi_masuk ? `. Bila redistribusi dijalankan (${p.redistribusi_masuk} dari ${p.redistribusi_dari}): ${p.saran_redistribusi}` : '');
};

const ai = reactive({ proses: false, html: '', teks: '', riwayat: null, error: '' });
const justifikasi = async (baru = false) => {
    Object.assign(ai, { proses: true, error: '' });
    try {
        const { data } = await axios.post(`/ai/usulan/${props.data.id}`, { baru });
        Object.assign(ai, { html: data.html, teks: data.teks, riwayat: data.riwayat });
    } catch (e) {
        ai.error = e.response?.data?.message || 'Justifikasi AI gagal. Silakan coba lagi.';
    } finally {
        ai.proses = false;
    }
};
const salin = async () => {
    try {
        await navigator.clipboard.writeText(ai.teks);
        Swal.fire({ icon: 'success', title: 'Teks disalin', timer: 1200, showConfirmButton: false });
    } catch {
        Swal.fire({ icon: 'info', title: 'Salin manual', text: 'Peramban tidak mengizinkan salin otomatis; blok teks lalu salin.' });
    }
};

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

const baru = useForm({ unit_kerja_id: '', jabatan_id: '', jumlah_usul: 1, prioritas: 2 });

const anggaran = computed(() => {
    const biaya = (d) => d.jabatan?.estimasi_biaya_tahunan || 0;
    const tot = (key) => rows.value.some((d) => d[key] !== null) ? rows.value.reduce((t, d) => t + (d[key] || 0) * biaya(d), 0) : null;
    return { adaData: rows.value.some((d) => biaya(d)), usul: tot('jumlah_usul'), rekomendasi: tot('jumlah_rekomendasi'), ditetapkan: tot('jumlah_ditetapkan') };
});
const tambahRincian = () => baru.post(`/usulan/${props.data.id}/details`, { preserveScroll: true, onSuccess: () => baru.reset() });

const simpanRincian = () => router.put(`/usulan/${props.data.id}/details`, {
    details: rows.value.map((d) => ({ id: d.id, jumlah_usul: d.jumlah_usul || 0, prioritas: d.prioritas || 2, kualifikasi_pendidikan: d.kualifikasi_pendidikan, keterangan: d.keterangan })),
}, { preserveScroll: true, onSuccess: () => (dirty.value = false) });

const tarikAbk = async () => {
    const ok = await Swal.fire({
        icon: 'question', title: 'Tarik rincian dari kebutuhan?',
        html: `<div class="text-start small">Posisi yang kekurangan ditambahkan/diperbarui dengan jumlah = kebutuhan − existing + pensiun 5 tahun
            − formasi yang sudah ditetapkan tetapi belum terisi − usulan lain yang sedang diproses.
            Kebutuhan dari ABK final tahun ${props.data.tahun} (atau sebelumnya); posisi tanpa ABK memakai kebutuhan sementara bila ada.</div>`,
        input: 'checkbox', inputValue: 1, inputPlaceholder: 'Kurangi dengan pegawai yang dapat dipindahkan dari unit gemuk (redistribusi)',
        showCancelButton: true, confirmButtonText: 'Ya, tarik', cancelButtonText: 'Batal',
    });
    if (ok.isConfirmed) router.post(`/usulan/${props.data.id}/tarik-abk`, { redistribusi: !!ok.value }, { preserveScroll: true });
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
    // peringatan (tidak memblokir) bila rincian melebihi saran sistem atau tanpa dasar kebutuhan
    const p = props.pemeriksaan;
    const peringatan = ['ajukan', 'rekomendasi', 'tetapkan'].includes(a.aksi) && p && (p.jumlah_melebihi || p.tanpa_dasar)
        ? [p.jumlah_melebihi ? `${p.jumlah_melebihi} rincian melebihi saran sistem` : '', p.tanpa_dasar ? `${p.tanpa_dasar} rincian tanpa dasar kebutuhan` : ''].filter(Boolean).join(', ') + '.'
        : '';
    const res = await Swal.fire({
        icon: perluCatatan || peringatan ? 'warning' : 'question',
        title: a.label + '?',
        text: peringatan ? `Perhatian: ${peringatan} Anda tetap dapat melanjutkan.` : undefined,
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
