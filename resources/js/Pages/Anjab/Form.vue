<template>
    <Head :title="data ? 'Ubah ABK' : 'Tambah ABK'" />
    <div class="page-title">{{ data ? 'Ubah' : 'Tambah' }} Analisis Jabatan & Beban Kerja</div>
    <div class="page-subtitle">Mengikuti PermenPANRB 1/2020: kebutuhan pegawai = Σ (volume tahunan × norma waktu) ÷ waktu kerja efektif, dibulatkan ≥ 0,5 ke atas.</div>

    <form @submit.prevent="submit">
        <div class="card mb-3">
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label">Instansi</label>
                    <select v-model="form.instansi_id" class="form-select" :class="{ 'is-invalid': form.errors.instansi_id }" :disabled="!!data" @change="gantiInstansi">
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
                    <select v-model="form.jabatan_id" class="form-select" :class="{ 'is-invalid': form.errors.jabatan_id }" @change="isiKelas">
                        <option value="">- pilih -</option>
                        <option v-for="j in jabatanOptions" :key="j.id" :value="j.id">{{ j.nama }}</option>
                    </select>
                    <div class="invalid-feedback">{{ form.errors.jabatan_id }}</div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tahun ABK</label>
                    <input v-model.number="form.tahun" type="number" class="form-control" :class="{ 'is-invalid': form.errors.tahun }">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Kelas jabatan</label>
                    <input v-model.number="form.kelas_jabatan" type="number" min="1" max="17" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select v-model="form.status" class="form-select">
                        <option value="draft">Draft</option>
                        <option value="final">Final</option>
                    </select>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs">
            <li class="nav-item"><a href="#" class="nav-link" :class="{ active: tab === 'abk' }" @click.prevent="tab = 'abk'">1. Uraian Tugas & Beban Kerja</a></li>
            <li class="nav-item"><a href="#" class="nav-link" :class="{ active: tab === 'info' }" @click.prevent="tab = 'info'">2. Informasi Jabatan</a></li>
            <li class="nav-item"><a href="#" class="nav-link" :class="{ active: tab === 'proyeksi' }" @click.prevent="tab = 'proyeksi'">3. Waktu Kerja & Proyeksi</a></li>
        </ul>

        <div v-show="tab === 'abk'" class="card mb-3 border-top-0 rounded-top-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Uraian tugas · WKE {{ num(wke) }} menit/tahun</span>
                <button type="button" class="btn btn-sm btn-outline-primary" @click="tambah"><i class="fa fa-plus"></i> Baris</button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr><th style="width: 36px">#</th><th style="min-width: 240px">Uraian tugas</th><th style="width: 140px">Hasil kerja</th><th class="num" style="width: 100px">Volume</th><th style="width: 120px">Periode</th><th class="num" style="width: 110px">Norma waktu (menit)</th><th class="num" style="width: 130px">Beban/th (menit)</th><th class="num" style="width: 80px">Pegawai</th><th></th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="(r, i) in form.uraian" :key="i">
                            <td>{{ i + 1 }}</td>
                            <td><textarea v-model="r.uraian_tugas" rows="1" class="form-control form-control-sm" :class="{ 'is-invalid': form.errors[`uraian.${i}.uraian_tugas`] }"></textarea></td>
                            <td><input v-model="r.hasil_kerja" class="form-control form-control-sm" placeholder="Dokumen, laporan…"></td>
                            <td><input v-model.number="r.volume" type="number" min="0" class="form-control form-control-sm text-end"></td>
                            <td><select v-model="r.satuan_periode" class="form-select form-select-sm"><option v-for="(l, k) in periodeOptions" :key="k" :value="k">{{ l }}</option></select></td>
                            <td><input v-model.number="r.norma_waktu" type="number" min="0" class="form-control form-control-sm text-end"></td>
                            <td class="num">{{ num(beban(r)) }}</td>
                            <td class="num">{{ (beban(r) / wke).toFixed(2) }}</td>
                            <td><button type="button" class="btn btn-sm btn-light text-danger" :disabled="form.uraian.length === 1" @click="form.uraian.splice(i, 1)"><i class="fa fa-times"></i></button></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold"><td colspan="6" class="text-end">Total beban kerja</td><td class="num">{{ num(total) }}</td><td class="num">{{ (total / wke).toFixed(2) }}</td><td></td></tr>
                        <tr class="table-primary fw-bold"><td colspan="7" class="text-end">Kebutuhan pegawai (dibulatkan)</td><td class="num fs-5">{{ kebutuhan }}</td><td></td></tr>
                    </tfoot>
                </table>
            </div>
            <div class="card-body small text-muted border-top">
                Volume per bulan/minggu/hari disetahunkan: × {{ periodePerTahun.bulan }} (bulan), × {{ periodePerTahun.minggu }} (minggu), × {{ periodePerTahun.hari }} (hari) — setara 6.250 / 1.500 / 300 menit kerja efektif.
                <div v-if="form.errors.uraian" class="text-danger">{{ form.errors.uraian }}</div>
            </div>
        </div>

        <div v-show="tab === 'info'" class="card mb-3 border-top-0 rounded-top-0">
            <div class="card-body row g-3">
                <div class="col-12">
                    <label class="form-label fw-semibold">Ikhtisar jabatan</label>
                    <textarea v-model="form.ikhtisar_jabatan" rows="2" class="form-control"></textarea>
                </div>
                <div v-for="(meta, key) in informasiFields" :key="key" class="col-md-6">
                    <label class="form-label fw-semibold small">{{ meta.label }}</label>
                    <textarea v-if="meta.tipe === 'text'" v-model="form.informasi[key]" rows="3" class="form-control form-control-sm"></textarea>
                    <template v-else>
                        <textarea :value="(form.informasi[key] || []).join('\n')" rows="3" class="form-control form-control-sm" placeholder="Satu butir per baris" @input="form.informasi[key] = $event.target.value.split('\n')"></textarea>
                    </template>
                </div>
            </div>
        </div>

        <div v-show="tab === 'proyeksi'" class="card mb-3 border-top-0 rounded-top-0">
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Waktu kerja efektif (menit/tahun)</label>
                    <input v-model.number="form.waktu_kerja_efektif" type="number" min="1" class="form-control">
                    <div class="form-text">Standar PermenPANRB 1/2020: 1.250 jam = 75.000 menit. Kalkulator:</div>
                    <div class="d-flex gap-2 align-items-center mt-1 small">
                        <input v-model.number="kalk.hari" type="number" class="form-control form-control-sm" style="width: 90px"> hari ×
                        <input v-model.number="kalk.jam" type="number" step="0.5" class="form-control form-control-sm" style="width: 80px"> jam
                        <button type="button" class="btn btn-sm btn-light" @click="form.waktu_kerja_efektif = Math.round(kalk.hari * kalk.jam * 60)">= {{ num(Math.round(kalk.hari * kalk.jam * 60)) }} menit</button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Perkiraan pertumbuhan beban kerja per tahun (%)</label>
                    <input v-model.number="form.pertumbuhan_beban" type="number" step="0.5" class="form-control">
                    <div class="form-text">Dipakai untuk proyeksi kebutuhan 5 tahun (misal pertambahan layanan/penduduk). 0 = beban kerja tetap.</div>
                </div>
                <div class="col-12">
                    <table class="table table-sm">
                        <thead><tr><th>Tahun</th><th v-for="t in proyeksi" :key="t.tahun" class="num">{{ t.tahun }}</th></tr></thead>
                        <tbody><tr><td>Kebutuhan</td><td v-for="t in proyeksi" :key="t.tahun" class="num fw-semibold">{{ t.kebutuhan }}</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button class="btn btn-primary" :disabled="form.processing"><i class="fa fa-save me-1"></i> Simpan</button>
            <Link :href="data ? `/anjab/${data.id}` : '/anjab'" class="btn btn-light">Batal</Link>
            <span v-if="Object.keys(form.errors).length" class="text-danger small align-self-center">Periksa kembali isian yang ditandai.</span>
        </div>
    </form>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { num } from '../../utils';

const props = defineProps({
    data: Object, instansiOptions: Array, unitOptions: Array, jabatanOptions: Array, instansiId: Number, wkeDefault: Number,
    informasiFields: Object, periodeOptions: Object, periodePerTahun: Object,
});

const tab = ref('abk');
const kalk = reactive({ hari: 250, jam: 5 });
const kosong = () => ({ uraian_tugas: '', hasil_kerja: '', volume: 0, satuan_periode: 'tahun', norma_waktu: 0 });
const informasi = Object.fromEntries(Object.entries(props.informasiFields).map(([k, m]) => [k, props.data?.informasi?.[k] ?? (m.tipe === 'list' ? [] : '')]));

const form = useForm({
    instansi_id: props.data?.instansi_id ?? props.instansiId ?? '',
    unit_kerja_id: props.data?.unit_kerja_id ?? '',
    jabatan_id: props.data?.jabatan_id ?? '',
    tahun: props.data?.tahun ?? new Date().getFullYear(),
    kelas_jabatan: props.data?.kelas_jabatan ?? null,
    waktu_kerja_efektif: props.data?.waktu_kerja_efektif ?? props.wkeDefault,
    pertumbuhan_beban: props.data?.pertumbuhan_beban ?? 0,
    status: props.data?.status ?? 'draft',
    ikhtisar_jabatan: props.data?.ikhtisar_jabatan ?? '',
    informasi,
    uraian: props.data?.uraian_tugas?.length
        ? props.data.uraian_tugas.map(({ uraian_tugas, hasil_kerja, volume, satuan_periode, norma_waktu }) => ({ uraian_tugas, hasil_kerja, volume, satuan_periode: satuan_periode || 'tahun', norma_waktu }))
        : [kosong()],
});

const wke = computed(() => form.waktu_kerja_efektif || props.wkeDefault);
const beban = (r) => (Number(r.volume) || 0) * (props.periodePerTahun[r.satuan_periode] || 1) * (Number(r.norma_waktu) || 0);
const total = computed(() => form.uraian.reduce((t, r) => t + beban(r), 0));
// sama dengan pembulatan server (PHP_ROUND_HALF_UP)
const kebutuhan = computed(() => Math.round(total.value / wke.value));
const proyeksi = computed(() => {
    const now = new Date().getFullYear();
    return Array.from({ length: 5 }, (_, i) => {
        const t = now + 1 + i;
        const n = Math.max(0, t - form.tahun);
        return { tahun: t, kebutuhan: Math.round((total.value * Math.pow(1 + (form.pertumbuhan_beban || 0) / 100, n)) / wke.value) };
    });
});

const tambah = () => form.uraian.push(kosong());
const isiKelas = () => {
    const j = props.jabatanOptions.find((x) => x.id === form.jabatan_id);
    if (j?.kelas_jabatan && !form.kelas_jabatan) form.kelas_jabatan = j.kelas_jabatan;
};
const gantiInstansi = () => {
    form.unit_kerja_id = '';
    router.reload({ data: { instansi_id: form.instansi_id }, only: ['unitOptions'] });
};
const submit = () => {
    const opts = { onError: (e) => { if (Object.keys(e).some((k) => k.startsWith('uraian'))) tab.value = 'abk'; } };
    return props.data ? form.put(`/anjab/${props.data.id}`, opts) : form.post('/anjab', opts);
};
</script>
