<template>
    <Head :title="halaman.judul + ' · Panduan'" />
    <div class="row g-4">
        <div class="col-xl-3 d-none d-xl-block no-print">
            <div class="card" style="position: sticky; top: 70px">
                <div class="card-header d-flex justify-content-between"><Link href="/panduan">Panduan</Link><a href="/panduan/unduh/pdf" title="Unduh PDF"><i class="fa fa-file-pdf-o"></i></a></div>
                <div class="list-group list-group-flush small" style="max-height: 75vh; overflow-y: auto">
                    <template v-for="(list, bagian) in perBagian" :key="bagian">
                        <div class="list-group-item bg-light text-uppercase text-muted fw-semibold" style="font-size: .7rem">{{ bagian }}</div>
                        <Link v-for="d in list" :key="d.slug" :href="`/panduan/${d.slug}`" class="list-group-item list-group-item-action" :class="{ active: d.slug === halaman.slug }">{{ d.judul }}</Link>
                    </template>
                </div>
            </div>
        </div>
        <div class="col-xl-9">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                <div>
                    <Link href="/panduan" class="small"><i class="fa fa-angle-left"></i> Semua panduan</Link>
                    <div class="page-title">{{ halaman.judul }}</div>
                    <div class="small text-muted">Diperbarui {{ tanggal(halaman.diperbarui) }}
                        <span v-if="!halaman.peran.includes('semua')"> · untuk: {{ halaman.peran.join(', ') }}</span></div>
                </div>
                <div class="d-flex gap-2 no-print">
                    <Link v-for="m in halaman.menu.slice(0, 2)" :key="m" :href="m" class="btn btn-sm btn-outline-primary">Buka {{ m }} <i class="fa fa-external-link"></i></Link>
                    <button class="btn btn-sm btn-light" @click="print"><i class="fa fa-print"></i></button>
                </div>
            </div>

            <div v-if="halaman.toc.length > 2" class="card mb-3 no-print"><div class="card-body py-2 small">
                <b>Isi:</b>
                <a v-for="t in halaman.toc" :key="t.id" :href="`#${t.id}`" class="ms-2">{{ t.judul }}</a>
            </div></div>

            <div class="card"><div class="card-body panduan-isi" v-html="halaman.html"></div></div>

            <div class="d-flex justify-content-between mt-3 no-print">
                <Link v-if="sebelum" :href="`/panduan/${sebelum.slug}`" class="btn btn-light"><i class="fa fa-angle-left"></i> {{ sebelum.judul }}</Link><span v-else></span>
                <Link v-if="sesudah" :href="`/panduan/${sesudah.slug}`" class="btn btn-light">{{ sesudah.judul }} <i class="fa fa-angle-right"></i></Link>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { tanggal } from '../../utils';

const props = defineProps({ halaman: Object, sebelum: Object, sesudah: Object, daftar: Array });
const perBagian = computed(() => props.daftar.reduce((acc, h) => ((acc[h.bagian] ||= []).push(h), acc), {}));
const print = () => window.print();
</script>
