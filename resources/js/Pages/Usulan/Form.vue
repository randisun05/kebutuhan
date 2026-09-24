<template>
    <Head :title="data ? 'Ubah Usulan' : 'Buat Usulan'" />
    <div class="page-title">{{ data ? 'Ubah Usulan ' + data.nomor : 'Buat Usulan Kebutuhan' }}</div>
    <div class="page-subtitle">Isi data surat usulan. Rincian jabatan diisi pada langkah berikutnya (bisa ditarik otomatis dari hasil ABK).</div>

    <div class="card" style="max-width: 820px">
        <div class="card-body">
            <form @submit.prevent="submit">
                <div class="row g-3">
                    <div class="col-md-12" v-if="!data">
                        <label class="form-label">Instansi</label>
                        <select v-model="form.instansi_id" class="form-select" :class="{ 'is-invalid': form.errors.instansi_id }">
                            <option value="">- pilih -</option>
                            <option v-for="i in instansiOptions" :key="i.id" :value="i.id">{{ i.nama }}</option>
                        </select>
                        <div class="invalid-feedback">{{ form.errors.instansi_id }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tahun anggaran</label>
                        <input v-model="form.tahun" type="number" class="form-control" :class="{ 'is-invalid': form.errors.tahun }">
                        <div class="invalid-feedback">{{ form.errors.tahun }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Periode perencanaan (5 th)</label>
                        <input v-model="form.periode" class="form-control" placeholder="2026-2030">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Jenis ASN</label>
                        <select v-model="form.jenis_asn" class="form-select">
                            <option value="pns">PNS</option>
                            <option value="pppk">PPPK</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Perihal</label>
                        <input v-model="form.perihal" class="form-control" :class="{ 'is-invalid': form.errors.perihal }">
                        <div class="invalid-feedback">{{ form.errors.perihal }}</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Keterangan / justifikasi</label>
                        <textarea v-model="form.keterangan" rows="3" class="form-control"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Surat pengantar (PDF, maks 10 MB)</label>
                        <input type="file" accept="application/pdf" class="form-control" :class="{ 'is-invalid': form.errors.surat_pengantar }" @input="form.surat_pengantar = $event.target.files[0]">
                        <div class="invalid-feedback">{{ form.errors.surat_pengantar }}</div>
                        <div v-if="data?.surat_pengantar" class="form-text">Sudah ada berkas; unggah untuk mengganti.</div>
                    </div>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <button class="btn btn-primary" :disabled="form.processing"><i class="fa fa-save me-1"></i> Simpan</button>
                    <Link :href="data ? `/usulan/${data.id}` : '/usulan'" class="btn btn-light">Batal</Link>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ data: Object, instansiOptions: Array, instansiId: Number });
const year = new Date().getFullYear();
const form = useForm({
    instansi_id: props.data?.instansi_id ?? props.instansiId ?? '',
    tahun: props.data?.tahun ?? year + 1,
    periode: props.data?.periode ?? `${year}-${year + 4}`,
    jenis_asn: props.data?.jenis_asn ?? 'pns',
    perihal: props.data?.perihal ?? `Usulan Kebutuhan ASN Tahun ${year + 1}`,
    keterangan: props.data?.keterangan ?? '',
    surat_pengantar: null,
});

// multipart: update memakai POST karena PHP tidak mem-parsing file pada PUT
const submit = () => (props.data ? form.post(`/usulan/${props.data.id}`) : form.post('/usulan'));
</script>
