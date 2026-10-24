<script setup lang="ts">
import TermSelect from '@/Components/TermSelect.vue';
import { formatPercent } from '@/composables/grades';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { ClassResultsStudent, IdName, TermOption } from '@/types/school';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    schoolClass: IdName;
    terms: TermOption[];
    term: IdName;
    subjects: { id: number; name: string; code: string }[];
    students: ClassResultsStudent[];
}>();

const rate = (value: number | null) =>
    value === null
        ? '—'
        : `${value.toLocaleString(undefined, { maximumFractionDigits: 1 })} %`;
</script>

<template>
    <Head :title="`Results ${schoolClass.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <nav aria-label="Breadcrumb" class="text-sm">
                <Link
                    :href="route('grades.index', { term: term.id })"
                    class="text-gray-600 hover:underline dark:text-gray-400"
                >
                    Grades
                </Link>
            </nav>
            <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
                <h2
                    class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
                >
                    Results · {{ schoolClass.name }} · {{ term.name }}
                </h2>
                <TermSelect
                    :terms="terms"
                    :current="term.id"
                    :url="route('results.show', schoolClass.id)"
                />
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Calculated from the marks entered so far: each subject is
                    the weighted average of its marked assessments, overall is
                    the average of the subjects.
                </p>

                <section
                    class="overflow-x-auto bg-white shadow-xs sm:rounded-lg dark:bg-gray-800"
                >
                    <table
                        class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700"
                    >
                        <caption class="sr-only">
                            Results by student and subject
                        </caption>
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr
                                class="text-left font-medium text-gray-600 dark:text-gray-300"
                            >
                                <th
                                    scope="col"
                                    class="sticky left-0 bg-gray-50 px-4 py-3 dark:bg-gray-900"
                                >
                                    Student
                                </th>
                                <th
                                    v-for="s in subjects"
                                    :key="s.id"
                                    scope="col"
                                    class="px-3 py-3 text-center"
                                >
                                    <abbr :title="s.name" class="no-underline">
                                        {{ s.code }}
                                    </abbr>
                                </th>
                                <th scope="col" class="px-4 py-3 text-right">
                                    Overall
                                </th>
                                <th scope="col" class="px-4 py-3 text-right">
                                    Attendance
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-200 text-gray-900 dark:divide-gray-700 dark:text-gray-100"
                        >
                            <tr v-for="student in students" :key="student.id">
                                <th
                                    scope="row"
                                    class="sticky left-0 bg-white px-4 py-2 text-left font-medium whitespace-nowrap dark:bg-gray-800"
                                >
                                    <Link
                                        :href="
                                            route('results.student', {
                                                schoolClass: schoolClass.id,
                                                student: student.id,
                                                term: term.id,
                                            })
                                        "
                                        class="text-indigo-700 hover:underline focus:ring-2 focus:ring-indigo-500 focus:outline-hidden dark:text-indigo-300"
                                    >
                                        {{ student.name }}
                                    </Link>
                                </th>
                                <td
                                    v-for="s in subjects"
                                    :key="s.id"
                                    class="px-3 py-2 text-center"
                                >
                                    <template
                                        v-if="student.results[s.id]?.grade"
                                    >
                                        <span class="font-semibold">
                                            {{ student.results[s.id]!.grade }}
                                        </span>
                                        <span
                                            class="block text-xs text-gray-600 tabular-nums dark:text-gray-400"
                                        >
                                            {{
                                                formatPercent(
                                                    student.results[s.id]!
                                                        .percentage,
                                                )
                                            }}
                                        </span>
                                    </template>
                                    <span
                                        v-else
                                        class="text-gray-400"
                                        :aria-label="`No marks in ${s.name}`"
                                    >
                                        —
                                    </span>
                                </td>
                                <td
                                    class="px-4 py-2 text-right whitespace-nowrap"
                                >
                                    <span class="font-semibold">
                                        {{ student.overall.grade ?? '—' }}
                                    </span>
                                    <span
                                        class="block text-xs text-gray-600 tabular-nums dark:text-gray-400"
                                    >
                                        {{
                                            formatPercent(
                                                student.overall.percentage,
                                            )
                                        }}
                                    </span>
                                </td>
                                <td
                                    class="px-4 py-2 text-right whitespace-nowrap tabular-nums"
                                >
                                    {{ rate(student.attendance_rate) }}
                                </td>
                            </tr>
                            <tr v-if="students.length === 0">
                                <td
                                    :colspan="subjects.length + 3"
                                    class="px-4 py-10 text-center text-gray-600 dark:text-gray-400"
                                >
                                    No students are enrolled in this class in
                                    this term.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
