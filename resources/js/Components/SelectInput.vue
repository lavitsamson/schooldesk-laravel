<script setup lang="ts">
const model = defineModel<string>({ required: true });
</script>

<template>
    <select
        v-model="model"
        class="rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
    >
        <slot />
    </select>
</template>
