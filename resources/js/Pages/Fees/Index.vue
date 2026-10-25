<script setup lang="ts">
import { trans, transChoice } from 'laravel-vue-i18n';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TermSelect from '@/Components/TermSelect.vue';
import { formatDate } from '@/composables/dates';
import { useMoney } from '@/composables/money';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CollectionChart from '@/Pages/Fees/Partials/CollectionChart.vue';
import type { FeeClassTotals, FeeTotals, TermOption } from '@/types/school';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    year: string;
    terms: TermOption[];
    term: { id: number; name: string; starts_on: string };
    dashboard: { classes: FeeClassTotals[]; totals: FeeTotals };
    feeStructures: number;
    onlinePayments: boolean;
}>();

const money = useMoney();

const generateForm = useForm({ term: props.term.id });
const generate = () =>
    generateForm
        .transform(() => ({ term: props.term.id }))
        .post(route('fees.generate'), { preserveScroll: true });

const share = computed(() =>
    props.dashboard.totals.invoiced === 0
        ? 0
        : Math.round(
              (props.dashboard.totals.collected /
                  props.dashboard.totals.invoiced) *
                  100,
          ),
);

const cards = computed(() => [
    {
        label: trans('Invoiced'),
        value: money(props.dashboard.totals.invoiced),
        note: transChoice(
            ':count invoice|:count invoices',
            props.dashboard.totals.invoices,
            { count: String(props.dashboard.totals.invoices) },
        ),
    },
    {
        label: trans('Collected'),
        value: money(props.dashboard.totals.collected),
        note: trans(':share % · :paid paid in full', {
            share: String(share.value),
            paid: String(props.dashboard.totals.paid),
        }),
    },
    {
        label: trans('Outstanding'),
        value: money(props.dashboard.totals.outstanding),
        note: trans('Still to pay'),
    },
    {
        label: trans('Overdue'),
        value: money(props.dashboard.totals.overdue),
        note: trans('Past the due date'),
    },
]);

const invoicesUrl = (params: Record<string, string | number> = {}) =>
    route('invoices.index', { term: props.term.id, ...params });
</script>

<template>
    <Head :title="$t('Fees')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2
                        class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
                    >
                        {{ $t('Fees') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {{ year }} · {{ term.name }}
                    </p>
                </div>
                <TermSelect
                    :terms="terms"
                    :current="term.id"
                    :url="route('fees.index')"
                />
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section
                    class="flex flex-wrap items-center justify-between gap-4 bg-white p-5 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    aria-labelledby="generate-title"
                >
                    <div class="max-w-3xl">
                        <h3
                            id="generate-title"
                            class="font-medium text-gray-900 dark:text-gray-100"
                        >
                            {{ $t('Term invoices') }}
                        </h3>
                        <p
                            class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                        >
                            {{
                                $t(
                                    'Invoices for the :term (starting :date) are generated automatically two weeks before the term starts, from the fees of this year:',
                                    {
                                        term: term.name,
                                        date: formatDate(term.starts_on),
                                    },
                                )
                            }}
                            <Link
                                :href="route('fee-structures.index')"
                                class="text-indigo-700 underline hover:no-underline dark:text-indigo-300"
                                >{{
                                    $tChoice(
                                        ':count fee|:count fees',
                                        feeStructures,
                                        { count: String(feeStructures) },
                                    )
                                }}</Link
                            >.
                            {{
                                $t('Generating now only adds the missing ones.')
                            }}
                            <template v-if="!onlinePayments">
                                {{
                                    $t(
                                        'Online payment is not set up, so parents pay by bank transfer or at the office.',
                                    )
                                }}
                            </template>
                        </p>
                        <InputError
                            class="mt-2"
                            :message="generateForm.errors.term"
                        />
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <Link
                            :href="invoicesUrl()"
                            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold tracking-widest text-gray-700 uppercase shadow-xs hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden dark:border-gray-500 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                        >
                            {{ $t('All invoices') }}
                        </Link>
                        <PrimaryButton
                            type="button"
                            :disabled="generateForm.processing"
                            @click="generate"
                        >
                            {{ $t('Generate invoices now') }}
                        </PrimaryButton>
                    </div>
                </section>

                <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        v-for="card in cards"
                        :key="card.label"
                        class="bg-white p-5 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    >
                        <dt
                            class="text-sm font-medium text-gray-600 dark:text-gray-400"
                        >
                            {{ card.label }}
                        </dt>
                        <dd
                            class="mt-1 text-2xl font-semibold text-gray-900 tabular-nums dark:text-gray-100"
                        >
                            {{ card.value }}
                        </dd>
                        <dd class="text-sm text-gray-600 dark:text-gray-400">
                            {{ card.note }}
                        </dd>
                    </div>
                </dl>

                <template v-if="dashboard.classes.length > 0">
                    <section
                        class="bg-white p-5 shadow-xs sm:rounded-lg dark:bg-gray-800"
                        aria-labelledby="chart-title"
                    >
                        <h3
                            id="chart-title"
                            class="mb-3 font-medium text-gray-900 dark:text-gray-100"
                        >
                            {{ $t('Collected and outstanding per class') }}
                        </h3>
                        <CollectionChart :classes="dashboard.classes" />
                    </section>

                    <section
                        class="overflow-x-auto bg-white shadow-xs sm:rounded-lg dark:bg-gray-800"
                    >
                        <table
                            class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700"
                        >
                            <caption class="sr-only">
                                {{
                                    $t('Fees per class')
                                }}
                            </caption>
                            <thead class="bg-gray-50 dark:bg-gray-900/50">
                                <tr
                                    class="text-left font-medium text-gray-600 dark:text-gray-300"
                                >
                                    <th scope="col" class="px-4 py-3">
                                        {{ $t('Class') }}
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-4 py-3 text-right"
                                    >
                                        {{ $t('Invoices') }}
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-4 py-3 text-right"
                                    >
                                        {{ $t('Invoiced') }}
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-4 py-3 text-right"
                                    >
                                        {{ $t('Collected') }}
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-4 py-3 text-right"
                                    >
                                        {{ $t('Outstanding') }}
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-4 py-3 text-right"
                                    >
                                        {{ $t('Overdue') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-gray-200 text-gray-900 tabular-nums dark:divide-gray-700 dark:text-gray-100"
                            >
                                <tr
                                    v-for="row in dashboard.classes"
                                    :key="row.id ?? 'none'"
                                >
                                    <th scope="row" class="px-4 py-3 text-left">
                                        <Link
                                            v-if="row.id !== null"
                                            :href="
                                                invoicesUrl({
                                                    class_id: row.id,
                                                })
                                            "
                                            class="font-medium text-indigo-700 hover:underline focus:ring-2 focus:ring-indigo-500 focus:outline-hidden dark:text-indigo-300"
                                        >
                                            {{ row.name }}
                                        </Link>
                                        <span v-else>{{ row.name }}</span>
                                    </th>
                                    <td class="px-4 py-3 text-right">
                                        {{
                                            $t(':paid / :count paid', {
                                                paid: String(row.paid),
                                                count: String(row.invoices),
                                            })
                                        }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        {{ money(row.invoiced) }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        {{ money(row.collected) }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        {{ money(row.outstanding) }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right"
                                        :class="{
                                            'font-medium text-red-700 dark:text-red-300':
                                                row.overdue > 0,
                                        }"
                                    >
                                        <Link
                                            v-if="row.overdue > 0 && row.id"
                                            :href="
                                                invoicesUrl({
                                                    class_id: row.id,
                                                    status: 'overdue',
                                                })
                                            "
                                            class="hover:underline"
                                        >
                                            {{ money(row.overdue) }}
                                        </Link>
                                        <template v-else>
                                            {{ money(row.overdue) }}
                                        </template>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </section>
                </template>
                <p
                    v-else
                    class="bg-white p-6 text-sm text-gray-600 shadow-xs sm:rounded-lg dark:bg-gray-800 dark:text-gray-400"
                >
                    {{
                        $t('No invoices for the :name yet.', {
                            name: term.name,
                        })
                    }}
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
