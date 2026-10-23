<script setup lang="ts">
import { computed } from 'vue';

const emit = defineEmits<{ 'update:checked': [checked: boolean] }>();

const props = defineProps<{
    checked: boolean;
    value?: string | number | boolean;
}>();

const proxyChecked = computed({
    get() {
        return props.checked;
    },

    set(val: boolean) {
        emit('update:checked', val);
    },
});
</script>

<template>
    <input
        v-model="proxyChecked"
        type="checkbox"
        :value="value"
        class="rounded-sm border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800"
    />
</template>
