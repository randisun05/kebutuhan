<template>
    <Head :title="data ? 'Ubah Jabatan' : 'Tambah Jabatan'" />
    <div class="page-title mb-3">{{ data ? 'Ubah' : 'Tambah' }} Jabatan</div>
    <div class="card" style="max-width: 820px"><div class="card-body">
        <form @submit.prevent="submit" class="row g-3">
            <Field class="col-md-4" label="Kode" :error="form.errors.kode"><input v-model="form.kode" class="form-control" :class="{ 'is-invalid': form.errors.kode }"></Field>
            <Field class="col-md-8" label="Nama jabatan" :error="form.errors.nama"><input v-model="form.nama" class="form-control" :class="{ 'is-invalid': form.errors.nama }"></Field>
            <Field class="col-md-4" label="Jenis jabatan" :error="form.errors.jenis"><select v-model="form.jenis" class="form-select"><option v-for="j in jenisOptions" :key="j.value" :value="j.value">{{ j.label }}</option></select></Field>
            <Field class="col-md-4" label="Kategori (JF)"><select v-model="form.kategori" class="form-select" :disabled="form.jenis !== 'fungsional'"><option :value="null">-</option><option value="keahlian">Keahlian</option><option value="keterampilan">Keterampilan</option></select></Field>
            <Field class="col-md-4" label="Jenjang"><input v-model="form.jenjang" class="form-control" placeholder="Ahli Pertama, Terampil, …"></Field>
            <template v-if="form.jenis === 'fungsional' && jfOptions.length">
                <Field class="col-md-4" label="Jabatan pokok JF MASN" :error="form.errors.jf_jabatan_pokok_id" help="Opsional. Mengaitkan jabatan ini ke JF MASN agar ABK dan pegawainya dapat disejajarkan dengan rekomendasi.">
                    <select v-model="form.jf_jabatan_pokok_id" class="form-select" @change="form.jf_jenjang_id = null"><option :value="null">- tidak dikaitkan -</option><option v-for="j in jfOptions" :key="j.id" :value="j.id">{{ j.nama }}<template v-if="jfOptions.some((x) => x.pembina !== j.pembina)"> ({{ j.pembina }})</template></option></select>
                </Field>
                <Field class="col-md-4" label="Jenjang JF MASN" :error="form.errors.jf_jenjang_id">
                    <select v-model="form.jf_jenjang_id" class="form-select" :disabled="!form.jf_jabatan_pokok_id"><option :value="null">-</option><option v-for="x in jenjangJf" :key="x.id" :value="x.id">{{ x.nama }}</option></select>
                </Field>
            </template>
            <Field class="col-md-4" label="Kelas jabatan" :error="form.errors.kelas_jabatan"><input v-model.number="form.kelas_jabatan" type="number" min="1" max="17" class="form-control"></Field>
            <Field class="col-md-4" label="Batas usia pensiun" :error="form.errors.bup"><input v-model.number="form.bup" type="number" min="50" max="70" class="form-control"></Field>
            <Field class="col-md-4" label="ID jabatan SIASN" help="ID jabatan fungsional / pelaksana / struktural"><input v-model="form.siasn_jabatan_id" class="form-control"></Field>
            <Field class="col-md-4" label="Estimasi belanja pegawai / orang / tahun (Rp)" :error="form.errors.estimasi_biaya_tahunan"><input v-model.number="form.estimasi_biaya_tahunan" type="number" min="0" class="form-control"></Field>
            <Field class="col-md-4" label="Kualifikasi pendidikan"><input v-model="form.kualifikasi_pendidikan" class="form-control"></Field>
            <div class="col-12 form-check form-switch ms-2"><input v-model="form.is_active" class="form-check-input" type="checkbox" id="aktif"><label for="aktif" class="form-check-label">Aktif</label></div>
            <div class="col-12 d-flex gap-2"><button class="btn btn-primary" :disabled="form.processing"><i class="fa fa-save me-1"></i> Simpan</button><Link href="/jabatan" class="btn btn-light">Batal</Link></div>
        </form>
    </div></div>
</template>

<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Field from '../../Components/Field.vue';

const props = defineProps({ data: Object, jenisOptions: Array, jfOptions: { type: Array, default: () => [] } });
const d = props.data || {};
const form = useForm({
    kode: d.kode ?? '', nama: d.nama ?? '', jenis: d.jenis ?? 'fungsional', kategori: d.kategori ?? null, jenjang: d.jenjang ?? '',
    kelas_jabatan: d.kelas_jabatan ?? null, bup: d.bup ?? 58, kualifikasi_pendidikan: d.kualifikasi_pendidikan ?? '', is_active: d.is_active ?? true,
    siasn_jabatan_id: d.siasn_jabatan_id ?? '', estimasi_biaya_tahunan: d.estimasi_biaya_tahunan ?? null,
    jf_jabatan_pokok_id: d.jf_jabatan_pokok_id ?? null, jf_jenjang_id: d.jf_jenjang_id ?? null,
});
// jenjang yang berlaku untuk jabatan pokok terpilih
const jenjangJf = computed(() => props.jfOptions.find((j) => j.id === form.jf_jabatan_pokok_id)?.jenjang ?? []);
const submit = () => {
    if (form.jenis !== 'fungsional') { form.kategori = null; form.jf_jabatan_pokok_id = null; form.jf_jenjang_id = null; }
    return props.data ? form.put(`/jabatan/${props.data.id}`) : form.post('/jabatan');
};
</script>
