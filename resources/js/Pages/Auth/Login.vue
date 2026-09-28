<template>
    <Head title="Masuk" />
    <AuthCard>
        <div v-if="!loginManual && !ssoSiasn" class="alert alert-warning small">Belum ada metode login yang aktif. Hubungi administrator.</div>
        <div v-if="!loginManual && form.errors.email" class="alert alert-danger small">{{ form.errors.email }}</div>
        <form v-if="loginManual" @submit.prevent="submit">
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input v-model="form.email" type="email" class="form-control" :class="{ 'is-invalid': form.errors.email }" autofocus autocomplete="username">
                <div class="invalid-feedback">{{ form.errors.email }}</div>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input v-model="form.password" type="password" class="form-control" :class="{ 'is-invalid': form.errors.password }" autocomplete="current-password">
                <div class="invalid-feedback">{{ form.errors.password }}</div>
            </div>
            <div class="d-flex justify-content-between mb-3">
                <div class="form-check">
                    <input v-model="form.remember" class="form-check-input" type="checkbox" id="remember">
                    <label class="form-check-label" for="remember">Ingat saya</label>
                </div>
                <Link href="/forgot-password" class="small">Lupa password?</Link>
            </div>
            <button class="btn btn-primary w-100" :disabled="form.processing"><i class="fa fa-sign-in me-1"></i> Masuk</button>
        </form>
        <template v-if="ssoSiasn">
            <div v-if="loginManual" class="text-center text-muted small my-3">atau</div>
            <a href="/auth/siasn/redirect" class="btn w-100" :class="loginManual ? 'btn-outline-primary' : 'btn-primary'"><i class="fa fa-id-card-o me-1"></i> Masuk dengan SSO SIASN</a>
            <div class="form-text text-center">Untuk admin & operator instansi yang NIP-nya terdaftar di SIMONKEB.</div>
        </template>
    </AuthCard>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthCard from '../../Components/AuthCard.vue';

defineOptions({ layout: null });
defineProps({ ssoSiasn: Boolean, loginManual: { type: Boolean, default: true } });
const form = useForm({ email: '', password: '', remember: false });
const submit = () => form.post('/login', { onFinish: () => form.reset('password') });
</script>
