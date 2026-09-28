<template>
    <Head title="Impor Pegawai" />
    <div class="page-title">Impor Data Pegawai dari Excel</div>
    <div class="page-subtitle">NIP yang sudah terdaftar akan diperbarui (mutasi/rotasi), NIP baru akan ditambahkan.</div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card"><div class="card-body">
                <form @submit.prevent="submit" class="row g-3">
                    <Field class="col-12" label="Instansi" :error="form.errors.instansi_id">
                        <select v-model="form.instansi_id" class="form-select">
                            <option value="">- pilih -</option>
                            <option v-for="i in instansiOptions" :key="i.id" :value="i.id">{{ i.nama }}</option>
                        </select>
                    </Field>
                    <Field class="col-12" label="Berkas (.xlsx / .xls / .csv, maks 10 MB)" :error="form.errors.file">
                        <input type="file" accept=".xlsx,.xls,.csv" class="form-control" @input="form.file = $event.target.files[0]">
                    </Field>
                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-success" :disabled="form.processing"><i class="fa fa-upload me-1"></i> Impor</button>
                        <a href="/pegawai/template" class="btn btn-light"><i class="fa fa-download me-1"></i> Unduh template</a>
                    </div>
                    <div v-if="form.progress" class="col-12"><div class="progress"><div class="progress-bar" :style="{ width: form.progress.percentage + '%' }"></div></div></div>
                </form>
            </div></div>
        </div>
        <div class="col-lg-6">
            <div class="card"><div class="card-header">Format kolom</div><div class="card-body small">
                <code>nip, nama, kode_unit, kode_jabatan, status_kepegawaian, golongan, pendidikan, tanggal_lahir, tmt_jabatan</code>
                <ul class="mt-2 mb-0">
                    <li><b>kode_unit</b> = kode pada menu Struktur Unit Kerja.</li>
                    <li><b>kode_jabatan</b> = kode pada referensi Jabatan.</li>
                    <li>Tanggal dengan format <code>YYYY-MM-DD</code> atau format tanggal Excel.</li>
                </ul>
            </div></div>
            <div v-if="importErrors?.length" class="card mt-3 border-warning"><div class="card-header text-warning">Baris yang gagal</div>
                <ul class="list-group list-group-flush small"><li v-for="(e, i) in importErrors" :key="i" class="list-group-item">{{ e }}</li></ul>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import Field from '../../Components/Field.vue';

const props = defineProps({ instansiOptions: Array });
const importErrors = computed(() => usePage().props.session.importErrors);
const form = useForm({ instansi_id: props.instansiOptions.length === 1 ? props.instansiOptions[0].id : '', file: null });
const submit = () => form.post('/pegawai/import');
</script>
