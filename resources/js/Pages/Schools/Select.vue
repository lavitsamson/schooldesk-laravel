<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { SchoolSummary } from '@/types';
import { Head, router } from '@inertiajs/vue3';

defineProps<{
    schools: SchoolSummary[];
}>();

const choose = (school: SchoolSummary) => {
    router.post(route('schools.switch', school.id));
};
</script>

<template>
    <Head title="Choose a school" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
            >
                Choose a school
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div
                    class="bg-white p-6 shadow-xs sm:rounded-lg dark:bg-gray-800"
                >
                    <ul
                        v-if="schools.length"
                        class="divide-y divide-gray-200 dark:divide-gray-700"
                    >
                        <li v-for="school in schools" :key="school.id">
                            <button
                                type="button"
                                class="w-full rounded-md px-3 py-4 text-left font-medium text-gray-900 hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden dark:text-gray-100 dark:hover:bg-gray-700"
                                @click="choose(school)"
                            >
                                {{ school.name }}
                            </button>
                        </li>
                    </ul>
                    <p v-else class="text-gray-700 dark:text-gray-300">
                        You are not a member of any school yet. Ask your school
                        administrator to send you an invitation.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
