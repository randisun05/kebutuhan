<template>
    <Head title="Konfirmasi Password" />
    <AuthCard subtitle="Demi keamanan, konfirmasi password Anda untuk melanjutkan.">
        <form @submit.prevent="form.post('/user/confirm-password', { onFinish: () => form.reset() })">
            <div class="mb-3">
                <input v-model="form.password" type="password" class="form-control" :class="{ 'is-invalid': form.errors.password }" autofocus autocomplete="current-password">
                <div class="invalid-feedback">{{ form.errors.password }}</div>
            </div>
            <button class="btn btn-primary w-100" :disabled="form.processing">Konfirmasi</button>
        </form>
    </AuthCard>
</template>

<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthCard from '../../Components/AuthCard.vue';

defineOptions({ layout: null });
const form = useForm({ password: '' });
</script>
