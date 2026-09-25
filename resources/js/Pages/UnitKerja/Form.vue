<template>
    <Head :title="data ? 'Ubah Unit Kerja' : 'Tambah Unit Kerja'" />
    <div class="page-title mb-3">{{ data ? 'Ubah' : 'Tambah' }} Unit Kerja</div>
    <div class="card" style="max-width: 720px"><div class="card-body">
        <form @submit.prevent="submit" class="row g-3">
            <Field class="col-12" label="Instansi" :error="form.errors.instansi_id">
                <select v-model="form.instansi_id" class="form-select" :disabled="!!data" @change="reloadParents">
                    <option value="">- pilih -</option>
                    <option v-for="i in instansiOptions" :key="i.id" :value="i.id">{{ i.nama }}</option>
                </select>
            </Field>
            <Field class="col-12" label="Unit induk" :error="form.errors.parent_id">
                <select v-model="form.parent_id" class="form-select">
                    <option :value="null">(tanpa induk / unit tertinggi)</option>
                    <option v-for="u in parentOptions" :key="u.id" :value="u.id" :disabled="data && u.id === data.id">{{ '— '.repeat(u.depth) }}{{ u.nama }}</option>
                </select>
            </Field>
            <Field class="col-md-4" label="Kode" :error="form.errors.kode" help="Dipakai untuk impor Excel pegawai"><input v-model="form.kode" class="form-control"></Field>
            <Field class="col-md-8" label="Nama unit kerja" :error="form.errors.nama"><input v-model="form.nama" class="form-control" :class="{ 'is-invalid': form.errors.nama }"></Field>
            <Field class="col-md-4" label="ID Unor SIASN" :error="form.errors.siasn_unor_id" help="Terisi otomatis bila disinkron dari SIASN"><input v-model="form.siasn_unor_id" class="form-control"></Field>
            <Field class="col-md-8" label="Nama jabatan pimpinan unit"><input v-model="form.nama_jabatan_pimpinan" class="form-control" placeholder="Kepala Dinas …"></Field>
            <Field class="col-md-4" label="Eselon"><select v-model="form.eselon" class="form-select"><option :value="null">-</option><option v-for="e in eselonOptions" :key="e" :value="e">{{ e }}</option></select></Field>
            <Field class="col-md-4" label="Urutan"><input v-model.number="form.urutan" type="number" min="0" class="form-control"></Field>
            <div class="col-12 form-check form-switch ms-2"><input v-model="form.is_active" class="form-check-input" type="checkbox" id="aktif"><label for="aktif" class="form-check-label">Aktif</label>
                <span v-if="data && !form.is_active && pegawai" class="text-warning small ms-2"><i class="fa fa-warning"></i> Masih ada {{ pegawai }} pegawai aktif di unit ini.</span></div>
            <div class="col-12 d-flex gap-2"><button class="btn btn-primary" :disabled="form.processing"><i class="fa fa-save me-1"></i> Simpan</button><Link href="/unit-kerja" class="btn btn-light">Batal</Link></div>
        </form>
    </div></div>
</template>

<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Field from '../../Components/Field.vue';

const props = defineProps({ data: Object, pegawai: Number, instansiOptions: Array, parentOptions: Array, eselonOptions: Array, instansiId: Number });
const form = useForm({
    instansi_id: props.data?.instansi_id ?? props.instansiId ?? '', parent_id: props.data?.parent_id ?? null,
    kode: props.data?.kode ?? '', nama: props.data?.nama ?? '', eselon: props.data?.eselon ?? null, urutan: props.data?.urutan ?? 0,
    siasn_unor_id: props.data?.siasn_unor_id ?? '', nama_jabatan_pimpinan: props.data?.nama_jabatan_pimpinan ?? '', is_active: props.data?.is_active ?? true,
});
const reloadParents = () => router.reload({ data: { instansi_id: form.instansi_id }, only: ['parentOptions'] });
const submit = () => (props.data ? form.put(`/unit-kerja/${props.data.id}`) : form.post('/unit-kerja'));
</script>
