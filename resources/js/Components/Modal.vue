<template>
    <teleport to="body">
        <div v-if="show" class="modal d-block" tabindex="-1" style="background: rgba(0, 0, 0, .45)" @mousedown.self="$emit('close')">
            <div class="modal-dialog modal-dialog-scrollable" :class="size">
                <div class="modal-content">
                    <div class="modal-header py-2"><h6 class="modal-title">{{ title }}</h6><button type="button" class="btn-close" aria-label="Tutup" @click="$emit('close')"></button></div>
                    <div class="modal-body"><slot /></div>
                    <div v-if="$slots.footer" class="modal-footer py-2"><slot name="footer" /></div>
                </div>
            </div>
        </div>
    </teleport>
</template>

<script setup>
defineProps({ show: Boolean, title: String, size: { type: String, default: '' } });
defineEmits(['close']);
</script>
