<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ImportStatusBadge from '@/Pages/Imports/Partials/ImportStatusBadge.vue';
import type { ImportBatch, ImportFailure } from '@/types/school';
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps<{
    batch: ImportBatch;
    failures: ImportFailure[];
    shownFailures: number;
}>();

// Reload the progress every 2 seconds until the import has finished.
const { stop } = usePoll(
    2000,
    { only: ['batch', 'failures'] },
    { autoStart: !props.batch.is_finished },
);
watch(
    () => props.batch.is_finished,
    (finished) => finished && stop(),
);
</script>

<template>
    <Head :title="`Import: ${batch.original_name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2
                    class="text-xl leading-tight font-semibold break-all text-gray-800 dark:text-gray-200"
                >
                    {{
                        $t('Import: :original_name', {
                            original_name: batch.original_name,
                        })
                    }}
                </h2>
                <Link
                    :href="route('imports.index')"
                    class="text-sm text-gray-600 hover:underline focus:underline focus:outline-hidden dark:text-gray-400"
                >
                    {{ $t('All imports') }}
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section
                    class="bg-white p-6 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    aria-labelledby="progress-heading"
                >
                    <div class="flex flex-wrap items-center gap-3">
                        <h3
                            id="progress-heading"
                            class="text-lg font-medium text-gray-900 dark:text-gray-100"
                        >
                            {{ $t('Progress') }}
                        </h3>
                        <ImportStatusBadge :batch="batch" />
                    </div>

                    <div class="mt-4" role="status" aria-live="polite">
                        <div
                            class="h-3 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"
                            role="progressbar"
                            :aria-valuenow="batch.progress"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            :aria-label="$t('Import progress')"
                        >
                            <div
                                class="h-full rounded-full transition-all"
                                :class="
                                    batch.status === 'failed'
                                        ? 'bg-red-600'
                                        : 'bg-indigo-600'
                                "
                                :style="{ width: `${batch.progress}%` }"
                            />
                        </div>
                        <p
                            class="mt-2 text-sm text-gray-600 dark:text-gray-400"
                        >
                            {{
                                $t(
                                    ':processed of :total rows processed (:progress%)',
                                    {
                                        processed: String(batch.processed_rows),
                                        total: String(batch.total_rows),
                                        progress: String(batch.progress),
                                    },
                                )
                            }}
                        </p>
                    </div>

                    <p
                        v-if="batch.failure_message"
                        class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 dark:bg-red-900/40 dark:text-red-200"
                        role="alert"
                    >
                        {{ batch.failure_message }}
                    </p>

                    <dl class="mt-6 grid grid-cols-3 gap-4 text-center">
                        <div
                            class="rounded-md bg-green-50 p-3 dark:bg-green-900/30"
                        >
                            <dt
                                class="text-sm text-green-800 dark:text-green-200"
                            >
                                {{ $t('Created') }}
                            </dt>
                            <dd
                                class="text-2xl font-semibold text-green-900 tabular-nums dark:text-green-100"
                            >
                                {{ batch.created_rows ?? 0 }}
                            </dd>
                        </div>
                        <div
                            class="rounded-md bg-indigo-50 p-3 dark:bg-indigo-900/30"
                        >
                            <dt
                                class="text-sm text-indigo-800 dark:text-indigo-200"
                            >
                                {{ $t('Updated') }}
                            </dt>
                            <dd
                                class="text-2xl font-semibold text-indigo-900 tabular-nums dark:text-indigo-100"
                            >
                                {{ batch.updated_rows ?? 0 }}
                            </dd>
                        </div>
                        <div
                            class="rounded-md bg-red-50 p-3 dark:bg-red-900/30"
                        >
                            <dt class="text-sm text-red-800 dark:text-red-200">
                                {{ $t('Failed') }}
                            </dt>
                            <dd
                                class="text-2xl font-semibold text-red-900 tabular-nums dark:text-red-100"
                            >
                                {{ batch.failed_rows }}
                            </dd>
                        </div>
                    </dl>

                    <div
                        v-if="batch.status === 'completed'"
                        class="mt-6 flex flex-wrap gap-3"
                    >
                        <Link
                            :href="route('students.index')"
                            class="inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase hover:bg-gray-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden dark:bg-gray-200 dark:text-gray-800 dark:hover:bg-white"
                        >
                            {{ $t('View students') }}
                        </Link>
                        <a
                            v-if="batch.error_report_url"
                            :href="batch.error_report_url"
                            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold tracking-widest text-gray-700 uppercase shadow-xs hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden dark:border-gray-500 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                        >
                            {{ $t('Download error report') }}
                        </a>
                    </div>
                </section>

                <section
                    v-if="failures.length > 0"
                    class="overflow-x-auto bg-white shadow-xs sm:rounded-lg dark:bg-gray-800"
                    aria-labelledby="failures-heading"
                >
                    <div class="px-6 pt-6">
                        <h3
                            id="failures-heading"
                            class="text-lg font-medium text-gray-900 dark:text-gray-100"
                        >
                            {{ $t('Rows that were not imported') }}
                        </h3>
                        <p
                            class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                        >
                            {{
                                $t(
                                    'Fix these rows in the error report (it has the original values) and upload it again.',
                                )
                            }}
                            <template v-if="batch.failed_rows > shownFailures">
                                {{
                                    $t(
                                        'The first :shown of :failed are shown here.',
                                        {
                                            shown: String(shownFailures),
                                            failed: String(batch.failed_rows),
                                        },
                                    )
                                }}
                            </template>
                        </p>
                    </div>
                    <table
                        class="mt-4 min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700"
                    >
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr
                                class="text-left font-medium text-gray-600 dark:text-gray-300"
                            >
                                <th scope="col" class="px-6 py-3">
                                    {{ $t('Row') }}
                                </th>
                                <th scope="col" class="px-6 py-3">
                                    {{ $t('Admission no.') }}
                                </th>
                                <th scope="col" class="px-6 py-3">
                                    {{ $t('Name') }}
                                </th>
                                <th scope="col" class="px-6 py-3">
                                    {{ $t('Problems') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-200 text-gray-900 dark:divide-gray-700 dark:text-gray-100"
                        >
                            <tr v-for="failure in failures" :key="failure.row">
                                <td class="px-6 py-3 tabular-nums">
                                    {{ failure.row }}
                                </td>
                                <td class="px-6 py-3 font-mono text-xs">
                                    {{ failure.admission_no ?? '—' }}
                                </td>
                                <td class="px-6 py-3">
                                    {{ failure.name || '—' }}
                                </td>
                                <td
                                    class="px-6 py-3 text-red-800 dark:text-red-300"
                                >
                                    <ul class="list-disc space-y-1 pl-4">
                                        <li
                                            v-for="(
                                                error, index
                                            ) in failure.errors"
                                            :key="index"
                                        >
                                            {{ error }}
                                        </li>
                                    </ul>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
