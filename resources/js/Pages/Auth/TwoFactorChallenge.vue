<template>
    <Head title="Verifikasi Dua Faktor" />
    <AuthCard :subtitle="recovery ? 'Masukkan salah satu kode pemulihan' : 'Masukkan 6 digit kode dari aplikasi autentikator'">
        <form @submit.prevent="submit">
            <div class="mb-3" v-if="!recovery">
                <input v-model="form.code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" class="form-control form-control-lg text-center" :class="{ 'is-invalid': form.errors.code }" autofocus>
                <div class="invalid-feedback">{{ form.errors.code }}</div>
            </div>
            <div class="mb-3" v-else>
                <input v-model="form.recovery_code" class="form-control" :class="{ 'is-invalid': form.errors.recovery_code }">
                <div class="invalid-feedback">{{ form.errors.recovery_code }}</div>
            </div>
            <button class="btn btn-primary w-100" :disabled="form.processing">Verifikasi</button>
            <div class="text-center mt-3"><a href="#" class="small" @click.prevent="recovery = !recovery">{{ recovery ? 'Gunakan kode autentikator' : 'Gunakan kode pemulihan' }}</a></div>
        </form>
    </AuthCard>
</template>

<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthCard from '../../Components/AuthCard.vue';

defineOptions({ layout: null });
const recovery = ref(false);
const form = useForm({ code: '', recovery_code: '' });
const submit = () => form.transform((d) => (recovery.value ? { recovery_code: d.recovery_code } : { code: d.code })).post('/two-factor-challenge');
</script>
