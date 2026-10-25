<script setup lang="ts">
import { formatRate } from '@/composables/numbers';
import { formatDateTime } from '@/composables/dates';
import { formatPercent } from '@/composables/grades';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ChildHeader from '@/Pages/Family/Partials/ChildHeader.vue';
import type { Child, PublishedReportCard } from '@/types/family';
import { Head } from '@inertiajs/vue3';

defineProps<{
    child: Child;
    children: Child[];
    reportCards: PublishedReportCard[];
}>();
</script>

<template>
    <Head
        :title="
            $t('Report cards :first_name', { first_name: child.first_name })
        "
    />

    <AuthenticatedLayout>
        <template #header>
            <ChildHeader
                :child="child"
                :children="children"
                page="family.report-cards"
            />
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <ul
                    v-if="reportCards.length > 0"
                    class="space-y-4"
                    :aria-label="$t('Report cards')"
                >
                    <li
                        v-for="card in reportCards"
                        :key="card.id"
                        class="bg-white p-6 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div>
                                <h3
                                    class="text-lg font-semibold text-gray-900 dark:text-gray-100"
                                >
                                    {{ card.term.name }}
                                </h3>
                                <p
                                    class="text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ card.class }}
                                    <template v-if="card.published_at">
                                        · {{ $t('published') }}
                                        {{ formatDateTime(card.published_at) }}
                                    </template>
                                </p>
                            </div>
                            <a
                                :href="card.pdf_url"
                                class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden dark:focus:ring-offset-gray-800"
                            >
                                {{ $t('Download PDF') }}
                                <span class="sr-only">
                                    for the {{ card.term.name }}</span
                                >
                            </a>
                        </div>
                        <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt class="text-gray-600 dark:text-gray-400">
                                    {{ $t('Overall result') }}
                                </dt>
                                <dd
                                    class="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100"
                                >
                                    {{ card.overall.grade ?? '—' }}
                                    <span
                                        class="text-base font-normal text-gray-600 dark:text-gray-400"
                                    >
                                        {{
                                            formatPercent(
                                                card.overall.percentage,
                                            )
                                        }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-600 dark:text-gray-400">
                                    {{ $t('Attendance') }}
                                </dt>
                                <dd
                                    class="mt-1 text-xl font-semibold text-gray-900 tabular-nums dark:text-gray-100"
                                >
                                    {{ formatRate(card.attendance_rate) }}
                                </dd>
                            </div>
                        </dl>
                        <blockquote
                            v-if="card.comment"
                            class="mt-4 border-l-4 border-gray-200 ps-4 text-sm whitespace-pre-line text-gray-700 dark:border-gray-600 dark:text-gray-300"
                        >
                            {{ card.comment }}
                        </blockquote>
                    </li>
                </ul>
                <p
                    v-else
                    class="bg-white p-6 text-sm text-gray-600 shadow-xs sm:rounded-lg dark:bg-gray-800 dark:text-gray-400"
                >
                    {{ $t('No report cards have been published for') }}
                    {{ child.first_name }} yet.
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
