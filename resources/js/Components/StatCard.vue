<template>
    <div class="card stat-card h-100">
        <div class="card-body d-flex justify-content-between align-items-start">
            <div>
                <div class="label">{{ label }}</div>
                <div class="value" :class="valueClass">{{ formatted }}</div>
                <div class="hint" v-if="hint">{{ hint }}</div>
            </div>
            <div class="icon" :style="{ background: tint, color }"><i class="fa" :class="icon"></i></div>
        </div>
    </div>
</template>

<script>
import { num } from '../utils';

export default {
    props: {
        label: String, value: [Number, String], hint: String, icon: { type: String, default: 'fa-circle' },
        color: { type: String, default: '#0d6efd' }, valueClass: String, suffix: String,
    },
    computed: {
        formatted() {
            if (this.value === null || this.value === undefined) return '-';
            return typeof this.value === 'number' ? num(this.value) + (this.suffix || '') : this.value;
        },
        tint() {
            return this.color + '1a';
        },
    },
};
</script>
