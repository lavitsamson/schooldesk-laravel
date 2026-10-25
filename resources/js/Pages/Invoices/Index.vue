<script setup lang="ts">
import InputLabel from '@/Components/InputLabel.vue';
import InvoiceStatusBadge from '@/Components/InvoiceStatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TermSelect from '@/Components/TermSelect.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatDate } from '@/composables/dates';
import { useMoney } from '@/composables/money';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { Option } from '@/types';
import type { IdName, InvoiceRow, Paginated, TermOption } from '@/types/school';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

interface Filters {
    term: number;
    class_id: number | null;
    status: string;
    search: string;
}

const props = defineProps<{
    year: string;
    terms: TermOption[];
    term: IdName;
    invoices: Paginated<InvoiceRow>;
    filters: Filters;
    classes: IdName[];
    statuses: Option[];
}>();

const money = useMoney();
const filters = reactive<Filters>({ ...props.filters });

const reload = () =>
    router.get(
        route('invoices.index'),
        {
            term: filters.term,
            class_id: filters.class_id ?? undefined,
            status: filters.status,
            search: filters.search || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );

let searchTimer: ReturnType<typeof setTimeout> | undefined;
watch(
    () => filters.search,
    () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(reload, 300);
    },
);
watch(() => [filters.class_id, filters.status], reload);
</script>

<template>
    <Head title="Invoices" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2
                        class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
                    >
                        Invoices
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {{ year }} · {{ term.name }} ·
                        <Link
                            :href="route('fees.index', { term: term.id })"
                            class="text-indigo-700 hover:underline dark:text-indigo-300"
                            >Fees overview</Link
                        >
                    </p>
                </div>
                <TermSelect
                    :terms="terms"
                    :current="term.id"
                    :url="route('invoices.index')"
                />
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                <form
                    class="grid gap-4 bg-white p-4 shadow-xs sm:grid-cols-3 sm:rounded-lg dark:bg-gray-800"
                    role="search"
                    @submit.prevent="reload"
                >
                    <div>
                        <InputLabel for="search" value="Search" />
                        <TextInput
                            id="search"
                            v-model="filters.search"
                            type="search"
                            placeholder="Student or invoice number"
                            class="mt-1 block w-full"
                        />
                    </div>
                    <div>
                        <InputLabel for="class" value="Class" />
                        <SelectInput
                            id="class"
                            v-model="filters.class_id"
                            class="mt-1 block w-full"
                        >
                            <option :value="null">All classes</option>
                            <option
                                v-for="c in classes"
                                :key="c.id"
                                :value="c.id"
                            >
                                {{ c.name }}
                            </option>
                        </SelectInput>
                    </div>
                    <div>
                        <InputLabel for="status" value="Status" />
                        <SelectInput
                            id="status"
                            v-model="filters.status"
                            class="mt-1 block w-full"
                        >
                            <option
                                v-for="s in statuses"
                                :key="s.value"
                                :value="s.value"
                            >
                                {{ s.label }}
                            </option>
                        </SelectInput>
                    </div>
                </form>

                <section
                    class="overflow-x-auto bg-white shadow-xs sm:rounded-lg dark:bg-gray-800"
                >
                    <table
                        class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700"
                    >
                        <caption class="sr-only">
                            Invoices
                        </caption>
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr
                                class="text-left font-medium text-gray-600 dark:text-gray-300"
                            >
                                <th scope="col" class="px-4 py-3">Invoice</th>
                                <th scope="col" class="px-4 py-3">Student</th>
                                <th scope="col" class="px-4 py-3">Class</th>
                                <th scope="col" class="px-4 py-3 text-right">
                                    Total
                                </th>
                                <th scope="col" class="px-4 py-3 text-right">
                                    Balance
                                </th>
                                <th scope="col" class="px-4 py-3">Due</th>
                                <th scope="col" class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-200 text-gray-900 dark:divide-gray-700 dark:text-gray-100"
                        >
                            <tr
                                v-for="invoice in invoices.data"
                                :key="invoice.id"
                            >
                                <th scope="row" class="px-4 py-3 text-left">
                                    <Link
                                        :href="
                                            route('invoices.show', invoice.id)
                                        "
                                        class="font-mono text-indigo-700 hover:underline focus:ring-2 focus:ring-indigo-500 focus:outline-hidden dark:text-indigo-300"
                                    >
                                        {{ invoice.number }}
                                    </Link>
                                </th>
                                <td class="px-4 py-3">
                                    {{ invoice.student.name }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ invoice.class ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    {{ money(invoice.total) }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    {{ money(invoice.balance) }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{
                                        formatDate(invoice.due_on, {
                                            day: 'numeric',
                                            month: 'short',
                                            year: 'numeric',
                                        })
                                    }}
                                </td>
                                <td class="px-4 py-3">
                                    <InvoiceStatusBadge
                                        :status="invoice.status"
                                        :label="invoice.status_label"
                                        :overdue="invoice.overdue"
                                    />
                                </td>
                            </tr>
                            <tr v-if="invoices.data.length === 0">
                                <td
                                    colspan="7"
                                    class="px-4 py-10 text-center text-gray-600 dark:text-gray-400"
                                >
                                    No invoices match.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <Pagination :meta="invoices.meta" label="Invoice pages" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
