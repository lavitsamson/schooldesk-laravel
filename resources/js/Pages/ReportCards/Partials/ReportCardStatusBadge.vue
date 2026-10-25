<script setup lang="ts">
import type { ReportCardStatus } from '@/types/school';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{
    status: ReportCardStatus | null;
}>();

const styles: Record<ReportCardStatus | 'none', [() => string, string]> = {
    none: [
        () => trans('Not generated'),
        'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
    ],
    draft: [
        () => trans('Draft'),
        'bg-amber-100 text-amber-900 dark:bg-amber-900/50 dark:text-amber-100',
    ],
    approved: [
        () => trans('Approved'),
        'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-100',
    ],
    published: [
        () => trans('Published'),
        'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-100',
    ],
};

const style = computed(() => styles[props.status ?? 'none']);
</script>

<template>
    <span
        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap"
        :class="style[1]"
    >
        {{ style[0]() }}
    </span>
</template>
