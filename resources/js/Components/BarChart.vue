<template>
    <div class="chart-box" :style="{ height: height + 'px' }">
        <Bar :data="chartData" :options="chartOptions" />
    </div>
</template>

<script>
import { Bar } from 'vue-chartjs';
import { Chart as ChartJS, BarElement, CategoryScale, LinearScale, Tooltip, Legend } from 'chart.js';
import { num } from '../utils';

ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip, Legend);

export default {
    components: { Bar },
    props: {
        labels: Array,
        datasets: Array, // [{ label, data, color }]
        horizontal: Boolean,
        stacked: Boolean,
        height: { type: Number, default: 320 },
    },
    computed: {
        chartData() {
            return {
                labels: this.labels,
                datasets: this.datasets.map((d) => ({
                    label: d.label,
                    data: d.data,
                    backgroundColor: d.color,
                    borderRadius: 4,
                    borderSkipped: 'start',
                    maxBarThickness: 28,
                    // celah 2px antar batang
                    borderColor: '#ffffff',
                    borderWidth: 1,
                })),
            };
        },
        chartOptions() {
            const valueAxis = {
                stacked: this.stacked,
                grid: { color: '#e1e0d9' },
                border: { color: '#c3c2b7' },
                ticks: { color: '#52514e', callback: (v) => num(Math.abs(v)) },
            };
            const catAxis = {
                stacked: this.stacked,
                grid: { display: false },
                ticks: { color: '#52514e', autoSkip: false, callback(v) {
                    const l = this.getLabelForValue(v);
                    return l && l.length > 28 ? l.slice(0, 26) + '…' : l;
                } },
            };
            return {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: this.horizontal ? 'y' : 'x',
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top', align: 'start', labels: { usePointStyle: true, pointStyle: 'rectRounded', color: '#0b0b0b' } },
                    tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${num(Math.abs(ctx.raw))}` } },
                },
                scales: this.horizontal ? { x: valueAxis, y: catAxis } : { x: catAxis, y: valueAxis },
            };
        },
    },
};
</script>
