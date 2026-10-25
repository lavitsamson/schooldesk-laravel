<script setup lang="ts">
import { formatDate } from '@/composables/dates';
import { useMoney } from '@/composables/money';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { ChildOverview } from '@/types/family';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{ children: ChildOverview[] }>();

const money = useMoney();
const school = computed(() => usePage().props.school);

const monthName = (month: string) =>
    formatDate(`${month}-01`, { month: 'long' });
</script>

<template>
    <Head title="My family" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
            >
                My family
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ school?.name }}
            </p>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <ul
                    v-if="children.length > 0"
                    class="grid gap-6 md:grid-cols-2"
                    aria-label="Children"
                >
                    <li
                        v-for="child in children"
                        :key="child.id"
                        class="flex flex-col bg-white p-6 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    >
                        <h3
                            class="text-lg font-semibold text-gray-900 dark:text-gray-100"
                        >
                            {{ child.name }}
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ child.class ?? 'Not in a class this year' }}
                        </p>

                        <dl class="mt-4 grid grid-cols-3 gap-3 text-sm">
                            <div>
                                <dt class="text-gray-600 dark:text-gray-400">
                                    Attendance in
                                    {{ monthName(child.attendance.month) }}
                                </dt>
                                <dd
                                    class="mt-1 text-xl font-semibold text-gray-900 tabular-nums dark:text-gray-100"
                                >
                                    {{
                                        child.attendance.rate === null
                                            ? '—'
                                            : `${child.attendance.rate.toLocaleString()} %`
                                    }}
                                </dd>
                                <dd
                                    v-if="child.attendance.absent > 0"
                                    class="text-red-700 dark:text-red-300"
                                >
                                    {{ child.attendance.absent }} absent
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-600 dark:text-gray-400">
                                    To pay
                                </dt>
                                <dd
                                    class="mt-1 text-xl font-semibold text-gray-900 tabular-nums dark:text-gray-100"
                                >
                                    {{ money(child.invoices.balance) }}
                                </dd>
                                <dd
                                    v-if="child.invoices.overdue > 0"
                                    class="text-red-700 dark:text-red-300"
                                >
                                    {{ child.invoices.overdue }} overdue
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-600 dark:text-gray-400">
                                    Latest report card
                                </dt>
                                <dd
                                    class="mt-1 font-semibold text-gray-900 dark:text-gray-100"
                                >
                                    {{ child.report_card?.term ?? '—' }}
                                </dd>
                            </div>
                        </dl>

                        <nav
                            :aria-label="`${child.first_name}'s pages`"
                            class="mt-6 flex flex-wrap gap-x-4 gap-y-2 text-sm font-medium"
                        >
                            <Link
                                :href="route('family.attendance', child.id)"
                                class="text-indigo-700 hover:underline dark:text-indigo-300"
                            >
                                Attendance
                            </Link>
                            <Link
                                :href="route('family.results', child.id)"
                                class="text-indigo-700 hover:underline dark:text-indigo-300"
                            >
                                Results
                            </Link>
                            <Link
                                :href="route('family.report-cards', child.id)"
                                class="text-indigo-700 hover:underline dark:text-indigo-300"
                            >
                                Report cards
                            </Link>
                            <Link
                                :href="route('family.invoices')"
                                class="text-indigo-700 hover:underline dark:text-indigo-300"
                            >
                                Invoices
                            </Link>
                        </nav>
                    </li>
                </ul>
                <p
                    v-else
                    class="bg-white p-6 text-sm text-gray-600 shadow-xs sm:rounded-lg dark:bg-gray-800 dark:text-gray-400"
                >
                    No children are linked to your account yet. Please contact
                    the school office.
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
