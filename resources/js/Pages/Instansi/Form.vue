<template>
    <Head :title="data ? 'Ubah Instansi' : 'Tambah Instansi'" />
    <div class="page-title mb-3">{{ data ? 'Ubah' : 'Tambah' }} Instansi</div>
    <div class="card" style="max-width: 720px"><div class="card-body">
        <form @submit.prevent="submit" class="row g-3">
            <Field class="col-md-4" label="Kode" :error="form.errors.kode"><input v-model="form.kode" class="form-control" :class="{ 'is-invalid': form.errors.kode }"></Field>
            <Field class="col-md-8" label="Nama instansi" :error="form.errors.nama"><input v-model="form.nama" class="form-control" :class="{ 'is-invalid': form.errors.nama }"></Field>
            <Field class="col-md-6" label="Jenis" :error="form.errors.jenis">
                <select v-model="form.jenis" class="form-select"><option v-for="j in jenisOptions" :key="j.value" :value="j.value">{{ j.label }}</option></select>
            </Field>
            <Field class="col-md-6" label="Provinsi"><input v-model="form.provinsi" class="form-control"></Field>
            <Field class="col-12" label="Alamat"><input v-model="form.alamat" class="form-control"></Field>
            <div class="col-12 form-check form-switch ms-2"><input v-model="form.is_active" class="form-check-input" type="checkbox" id="aktif"><label for="aktif" class="form-check-label">Aktif</label></div>
            <div class="col-12 d-flex gap-2"><button class="btn btn-primary" :disabled="form.processing"><i class="fa fa-save me-1"></i> Simpan</button><Link href="/instansi" class="btn btn-light">Batal</Link></div>
        </form>
    </div></div>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import Field from '../../Components/Field.vue';

const props = defineProps({ data: Object, jenisOptions: Array });
const form = useForm({
    kode: props.data?.kode ?? '', nama: props.data?.nama ?? '', jenis: props.data?.jenis ?? 'pusat',
    provinsi: props.data?.provinsi ?? '', alamat: props.data?.alamat ?? '', is_active: props.data?.is_active ?? true,
});
const submit = () => (props.data ? form.put(`/instansi/${props.data.id}`) : form.post('/instansi'));
</script>
