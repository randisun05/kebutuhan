<template>
    <div class="chart-box" :style="{ height: height + 'px' }">
        <Line :data="chartData" :options="options" />
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import { Chart as ChartJS, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Legend } from 'chart.js';
import { num } from '../utils';

ChartJS.register(LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Legend);

const props = defineProps({ labels: Array, datasets: Array, height: { type: Number, default: 300 } });

const chartData = computed(() => ({
    labels: props.labels,
    datasets: props.datasets.map((d) => ({
        label: d.label, data: d.data, borderColor: d.color, backgroundColor: d.color,
        borderWidth: 2, pointRadius: 4, pointHoverRadius: 6, tension: 0.25, spanGaps: false,
        borderDash: d.dash || [],
    })),
}));

const options = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        legend: { position: 'top', align: 'start', labels: { usePointStyle: true, color: '#0b0b0b' } },
        tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ${c.raw === null ? 'belum ada data' : num(c.raw)}` } },
    },
    scales: {
        x: { grid: { display: false }, ticks: { color: '#52514e' } },
        y: { grid: { color: '#e1e0d9' }, border: { color: '#c3c2b7' }, ticks: { color: '#52514e', callback: (v) => num(v) } },
    },
};
</script>
