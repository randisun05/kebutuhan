<template>
    <Head title="Reset Password" />
    <AuthCard subtitle="Buat password baru">
        <form @submit.prevent="form.post('/reset-password', { onFinish: () => form.reset('password', 'password_confirmation') })">
            <div class="mb-3"><label class="form-label">Email</label>
                <input v-model="form.email" type="email" class="form-control" :class="{ 'is-invalid': form.errors.email }"><div class="invalid-feedback">{{ form.errors.email }}</div></div>
            <div class="mb-3"><label class="form-label">Password baru</label>
                <input v-model="form.password" type="password" class="form-control" :class="{ 'is-invalid': form.errors.password }" autocomplete="new-password"><div class="invalid-feedback">{{ form.errors.password }}</div></div>
            <div class="mb-3"><label class="form-label">Ulangi password</label>
                <input v-model="form.password_confirmation" type="password" class="form-control" autocomplete="new-password"></div>
            <button class="btn btn-primary w-100" :disabled="form.processing">Simpan password</button>
        </form>
    </AuthCard>
</template>

<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthCard from '../../Components/AuthCard.vue';

defineOptions({ layout: null });
const props = defineProps({ token: String, email: String });
const form = useForm({ token: props.token, email: props.email || '', password: '', password_confirmation: '' });
</script>
