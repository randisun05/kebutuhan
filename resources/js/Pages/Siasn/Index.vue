<template>
    <Head title="Integrasi SIASN" />
    <div class="page-title">Integrasi SIASN BKN</div>
    <div class="page-subtitle">Tarik unit organisasi (unor) dan data utama PNS dari SIASN untuk memperbarui data existing secara otomatis. Input manual dan impor Excel tetap bisa dipakai.</div>

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">Status koneksi</div>
                <div class="card-body small">
                    <div class="mb-2">
                        <span v-if="configured" class="kondisi kondisi-ideal"><i class="fa fa-check"></i> Terkonfigurasi</span>
                        <span v-else class="kondisi kondisi-kurang"><i class="fa fa-times"></i> Belum dikonfigurasi</span>
                    </div>
                    <div><span class="text-muted">Mode:</span> {{ mode }}</div>
                    <div class="text-break"><span class="text-muted">Base URL:</span> {{ baseUrl }}</div>
                    <div><span class="text-muted">Sinkron terjadwal (01.00):</span> {{ scheduled ? 'aktif' : 'nonaktif' }}</div>
                    <p v-if="!configured" class="mt-2 mb-0">Isi <code>SIASN_ENABLED</code>, <code>SIASN_APIM_USERNAME/PASSWORD</code>, dan <code>SIASN_SSO_CLIENT_ID/USERNAME/PASSWORD</code> di <code>.env</code>. Kredensial dan whitelist IP diajukan ke BKN.</p>
                    <button v-if="isAdmin && configured" class="btn btn-sm btn-outline-primary mt-2" @click="router.post('/siasn/test')"><i class="fa fa-plug me-1"></i> Uji koneksi</button>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">Pemetaan kode</div>
                <div class="card-body small">
                    <div class="mb-2">Unit kerja terpetakan ke unor SIASN
                        <div class="fw-semibold">{{ pemetaan.unit_terpetakan }} / {{ pemetaan.unit_total }}</div>
                        <div class="progress progress-thin"><div class="progress-bar bg-success" :style="{ width: pct(pemetaan.unit_terpetakan, pemetaan.unit_total) }"></div></div>
                    </div>
                    <div class="mb-2">Jabatan terpetakan ke ID jabatan SIASN
                        <div class="fw-semibold">{{ pemetaan.jabatan_terpetakan }} / {{ pemetaan.jabatan_total }}</div>
                        <div class="progress progress-thin"><div class="progress-bar bg-success" :style="{ width: pct(pemetaan.jabatan_terpetakan, pemetaan.jabatan_total) }"></div></div>
                    </div>
                    <p class="mb-0 text-muted">Pegawai baru hanya dibuat bila unor & jabatannya sudah terpetakan. Jalankan sinkron unor lebih dulu, lalu isi ID SIASN pada referensi jabatan (atau samakan nama jabatannya).</p>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">Jalankan sinkronisasi</div>
                <div class="card-body">
                    <form @submit.prevent="submit" class="d-grid gap-2">
                        <select v-model="form.instansi_id" class="form-select form-select-sm" @change="router.get('/siasn', { instansi_id: form.instansi_id }, { preserveState: true })">
                            <option v-for="i in instansiOptions" :key="i.id" :value="i.id">{{ i.nama }}</option>
                        </select>
                        <div class="small text-muted" v-if="instansi">ID instansi SIASN: {{ instansi.siasn_instansi_id || 'belum diisi (semua unor akan ditarik)' }}</div>
                        <select v-model="form.jenis" class="form-select form-select-sm">
                            <option value="unor">Unit organisasi (unor)</option>
                            <option value="pegawai">Data pegawai (data utama PNS)</option>
                        </select>
                        <textarea v-if="form.jenis === 'pegawai'" v-model="form.nips" rows="3" class="form-control form-control-sm" placeholder="Opsional: daftar NIP (pisahkan baris/koma). Kosong = semua PNS aktif instansi."></textarea>
                        <button class="btn btn-primary btn-sm" :disabled="form.processing || !configured"><i class="fa fa-refresh me-1"></i> Sinkronkan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between"><span>Riwayat sinkronisasi</span>
            <button class="btn btn-sm btn-link p-0" @click="router.reload({ only: ['logs'] })"><i class="fa fa-refresh"></i></button></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Waktu</th><th>Instansi</th><th>Jenis</th><th>Status</th><th class="num">Total</th><th class="num">Berhasil</th><th class="num">Gagal</th><th>Oleh</th><th></th></tr></thead>
                <tbody>
                    <template v-for="l in logs" :key="l.id">
                        <tr>
                            <td class="small">{{ waktu(l.started_at) }}</td>
                            <td>{{ l.instansi?.nama }}</td>
                            <td>{{ l.jenis }}</td>
                            <td><span class="badge" :class="{ selesai: 'bg-success', gagal: 'bg-danger', berjalan: 'bg-info' }[l.status]">{{ l.status }}</span></td>
                            <td class="num">{{ l.total }}</td><td class="num">{{ l.berhasil }}</td><td class="num">{{ l.gagal }}</td>
                            <td class="small">{{ l.user?.name || 'terjadwal' }}</td>
                            <td><button v-if="l.pesan?.length" class="btn btn-sm btn-light" @click="open = open === l.id ? null : l.id"><i class="fa fa-list"></i> {{ l.pesan.length }}</button></td>
                        </tr>
                        <tr v-if="open === l.id"><td colspan="9" class="bg-light small"><ul class="mb-0"><li v-for="(p, i) in l.pesan" :key="i">{{ p }}</li></ul></td></tr>
                    </template>
                    <tr v-if="!logs.length"><td colspan="9" class="text-center text-muted py-4">Belum ada sinkronisasi.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { waktu } from '../../utils';

const props = defineProps({ configured: Boolean, mode: String, baseUrl: String, scheduled: Boolean, instansiOptions: Array, instansiId: Number, instansi: Object, pemetaan: Object, logs: Array });
const isAdmin = computed(() => usePage().props.auth.user.role === 'admin');
const open = ref(null);
const form = useForm({ instansi_id: props.instansiId, jenis: 'unor', nips: '' });
const pct = (a, b) => (b ? Math.round((a / b) * 100) : 0) + '%';
const submit = () => form.post('/siasn/sync', { preserveScroll: true });
</script>
