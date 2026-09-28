<template>
    <Head title="Keamanan Akun" />
    <div class="page-title">Keamanan Akun</div>
    <div class="page-subtitle">{{ $page.props.auth.user.name }} · {{ $page.props.auth.user.email }}</div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">Autentikasi dua faktor (2FA)</div>
                <div class="card-body">
                    <div v-if="wajib2fa && !twoFactorConfirmed" class="alert alert-warning small">Peran Anda wajib mengaktifkan 2FA.</div>
                    <p class="small text-muted">Setelah aktif, login memerlukan kode 6 digit dari aplikasi autentikator (Google Authenticator, Microsoft Authenticator, dll.).</p>

                    <template v-if="!twoFactorEnabled">
                        <span class="kondisi kondisi-tanpa_abk mb-3"><i class="fa fa-unlock"></i> Belum aktif</span>
                        <div><button class="btn btn-primary btn-sm" :disabled="busy" @click="aktifkan"><i class="fa fa-shield me-1"></i> Aktifkan 2FA</button></div>
                    </template>

                    <template v-else-if="!twoFactorConfirmed">
                        <p class="small">Pindai QR code berikut dengan aplikasi autentikator, lalu masukkan kode untuk konfirmasi.</p>
                        <div v-html="qr" class="mb-2"></div>
                        <div v-if="secret" class="small text-muted mb-2">Kunci manual: <code>{{ secret }}</code></div>
                        <form class="d-flex gap-2" @submit.prevent="konfirmasi">
                            <input v-model="kode.code" class="form-control form-control-sm" style="max-width: 160px" placeholder="123456" :class="{ 'is-invalid': kode.errors.code }">
                            <button class="btn btn-sm btn-success" :disabled="kode.processing">Konfirmasi</button>
                            <button type="button" class="btn btn-sm btn-light" @click="nonaktifkan">Batal</button>
                        </form>
                        <div class="text-danger small">{{ kode.errors.code }}</div>
                    </template>

                    <template v-else>
                        <span class="kondisi kondisi-ideal mb-3"><i class="fa fa-lock"></i> Aktif</span>
                        <div class="d-flex gap-2 flex-wrap mt-2">
                            <button class="btn btn-sm btn-outline-primary" @click="tampilkanKode">Tampilkan kode pemulihan</button>
                            <button class="btn btn-sm btn-outline-secondary" @click="buatUlangKode">Buat ulang kode pemulihan</button>
                            <button v-if="!wajib2fa" class="btn btn-sm btn-outline-danger" @click="nonaktifkan">Nonaktifkan 2FA</button>
                        </div>
                    </template>

                    <div v-if="recoveryCodes.length" class="mt-3">
                        <div class="small text-muted">Simpan kode pemulihan berikut di tempat aman; masing-masing hanya bisa dipakai sekali.</div>
                        <pre class="bg-light p-2 small mb-0">{{ recoveryCodes.join('\n') }}</pre>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">Ganti password</div>
                <div class="card-body">
                    <form @submit.prevent="gantiPassword" class="d-grid gap-2">
                        <input v-model="pwd.current_password" type="password" class="form-control" placeholder="Password saat ini" autocomplete="current-password" :class="{ 'is-invalid': err.current_password }">
                        <div class="text-danger small" v-if="err.current_password">{{ err.current_password }}</div>
                        <input v-model="pwd.password" type="password" class="form-control" placeholder="Password baru (min. 8 karakter)" autocomplete="new-password" :class="{ 'is-invalid': err.password }">
                        <div class="text-danger small" v-if="err.password">{{ err.password }}</div>
                        <input v-model="pwd.password_confirmation" type="password" class="form-control" placeholder="Ulangi password baru" autocomplete="new-password">
                        <button class="btn btn-primary" :disabled="pwd.processing">Simpan password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({ twoFactorEnabled: Boolean, twoFactorConfirmed: Boolean, wajib2fa: Boolean });
const busy = ref(false);
const qr = ref('');
const secret = ref('');
const recoveryCodes = ref([]);
const kode = useForm({ code: '' });
const pwd = useForm({ current_password: '', password: '', password_confirmation: '' });
const err = computed(() => usePage().props.errors?.updatePassword || {});

const muatQr = async () => {
    qr.value = (await axios.get('/user/two-factor-qr-code')).data.svg;
    secret.value = (await axios.get('/user/two-factor-secret-key')).data.secretKey;
};
if (props.twoFactorEnabled && !props.twoFactorConfirmed) muatQr().catch(() => {});

const aktifkan = () => {
    busy.value = true;
    router.post('/user/two-factor-authentication', {}, {
        preserveScroll: true,
        onSuccess: () => muatQr(),
        onFinish: () => (busy.value = false),
    });
};
const konfirmasi = () => kode.post('/user/confirmed-two-factor-authentication', {
    errorBag: 'confirmTwoFactorAuthentication',
    preserveScroll: true,
    onSuccess: async () => {
        await tampilkanKode();
        Swal.fire({ icon: 'success', title: '2FA aktif', timer: 1800, showConfirmButton: false });
    },
});
const tampilkanKode = async () => {
    recoveryCodes.value = (await axios.get('/user/two-factor-recovery-codes')).data;
};
const buatUlangKode = () => axios.post('/user/two-factor-recovery-codes').then(tampilkanKode);
const nonaktifkan = () => router.delete('/user/two-factor-authentication', { preserveScroll: true, onSuccess: () => { qr.value = ''; recoveryCodes.value = []; } });
const gantiPassword = () => pwd.put('/user/password', {
    errorBag: 'updatePassword',
    preserveScroll: true,
    onSuccess: () => { pwd.reset(); Swal.fire({ icon: 'success', title: 'Password diperbarui', timer: 1800, showConfirmButton: false }); },
});
</script>
