<template>
    <Head title="Alias Instansi" />
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <div class="page-title">Alias Instansi</div>
            <div class="page-subtitle">Nama instansi sebagaimana tertulis di sumber data, dipetakan ke referensi instansi BKN. Beberapa alias boleh menuju satu instansi, sehingga instansi yang berganti nama atau ditulis berbeda tetap menyatu.</div>
        </div>
        <button class="btn btn-primary" @click="buka()"><i class="fa fa-plus-circle me-1"></i> Tambah alias</button>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><StatCard label="Alias" :value="ringkasan.total" icon="fa-list" color="#2a78d6" /></div>
        <div class="col-6 col-lg-3"><StatCard label="Belum dipetakan" :value="ringkasan.belum_cocok" icon="fa-chain-broken" color="#b77900" /></div>
    </div>

    <div class="card mb-2"><div class="card-body py-2 d-flex flex-wrap gap-2 align-items-center">
        <input v-model="f.q" class="form-control form-control-sm w-auto" placeholder="Cari nama..." @keyup.enter="go">
        <label class="small mb-0"><input v-model="f.belum_cocok" type="checkbox" class="form-check-input me-1" @change="go">Hanya yang belum dipetakan</label>
        <button class="btn btn-sm btn-primary" @click="go"><i class="fa fa-search"></i></button>
    </div></div>

    <div class="card">
        <div class="table-responsive"><table class="table table-hover mb-0">
            <thead><tr><th>Nama di sumber</th><th>Referensi instansi BKN</th><th>Pokja</th><th>Wilayah kerja</th><th class="num">Dipakai</th><th>Asal</th><th></th></tr></thead>
            <tbody>
                <tr v-for="a in datas.data" :key="a.id">
                    <td>{{ a.nama }}</td>
                    <td><span v-if="a.ref_instansi">{{ a.ref_instansi.nama }}</span><span v-else class="badge bg-warning text-dark">belum dipetakan</span></td>
                    <td>{{ a.pokja || '-' }}</td><td class="small">{{ a.wilayah_kerja || '-' }}</td><td class="num">{{ a.rekomendasis_count }}</td><td class="small text-capitalize">{{ a.asal }}</td>
                    <td class="text-nowrap"><button class="btn btn-sm btn-light" @click="buka(a)"><i class="fa fa-pencil"></i></button>
                        <button class="btn btn-sm btn-light text-danger" :disabled="a.rekomendasis_count > 0" :title="a.rekomendasis_count ? 'Sudah dipakai' : 'Hapus'" @click="destroy(`/alias-instansi/${a.id}`, 'Hapus alias ini?')"><i class="fa fa-trash"></i></button></td>
                </tr>
                <tr v-if="!datas.data.length"><td colspan="7" class="text-center text-muted py-4">Tidak ada alias.</td></tr>
            </tbody>
        </table></div>
        <div class="card-body pt-2"><Pagination :links="datas.links" /></div>
    </div>

    <Modal :show="!!dlg" :title="dlg?.id ? 'Ubah alias' : 'Tambah alias'" @close="dlg = null">
        <form v-if="dlg" id="form-alias" class="row g-2" @submit.prevent="simpan">
            <Field class="col-12" label="Nama di sumber" :error="err.nama"><input v-model="form.nama" class="form-control" :class="{ 'is-invalid': err.nama }"></Field>
            <Field class="col-12" label="Referensi instansi BKN" :error="err.ref_instansi_id" help="Ketik untuk mencari lalu pilih dari daftar; kosongkan untuk melepas pemetaan.">
                <input v-model="refNama" class="form-control" list="ref-opsi" placeholder="Ketik nama instansi..." :class="{ 'is-invalid': err.ref_instansi_id }">
                <datalist id="ref-opsi"><option v-for="r in refOptions" :key="r.id" :value="r.nama"></option></datalist>
            </Field>
            <Field class="col-md-4" label="Pokja (opsional)" :error="err.pokja"><input v-model="form.pokja" class="form-control"></Field>
            <Field class="col-md-8" label="Wilayah kerja (opsional)" :error="err.wilayah_kerja"><input v-model="form.wilayah_kerja" class="form-control"></Field>
        </form>
        <template #footer><button class="btn btn-light" @click="dlg = null">Batal</button><button class="btn btn-primary" form="form-alias" :disabled="proses">Simpan</button></template>
    </Modal>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import Modal from '../../Components/Modal.vue';
import Field from '../../Components/Field.vue';
import StatCard from '../../Components/StatCard.vue';
import Pagination from '../../Components/Pagination.vue';
import { destroy } from '../../confirm';

const props = defineProps({ datas: Object, filters: Object, ringkasan: Object, refOptions: Array });
const f = reactive({ q: '', belum_cocok: false, ...props.filters });
const go = () => router.get('/alias-instansi', Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true, replace: true });

const dlg = ref(null);
const form = reactive({ nama: '', pokja: '', wilayah_kerja: '' });
const refNama = ref('');
const err = ref({});
const proses = ref(false);
const refId = computed(() => props.refOptions.find((r) => r.nama === refNama.value)?.id ?? null);

const buka = (a = null) => {
    Object.assign(form, { nama: a?.nama ?? '', pokja: a?.pokja ?? '', wilayah_kerja: a?.wilayah_kerja ?? '' });
    refNama.value = a?.ref_instansi?.nama ?? '';
    err.value = {};
    dlg.value = { id: a?.id ?? null };
};
const simpan = () => {
    if (refNama.value && !refId.value) return (err.value = { ref_instansi_id: 'Pilih nama yang ada di daftar.' });
    proses.value = true;
    const data = { ...form, ref_instansi_id: refId.value };
    const opsi = { preserveScroll: true, onError: (e) => (err.value = e), onSuccess: () => (dlg.value = null), onFinish: () => (proses.value = false) };
    dlg.value.id ? router.put(`/alias-instansi/${dlg.value.id}`, data, opsi) : router.post('/alias-instansi', data, opsi);
};
</script>
