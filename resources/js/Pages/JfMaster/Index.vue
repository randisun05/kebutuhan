<template>
    <Head title="Master JF & Sumber" />
    <div class="page-title">Master JF & Sumber</div>
    <div class="page-subtitle">Jabatan pokok, jenjang, dan sumber angka kebutuhan dikelola sebagai data. Menambah JF, jenjang, atau sumber baru cukup lewat halaman ini, tanpa mengubah kode.</div>

    <div v-if="pembinaOptions.length > 1" class="mb-3 d-flex align-items-center gap-2">
        <span class="small text-muted">Instansi pembina:</span>
        <select class="form-select form-select-sm w-auto" :value="pembinaId" @change="router.get('/jf-master', { pembina_id: $event.target.value })"><option v-for="p in pembinaOptions" :key="p.id" :value="p.id">{{ p.nama }}</option></select>
    </div>
    <div v-else-if="pembinaOptions.length === 1" class="mb-3 small text-muted">Instansi pembina: <b>{{ pembinaOptions[0].nama }}</b> — Anda hanya dapat mengelola data milik instansi ini.</div>

    <ul class="nav nav-tabs mb-3">
        <li v-for="t in tabs.filter((x) => !x.admin || bolehAdmin)" :key="t.key" class="nav-item"><a href="#" class="nav-link" :class="{ active: tab === t.key }" @click.prevent="tab = t.key">{{ t.label }}</a></li>
    </ul>

    <!-- Jabatan pokok -->
    <div v-show="tab === 'jabatan'" class="card">
        <div class="card-header d-flex justify-content-between align-items-center"><span>Jabatan pokok</span><button class="btn btn-sm btn-primary" @click="buka('jabatan')"><i class="fa fa-plus-circle me-1"></i> Tambah</button></div>
        <div class="table-responsive"><table class="table table-hover mb-0">
            <thead><tr><th class="num">Urut</th><th>Kode</th><th>Nama</th><th>Rumpun</th><th>Jenjang yang berlaku (posisi kolom)</th><th class="num">Dipakai</th><th>Status</th><th></th></tr></thead>
            <tbody>
                <tr v-for="j in jabatan" :key="j.id">
                    <td class="num">{{ j.urutan }}</td><td class="small">{{ j.kode }}</td><td>{{ j.nama }}</td><td class="text-capitalize">{{ j.rumpun }}</td>
                    <td><span v-for="x in j.jenjangs" :key="x.id" class="badge bg-light text-dark border me-1">{{ x.pivot.urutan_kolom }}. {{ x.nama }}</span><span v-if="!j.jenjangs.length" class="text-muted small">belum ada</span></td>
                    <td class="num">{{ j.rekomendasis_count }}</td>
                    <td><span class="badge" :class="j.is_active ? 'bg-success' : 'bg-secondary'">{{ j.is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td class="text-nowrap">
                        <button class="btn btn-sm btn-light" title="Atur jenjang" @click="bukaJenjang(j)"><i class="fa fa-sort-numeric-asc"></i></button>
                        <button class="btn btn-sm btn-light" title="Ubah" @click="buka('jabatan', j)"><i class="fa fa-pencil"></i></button>
                        <button class="btn btn-sm btn-light text-danger" title="Hapus" @click="destroy(`/jf-master/jabatan-pokok/${j.id}`, 'Hapus jabatan pokok ini?')"><i class="fa fa-trash"></i></button>
                    </td>
                </tr>
            </tbody>
        </table></div>
    </div>

    <!-- Jenjang -->
    <div v-show="tab === 'jenjang'" class="card">
        <div class="card-header d-flex justify-content-between align-items-center"><span>Jenjang <span v-if="!bolehAdmin" class="small text-muted ms-1">(milik bersama; penambahan jenjang oleh administrator)</span></span><button v-if="bolehAdmin" class="btn btn-sm btn-primary" @click="buka('jenjang')"><i class="fa fa-plus-circle me-1"></i> Tambah</button></div>
        <div class="table-responsive"><table class="table table-hover mb-0">
            <thead><tr><th>Rumpun</th><th class="num">Urut</th><th>Kode</th><th>Nama</th><th>Status</th><th v-if="bolehAdmin"></th></tr></thead>
            <tbody>
                <tr v-for="j in jenjang" :key="j.id">
                    <td class="text-capitalize">{{ j.rumpun }}</td><td class="num">{{ j.urutan }}</td><td class="small">{{ j.kode }}</td><td>{{ j.nama }}</td>
                    <td><span class="badge" :class="j.is_active ? 'bg-success' : 'bg-secondary'">{{ j.is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td v-if="bolehAdmin" class="text-nowrap"><button class="btn btn-sm btn-light" @click="buka('jenjang', j)"><i class="fa fa-pencil"></i></button>
                        <button class="btn btn-sm btn-light text-danger" :disabled="j.dipakai" :title="j.dipakai ? 'Masih dipakai' : 'Hapus'" @click="destroy(`/jf-master/jenjang/${j.id}`, 'Hapus jenjang ini?')"><i class="fa fa-trash"></i></button></td>
                </tr>
            </tbody>
        </table></div>
    </div>

    <!-- Sumber data -->
    <div v-show="tab === 'sumber'" class="card">
        <div class="card-header d-flex justify-content-between align-items-center"><span>Sumber angka</span><button class="btn btn-sm btn-primary" @click="buka('sumber')"><i class="fa fa-plus-circle me-1"></i> Tambah</button></div>
        <div class="table-responsive"><table class="table table-hover mb-0">
            <thead><tr><th class="num">Urut</th><th>Kode</th><th>Nama</th><th>Jenis</th><th>Tingkat</th><th>Sifat</th><th>Asal nilai</th><th class="num">Angka</th><th>Status</th><th></th></tr></thead>
            <tbody>
                <tr v-for="s in sumber" :key="s.id">
                    <td class="num">{{ s.urutan }}</td><td class="small">{{ s.kode }}</td><td>{{ s.nama }}<div class="small text-muted">{{ s.keterangan }}</div></td>
                    <td class="text-capitalize">{{ s.jenis }}</td><td>{{ label('tingkat', s.tingkat) }}</td><td>{{ label('sifat', s.sifat) }}</td><td>{{ label('adapter', s.adapter) }}</td>
                    <td class="num">{{ s.jumlah_fakta }}</td>
                    <td><span class="badge" :class="s.is_active ? 'bg-success' : 'bg-secondary'">{{ s.is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td class="text-nowrap"><button class="btn btn-sm btn-light" @click="buka('sumber', s)"><i class="fa fa-pencil"></i></button>
                        <button class="btn btn-sm btn-light text-danger" :disabled="s.jumlah_fakta > 0" :title="s.jumlah_fakta ? 'Sudah punya data' : 'Hapus'" @click="destroy(`/jf-master/sumber/${s.id}`, 'Hapus sumber ini?')"><i class="fa fa-trash"></i></button></td>
                </tr>
            </tbody>
        </table></div>
        <div class="card-body small text-muted">Sumber aktif tampil sebagai kolom di halaman Perbandingan; sumber impor juga di daftar rekomendasi dan dapat dipakai pada profil impor. Asal nilai: <b>impor Excel</b> (diisi lewat profil impor), <b>ABK</b> dan <b>pegawai</b> SIMONKEB (dihitung langsung; perlu kaitan jabatan ke JF), atau <b>API Dashboard</b> (ditarik berkala). Sumber selain impor hanya satu per pembina.</div>
    </div>

    <!-- Instansi pembina (administrator) -->
    <div v-if="bolehAdmin" v-show="tab === 'pembina'" class="card">
        <div class="card-header d-flex justify-content-between align-items-center"><span>Instansi pembina</span><button class="btn btn-sm btn-primary" @click="buka('pembina')"><i class="fa fa-plus-circle me-1"></i> Tambah</button></div>
        <div class="table-responsive"><table class="table table-hover mb-0">
            <thead><tr><th>Kode</th><th>Nama</th><th class="num">JF</th><th class="num">Sumber</th><th class="num">Profil impor</th><th class="num">Pengguna</th><th>Status</th><th></th></tr></thead>
            <tbody>
                <tr v-for="p in pembina" :key="p.id">
                    <td class="small">{{ p.kode }}</td><td>{{ p.nama }}<div class="small text-muted">{{ p.keterangan }}</div></td>
                    <td class="num">{{ p.jabatan_pokok_count }}</td><td class="num">{{ p.sumber_count }}</td><td class="num">{{ p.profil_count }}</td><td class="num">{{ p.users_count }}</td>
                    <td><span class="badge" :class="p.is_active ? 'bg-success' : 'bg-secondary'">{{ p.is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td class="text-nowrap"><button class="btn btn-sm btn-light" @click="buka('pembina', p)"><i class="fa fa-pencil"></i></button>
                        <button class="btn btn-sm btn-light text-danger" @click="destroy(`/jf-master/pembina/${p.id}`, 'Hapus instansi pembina ini?')"><i class="fa fa-trash"></i></button></td>
                </tr>
            </tbody>
        </table></div>
        <div class="card-body small text-muted">Tiap instansi pembina memiliki jabatan pokok, sumber angka, dan profil impor sendiri. Penggunanya (peran <b>Operator Instansi Pembina</b>, dibuat di menu Pengguna) hanya dapat mengakses data instansi pembina itu. Pembina baru memakai metode yang sama.</div>
    </div>

    <!-- Form tambah/ubah -->
    <Modal :show="!!dlg" :title="dlg ? (dlg.id ? 'Ubah ' : 'Tambah ') + dlg.nama : ''" @close="dlg = null">
        <form v-if="dlg" id="form-master" class="row g-2" @submit.prevent="simpan">
            <Field class="col-md-4" label="Kode" :error="err.kode" help="huruf kecil, angka, - atau _"><input v-model="f.kode" class="form-control" :class="{ 'is-invalid': err.kode }" :readonly="dlg.kunciKode"></Field>
            <Field class="col-md-8" label="Nama" :error="err.nama"><input v-model="f.nama" class="form-control" :class="{ 'is-invalid': err.nama }"></Field>
            <Field v-if="['jabatan', 'jenjang'].includes(dlg.tipe)" class="col-md-6" label="Rumpun" :error="err.rumpun"><select v-model="f.rumpun" class="form-select"><option v-for="o in opsi.rumpun" :key="o.value" :value="o.value">{{ o.label }}</option></select></Field>
            <template v-if="dlg.tipe === 'sumber'">
                <Field class="col-md-6" label="Jenis" :error="err.jenis"><select v-model="f.jenis" class="form-select"><option v-for="o in opsi.jenis" :key="o.value" :value="o.value">{{ o.label }}</option></select></Field>
                <Field class="col-md-6" label="Tingkat rinci" :error="err.tingkat"><select v-model="f.tingkat" class="form-select"><option v-for="o in opsi.tingkat" :key="o.value" :value="o.value">{{ o.label }}</option></select></Field>
                <Field class="col-md-6" label="Sifat" :error="err.sifat"><select v-model="f.sifat" class="form-select"><option v-for="o in opsi.sifat" :key="o.value" :value="o.value">{{ o.label }}</option></select></Field>
                <Field v-if="opsi.adapter.length > 1" class="col-md-12" label="Asal nilai" :error="err.adapter"><select v-model="f.adapter" class="form-select"><option v-for="o in opsi.adapter" :key="o.value" :value="o.value">{{ o.label }}</option></select></Field>
            </template>
            <Field v-if="dlg.tipe !== 'pembina'" class="col-md-6" label="Urutan tampil" :error="err.urutan"><input v-model="f.urutan" type="number" min="0" class="form-control"></Field>
            <Field v-if="dlg.tipe !== 'jenjang'" class="col-12" label="Keterangan" :error="err.keterangan"><input v-model="f.keterangan" class="form-control"></Field>
            <div class="col-12 form-check form-switch ms-2"><input id="aktif-master" v-model="f.is_active" type="checkbox" class="form-check-input"><label for="aktif-master" class="form-check-label">Aktif</label></div>
        </form>
        <template #footer><button class="btn btn-light" @click="dlg = null">Batal</button><button class="btn btn-primary" form="form-master" :disabled="proses">Simpan</button></template>
    </Modal>

    <!-- Atur jenjang per jabatan -->
    <Modal :show="!!dj" :title="dj ? 'Jenjang untuk ' + dj.jabatan.nama : ''" @close="dj = null">
        <div v-if="dj">
            <p class="small text-muted">Centang jenjang yang berlaku dan isi <b>posisi kolom</b> pada blok angka di Excel (1 = kolom pertama blok, dst.). Contoh: Pranata hanya tiga jenjang, jadi kolom keempat diabaikan.</p>
            <table class="table table-sm align-middle"><thead><tr><th></th><th>Jenjang</th><th class="num" style="width: 110px">Posisi kolom</th></tr></thead>
                <tbody><tr v-for="x in dj.baris" :key="x.id"><td><input v-model="x.pilih" type="checkbox" class="form-check-input"></td><td>{{ x.nama }} <span v-if="!x.is_active" class="badge bg-secondary">nonaktif</span></td>
                    <td><input v-model="x.urutan_kolom" type="number" min="1" max="12" class="form-control form-control-sm" :disabled="!x.pilih"></td></tr></tbody></table>
        </div>
        <template #footer><button class="btn btn-light" @click="dj = null">Batal</button><button class="btn btn-primary" :disabled="proses" @click="simpanJenjang">Simpan</button></template>
    </Modal>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import Modal from '../../Components/Modal.vue';
import Field from '../../Components/Field.vue';
import { destroy } from '../../confirm';

const props = defineProps({ pembinaOptions: Array, pembinaId: Number, bolehAdmin: Boolean, pembina: { type: Array, default: () => [] }, jabatan: Array, jenjang: Array, sumber: Array, opsi: Object });

const tabs = [{ key: 'jabatan', label: 'Jabatan pokok' }, { key: 'jenjang', label: 'Jenjang' }, { key: 'sumber', label: 'Sumber angka' }, { key: 'pembina', label: 'Instansi pembina', admin: true }];
const tab = ref('jabatan');
const label = (grup, v) => props.opsi[grup].find((o) => o.value === v)?.label || v;

const KONFIG = {
    jabatan: { nama: 'jabatan pokok', url: '/jf-master/jabatan-pokok', awal: () => ({ kode: '', nama: '', rumpun: 'keahlian', urutan: 0, keterangan: '', is_active: true }) },
    jenjang: { nama: 'jenjang', url: '/jf-master/jenjang', awal: () => ({ kode: '', nama: '', rumpun: 'keahlian', urutan: 0, is_active: true }) },
    pembina: { nama: 'instansi pembina', url: '/jf-master/pembina', awal: () => ({ kode: '', nama: '', keterangan: '', is_active: true }) },
    sumber: { nama: 'sumber angka', url: '/jf-master/sumber', awal: () => ({ kode: '', nama: '', jenis: 'kebutuhan', tingkat: 'instansi', sifat: 'foto', adapter: 'fakta', urutan: 0, keterangan: '', is_active: true }) },
};

const dlg = ref(null);
const f = reactive({});
const err = ref({});
const proses = ref(false);

const buka = (tipe, data = null) => {
    const k = KONFIG[tipe];
    Object.keys(f).forEach((x) => delete f[x]);
    Object.assign(f, k.awal(), data ? Object.fromEntries(Object.keys(k.awal()).map((x) => [x, data[x] ?? k.awal()[x]])) : {});
    err.value = {};
    dlg.value = { tipe, id: data?.id ?? null, nama: k.nama, url: k.url, kunciKode: tipe === 'sumber' && !!data && data.jumlah_fakta > 0 };
};

const kirim = (aksi, url, data, selesai) => {
    proses.value = true;
    router[aksi](url, data, {
        preserveScroll: true, onError: (e) => (err.value = e), onSuccess: selesai, onFinish: () => (proses.value = false),
    });
};

const simpan = () => {
    const d = dlg.value;
    // jabatan dan sumber baru dimiliki pembina yang sedang dipilih (pengguna pembina dipaksa ke pembinanya oleh server)
    const milik = ['jabatan', 'sumber'].includes(d.tipe) ? { pembina_id: props.pembinaId } : {};
    kirim(d.id ? 'put' : 'post', d.id ? `${d.url}/${d.id}` : d.url, { ...f, ...milik }, () => (dlg.value = null));
};

const dj = ref(null);
const bukaJenjang = (j) => {
    const semua = props.jenjang.filter((x) => x.rumpun === j.rumpun);
    const ada = Object.fromEntries(j.jenjangs.map((x) => [x.id, x.pivot.urutan_kolom]));
    dj.value = { jabatan: j, baris: semua.map((x) => ({ id: x.id, nama: x.nama, is_active: x.is_active, pilih: x.id in ada, urutan_kolom: ada[x.id] ?? '' })) };
};
const simpanJenjang = () => {
    const pilih = dj.value.baris.filter((x) => x.pilih).map((x) => ({ jenjang_id: x.id, urutan_kolom: Number(x.urutan_kolom) }));
    kirim('put', `/jf-master/jabatan-pokok/${dj.value.jabatan.id}/jenjang`, { jenjang: pilih }, () => (dj.value = null));
};
</script>
