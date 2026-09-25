<template>
    <div>
        <label v-if="label" class="form-label">{{ label }}</label>
        <slot />
        <div v-if="error" class="invalid-feedback d-block">{{ error }}</div>
        <div v-if="help" class="form-text">{{ help }}</div>
    </div>
</template>

<script setup>
defineProps({ label: String, error: String, help: String });
</script>
