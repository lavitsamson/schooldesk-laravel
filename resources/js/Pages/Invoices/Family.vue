<script setup lang="ts">
import InvoiceStatusBadge from '@/Components/InvoiceStatusBadge.vue';
import { formatDate } from '@/composables/dates';
import { useMoney } from '@/composables/money';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { InvoiceRow } from '@/types/school';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ invoices: InvoiceRow[] }>();

const money = useMoney();

const open = computed(() =>
    props.invoices
        .filter((i) => i.status === 'issued' || i.status === 'partially_paid')
        .reduce((sum, i) => sum + i.balance, 0),
);
</script>

<template>
    <Head :title="$t('Invoices')" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
            >
                {{ $t('Invoices') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ $t('Open: :open', { open: money(open) }) }}
            </p>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <ul
                    v-if="invoices.length > 0"
                    class="space-y-3"
                    :aria-label="$t('Invoices')"
                >
                    <li
                        v-for="invoice in invoices"
                        :key="invoice.id"
                        class="flex flex-wrap items-center justify-between gap-3 bg-white p-4 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    >
                        <div>
                            <Link
                                :href="route('invoices.show', invoice.id)"
                                class="font-medium text-indigo-700 hover:underline focus:ring-2 focus:ring-indigo-500 focus:outline-hidden dark:text-indigo-300"
                            >
                                {{ invoice.student.name }} ·
                                {{ invoice.term }}
                            </Link>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{
                                    $t(':number · due :date', {
                                        number: invoice.number,
                                        date: formatDate(invoice.due_on),
                                    })
                                }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span
                                class="font-semibold text-gray-900 tabular-nums dark:text-gray-100"
                            >
                                {{
                                    money(
                                        invoice.status === 'paid'
                                            ? invoice.total
                                            : invoice.balance,
                                    )
                                }}
                            </span>
                            <InvoiceStatusBadge
                                :status="invoice.status"
                                :label="invoice.status_label"
                                :overdue="invoice.overdue"
                            />
                        </div>
                    </li>
                </ul>
                <p
                    v-else
                    class="bg-white p-6 text-sm text-gray-600 shadow-xs sm:rounded-lg dark:bg-gray-800 dark:text-gray-400"
                >
                    {{ $t('No invoices yet.') }}
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
