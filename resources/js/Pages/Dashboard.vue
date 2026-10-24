<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const school = computed(() => usePage().props.school);
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
            >
                {{ school?.name ?? 'Dashboard' }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div
                    class="overflow-hidden bg-white shadow-xs sm:rounded-lg dark:bg-gray-800"
                >
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <p v-if="school && !school.setup_completed">
                            Your school administrator is still setting up
                            {{ school.name }}. Check back soon.
                        </p>
                        <p v-else>
                            Welcome, {{ $page.props.auth.user.name }}. You're
                            signed in as
                            {{ school?.roles.join(', ') || 'a member' }}.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
