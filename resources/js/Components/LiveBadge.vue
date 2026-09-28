<template>
    <span class="small text-muted d-inline-flex align-items-center gap-2">
        <span class="live-dot"></span>
        Real-time · diperbarui {{ time }}
        <button class="btn btn-sm btn-link p-0" title="Muat ulang sekarang" @click="$emit('refresh')"><i class="fa fa-refresh"></i></button>
    </span>
</template>

<script>
export default {
    props: { updatedAt: String },
    emits: ['refresh'],
    computed: {
        time() {
            return this.updatedAt ? new Date(this.updatedAt).toLocaleTimeString('id-ID') : '-';
        },
    },
};
</script>
