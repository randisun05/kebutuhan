<template>
    <div class="min-vh-100 d-flex align-items-center justify-content-center p-3" style="background: linear-gradient(135deg, #0b3d91, #0d6efd);">
        <div class="card shadow-lg" style="width: 100%; max-width: 420px;">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <div class="fs-2 fw-bold text-primary"><i class="fa fa-sitemap"></i> SIMONKEB</div>
                    <div class="text-muted small">{{ subtitle }}</div>
                </div>
                <div v-if="$page.props.session?.status || status" class="alert alert-success small">{{ status || $page.props.session.status }}</div>
                <slot />
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({ subtitle: { type: String, default: 'Sistem Informasi Monitoring Penyusunan Kebutuhan ASN' }, status: String });
</script>
