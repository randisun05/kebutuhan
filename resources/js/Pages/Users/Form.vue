<template>
    <Head :title="data ? 'Ubah Pengguna' : 'Tambah Pengguna'" />
    <div class="page-title mb-3">{{ data ? 'Ubah' : 'Tambah' }} Pengguna</div>
    <div class="card" style="max-width: 720px"><div class="card-body">
        <form @submit.prevent="submit" class="row g-3">
            <Field class="col-md-6" label="Nama" :error="form.errors.name"><input v-model="form.name" class="form-control"></Field>
            <Field class="col-md-6" label="NIP" :error="form.errors.nip"><input v-model="form.nip" maxlength="18" class="form-control"></Field>
            <Field class="col-md-6" label="Email" :error="form.errors.email"><input v-model="form.email" type="email" class="form-control"></Field>
            <Field class="col-md-6" label="Password" :error="form.errors.password" :help="data ? 'Kosongkan jika tidak diubah' : 'Minimal 8 karakter'"><input v-model="form.password" type="password" class="form-control" autocomplete="new-password"></Field>
            <Field class="col-md-6" label="Peran" :error="form.errors.role"><select v-model="form.role" class="form-select"><option v-for="r in roleOptions" :key="r.value" :value="r.value">{{ r.label }}</option></select></Field>
            <Field class="col-md-6" label="Instansi" :error="form.errors.instansi_id" help="Wajib untuk operator instansi">
                <select v-model="form.instansi_id" class="form-select"><option :value="null">-</option><option v-for="i in instansiOptions" :key="i.id" :value="i.id">{{ i.nama }}</option></select>
            </Field>
            <div class="col-12 form-check form-switch ms-2"><input v-model="form.is_active" class="form-check-input" type="checkbox" id="aktif"><label for="aktif" class="form-check-label">Aktif</label></div>
            <div class="col-12 d-flex gap-2"><button class="btn btn-primary" :disabled="form.processing"><i class="fa fa-save me-1"></i> Simpan</button><Link href="/users" class="btn btn-light">Batal</Link></div>
        </form>
    </div></div>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import Field from '../../Components/Field.vue';

const props = defineProps({ data: Object, roleOptions: Array, instansiOptions: Array });
const d = props.data || {};
const form = useForm({
    name: d.name ?? '', nip: d.nip ?? '', email: d.email ?? '', password: '', role: d.role ?? 'operator_instansi',
    instansi_id: d.instansi_id ?? null, is_active: d.is_active ?? true,
});
const submit = () => (props.data ? form.put(`/users/${props.data.id}`) : form.post('/users'));
</script>
