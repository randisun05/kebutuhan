<template>
    <Head title="Masuk" />
    <div class="min-vh-100 d-flex align-items-center justify-content-center" style="background: linear-gradient(135deg, #0b3d91, #0d6efd);">
        <div class="card shadow-lg" style="width: 100%; max-width: 420px;">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <div class="fs-2 fw-bold text-primary"><i class="fa fa-sitemap"></i> SIMONKEB</div>
                    <div class="text-muted small">Sistem Informasi Monitoring Penyusunan Kebutuhan ASN</div>
                </div>
                <form @submit.prevent="submit">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input v-model="form.email" type="email" class="form-control" :class="{ 'is-invalid': form.errors.email }" autofocus>
                        <div class="invalid-feedback">{{ form.errors.email }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input v-model="form.password" type="password" class="form-control" :class="{ 'is-invalid': form.errors.password }">
                        <div class="invalid-feedback">{{ form.errors.password }}</div>
                    </div>
                    <div class="form-check mb-3">
                        <input v-model="form.remember" class="form-check-input" type="checkbox" id="remember">
                        <label class="form-check-label" for="remember">Ingat saya</label>
                    </div>
                    <button class="btn btn-primary w-100" :disabled="form.processing">
                        <i class="fa fa-sign-in me-1"></i> Masuk
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Head, useForm } from '@inertiajs/vue3';

defineOptions({ layout: null });

const form = useForm({ email: '', password: '', remember: false });
const submit = () => form.post('/login', { onFinish: () => form.reset('password') });
</script>
