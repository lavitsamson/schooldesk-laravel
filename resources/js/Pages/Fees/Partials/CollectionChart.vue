<script setup lang="ts">
import { useMoney } from '@/composables/money';
import type { FeeClassTotals } from '@/types/school';
import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    Tooltip,
    type ChartData,
    type ChartOptions,
} from 'chart.js';
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';

ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip, Legend);

const props = defineProps<{ classes: FeeClassTotals[] }>();
const money = useMoney();

const dark =
    typeof window !== 'undefined' &&
    window.matchMedia('(prefers-color-scheme: dark)').matches;
const textColor = dark ? '#d1d5db' : '#374151';
const gridColor = dark ? '#374151' : '#e5e7eb';

const data = computed<ChartData<'bar'>>(() => ({
    labels: props.classes.map((c) => c.name),
    datasets: [
        {
            label: 'Collected',
            data: props.classes.map((c) => c.collected),
            backgroundColor: '#16a34a',
            stack: 'fees',
        },
        {
            label: 'Outstanding',
            data: props.classes.map((c) => c.outstanding),
            backgroundColor: '#f59e0b',
            stack: 'fees',
        },
    ],
}));

const options = computed<ChartOptions<'bar'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    animation: false,
    scales: {
        x: {
            stacked: true,
            ticks: { color: textColor },
            grid: { display: false },
        },
        y: {
            stacked: true,
            beginAtZero: true,
            ticks: {
                color: textColor,
                callback: (value) => money(Number(value)),
            },
            grid: { color: gridColor },
        },
    },
    plugins: {
        legend: { labels: { color: textColor } },
        tooltip: {
            callbacks: {
                label: (item) =>
                    `${item.dataset.label}: ${money(Number(item.raw))}`,
            },
        },
    },
}));

// The canvas is not readable by screen readers; the table below has the same numbers.
const summary = computed(
    () =>
        `Stacked bar chart of fees collected and outstanding for ${props.classes.length} classes. The table below lists every class.`,
);
</script>

<template>
    <div class="h-72">
        <Bar :data="data" :options="options" :aria-label="summary" role="img" />
    </div>
</template>
