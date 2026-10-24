<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { Option } from '@/types';
import type { IdName, Paginated, StudentRow } from '@/types/school';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

interface Filters {
    search: string;
    class_id: number | null;
    status: string;
    sort: 'name' | 'admission_no' | 'class';
    direction: 'asc' | 'desc';
}

const props = defineProps<{
    students: Paginated<StudentRow>;
    filters: Filters;
    classes: IdName[];
    statuses: Option[];
    bulkActions: Option[];
    can: { create: boolean };
}>();

const filters = reactive<Filters>({ ...props.filters });

const reload = () =>
    router.get(
        route('students.index'),
        {
            search: filters.search || undefined,
            class_id: filters.class_id ?? undefined,
            status: filters.status,
            sort: filters.sort,
            direction: filters.direction,
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

const sortBy = (column: Filters['sort']) => {
    filters.direction =
        filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc';
    filters.sort = column;
    reload();
};
const ariaSort = (column: Filters['sort']) =>
    filters.sort === column
        ? filters.direction === 'asc'
            ? 'ascending'
            : 'descending'
        : 'none';

// Selection is per page: it is cleared whenever the list changes.
const selected = ref<number[]>([]);
watch(
    () => props.students.data,
    () => (selected.value = []),
);
const allSelected = computed(
    () =>
        props.students.data.length > 0 &&
        selected.value.length === props.students.data.length,
);
const toggleAll = (checked: boolean) =>
    (selected.value = checked ? props.students.data.map((s) => s.id) : []);
const toggle = (id: number, checked: boolean) =>
    (selected.value = checked
        ? [...selected.value, id]
        : selected.value.filter((s) => s !== id));

const bulk = reactive({
    action: props.bulkActions[0]?.value ?? '',
    class_id: null as number | null,
    processing: false,
});
const confirmingLeave = ref(false);
const errors = computed(() => usePage().props.errors);

const applyBulk = () => {
    if (bulk.action === 'mark_as_left' && !confirmingLeave.value) {
        confirmingLeave.value = true;
        return;
    }

    router.post(
        route('students.bulk'),
        {
            action: bulk.action,
            student_ids: selected.value,
            class_id: bulk.action === 'move_to_class' ? bulk.class_id : null,
        },
        {
            preserveScroll: true,
            onStart: () => (bulk.processing = true),
            onFinish: () => {
                bulk.processing = false;
                confirmingLeave.value = false;
            },
        },
    );
};

const statusClasses: Record<StudentRow['status'], string> = {
    active: 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200',
    left: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
    graduated:
        'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200',
};
</script>

<template>
    <Head title="Students" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2
                    class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
                >
                    Students
                </h2>
                <Link
                    v-if="can.create"
                    :href="route('students.create')"
                    class="inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase hover:bg-gray-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden dark:bg-gray-200 dark:text-gray-800 dark:hover:bg-white"
                >
                    Add student
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                <section
                    class="grid gap-4 bg-white p-4 shadow-xs sm:grid-cols-[2fr_1fr_1fr] sm:rounded-lg dark:bg-gray-800"
                    aria-label="Filters"
                >
                    <div>
                        <InputLabel for="search" value="Search" />
                        <TextInput
                            id="search"
                            v-model="filters.search"
                            type="search"
                            class="mt-1 block w-full"
                            placeholder="Name or admission number"
                        />
                    </div>
                    <div>
                        <InputLabel for="filter-class" value="Class" />
                        <SelectInput
                            id="filter-class"
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
                        <InputLabel for="filter-status" value="Status" />
                        <SelectInput
                            id="filter-status"
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
                            <option value="all">All</option>
                        </SelectInput>
                    </div>
                </section>

                <section
                    v-if="selected.length > 0"
                    class="flex flex-wrap items-end gap-3 rounded-lg border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-800 dark:bg-indigo-950/40"
                    aria-label="Bulk actions"
                >
                    <p
                        class="self-center text-sm font-medium text-indigo-900 dark:text-indigo-100"
                        role="status"
                    >
                        {{ selected.length }} selected
                    </p>
                    <div>
                        <InputLabel for="bulk-action" value="Action" />
                        <SelectInput
                            id="bulk-action"
                            v-model="bulk.action"
                            class="mt-1 block"
                        >
                            <option
                                v-for="a in bulkActions"
                                :key="a.value"
                                :value="a.value"
                            >
                                {{ a.label }}
                            </option>
                        </SelectInput>
                    </div>
                    <div v-if="bulk.action === 'move_to_class'">
                        <InputLabel for="bulk-class" value="Target class" />
                        <SelectInput
                            id="bulk-class"
                            v-model="bulk.class_id"
                            class="mt-1 block"
                        >
                            <option :value="null" disabled>
                                Choose a class
                            </option>
                            <option
                                v-for="c in classes"
                                :key="c.id"
                                :value="c.id"
                            >
                                {{ c.name }}
                            </option>
                        </SelectInput>
                    </div>
                    <PrimaryButton
                        type="button"
                        :disabled="
                            bulk.processing ||
                            (bulk.action === 'move_to_class' && !bulk.class_id)
                        "
                        @click="applyBulk"
                    >
                        Apply
                    </PrimaryButton>
                    <SecondaryButton type="button" @click="selected = []">
                        Clear selection
                    </SecondaryButton>
                    <InputError
                        class="w-full"
                        :message="errors.student_ids ?? errors.class_id"
                    />
                </section>

                <section
                    class="overflow-x-auto bg-white shadow-xs sm:rounded-lg dark:bg-gray-800"
                >
                    <table
                        class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700"
                    >
                        <caption class="sr-only">
                            Students
                        </caption>
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr
                                class="text-left font-medium text-gray-600 dark:text-gray-300"
                            >
                                <th scope="col" class="w-10 px-4 py-3">
                                    <Checkbox
                                        :checked="allSelected"
                                        aria-label="Select all students on this page"
                                        @update:checked="toggleAll"
                                    />
                                </th>
                                <th
                                    scope="col"
                                    class="px-4 py-3"
                                    :aria-sort="ariaSort('name')"
                                >
                                    <button
                                        type="button"
                                        class="font-medium hover:underline focus:underline focus:outline-hidden"
                                        @click="sortBy('name')"
                                    >
                                        Name
                                        <span aria-hidden="true">{{
                                            filters.sort === 'name'
                                                ? filters.direction === 'asc'
                                                    ? '▲'
                                                    : '▼'
                                                : ''
                                        }}</span>
                                    </button>
                                </th>
                                <th
                                    scope="col"
                                    class="px-4 py-3"
                                    :aria-sort="ariaSort('admission_no')"
                                >
                                    <button
                                        type="button"
                                        class="font-medium hover:underline focus:underline focus:outline-hidden"
                                        @click="sortBy('admission_no')"
                                    >
                                        Admission no.
                                        <span aria-hidden="true">{{
                                            filters.sort === 'admission_no'
                                                ? filters.direction === 'asc'
                                                    ? '▲'
                                                    : '▼'
                                                : ''
                                        }}</span>
                                    </button>
                                </th>
                                <th
                                    scope="col"
                                    class="px-4 py-3"
                                    :aria-sort="ariaSort('class')"
                                >
                                    <button
                                        type="button"
                                        class="font-medium hover:underline focus:underline focus:outline-hidden"
                                        @click="sortBy('class')"
                                    >
                                        Class
                                        <span aria-hidden="true">{{
                                            filters.sort === 'class'
                                                ? filters.direction === 'asc'
                                                    ? '▲'
                                                    : '▼'
                                                : ''
                                        }}</span>
                                    </button>
                                </th>
                                <th scope="col" class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-200 text-gray-900 dark:divide-gray-700 dark:text-gray-100"
                        >
                            <tr
                                v-for="student in students.data"
                                :key="student.id"
                                :class="{
                                    'bg-indigo-50/60 dark:bg-indigo-950/30':
                                        selected.includes(student.id),
                                }"
                            >
                                <td class="px-4 py-3">
                                    <Checkbox
                                        :checked="selected.includes(student.id)"
                                        :aria-label="`Select ${student.name}`"
                                        @update:checked="
                                            (checked: boolean) =>
                                                toggle(student.id, checked)
                                        "
                                    />
                                </td>
                                <td class="px-4 py-3 font-medium">
                                    <Link
                                        :href="
                                            route('students.show', student.id)
                                        "
                                        class="hover:underline focus:underline focus:outline-hidden"
                                    >
                                        {{ student.last_name }},
                                        {{ student.first_name }}
                                    </Link>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs">
                                    {{ student.admission_no }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ student.class?.name ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="statusClasses[student.status]"
                                    >
                                        {{ student.status_label }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="students.data.length === 0">
                                <td
                                    colspan="5"
                                    class="px-4 py-10 text-center text-gray-600 dark:text-gray-400"
                                >
                                    No students match these filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <Pagination :meta="students.meta" label="Student pages" />
            </div>
        </div>

        <Modal
            :show="confirmingLeave"
            max-width="md"
            @close="confirmingLeave = false"
        >
            <div class="p-6">
                <h2
                    class="text-lg font-medium text-gray-900 dark:text-gray-100"
                >
                    Mark {{ selected.length }} students as left?
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Their enrolment this year ends today. Their records stay,
                    and you can make them active again from their profile.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton
                        type="button"
                        @click="confirmingLeave = false"
                    >
                        Cancel
                    </SecondaryButton>
                    <DangerButton
                        type="button"
                        :disabled="bulk.processing"
                        @click="applyBulk"
                    >
                        Mark as left
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
