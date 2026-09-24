<template>
    <Head :title="data ? 'Ubah ABK' : 'Tambah ABK'" />
    <div class="page-title">{{ data ? 'Ubah' : 'Tambah' }} Analisis Jabatan & Beban Kerja</div>
    <div class="page-subtitle">Waktu kerja efektif default {{ num(wkeDefault) }} menit/tahun (1.250 jam). Kebutuhan dibulatkan: pecahan ≥ 0,5 ke atas.</div>

    <form @submit.prevent="submit">
        <div class="card mb-3">
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label">Instansi</label>
                    <select v-model="form.instansi_id" class="form-select" :class="{ 'is-invalid': form.errors.instansi_id }" @change="gantiInstansi">
                        <option value="">- pilih -</option>
                        <option v-for="i in instansiOptions" :key="i.id" :value="i.id">{{ i.nama }}</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Unit kerja</label>
                    <select v-model="form.unit_kerja_id" class="form-select" :class="{ 'is-invalid': form.errors.unit_kerja_id }">
                        <option value="">- pilih -</option>
                        <option v-for="u in unitOptions" :key="u.id" :value="u.id">{{ '— '.repeat(u.depth) }}{{ u.nama }}</option>
                    </select>
                    <div class="invalid-feedback">{{ form.errors.unit_kerja_id }}</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Jabatan</label>
                    <select v-model="form.jabatan_id" class="form-select" :class="{ 'is-invalid': form.errors.jabatan_id }">
                        <option value="">- pilih -</option>
                        <option v-for="j in jabatanOptions" :key="j.id" :value="j.id">{{ j.nama }}</option>
                    </select>
                    <div class="invalid-feedback">{{ form.errors.jabatan_id }}</div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tahun</label>
                    <input v-model.number="form.tahun" type="number" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Waktu kerja efektif (menit)</label>
                    <input v-model.number="form.waktu_kerja_efektif" type="number" min="1" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select v-model="form.status" class="form-select">
                        <option value="draft">Draft</option>
                        <option value="final">Final</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Ikhtisar jabatan</label>
                    <textarea v-model="form.ikhtisar_jabatan" rows="2" class="form-control"></textarea>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Uraian Tugas & Beban Kerja</span>
                <button type="button" class="btn btn-sm btn-outline-primary" @click="tambah"><i class="fa fa-plus"></i> Baris</button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr><th style="width: 40px">#</th><th>Uraian tugas</th><th style="width: 160px">Hasil kerja (satuan)</th><th class="num" style="width: 130px">Volume / tahun</th><th class="num" style="width: 130px">Norma waktu (menit)</th><th class="num" style="width: 150px">Beban kerja (menit)</th><th class="num" style="width: 110px">Pegawai</th><th></th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="(r, i) in form.uraian" :key="i">
                            <td>{{ i + 1 }}</td>
                            <td><textarea v-model="r.uraian_tugas" rows="1" class="form-control form-control-sm" :class="{ 'is-invalid': form.errors[`uraian.${i}.uraian_tugas`] }"></textarea></td>
                            <td><input v-model="r.hasil_kerja" class="form-control form-control-sm"></td>
                            <td><input v-model.number="r.volume" type="number" min="0" class="form-control form-control-sm text-end"></td>
                            <td><input v-model.number="r.norma_waktu" type="number" min="0" class="form-control form-control-sm text-end"></td>
                            <td class="num">{{ num(beban(r)) }}</td>
                            <td class="num">{{ (beban(r) / wke).toFixed(2) }}</td>
                            <td><button type="button" class="btn btn-sm btn-light text-danger" :disabled="form.uraian.length === 1" @click="form.uraian.splice(i, 1)"><i class="fa fa-times"></i></button></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold">
                            <td colspan="5" class="text-end">Total beban kerja</td>
                            <td class="num">{{ num(total) }}</td>
                            <td class="num">{{ (total / wke).toFixed(2) }}</td>
                            <td></td>
                        </tr>
                        <tr class="table-primary fw-bold">
                            <td colspan="6" class="text-end">Kebutuhan pegawai (dibulatkan)</td>
                            <td class="num fs-5">{{ kebutuhan }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div v-if="form.errors.uraian" class="text-danger small p-2">{{ form.errors.uraian }}</div>
        </div>

        <div class="d-flex gap-2">
            <button class="btn btn-primary" :disabled="form.processing"><i class="fa fa-save me-1"></i> Simpan</button>
            <Link :href="data ? `/anjab/${data.id}` : '/anjab'" class="btn btn-light">Batal</Link>
        </div>
    </form>
</template>

<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { num } from '../../utils';

const props = defineProps({ data: Object, instansiOptions: Array, unitOptions: Array, jabatanOptions: Array, instansiId: Number, wkeDefault: Number });

const kosong = () => ({ uraian_tugas: '', hasil_kerja: '', volume: 0, norma_waktu: 0 });
const form = useForm({
    instansi_id: props.data?.instansi_id ?? props.instansiId ?? '',
    unit_kerja_id: props.data?.unit_kerja_id ?? '',
    jabatan_id: props.data?.jabatan_id ?? '',
    tahun: props.data?.tahun ?? new Date().getFullYear(),
    waktu_kerja_efektif: props.data?.waktu_kerja_efektif ?? props.wkeDefault,
    status: props.data?.status ?? 'draft',
    ikhtisar_jabatan: props.data?.ikhtisar_jabatan ?? '',
    uraian: props.data?.uraian_tugas?.length
        ? props.data.uraian_tugas.map(({ uraian_tugas, hasil_kerja, volume, norma_waktu }) => ({ uraian_tugas, hasil_kerja, volume, norma_waktu }))
        : [kosong()],
});

const wke = computed(() => form.waktu_kerja_efektif || props.wkeDefault);
const beban = (r) => (Number(r.volume) || 0) * (Number(r.norma_waktu) || 0);
const total = computed(() => form.uraian.reduce((t, r) => t + beban(r), 0));
// sama dengan pembulatan di server (PHP_ROUND_HALF_UP)
const kebutuhan = computed(() => Math.round(total.value / wke.value));

const tambah = () => form.uraian.push(kosong());
const gantiInstansi = () => {
    form.unit_kerja_id = '';
    router.reload({ data: { instansi_id: form.instansi_id }, only: ['unitOptions'] });
};
const submit = () => (props.data ? form.put(`/anjab/${props.data.id}`) : form.post('/anjab'));
</script>
