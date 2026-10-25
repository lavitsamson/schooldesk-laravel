<script setup lang="ts">
import { formatRate } from '@/composables/numbers';
import { formatDate } from '@/composables/dates';
import { formatMarks } from '@/composables/grades';
import { HttpError, sendJson } from '@/composables/http';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { AssessmentType, GradeSheetStudent, IdName } from '@/types/school';
import { Head, Link } from '@inertiajs/vue3';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
} from 'vue';

const props = defineProps<{
    assessment: {
        id: number;
        name: string;
        type: AssessmentType;
        type_label: string;
        held_on: string;
        max_marks: number;
        weight: string;
    };
    schoolClass: IdName;
    subject: IdName;
    term: IdName;
    students: GradeSheetStudent[];
    saved: { count: number; by: string | null; at: string | null };
}>();

type Field = 'marks' | 'remarks';

interface Row {
    student: GradeSheetStudent;
    marks: string;
    remarks: string;
    error: string | null;
}

const rows = reactive<Row[]>(
    props.students.map((student) => ({
        student,
        // "17.50" → "17.5"; never locale-formatted, so 1,000 cannot be misread as 1.000.
        marks: student.marks === null ? '' : String(Number(student.marks)),
        remarks: student.remarks ?? '',
        error: null,
    })),
);

/** "12,5" and "12.5" are both fine; blank means no marks. */
const normalise = (value: string): string | null => {
    const trimmed = value.trim().replace(',', '.');
    return trimmed === '' ? null : trimmed;
};

const validate = (row: Row): string | null => {
    const marks = normalise(row.marks);
    if (marks === null) return null;
    if (!/^\d+(\.\d{1,2})?$/.test(marks)) return 'Enter a number.';
    if (Number(marks) > props.assessment.max_marks)
        return `At most ${props.assessment.max_marks} marks.`;
    return null;
};

const percent = (row: Row): string => {
    const marks = normalise(row.marks);
    if (marks === null || row.error) return '';
    return formatRate((Number(marks) / props.assessment.max_marks) * 100);
};

const marked = computed(
    () => rows.filter((r) => normalise(r.marks) !== null && !r.error).length,
);

// Autosave: changed rows are collected and sent shortly after typing stops. Rows with
// invalid marks wait until they are fixed. One request at a time; changes made while a
// request is running go in the next one.
type Status =
    'idle' | 'pending' | 'saving' | 'saved' | 'invalid' | 'error' | 'expired';

const status = ref<Status>('idle');
const savedAt = ref<string | null>(props.saved.at);
const generalError = ref<string | null>(null);
const dirty = new Set<number>();
let timer: ReturnType<typeof setTimeout> | undefined;
let saving = false;

const schedule = (delay = 800) => {
    clearTimeout(timer);
    timer = setTimeout(flush, delay);
};

const changed = (index: number) => {
    const row = rows[index]!;
    row.error = validate(row);
    dirty.add(index);
    status.value = 'pending';
    schedule();
};

const flush = async () => {
    clearTimeout(timer);
    if (saving) return;

    const ready = [...dirty].filter((i) => !rows[i]!.error);
    if (ready.length === 0) {
        if (rows.some((r) => r.error)) status.value = 'invalid';
        return;
    }

    ready.forEach((i) => dirty.delete(i));
    saving = true;
    status.value = 'saving';
    generalError.value = null;

    try {
        const result = await sendJson<{ saved_at: string }>(
            'PUT',
            route('assessments.grades.update', props.assessment.id),
            {
                grades: ready.map((i) => ({
                    student_id: rows[i]!.student.id,
                    marks: normalise(rows[i]!.marks),
                    remarks: rows[i]!.remarks.trim() || null,
                })),
            },
        );
        savedAt.value = result.saved_at;
        status.value = rows.some((r) => r.error) ? 'invalid' : 'saved';
    } catch (error) {
        if (error instanceof HttpError && error.status === 422) {
            const errors =
                (error.body as { errors?: Record<string, string[]> } | null)
                    ?.errors ?? {};
            for (const [key, messages] of Object.entries(errors)) {
                const match = /^grades\.(\d+)\.(marks|remarks)$/.exec(key);
                const index = match ? ready[Number(match[1])] : undefined;
                if (index !== undefined) {
                    rows[index]!.error = messages[0] ?? 'Not valid.';
                } else {
                    generalError.value = messages[0] ?? 'Not saved.';
                }
            }
            status.value = 'invalid';
        } else if (error instanceof HttpError && error.status === 419) {
            ready.forEach((i) => dirty.add(i));
            status.value = 'expired';
        } else {
            // Network trouble or a server error: keep the rows and try again.
            ready.forEach((i) => dirty.add(i));
            status.value = 'error';
            schedule(5000);
        }
    } finally {
        saving = false;
    }

    if (
        [...dirty].some((i) => !rows[i]!.error) &&
        status.value !== 'error' &&
        status.value !== 'expired'
    ) {
        void flush();
    }
};

// Spreadsheet keys: Enter and ↓ go to the same cell in the next row, ↑ to the previous one.
const focusCell = (index: number, field: Field) => {
    const input = document.getElementById(
        `${field}-${index}`,
    ) as HTMLInputElement | null;
    input?.focus();
    input?.select();
};

const onKeydown = (event: KeyboardEvent, index: number, field: Field) => {
    if (event.key === 'Enter' || event.key === 'ArrowDown') {
        event.preventDefault();
        if (index + 1 < rows.length) focusCell(index + 1, field);
        else void flush();
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        if (index > 0) focusCell(index - 1, field);
    }
};

const warnBeforeLeaving = (event: BeforeUnloadEvent) => {
    if (dirty.size > 0 || saving) event.preventDefault();
};

onMounted(() => {
    window.addEventListener('beforeunload', warnBeforeLeaving);
    void nextTick(() => {
        const first = rows.findIndex((r) => r.marks === '');
        if (rows.length > 0) focusCell(first === -1 ? 0 : first, 'marks');
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', warnBeforeLeaving);
    // Leaving through a link: send what is left (fetch keeps running in an Inertia visit).
    if (dirty.size > 0) void flush();
});

const statusText = computed(() => {
    switch (status.value) {
        case 'pending':
            return 'Unsaved changes…';
        case 'saving':
            return 'Saving…';
        case 'saved':
            return 'All changes saved';
        case 'invalid':
            return 'Some marks are not valid and were not saved.';
        case 'error':
            return 'Could not save. Trying again…';
        case 'expired':
            return 'Your session has expired. Copy your changes and reload the page.';
        default:
            return savedAt.value
                ? 'All changes saved'
                : 'Marks are saved as you type.';
    }
});

const savedAtText = computed(() =>
    savedAt.value
        ? new Intl.DateTimeFormat(undefined, { timeStyle: 'short' }).format(
              new Date(savedAt.value),
          )
        : null,
);

const inputClasses =
    'block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300';
</script>

<template>
    <Head :title="$t('Marks :name', { name: assessment.name })" />

    <AuthenticatedLayout>
        <template #header>
            <nav :aria-label="$t('Breadcrumb')" class="text-sm">
                <Link
                    :href="route('grades.index', { term: term.id })"
                    class="text-gray-600 hover:underline dark:text-gray-400"
                >
                    {{ $t('Grades') }}
                </Link>
                <span class="mx-1 text-gray-400" aria-hidden="true">›</span>
                <Link
                    :href="
                        route('grades.gradebook', {
                            schoolClass: schoolClass.id,
                            subject: subject.id,
                            term: term.id,
                        })
                    "
                    class="text-gray-600 hover:underline dark:text-gray-400"
                >
                    {{ subject.name }} · {{ schoolClass.name }}
                </Link>
            </nav>
            <div
                class="mt-1 flex flex-wrap items-baseline justify-between gap-3"
            >
                <h2
                    class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
                >
                    {{ assessment.name }}
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ assessment.type_label }} ·
                    {{
                        $t(':held_on · out of', {
                            held_on: formatDate(assessment.held_on),
                        })
                    }}
                    {{
                        $t(':max_marks · weight', {
                            max_marks: assessment.max_marks,
                        })
                    }}
                    {{ formatMarks(assessment.weight) }}
                </p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-4 px-4 sm:px-6 lg:px-8">
                <section
                    class="flex flex-wrap items-center justify-between gap-3 bg-white p-4 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    :aria-label="$t('Summary')"
                >
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        <span class="font-medium">{{ marked }}</span> of
                        {{
                            $t(':count marked', { count: String(rows.length) })
                        }}
                    </p>
                    <p
                        class="text-sm"
                        :class="
                            status === 'invalid' ||
                            status === 'error' ||
                            status === 'expired'
                                ? 'font-medium text-red-700 dark:text-red-400'
                                : 'text-gray-600 dark:text-gray-400'
                        "
                        role="status"
                        aria-live="polite"
                        data-testid="save-status"
                    >
                        {{ statusText }}
                        <template v-if="status === 'saved' && savedAtText">
                            ({{ savedAtText }})
                        </template>
                    </p>
                    <p class="w-full text-xs text-gray-600 dark:text-gray-400">
                        {{
                            $t(
                                'Enter or ↓ moves to the next student, ↑ to the previous one. Leave marks empty if there are none, and add a remark such as "absent".',
                            )
                        }}
                    </p>
                    <p
                        v-if="generalError"
                        class="w-full text-sm text-red-700 dark:text-red-400"
                        role="alert"
                    >
                        {{ generalError }}
                    </p>
                </section>

                <section
                    class="overflow-x-auto bg-white shadow-xs sm:rounded-lg dark:bg-gray-800"
                >
                    <table
                        class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700"
                    >
                        <caption class="sr-only">
                            {{
                                $t('Marks for')
                            }}
                            {{
                                assessment.name
                            }}
                        </caption>
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr
                                class="text-left font-medium text-gray-600 dark:text-gray-300"
                            >
                                <th scope="col" class="w-14 px-4 py-3">
                                    {{ $t('Roll') }}
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    {{ $t('Student') }}
                                </th>
                                <th scope="col" class="w-36 px-4 py-3">
                                    Marks (/ {{ assessment.max_marks }})
                                </th>
                                <th
                                    scope="col"
                                    class="w-20 px-4 py-3 text-right"
                                >
                                    %
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    {{ $t('Remark') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-200 text-gray-900 dark:divide-gray-700 dark:text-gray-100"
                        >
                            <tr
                                v-for="(row, index) in rows"
                                :key="row.student.id"
                                :class="{
                                    'bg-red-50/60 dark:bg-red-950/30':
                                        row.error,
                                }"
                            >
                                <td
                                    class="px-4 py-2 text-gray-600 dark:text-gray-400"
                                >
                                    {{ row.student.roll_no ?? '—' }}
                                </td>
                                <th
                                    :id="`student-${row.student.id}`"
                                    scope="row"
                                    class="px-4 py-2 text-left font-medium whitespace-nowrap"
                                >
                                    {{ row.student.name }}
                                </th>
                                <td class="px-4 py-2">
                                    <input
                                        :id="`marks-${index}`"
                                        v-model="row.marks"
                                        type="text"
                                        inputmode="decimal"
                                        autocomplete="off"
                                        :aria-label="
                                            $t('Marks for :name', {
                                                name: row.student.name,
                                            })
                                        "
                                        :aria-invalid="
                                            row.error ? 'true' : 'false'
                                        "
                                        :aria-describedby="
                                            row.error
                                                ? `error-${index}`
                                                : undefined
                                        "
                                        :class="[
                                            inputClasses,
                                            'text-right tabular-nums',
                                            row.error
                                                ? 'border-red-500 ring-1 ring-red-500'
                                                : '',
                                        ]"
                                        @input="changed(index)"
                                        @keydown="
                                            onKeydown($event, index, 'marks')
                                        "
                                    />
                                    <p
                                        v-if="row.error"
                                        :id="`error-${index}`"
                                        class="mt-1 text-xs text-red-700 dark:text-red-400"
                                    >
                                        {{ row.error }}
                                    </p>
                                </td>
                                <td
                                    class="px-4 py-2 text-right text-gray-600 tabular-nums dark:text-gray-400"
                                >
                                    {{ percent(row) }}
                                </td>
                                <td class="px-4 py-2">
                                    <input
                                        :id="`remarks-${index}`"
                                        v-model="row.remarks"
                                        type="text"
                                        maxlength="255"
                                        autocomplete="off"
                                        :aria-label="
                                            $t('Remark for :name', {
                                                name: row.student.name,
                                            })
                                        "
                                        :class="[inputClasses, 'min-w-40']"
                                        @input="changed(index)"
                                        @keydown="
                                            onKeydown($event, index, 'remarks')
                                        "
                                    />
                                </td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td
                                    colspan="5"
                                    class="px-4 py-10 text-center text-gray-600 dark:text-gray-400"
                                >
                                    {{
                                        $t(
                                            'No students were enrolled in this class on the day of the assessment.',
                                        )
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
