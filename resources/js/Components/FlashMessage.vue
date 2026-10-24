<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const status = computed(() => usePage().props.flash.status);
</script>

<template>
    <div
        v-if="status"
        class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8"
        role="status"
    >
        <p
            class="rounded-md bg-green-50 px-4 py-3 text-sm font-medium text-green-800 dark:bg-green-900/40 dark:text-green-200"
        >
            {{ status }}
        </p>
    </div>
</template>
