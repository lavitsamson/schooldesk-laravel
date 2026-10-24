<script setup lang="ts">
import type { ImportBatch } from '@/types/school';

defineProps<{ batch: Pick<ImportBatch, 'status' | 'status_label'> }>();

const classes: Record<ImportBatch['status'], string> = {
    pending: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
    processing:
        'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200',
    completed:
        'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200',
    failed: 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200',
};
</script>

<template>
    <span
        class="rounded-full px-2 py-0.5 text-xs font-medium"
        :class="classes[batch.status]"
    >
        {{ batch.status_label }}
    </span>
</template>
