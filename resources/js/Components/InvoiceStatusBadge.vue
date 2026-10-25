<script setup lang="ts">
import type { InvoiceStatus } from '@/types/school';
import { computed } from 'vue';

const props = defineProps<{
    status: InvoiceStatus;
    label: string;
    overdue?: boolean;
}>();

// Text says the status, so colour is never the only signal.
const classes = computed(() => {
    if (props.overdue) {
        return 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200';
    }

    return {
        issued: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        partially_paid:
            'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
        paid: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
        void: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    }[props.status];
});
</script>

<template>
    <span
        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap"
        :class="classes"
    >
        {{ label }}<template v-if="overdue"> · {{ $t('Overdue') }}</template>
    </span>
</template>
