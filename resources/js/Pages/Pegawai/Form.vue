<template>
    <Head :title="data ? 'Ubah Pegawai' : 'Tambah Pegawai'" />
    <div class="page-title mb-3">{{ data ? 'Ubah' : 'Tambah' }} Data Pegawai</div>
    <div class="card" style="max-width: 900px"><div class="card-body">
        <form @submit.prevent="submit" class="row g-3">
            <Field class="col-md-6" label="Instansi" :error="form.errors.instansi_id">
                <select v-model="form.instansi_id" class="form-select" @change="gantiInstansi">
                    <option value="">- pilih -</option>
                    <option v-for="i in instansiOptions" :key="i.id" :value="i.id">{{ i.nama }}</option>
                </select>
            </Field>
            <Field class="col-md-6" label="Unit kerja" :error="form.errors.unit_kerja_id">
                <select v-model="form.unit_kerja_id" class="form-select">
                    <option value="">- pilih -</option>
                    <option v-for="u in unitOptions" :key="u.id" :value="u.id">{{ '— '.repeat(u.depth) }}{{ u.nama }}</option>
                </select>
            </Field>
            <Field class="col-md-6" label="Jabatan" :error="form.errors.jabatan_id">
                <select v-model="form.jabatan_id" class="form-select">
                    <option value="">- pilih -</option>
                    <option v-for="j in jabatanOptions" :key="j.id" :value="j.id">{{ j.nama }}</option>
                </select>
            </Field>
            <Field class="col-md-6" label="NIP (18 digit)" :error="form.errors.nip"><input v-model="form.nip" maxlength="18" class="form-control" :class="{ 'is-invalid': form.errors.nip }"></Field>
            <Field class="col-md-6" label="Nama lengkap" :error="form.errors.nama"><input v-model="form.nama" class="form-control" :class="{ 'is-invalid': form.errors.nama }"></Field>
            <Field class="col-md-3" label="Status"><select v-model="form.status_kepegawaian" class="form-select"><option value="pns">PNS</option><option value="pppk">PPPK</option></select></Field>
            <Field class="col-md-3" label="Golongan"><input v-model="form.golongan" class="form-control" placeholder="III/a"></Field>
            <Field class="col-md-4" label="Pendidikan"><input v-model="form.pendidikan" class="form-control" placeholder="S-1"></Field>
            <Field class="col-md-4" label="Tanggal lahir" :error="form.errors.tanggal_lahir" help="Untuk menghitung TMT pensiun (BUP jabatan)"><input v-model="form.tanggal_lahir" type="date" class="form-control"></Field>
            <Field class="col-md-4" label="TMT jabatan"><input v-model="form.tmt_jabatan" type="date" class="form-control"></Field>
            <Field v-if="formasiOptions.length" class="col-12" label="Diangkat dari formasi (opsional)" :error="form.errors.usulan_detail_id"
                help="Tautkan pegawai hasil seleksi ke formasi yang ditetapkan agar jumlah terisi di Pengisian Formasi bertambah otomatis.">
                <select v-model="form.usulan_detail_id" class="form-select">
                    <option :value="null">- tidak ditautkan -</option>
                    <option v-for="f in formasiPilihan" :key="f.id" :value="f.id">
                        Formasi {{ f.tahun }}{{ f.nomor ? ' · ' + f.nomor : '' }} · {{ f.unit_nama }} · terisi {{ f.jumlah_terisi }}/{{ f.jumlah_ditetapkan }}
                    </option>
                </select>
                <div v-if="form.jabatan_id && !formasiPilihan.length" class="form-text">Tidak ada formasi tersisa untuk jabatan ini.</div>
            </Field>
            <Field v-if="data" class="col-12" label="Keterangan perubahan (dicatat di riwayat bila unit/jabatan/status berubah)"><input v-model="form.keterangan_mutasi" class="form-control" placeholder="Contoh: SK Mutasi No. 821/123/2026"></Field>
            <div class="col-12 form-check form-switch ms-2"><input v-model="form.is_active" class="form-check-input" type="checkbox" id="aktif"><label for="aktif" class="form-check-label">Aktif (dihitung sebagai existing)</label></div>
            <div class="col-12 d-flex gap-2"><button class="btn btn-primary" :disabled="form.processing"><i class="fa fa-save me-1"></i> Simpan</button><Link href="/pegawai" class="btn btn-light">Batal</Link></div>
        </form>
    </div></div>

    <div v-if="data" class="card mt-3" style="max-width: 900px">
        <div class="card-header d-flex justify-content-between"><span>Riwayat jabatan & mutasi</span>
            <span class="small text-muted">Sumber data: {{ data.sumber }}<template v-if="data.siasn_synced_at"> · sinkron SIASN {{ waktu(data.siasn_synced_at) }}</template></span></div>
        <ul class="list-group list-group-flush small">
            <li v-for="r in riwayat" :key="r.id" class="list-group-item">
                <div class="d-flex justify-content-between"><b>{{ jenisRiwayat[r.jenis] || r.jenis }}</b><span class="text-muted">{{ waktu(r.created_at) }} · {{ r.user?.name || r.sumber }}</span></div>
                <div v-if="r.jenis === 'mutasi_unit'">{{ r.dari_unit?.nama }} → {{ r.ke_unit?.nama }}</div>
                <div v-if="r.dari_jabatan_id !== r.ke_jabatan_id && r.dari_jabatan">{{ r.dari_jabatan?.nama }} → {{ r.ke_jabatan?.nama }}</div>
                <div v-if="r.jenis === 'masuk' && !r.dari_unit_id">{{ r.ke_jabatan?.nama }} pada {{ r.ke_unit?.nama }}</div>
                <div v-if="r.keterangan" class="text-muted fst-italic">{{ r.keterangan }}</div>
            </li>
            <li v-if="!riwayat.length" class="list-group-item text-muted">Belum ada riwayat.</li>
        </ul>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Field from '../../Components/Field.vue';
import { waktu } from '../../utils';

const props = defineProps({ data: Object, riwayat: Array, jenisRiwayat: Object, instansiOptions: Array, unitOptions: Array, jabatanOptions: Array, instansiId: Number, formasiOptions: { type: Array, default: () => [] } });
const d = props.data || {};
const form = useForm({
    instansi_id: d.instansi_id ?? props.instansiId ?? '', unit_kerja_id: d.unit_kerja_id ?? '', jabatan_id: d.jabatan_id ?? '',
    nip: d.nip ?? '', nama: d.nama ?? '', status_kepegawaian: d.status_kepegawaian ?? 'pns', golongan: d.golongan ?? '',
    pendidikan: d.pendidikan ?? '', tanggal_lahir: d.tanggal_lahir ?? '', tmt_jabatan: d.tmt_jabatan ?? '', is_active: d.is_active ?? true, keterangan_mutasi: '', usulan_detail_id: d.usulan_detail_id ?? null,
});
// formasi untuk jabatan yang dipilih (formasi yang sudah tertaut selalu ditampilkan)
const formasiPilihan = computed(() => props.formasiOptions.filter((f) => f.jabatan_id === form.jabatan_id || f.id === form.usulan_detail_id));
const gantiInstansi = () => {
    form.unit_kerja_id = '';
    form.usulan_detail_id = null;
    router.reload({ data: { instansi_id: form.instansi_id }, only: ['unitOptions', 'formasiOptions'] });
};
const submit = () => (props.data ? form.put(`/pegawai/${props.data.id}`) : form.post('/pegawai'));
</script>
