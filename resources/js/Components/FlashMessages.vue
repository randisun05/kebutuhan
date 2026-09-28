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
        show() {
            const session = this.$page.props.session || {};
            const errors = this.$page.props.errors || {};
            for (const key of Object.keys(ICON)) {
                if (session[key] && this.lastShown !== session[key] + key) {
                    this.lastShown = session[key] + key;
                    Swal.fire({ icon: ICON[key], title: session[key], timer: key === 'success' ? 2200 : undefined, showConfirmButton: key !== 'success' });
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
