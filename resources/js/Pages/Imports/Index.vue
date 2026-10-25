<script setup lang="ts">
import { getActiveLanguage } from 'laravel-vue-i18n';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ImportStatusBadge from '@/Pages/Imports/Partials/ImportStatusBadge.vue';
import type { ImportBatch, ImportColumn, Paginated } from '@/types/school';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    batches: Paginated<ImportBatch>;
    columns: ImportColumn[];
    classes: string[];
    maxFileMb: number;
}>();

const form = useForm<{ file: File | null }>({ file: null });

const pick = (event: Event) =>
    (form.file = (event.target as HTMLInputElement).files?.[0] ?? null);

const submit = () => form.post(route('imports.store'), { forceFormData: true });

const dateTime = (iso: string) =>
    new Date(iso).toLocaleString(getActiveLanguage(), {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
</script>

<template>
    <Head :title="$t('Import students')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2
                    class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
                >
                    {{ $t('Import students') }}
                </h2>
                <Link
                    :href="route('students.index')"
                    class="text-sm text-gray-600 hover:underline focus:underline focus:outline-hidden dark:text-gray-400"
                >
                    {{ $t('Back to students') }}
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section
                    class="bg-white p-6 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    aria-labelledby="upload-heading"
                >
                    <h3
                        id="upload-heading"
                        class="text-lg font-medium text-gray-900 dark:text-gray-100"
                    >
                        {{ $t('Upload a file') }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        CSV or Excel (.xlsx), up to {{ maxFileMb }} MB, with the
                        {{
                            $t(
                                'column headings in the first row. Students whose admission number already exists are updated, so you can upload a corrected file again. Download the',
                            )
                        }}
                        <a
                            :href="
                                route('imports.template', { format: 'xlsx' })
                            "
                            class="font-medium text-indigo-700 underline dark:text-indigo-300"
                            >{{ $t('Excel template') }}</a
                        >
                        {{ $t('or the') }}
                        <a
                            :href="route('imports.template')"
                            class="font-medium text-indigo-700 underline dark:text-indigo-300"
                            >{{ $t('CSV template') }}</a
                        >.
                    </p>

                    <form
                        class="mt-4 flex flex-wrap items-end gap-4"
                        @submit.prevent="submit"
                    >
                        <div class="min-w-64 flex-1">
                            <InputLabel for="file" :value="$t('File')" />
                            <input
                                id="file"
                                type="file"
                                accept=".csv,.xlsx"
                                class="mt-1 block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-gray-800 hover:file:bg-gray-200 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-gray-300 dark:file:bg-gray-700 dark:file:text-gray-100"
                                @change="pick"
                            />
                            <InputError
                                class="mt-2"
                                :message="form.errors.file"
                            />
                        </div>
                        <PrimaryButton
                            :disabled="!form.file || form.processing"
                        >
                            {{ form.processing ? 'Uploading…' : 'Import' }}
                        </PrimaryButton>
                    </form>
                    <progress
                        v-if="form.progress"
                        class="mt-3 w-full"
                        :value="form.progress.percentage"
                        max="100"
                        :aria-label="$t('Upload progress')"
                    />
                </section>

                <section
                    class="bg-white shadow-xs sm:rounded-lg dark:bg-gray-800"
                    aria-labelledby="recent-heading"
                >
                    <h3
                        id="recent-heading"
                        class="px-6 pt-6 text-lg font-medium text-gray-900 dark:text-gray-100"
                    >
                        {{ $t('Recent imports') }}
                    </h3>
                    <div class="mt-4 overflow-x-auto">
                        <table
                            class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700"
                        >
                            <thead class="bg-gray-50 dark:bg-gray-900/50">
                                <tr
                                    class="text-left font-medium text-gray-600 dark:text-gray-300"
                                >
                                    <th scope="col" class="px-6 py-3">
                                        {{ $t('File') }}
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        {{ $t('Uploaded') }}
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        {{ $t('Status') }}
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-6 py-3 text-right"
                                    >
                                        {{ $t('Created') }}
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-6 py-3 text-right"
                                    >
                                        {{ $t('Updated') }}
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-6 py-3 text-right"
                                    >
                                        {{ $t('Failed') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-gray-200 text-gray-900 dark:divide-gray-700 dark:text-gray-100"
                            >
                                <tr
                                    v-for="batch in batches.data"
                                    :key="batch.id"
                                >
                                    <td class="px-6 py-3 font-medium">
                                        <Link
                                            :href="
                                                route('imports.show', batch.id)
                                            "
                                            class="hover:underline focus:underline focus:outline-hidden"
                                        >
                                            {{ batch.original_name }}
                                        </Link>
                                    </td>
                                    <td
                                        class="px-6 py-3 text-gray-600 dark:text-gray-400"
                                    >
                                        {{ dateTime(batch.created_at) }}
                                        <template v-if="batch.uploaded_by">
                                            · {{ batch.uploaded_by }}
                                        </template>
                                    </td>
                                    <td class="px-6 py-3">
                                        <ImportStatusBadge :batch="batch" />
                                    </td>
                                    <td
                                        class="px-6 py-3 text-right tabular-nums"
                                    >
                                        {{ batch.created_rows ?? '—' }}
                                    </td>
                                    <td
                                        class="px-6 py-3 text-right tabular-nums"
                                    >
                                        {{ batch.updated_rows ?? '—' }}
                                    </td>
                                    <td
                                        class="px-6 py-3 text-right tabular-nums"
                                    >
                                        {{ batch.failed_rows }}
                                    </td>
                                </tr>
                                <tr v-if="batches.data.length === 0">
                                    <td
                                        colspan="6"
                                        class="px-6 py-10 text-center text-gray-600 dark:text-gray-400"
                                    >
                                        {{ $t('No imports yet.') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4">
                        <Pagination
                            :meta="batches.meta"
                            :label="$t('Import pages')"
                        />
                    </div>
                </section>

                <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
                    <section
                        class="overflow-x-auto bg-white shadow-xs sm:rounded-lg dark:bg-gray-800"
                        aria-labelledby="columns-heading"
                    >
                        <h3
                            id="columns-heading"
                            class="px-6 pt-6 text-lg font-medium text-gray-900 dark:text-gray-100"
                        >
                            {{ $t('Columns') }}
                        </h3>
                        <table
                            class="mt-4 min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700"
                        >
                            <tbody
                                class="divide-y divide-gray-200 text-gray-900 dark:divide-gray-700 dark:text-gray-100"
                            >
                                <tr
                                    v-for="column in columns"
                                    :key="column.name"
                                >
                                    <th
                                        scope="row"
                                        class="px-6 py-2 text-left font-mono text-xs font-medium whitespace-nowrap"
                                    >
                                        {{ column.name }}
                                        <span
                                            v-if="column.required"
                                            class="text-red-700 dark:text-red-400"
                                        >
                                            *<span class="sr-only">
                                                (required)</span
                                            >
                                        </span>
                                    </th>
                                    <td
                                        class="px-6 py-2 text-gray-600 dark:text-gray-400"
                                    >
                                        {{ column.description }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                    <section
                        class="bg-white p-6 shadow-xs sm:rounded-lg dark:bg-gray-800"
                        aria-labelledby="classes-heading"
                    >
                        <h3
                            id="classes-heading"
                            class="text-lg font-medium text-gray-900 dark:text-gray-100"
                        >
                            {{ $t('Class names') }}
                        </h3>
                        <p
                            class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                        >
                            {{
                                $t(
                                    'Classes of the current academic year (case does not matter).',
                                )
                            }}
                        </p>
                        <ul
                            v-if="classes.length > 0"
                            class="mt-3 flex flex-wrap gap-2"
                        >
                            <li
                                v-for="name in classes"
                                :key="name"
                                class="rounded-md bg-gray-100 px-2 py-1 font-mono text-xs text-gray-800 dark:bg-gray-700 dark:text-gray-100"
                            >
                                {{ name }}
                            </li>
                        </ul>
                        <p
                            v-else
                            class="mt-3 text-sm text-gray-600 dark:text-gray-400"
                        >
                            {{
                                $t(
                                    'There are no classes this year yet. Leave the class column empty, or add classes first.',
                                )
                            }}
                        </p>
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
