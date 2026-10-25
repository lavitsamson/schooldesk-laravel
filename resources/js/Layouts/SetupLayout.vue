<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { SetupStep } from '@/types/setup';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    steps: SetupStep[];
    current: string;
}>();

const currentIndex = computed(() =>
    props.steps.findIndex((step) => step.key === props.current),
);
const currentStep = computed(() => props.steps[currentIndex.value]);
</script>

<template>
    <Head :title="`Setup: ${currentStep?.label ?? ''}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
            >
                {{ $t('Set up your school') }}
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <nav :aria-label="$t('Setup progress')" class="mb-6">
                    <ol class="flex flex-wrap gap-2 text-sm">
                        <li v-for="(step, index) in steps" :key="step.key">
                            <Link
                                :href="route('setup.show', step.key)"
                                :aria-current="
                                    step.key === current ? 'step' : undefined
                                "
                                class="inline-flex items-center gap-2 rounded-full px-3 py-1 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden"
                                :class="
                                    step.key === current
                                        ? 'bg-indigo-600 text-white'
                                        : index < currentIndex
                                          ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200'
                                          : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200'
                                "
                            >
                                <span aria-hidden="true">{{ index + 1 }}</span>
                                {{ step.label }}
                            </Link>
                        </li>
                    </ol>
                </nav>

                <section
                    class="bg-white p-6 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    :aria-labelledby="'setup-step-title'"
                >
                    <h3
                        id="setup-step-title"
                        class="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100"
                    >
                        {{ currentStep?.label }}
                    </h3>
                    <slot />
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
