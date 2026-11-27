<template>
    <Head :title="data ? 'Ubah Profil Impor' : 'Tambah Profil Impor'" />
    <Link href="/impor-profil" class="small"><i class="fa fa-angle-left"></i> Semua profil</Link>
    <div class="page-title mb-3">{{ data ? 'Ubah' : 'Tambah' }} Profil Impor</div>

    <form class="row g-3" @submit.prevent="submit">
        <div class="col-lg-8"><div class="card"><div class="card-header">Umum</div><div class="card-body row g-2">
            <Field class="col-md-4" label="Kode" :error="e('kode')" help="huruf kecil, angka, - atau _"><input v-model="form.kode" class="form-control" :class="{ 'is-invalid': e('kode') }"></Field>
            <Field class="col-md-8" label="Nama" :error="e('nama')"><input v-model="form.nama" class="form-control" :class="{ 'is-invalid': e('nama') }"></Field>
            <Field class="col-md-8" label="Nama sheet" :error="e('sheet')"><input v-model="form.sheet" class="form-control" :class="{ 'is-invalid': e('sheet') }"></Field>
            <div class="col-md-4 d-flex align-items-end"><div class="form-check form-switch"><input id="aktif" v-model="form.is_active" type="checkbox" class="form-check-input"><label for="aktif" class="form-check-label">Aktif</label></div></div>
        </div></div></div>

        <div class="col-lg-8"><div class="card"><div class="card-header">Kolom isian <span class="text-muted small">(huruf kolom Excel, mis. D, AX)</span></div><div class="card-body row g-2">
            <Field v-for="k in kolomUtama" :key="k.key" class="col-md-3 col-6" :label="k.label + (k.wajib ? ' *' : '')" :error="e('pemetaan.kolom.' + k.key)">
                <input v-model="form.pemetaan.kolom[k.key]" class="form-control text-uppercase" maxlength="3" :class="{ 'is-invalid': e('pemetaan.kolom.' + k.key) }">
            </Field>
            <div class="col-12"><label class="form-label">Kolom catatan (boleh lebih dari satu)</label>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <div v-for="(c, i) in form.pemetaan.kolom.catatan" :key="i" class="input-group input-group-sm" style="width: 110px"><input v-model="form.pemetaan.kolom.catatan[i]" class="form-control text-uppercase" maxlength="3"><button type="button" class="btn btn-outline-danger" @click="form.pemetaan.kolom.catatan.splice(i, 1)">×</button></div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="form.pemetaan.kolom.catatan.push('')"><i class="fa fa-plus"></i></button>
                </div></div>
        </div></div></div>

        <div class="col-12"><div class="card"><div class="card-header d-flex justify-content-between align-items-center"><span>Blok angka <span class="text-muted small">(satu blok = satu sumber; kolom awal = jenjang pertama)</span></span>
            <button type="button" class="btn btn-sm btn-outline-primary" @click="form.pemetaan.blok.push({ sumber: '', kolom_awal: '', lebar: 4, cadangan_kolom_awal: '' })"><i class="fa fa-plus me-1"></i> Blok</button></div>
            <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
                <thead><tr><th>Sumber angka</th><th style="width: 120px">Kolom awal</th><th style="width: 100px">Lebar</th><th style="width: 160px">Kolom awal cadangan</th><th></th></tr></thead>
                <tbody><tr v-for="(b, i) in form.pemetaan.blok" :key="i">
                    <td><select v-model="b.sumber" class="form-select form-select-sm" :class="{ 'is-invalid': e(`pemetaan.blok.${i}.sumber`) }"><option value="">- pilih -</option><option v-for="s in sumberOptions" :key="s.kode" :value="s.kode">{{ s.nama }} ({{ s.kode }})</option></select></td>
                    <td><input v-model="b.kolom_awal" class="form-control form-control-sm text-uppercase" maxlength="3" :class="{ 'is-invalid': e(`pemetaan.blok.${i}.kolom_awal`) }"></td>
                    <td><input v-model="b.lebar" type="number" min="1" max="12" class="form-control form-control-sm"></td>
                    <td><input v-model="b.cadangan_kolom_awal" class="form-control form-control-sm text-uppercase" maxlength="3" placeholder="opsional"></td>
                    <td><button type="button" class="btn btn-sm btn-light text-danger" @click="form.pemetaan.blok.splice(i, 1)"><i class="fa fa-trash"></i></button></td></tr></tbody>
            </table></div>
            <div v-if="e('pemetaan.blok')" class="card-body py-1 text-danger small">{{ e('pemetaan.blok') }}</div></div></div>

        <div class="col-12"><div class="card"><div class="card-header d-flex justify-content-between align-items-center"><span>Surat <span class="text-muted small">(nomor dan tanggal; satu sel boleh memuat beberapa surat)</span></span>
            <button type="button" class="btn btn-sm btn-outline-primary" @click="form.pemetaan.surat.push({ jenis: '', nomor: '', tanggal: '', tautan: '', ttd_jabatan: '', ttd_nama: '' })"><i class="fa fa-plus me-1"></i> Surat</button></div>
            <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
                <thead><tr><th>Jenis</th><th>Nomor</th><th>Tanggal</th><th>Tautan</th><th>Jabatan TTD</th><th>Nama TTD</th><th></th></tr></thead>
                <tbody><tr v-for="(s, i) in form.pemetaan.surat" :key="i">
                    <td><input v-model="s.jenis" class="form-control form-control-sm" placeholder="rekomendasi" :class="{ 'is-invalid': e(`pemetaan.surat.${i}.jenis`) }"></td>
                    <td v-for="k in ['nomor', 'tanggal', 'tautan', 'ttd_jabatan', 'ttd_nama']" :key="k" style="width: 90px"><input v-model="s[k]" class="form-control form-control-sm text-uppercase" maxlength="3" :class="{ 'is-invalid': e(`pemetaan.surat.${i}.${k}`) }"></td>
                    <td><button type="button" class="btn btn-sm btn-light text-danger" @click="form.pemetaan.surat.splice(i, 1)"><i class="fa fa-trash"></i></button></td></tr>
                <tr v-if="!form.pemetaan.surat.length"><td colspan="7" class="text-muted text-center small py-2">Tidak ada kolom surat.</td></tr></tbody>
            </table></div></div></div>

        <div class="col-12 d-flex gap-2"><button class="btn btn-primary" :disabled="form.processing"><i class="fa fa-save me-1"></i> Simpan</button><Link href="/impor-profil" class="btn btn-light">Batal</Link></div>
    </form>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import Field from '../../Components/Field.vue';

const props = defineProps({ data: Object, sumberOptions: Array });

const kolomUtama = [
    { key: 'instansi', label: 'Nama instansi', wajib: true }, { key: 'jabatan', label: 'Jabatan', wajib: true }, { key: 'jenis_instansi', label: 'Jenis instansi' },
    { key: 'pokja', label: 'Pokja' }, { key: 'status_rekom', label: 'Status rekomendasi' }, { key: 'jenis_rekom', label: 'Jenis rekomendasi' },
    { key: 'wilayah_kerja', label: 'Wilayah kerja' }, { key: 'sesuai_bezetting', label: 'Sesuai bezetting' },
];

const p = props.data?.pemetaan;
const form = useForm({
    kode: props.data?.kode ?? '', nama: props.data?.nama ?? '', sheet: props.data?.sheet ?? '', is_active: props.data?.is_active ?? true,
    pemetaan: {
        kolom: { instansi: '', jabatan: '', jenis_instansi: '', pokja: '', status_rekom: '', jenis_rekom: '', wilayah_kerja: '', sesuai_bezetting: '', ...(p?.kolom ?? {}), catatan: [...(p?.kolom?.catatan ?? [])] },
        surat: (p?.surat ?? []).map((s) => ({ jenis: '', nomor: '', tanggal: '', tautan: '', ttd_jabatan: '', ttd_nama: '', ...s })),
        blok: (p?.blok ?? [{ sumber: '', kolom_awal: '', lebar: 4, cadangan_kolom_awal: '' }]).map((b) => ({ lebar: 4, cadangan_kolom_awal: '', ...b })),
    },
});
const e = (k) => form.errors[k];
// huruf kolom Excel dinormalkan ke huruf besar; kode sumber/jenis hanya dirapikan spasinya
const huruf = (v) => (typeof v === 'string' ? v.trim().toUpperCase() : v);
const rapikan = (d) => ({
    ...d,
    pemetaan: {
        kolom: Object.fromEntries(Object.entries(d.pemetaan.kolom).map(([k, v]) => [k, Array.isArray(v) ? v.map(huruf).filter(Boolean) : huruf(v)])),
        surat: d.pemetaan.surat.map((s) => ({ jenis: (s.jenis || '').trim(), nomor: huruf(s.nomor), tanggal: huruf(s.tanggal), tautan: huruf(s.tautan), ttd_jabatan: huruf(s.ttd_jabatan), ttd_nama: huruf(s.ttd_nama) })),
        blok: d.pemetaan.blok.map((b) => ({ sumber: b.sumber, kolom_awal: huruf(b.kolom_awal), lebar: b.lebar, cadangan_kolom_awal: huruf(b.cadangan_kolom_awal) })),
    },
});
const submit = () => form.transform(rapikan).submit(props.data ? 'put' : 'post', props.data ? `/impor-profil/${props.data.id}` : '/impor-profil');
</script>
