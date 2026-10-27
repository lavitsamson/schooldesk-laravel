<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

// The demo seed's logins (README), offered on the sign-in page of the public demo only.
const emit = defineEmits<{
    choose: [email: string, password: string];
}>();

const password = 'DemoPass123!';

const accounts = computed(() => [
    { email: 'admin@riverside.demo', role: trans('School admin') },
    { email: 'teacher@riverside.demo', role: trans('Teacher') },
    { email: 'parent@riverside.demo', role: trans('Parent') },
    { email: 'admin@gymnasium.demo', role: trans('School admin (German)') },
]);
</script>

<template>
    <section
        aria-labelledby="demo-accounts"
        class="mb-6 rounded-md border border-indigo-200 bg-indigo-50 p-4 text-sm dark:border-indigo-800 dark:bg-gray-900"
    >
        <h2
            id="demo-accounts"
            class="font-semibold text-gray-900 dark:text-gray-100"
        >
            {{ $t('Demo accounts') }}
        </h2>
        <p class="mt-1 text-gray-700 dark:text-gray-300">
            {{ $t('Choose one to fill in the form. The password is') }}
            <code class="font-mono">{{ password }}</code>
        </p>
        <ul class="mt-3 grid gap-2 sm:grid-cols-2">
            <li v-for="account in accounts" :key="account.email">
                <button
                    type="button"
                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-left hover:border-indigo-500 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden dark:border-gray-600 dark:bg-gray-800 dark:hover:border-indigo-400"
                    @click="emit('choose', account.email, password)"
                >
                    <span
                        class="block font-medium text-gray-900 dark:text-gray-100"
                        >{{ account.role }}</span
                    >
                    <span
                        class="block truncate text-gray-700 dark:text-gray-300"
                        >{{ account.email }}</span
                    >
                </button>
            </li>
        </ul>
    </section>
</template>
