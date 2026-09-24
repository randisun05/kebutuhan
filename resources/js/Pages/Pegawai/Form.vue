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
            <div class="col-12 form-check form-switch ms-2"><input v-model="form.is_active" class="form-check-input" type="checkbox" id="aktif"><label for="aktif" class="form-check-label">Aktif (dihitung sebagai existing)</label></div>
            <div class="col-12 d-flex gap-2"><button class="btn btn-primary" :disabled="form.processing"><i class="fa fa-save me-1"></i> Simpan</button><Link href="/pegawai" class="btn btn-light">Batal</Link></div>
        </form>
    </div></div>
</template>

<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Field from '../../Components/Field.vue';

const props = defineProps({ data: Object, instansiOptions: Array, unitOptions: Array, jabatanOptions: Array, instansiId: Number });
const d = props.data || {};
const form = useForm({
    instansi_id: d.instansi_id ?? props.instansiId ?? '', unit_kerja_id: d.unit_kerja_id ?? '', jabatan_id: d.jabatan_id ?? '',
    nip: d.nip ?? '', nama: d.nama ?? '', status_kepegawaian: d.status_kepegawaian ?? 'pns', golongan: d.golongan ?? '',
    pendidikan: d.pendidikan ?? '', tanggal_lahir: d.tanggal_lahir ?? '', tmt_jabatan: d.tmt_jabatan ?? '', is_active: d.is_active ?? true,
});
const gantiInstansi = () => {
    form.unit_kerja_id = '';
    router.reload({ data: { instansi_id: form.instansi_id }, only: ['unitOptions'] });
};
const submit = () => (props.data ? form.put(`/pegawai/${props.data.id}`) : form.post('/pegawai'));
</script>
