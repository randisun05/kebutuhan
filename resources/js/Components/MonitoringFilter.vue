<template>
    <div class="card mb-3">
        <div class="card-body py-2">
            <form class="d-flex flex-wrap gap-2 align-items-center" @submit.prevent="apply">
                <i class="fa fa-filter text-muted"></i>
                <select v-model="form.jenis" class="form-select form-select-sm w-auto" @change="apply">
                    <option value="">Semua jenis jabatan</option>
                    <option v-for="j in jenisOptions" :key="j.value" :value="j.value">{{ j.label }}</option>
                </select>
                <select v-model="form.status" class="form-select form-select-sm w-auto" @change="apply">
                    <option value="">Semua kondisi posisi</option>
                    <option value="kurang">Kurang</option>
                    <option value="kosong">Kosong (existing 0)</option>
                    <option value="lebih">Lebih / Gemuk</option>
                    <option value="sesuai">Sesuai</option>
                    <option value="tanpa_abk">Belum ada ABK</option>
                </select>
                <input v-model="form.q" class="form-control form-control-sm w-auto" placeholder="Cari nama jabatan…">
                <slot />
                <button class="btn btn-sm btn-primary"><i class="fa fa-search"></i></button>
                <button type="button" class="btn btn-sm btn-light" @click="reset">Reset</button>
                <a :href="exportUrl" class="btn btn-sm btn-success ms-auto"><i class="fa fa-file-excel-o me-1"></i> Export Excel</a>
            </form>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({ filters: Object, jenisOptions: Array, url: String, extra: Object, instansiId: Number });
const form = reactive({ jenis: props.filters.jenis || '', status: props.filters.status || '', q: props.filters.q || '' });

const params = () => ({ ...props.extra, ...Object.fromEntries(Object.entries(form).filter(([, v]) => v)) });
const apply = () => router.get(props.url, params(), { preserveState: true, replace: true });
const reset = () => { form.jenis = ''; form.status = ''; form.q = ''; apply(); };
const exportUrl = computed(() => '/monitoring/export?' + new URLSearchParams({ ...params(), ...(props.instansiId ? { instansi_id: props.instansiId } : {}) }).toString());
</script>
