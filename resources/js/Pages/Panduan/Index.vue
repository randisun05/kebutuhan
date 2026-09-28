<template>
    <Head title="Panduan Pengguna" />
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <div class="page-title">Panduan Pengguna</div>
            <div class="page-subtitle">Versi {{ versi }} · panduan ini disimpan bersama kode aplikasi dan diperbarui setiap ada perubahan fitur.</div>
        </div>
        <div class="d-flex gap-2">
            <Link href="/panduan/perubahan" class="btn btn-outline-primary btn-sm"><i class="fa fa-bullhorn me-1"></i> Apa yang baru
                <span v-if="$page.props.panduan?.baru" class="badge bg-danger ms-1">baru</span></Link>
            <a href="/panduan/unduh/pdf" class="btn btn-outline-danger btn-sm"><i class="fa fa-file-pdf-o me-1"></i> Unduh PDF</a>
        </div>
    </div>

    <form class="mb-3" @submit.prevent="router.get('/panduan', q ? { q } : {}, { preserveState: true })">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-search"></i></span>
            <input v-model="q" class="form-control" placeholder="Cari di panduan, misal: finalkan ABK, impor pegawai, SSO…">
            <button class="btn btn-primary">Cari</button>
        </div>
    </form>

    <div v-if="props.q" class="card mb-3">
        <div class="card-header">Hasil pencarian "{{ props.q }}"</div>
        <div class="list-group list-group-flush">
            <Link v-for="h in hasilCari" :key="h.slug" :href="`/panduan/${h.slug}`" class="list-group-item list-group-item-action">
                <div class="fw-semibold">{{ h.judul }}</div><div class="small text-muted">{{ h.cuplikan }}</div>
            </Link>
            <div v-if="!hasilCari.length" class="list-group-item text-muted small">Tidak ditemukan (minimal 3 huruf).</div>
        </div>
    </div>

    <div class="form-check form-switch mb-2">
        <input id="saya" v-model="hanyaSaya" type="checkbox" class="form-check-input">
        <label for="saya" class="form-check-label small">Hanya yang relevan untuk peran saya ({{ $page.props.auth.user.role_label }})</label>
    </div>

    <div v-for="(list, bagian) in perBagian" :key="bagian" class="mb-3">
        <div class="menu-label text-muted small text-uppercase fw-semibold mb-2">{{ bagian }}</div>
        <div class="row g-3">
            <div v-for="h in list" :key="h.slug" class="col-md-6 col-xl-4">
                <Link :href="`/panduan/${h.slug}`" class="card h-100 text-decoration-none text-reset">
                    <div class="card-body">
                        <div class="fw-semibold text-primary">{{ h.judul }}</div>
                        <div class="small text-muted">{{ h.ringkas }}</div>
                    </div>
                </Link>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({ halaman: Array, q: String, hasilCari: Array, versi: String });
const q = ref(props.q || '');
const hanyaSaya = ref(false);
const perBagian = computed(() => props.halaman
    .filter((h) => !hanyaSaya.value || h.untuk_saya)
    .reduce((acc, h) => ((acc[h.bagian] ||= []).push(h), acc), {}));
</script>
