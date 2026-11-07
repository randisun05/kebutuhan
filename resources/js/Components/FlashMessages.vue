<template><span class="d-none"></span></template>

<script>
import Swal from 'sweetalert2';
import { router } from '@inertiajs/vue3';

const ICON = { success: 'success', error: 'error', warning: 'warning', info: 'info' };

export default {
    mounted() {
        this.show();
        this.off = router.on('finish', () => this.$nextTick(this.show));
    },
    unmounted() {
        this.off && this.off();
    },
    methods: {
        // sukses & info: toast kecil di pojok (tidak menutupi halaman); peringatan & galat: dialog yang harus dibaca
        tampilkan(jenis, pesan) {
            if (jenis === 'success' || jenis === 'info') {
                Swal.fire({
                    toast: true, position: 'top-end', icon: ICON[jenis], title: pesan, showConfirmButton: false, showCloseButton: true,
                    timer: Math.min(9000, 3500 + pesan.length * 40), timerProgressBar: true, width: 'min(440px, 92vw)',
                    customClass: { title: 'text-start fs-6 fw-normal' },
                });
                return;
            }
            Swal.fire({ icon: ICON[jenis], title: pesan, customClass: { title: 'fs-6 fw-normal' }, confirmButtonText: 'Mengerti' });
        },
        show() {
            const session = this.$page.props.session || {};
            const errors = this.$page.props.errors || {};
            for (const key of Object.keys(ICON)) {
                if (session[key] && this.lastShown !== session[key] + key) {
                    this.lastShown = session[key] + key;
                    this.tampilkan(key, session[key]);
                    return;
                }
            }
            // error alur kerja (bukan error field form) ditampilkan sebagai popup
            const flowError = errors.aksi || errors.details || errors.catatan || errors.nomor_sk;
            if (flowError && this.lastShown !== flowError) {
                this.lastShown = flowError;
                Swal.fire({ icon: 'error', title: 'Gagal', text: flowError });
            }
        },
    },
};
</script>
