<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { formatDate } from '@/composables/dates';
import { errorFor } from '@/composables/formErrors';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { Option } from '@/types';
import type {
    AttendanceStatus,
    AttendanceStudent,
    IdName,
} from '@/types/school';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    schoolClass: IdName;
    date: string;
    previousDate: string | null;
    nextDate: string | null;
    students: AttendanceStudent[];
    recorded: { count: number; by: string | null; at: string | null };
    statuses: Option[];
}>();

// Students without a record yet start as present: one click per absent student.
const form = useForm({
    records: props.students.map((s) => ({
        student_id: s.id,
        status: (s.status ?? 'present') as AttendanceStatus,
        note: s.note ?? '',
    })),
});

const counts = computed(() =>
    form.records.reduce(
        (totals, r) => ({ ...totals, [r.status]: totals[r.status] + 1 }),
        { present: 0, absent: 0, late: 0, excused: 0 } as Record<
            AttendanceStatus,
            number
        >,
    ),
);

const markAll = (status: AttendanceStatus) =>
    form.records.forEach((r) => (r.status = status));

const save = () =>
    form.put(route('attendance.update', [props.schoolClass.id, props.date]), {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });

const statusStyles: Record<AttendanceStatus, string> = {
    present:
        'peer-checked:border-green-600 peer-checked:bg-green-600 peer-checked:text-white',
    absent: 'peer-checked:border-red-600 peer-checked:bg-red-600 peer-checked:text-white',
    late: 'peer-checked:border-amber-500 peer-checked:bg-amber-500 peer-checked:text-gray-900',
    excused:
        'peer-checked:border-sky-600 peer-checked:bg-sky-600 peer-checked:text-white',
};

const recordedAt = computed(() =>
    props.recorded.at
        ? new Intl.DateTimeFormat(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(props.recorded.at))
        : null,
);

const navClasses =
    'inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-xs hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden dark:border-gray-500 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700';
</script>

<template>
    <Head :title="`Attendance ${schoolClass.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <nav aria-label="Breadcrumb" class="text-sm">
                <Link
                    :href="route('attendance.index', { date })"
                    class="text-gray-600 hover:underline dark:text-gray-400"
                >
                    Attendance
                </Link>
            </nav>
            <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
                <h2
                    class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
                >
                    {{ schoolClass.name }} · {{ formatDate(date) }}
                </h2>
                <nav class="flex gap-2" aria-label="Other days">
                    <Link
                        v-if="previousDate"
                        :href="
                            route('attendance.show', [
                                schoolClass.id,
                                previousDate,
                            ])
                        "
                        :class="navClasses"
                    >
                        ‹ Previous day
                    </Link>
                    <Link
                        v-if="nextDate"
                        :href="
                            route('attendance.show', [schoolClass.id, nextDate])
                        "
                        :class="navClasses"
                    >
                        Next day ›
                    </Link>
                    <Link
                        :href="route('attendance.report', schoolClass.id)"
                        :class="navClasses"
                    >
                        Monthly report
                    </Link>
                </nav>
            </div>
        </template>

        <div class="py-8">
            <form
                class="mx-auto max-w-5xl space-y-4 px-4 sm:px-6 lg:px-8"
                @submit.prevent="save"
            >
                <section
                    class="flex flex-wrap items-center justify-between gap-3 bg-white p-4 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    aria-label="Summary"
                >
                    <p
                        class="text-sm text-gray-700 dark:text-gray-300"
                        role="status"
                    >
                        <span class="font-medium">{{ counts.present }}</span>
                        present ·
                        <span class="font-medium">{{ counts.absent }}</span>
                        absent ·
                        <span class="font-medium">{{ counts.late }}</span>
                        late ·
                        <span class="font-medium">{{ counts.excused }}</span>
                        excused
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        <template v-if="recorded.count === 0">
                            Not taken yet. Everyone starts as present.
                        </template>
                        <template v-else>
                            Saved {{ recordedAt }}
                            <template v-if="recorded.by">
                                by {{ recorded.by }}</template
                            >
                        </template>
                    </p>
                    <SecondaryButton type="button" @click="markAll('present')">
                        Mark all present
                    </SecondaryButton>
                </section>

                <InputError :message="form.errors.records" />

                <section
                    class="overflow-x-auto bg-white shadow-xs sm:rounded-lg dark:bg-gray-800"
                >
                    <table
                        class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700"
                    >
                        <caption class="sr-only">
                            Attendance
                        </caption>
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr
                                class="text-left font-medium text-gray-600 dark:text-gray-300"
                            >
                                <th scope="col" class="w-14 px-4 py-3">Roll</th>
                                <th scope="col" class="px-4 py-3">Student</th>
                                <th scope="col" class="px-4 py-3">Status</th>
                                <th scope="col" class="px-4 py-3">Note</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-200 text-gray-900 dark:divide-gray-700 dark:text-gray-100"
                        >
                            <tr
                                v-for="(student, index) in students"
                                :key="student.id"
                                :class="{
                                    'bg-red-50/60 dark:bg-red-950/30':
                                        form.records[index]!.status ===
                                        'absent',
                                }"
                            >
                                <td
                                    class="px-4 py-2 text-gray-600 dark:text-gray-400"
                                >
                                    {{ student.roll_no ?? '—' }}
                                </td>
                                <th
                                    :id="`student-${student.id}`"
                                    scope="row"
                                    class="px-4 py-2 text-left font-medium"
                                >
                                    {{ student.name }}
                                </th>
                                <td class="px-4 py-2">
                                    <div
                                        role="radiogroup"
                                        :aria-labelledby="`student-${student.id}`"
                                        class="flex flex-wrap gap-1"
                                    >
                                        <label
                                            v-for="option in statuses"
                                            :key="option.value"
                                        >
                                            <input
                                                v-model="
                                                    form.records[index]!.status
                                                "
                                                type="radio"
                                                class="peer sr-only"
                                                :name="`status-${student.id}`"
                                                :value="option.value"
                                            />
                                            <span
                                                class="inline-block cursor-pointer rounded-md border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 select-none peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500 peer-focus-visible:ring-offset-1 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                                :class="
                                                    statusStyles[
                                                        option.value as AttendanceStatus
                                                    ]
                                                "
                                            >
                                                {{ option.label }}
                                            </span>
                                        </label>
                                    </div>
                                    <InputError
                                        class="mt-1"
                                        :message="
                                            errorFor(
                                                form.errors,
                                                `records.${index}.status`,
                                            )
                                        "
                                    />
                                </td>
                                <td class="px-4 py-2">
                                    <input
                                        v-model="form.records[index]!.note"
                                        type="text"
                                        maxlength="255"
                                        :aria-label="`Note for ${student.name}`"
                                        class="w-full min-w-40 rounded-md border-gray-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    />
                                    <InputError
                                        class="mt-1"
                                        :message="
                                            errorFor(
                                                form.errors,
                                                `records.${index}.note`,
                                            )
                                        "
                                    />
                                </td>
                            </tr>
                            <tr v-if="students.length === 0">
                                <td
                                    colspan="4"
                                    class="px-4 py-10 text-center text-gray-600 dark:text-gray-400"
                                >
                                    No students are enrolled in this class on
                                    this day.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <div
                    class="sticky bottom-0 flex items-center justify-end gap-3 bg-gray-100/90 py-3 backdrop-blur-sm dark:bg-gray-900/90"
                >
                    <p
                        v-if="form.isDirty"
                        class="text-sm text-gray-600 dark:text-gray-400"
                    >
                        Unsaved changes
                    </p>
                    <PrimaryButton
                        :disabled="form.processing || students.length === 0"
                    >
                        Save attendance
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
