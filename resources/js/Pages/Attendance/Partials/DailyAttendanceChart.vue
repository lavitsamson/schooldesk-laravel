<script setup lang="ts">
import { formatDate } from '@/composables/dates';
import type { AttendanceReport } from '@/types/school';
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

const props = defineProps<{ daily: AttendanceReport['daily'] }>();

// The same colours as the status buttons on the attendance grid.
const series = [
    { key: 'present', label: 'Present', color: '#16a34a' },
    { key: 'late', label: 'Late', color: '#f59e0b' },
    { key: 'excused', label: 'Excused', color: '#0284c7' },
    { key: 'absent', label: 'Absent', color: '#dc2626' },
] as const;

const dark =
    typeof window !== 'undefined' &&
    window.matchMedia('(prefers-color-scheme: dark)').matches;
const textColor = dark ? '#d1d5db' : '#374151';
const gridColor = dark ? '#374151' : '#e5e7eb';

const data = computed<ChartData<'bar'>>(() => ({
    labels: props.daily.map((d) =>
        formatDate(d.date, { weekday: 'short', day: 'numeric' }),
    ),
    datasets: series.map((s) => ({
        label: s.label,
        data: props.daily.map((d) => d[s.key]),
        backgroundColor: s.color,
        stack: 'students',
    })),
}));

const options: ChartOptions<'bar'> = {
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
            ticks: { color: textColor, precision: 0 },
            grid: { color: gridColor },
            title: { display: true, text: 'Students', color: textColor },
        },
    },
    plugins: {
        legend: { labels: { color: textColor } },
    },
};

// The canvas is not readable by screen readers; the table below has the same data.
const summary = computed(
    () =>
        `Stacked bar chart of daily attendance on ${props.daily.length} days. The table below lists every day.`,
);
</script>

<template>
    <div class="h-72">
        <Bar :data="data" :options="options" :aria-label="summary" role="img" />
    </div>
</template>
