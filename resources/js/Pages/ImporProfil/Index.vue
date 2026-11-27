<template>
    <Head title="Profil Impor" />
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <div class="page-title">Profil Impor</div>
            <div class="page-subtitle">Tata letak berkas Excel sumber (sheet, kolom, blok angka) disimpan sebagai data. Bila tim data mengubah susunan kolom, cukup ubah profilnya; kode aplikasi tidak perlu disentuh.</div>
        </div>
        <Link href="/impor-profil/create" class="btn btn-primary"><i class="fa fa-plus-circle me-1"></i> Tambah profil</Link>
    </div>
    <div class="card">
        <div class="table-responsive"><table class="table table-hover mb-0">
            <thead><tr><th>Kode</th><th>Nama</th><th>Instansi pembina</th><th>Sheet</th><th>Blok angka</th><th>Status</th><th></th></tr></thead>
            <tbody>
                <tr v-for="d in datas" :key="d.id">
                    <td class="small">{{ d.kode }}</td><td>{{ d.nama }}</td><td class="small">{{ d.pembina?.nama }}</td><td class="small">{{ d.sheet }}</td>
                    <td><span v-for="b in d.pemetaan.blok" :key="b.sumber + b.kolom_awal" class="badge bg-light text-dark border me-1">{{ b.sumber }} @ {{ b.kolom_awal }}</span></td>
                    <td><span class="badge" :class="d.is_active ? 'bg-success' : 'bg-secondary'">{{ d.is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td class="text-nowrap"><Link :href="`/impor-profil/${d.id}/edit`" class="btn btn-sm btn-light"><i class="fa fa-pencil"></i></Link>
                        <button class="btn btn-sm btn-light text-danger" @click="destroy(`/impor-profil/${d.id}`, 'Hapus profil impor ini?')"><i class="fa fa-trash"></i></button></td>
                </tr>
                <tr v-if="!datas.length"><td colspan="7" class="text-center text-muted py-4">Belum ada profil impor.</td></tr>
            </tbody>
        </table></div>
    </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { destroy } from '../../confirm';

defineProps({ datas: Array });
</script>
