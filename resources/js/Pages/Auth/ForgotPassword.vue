<template>
    <Head title="Lupa Password" />
    <AuthCard subtitle="Masukkan email akun; tautan reset password akan dikirim.">
        <form @submit.prevent="form.post('/forgot-password')">
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input v-model="form.email" type="email" class="form-control" :class="{ 'is-invalid': form.errors.email }" autofocus>
                <div class="invalid-feedback">{{ form.errors.email }}</div>
            </div>
            <button class="btn btn-primary w-100" :disabled="form.processing">Kirim tautan reset</button>
            <div class="text-center mt-3"><Link href="/login" class="small">Kembali ke login</Link></div>
        </form>
    </AuthCard>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthCard from '../../Components/AuthCard.vue';

defineOptions({ layout: null });
const form = useForm({ email: '' });
</script>
