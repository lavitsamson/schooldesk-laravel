<script setup lang="ts">
import InputLabel from '@/Components/InputLabel.vue';
import Pagination from '@/Components/Pagination.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatDateTime } from '@/composables/dates';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { Option } from '@/types';
import type { ActivityEntry, IdName, Paginated } from '@/types/school';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

interface Filters {
    area: string | null;
    user_id: number | null;
    student_id: number | null;
    from: string | null;
    to: string | null;
}

const props = defineProps<{
    activities: Paginated<ActivityEntry>;
    filters: Filters;
    student: IdName | null;
    areas: Option[];
    users: IdName[];
}>();

// Date inputs hold '' when empty.
const filters = reactive({
    ...props.filters,
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

const reload = () =>
    router.get(
        route('audit-log.index'),
        {
            area: filters.area ?? undefined,
            user_id: filters.user_id ?? undefined,
            student: filters.student_id ?? undefined,
            from: filters.from || undefined,
            to: filters.to || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );

watch(() => [filters.area, filters.user_id, filters.from, filters.to], reload);

const showAllStudents = () => {
    filters.student_id = null;
    reload();
};
</script>

<template>
    <Head :title="$t('Audit log')" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
            >
                {{ $t('Audit log') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{
                    $t(
                        'Changes to students, marks, invoices, payments and report cards, and data protection actions.',
                    )
                }}
            </p>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                <form
                    class="grid gap-4 bg-white p-4 shadow-xs sm:grid-cols-2 sm:rounded-lg lg:grid-cols-4 dark:bg-gray-800"
                    :aria-label="$t('Filter the audit log')"
                    @submit.prevent="reload"
                >
                    <div>
                        <InputLabel for="area" :value="$t('Area')" />
                        <SelectInput
                            id="area"
                            v-model="filters.area"
                            class="mt-1 block w-full"
                        >
                            <option :value="null">{{ $t('All areas') }}</option>
                            <option
                                v-for="a in areas"
                                :key="a.value"
                                :value="a.value"
                            >
                                {{ a.label }}
                            </option>
                        </SelectInput>
                    </div>
                    <div>
                        <InputLabel for="user" :value="$t('Changed by')" />
                        <SelectInput
                            id="user"
                            v-model="filters.user_id"
                            class="mt-1 block w-full"
                        >
                            <option :value="null">{{ $t('Anyone') }}</option>
                            <option
                                v-for="u in users"
                                :key="u.id"
                                :value="u.id"
                            >
                                {{ u.name }}
                            </option>
                        </SelectInput>
                    </div>
                    <div>
                        <InputLabel for="from" :value="$t('From')" />
                        <TextInput
                            id="from"
                            v-model="filters.from"
                            type="date"
                            class="mt-1 block w-full"
                        />
                    </div>
                    <div>
                        <InputLabel for="to" :value="$t('To')" />
                        <TextInput
                            id="to"
                            v-model="filters.to"
                            type="date"
                            :min="filters.from || undefined"
                            class="mt-1 block w-full"
                        />
                    </div>
                </form>

                <p
                    v-if="student"
                    class="flex flex-wrap items-center gap-3 rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-900 dark:bg-indigo-950 dark:text-indigo-100"
                >
                    <span>{{
                        $t('History of :name', { name: student.name })
                    }}</span>
                    <button
                        type="button"
                        class="font-medium underline hover:no-underline focus:ring-2 focus:ring-indigo-500 focus:outline-hidden"
                        @click="showAllStudents"
                    >
                        {{ $t('Show all students') }}
                    </button>
                </p>

                <section
                    class="overflow-x-auto bg-white shadow-xs sm:rounded-lg dark:bg-gray-800"
                >
                    <table
                        class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700"
                    >
                        <caption class="sr-only">
                            {{
                                $t('Audit log')
                            }}
                        </caption>
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr
                                class="text-left font-medium text-gray-600 dark:text-gray-300"
                            >
                                <th scope="col" class="px-4 py-3">
                                    {{ $t('When') }}
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    {{ $t('What') }}
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    {{ $t('Student') }}
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    {{ $t('Changes') }}
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    {{ $t('Changed by') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-200 align-top text-gray-900 dark:divide-gray-700 dark:text-gray-100"
                        >
                            <tr
                                v-for="entry in activities.data"
                                :key="entry.id"
                            >
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <time :datetime="entry.at">{{
                                        formatDateTime(entry.at)
                                    }}</time>
                                </td>
                                <th
                                    scope="row"
                                    class="px-4 py-3 text-left font-normal"
                                >
                                    <span class="font-medium">{{
                                        entry.summary
                                    }}</span>
                                    <template v-if="entry.record">
                                        <br />
                                        <Link
                                            v-if="entry.record.url"
                                            :href="entry.record.url"
                                            class="text-indigo-700 hover:underline focus:ring-2 focus:ring-indigo-500 focus:outline-hidden dark:text-indigo-300"
                                        >
                                            {{ entry.record.label }}
                                        </Link>
                                        <span
                                            v-else
                                            class="text-gray-600 dark:text-gray-400"
                                            >{{ entry.record.label }}</span
                                        >
                                    </template>
                                </th>
                                <td class="px-4 py-3">
                                    <template v-if="entry.student">
                                        {{ entry.student.name }}
                                        <span
                                            class="block font-mono text-xs text-gray-600 dark:text-gray-400"
                                            >{{
                                                entry.student.admission_no
                                            }}</span
                                        >
                                    </template>
                                    <template v-else>—</template>
                                </td>
                                <td class="px-4 py-3">
                                    <dl
                                        v-if="entry.changes.length"
                                        class="space-y-1"
                                    >
                                        <div
                                            v-for="change in entry.changes"
                                            :key="change.field"
                                            class="flex flex-wrap gap-x-2"
                                        >
                                            <dt
                                                class="text-gray-600 dark:text-gray-400"
                                            >
                                                {{ change.field }}:
                                            </dt>
                                            <dd>
                                                <del
                                                    v-if="change.old !== null"
                                                    class="text-gray-600 dark:text-gray-400"
                                                    >{{ change.old }}</del
                                                >
                                                <span
                                                    v-if="
                                                        change.old !== null &&
                                                        change.new !== null
                                                    "
                                                    aria-hidden="true"
                                                >
                                                    →
                                                </span>
                                                <span
                                                    v-if="
                                                        change.old !== null &&
                                                        change.new !== null
                                                    "
                                                    class="sr-only"
                                                    >{{
                                                        $t('changed to')
                                                    }}</span
                                                >
                                                <ins
                                                    v-if="change.new !== null"
                                                    class="no-underline"
                                                    >{{ change.new }}</ins
                                                >
                                                <span
                                                    v-if="
                                                        change.old !== null &&
                                                        change.new === null
                                                    "
                                                >
                                                    → {{ $t('(empty)') }}
                                                </span>
                                            </dd>
                                        </div>
                                    </dl>
                                    <template v-else>—</template>
                                </td>
                                <td class="px-4 py-3">
                                    {{ entry.causer ?? $t('System') }}
                                </td>
                            </tr>
                            <tr v-if="activities.data.length === 0">
                                <td
                                    colspan="5"
                                    class="px-4 py-10 text-center text-gray-600 dark:text-gray-400"
                                >
                                    {{ $t('No entries match.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <Pagination
                    :meta="activities.meta"
                    :label="$t('Audit log pages')"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
