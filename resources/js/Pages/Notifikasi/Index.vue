<template>
    <Head title="Notifikasi" />
    <div class="d-flex justify-content-between align-items-start">
        <div><div class="page-title">Notifikasi</div><div class="page-subtitle">Pemberitahuan alur usulan kebutuhan.</div></div>
        <button class="btn btn-sm btn-outline-primary" @click="router.post('/notifikasi/baca-semua', {}, { preserveScroll: true })"><i class="fa fa-check me-1"></i> Tandai semua dibaca</button>
    </div>
    <div class="card">
        <ul class="list-group list-group-flush">
            <li v-for="n in datas.data" :key="n.id" class="list-group-item" :class="{ 'bg-light': !n.read_at }">
                <a :href="`/notifikasi/${n.id}`" class="text-decoration-none text-reset d-block">
                    <div class="d-flex justify-content-between"><b>{{ n.data.judul }}</b><span class="small text-muted">{{ waktu(n.created_at) }}</span></div>
                    <div class="small">{{ n.data.pesan }}</div>
                    <div v-if="n.data.catatan" class="small fst-italic text-muted">"{{ n.data.catatan }}"</div>
                    <div class="small text-muted" v-if="n.data.oleh">oleh {{ n.data.oleh }}</div>
                </a>
            </li>
            <li v-if="!datas.data.length" class="list-group-item text-muted">Belum ada notifikasi.</li>
        </ul>
        <div class="card-body pt-0"><Pagination :links="datas.links" /></div>
    </div>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3';
import Pagination from '../../Components/Pagination.vue';
import { waktu } from '../../utils';

defineProps({ datas: Object });
</script>
