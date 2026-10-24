<script setup lang="ts">
import TermSelect from '@/Components/TermSelect.vue';
import { formatDate } from '@/composables/dates';
import { formatMarks, formatPercent } from '@/composables/grades';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { IdName, ReportCardSnapshot, TermOption } from '@/types/school';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    schoolClass: IdName;
    terms: TermOption[];
    report: ReportCardSnapshot;
}>();

const name = computed(
    () =>
        `${props.report.student.first_name} ${props.report.student.last_name}`,
);

const shortDate = (date: string) =>
    formatDate(date, { day: 'numeric', month: 'short' });

const rate = (value: number | null) =>
    value === null
        ? '—'
        : `${value.toLocaleString(undefined, { maximumFractionDigits: 1 })} %`;
</script>

<template>
    <Head :title="`Results ${name}`" />

    <AuthenticatedLayout>
        <template #header>
            <nav aria-label="Breadcrumb" class="text-sm">
                <Link
                    :href="route('grades.index', { term: report.term.id })"
                    class="text-gray-600 hover:underline dark:text-gray-400"
                >
                    Grades
                </Link>
                <span class="mx-1 text-gray-400" aria-hidden="true">›</span>
                <Link
                    :href="
                        route('results.show', {
                            schoolClass: schoolClass.id,
                            term: report.term.id,
                        })
                    "
                    class="text-gray-600 hover:underline dark:text-gray-400"
                >
                    Results · {{ schoolClass.name }}
                </Link>
            </nav>
            <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
                <h2
                    class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
                >
                    {{ name }} · {{ report.term.name }}
                </h2>
                <TermSelect
                    :terms="terms"
                    :current="report.term.id"
                    :url="
                        route('results.student', [
                            schoolClass.id,
                            report.student.id,
                        ])
                    "
                />
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                <dl class="grid gap-4 sm:grid-cols-3">
                    <div
                        class="bg-white p-5 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    >
                        <dt class="text-sm text-gray-600 dark:text-gray-400">
                            Overall result
                        </dt>
                        <dd
                            class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100"
                        >
                            {{ report.overall.grade ?? '—' }}
                            <span
                                class="text-base font-normal text-gray-600 dark:text-gray-400"
                            >
                                {{ formatPercent(report.overall.percentage) }}
                            </span>
                        </dd>
                    </div>
                    <div
                        class="bg-white p-5 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    >
                        <dt class="text-sm text-gray-600 dark:text-gray-400">
                            Attendance
                        </dt>
                        <dd
                            class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100"
                        >
                            {{ rate(report.attendance.rate) }}
                            <span
                                class="text-base font-normal text-gray-600 dark:text-gray-400"
                            >
                                {{ report.attendance.absent }} absent of
                                {{ report.attendance.days }} days
                            </span>
                        </dd>
                    </div>
                    <div
                        class="bg-white p-5 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    >
                        <dt class="text-sm text-gray-600 dark:text-gray-400">
                            Admission no.
                        </dt>
                        <dd
                            class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100"
                        >
                            {{ report.student.admission_no }}
                        </dd>
                    </div>
                </dl>

                <section
                    class="bg-white shadow-xs sm:rounded-lg dark:bg-gray-800"
                    aria-labelledby="subjects-title"
                >
                    <h3
                        id="subjects-title"
                        class="px-4 pt-4 text-lg font-medium text-gray-900 dark:text-gray-100"
                    >
                        Subjects
                    </h3>
                    <ul
                        class="mt-2 divide-y divide-gray-200 dark:divide-gray-700"
                    >
                        <li v-for="s in report.subjects" :key="s.id">
                            <details class="group">
                                <summary
                                    class="flex cursor-pointer items-center justify-between gap-4 px-4 py-3 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden focus:ring-inset"
                                >
                                    <span
                                        class="font-medium text-gray-900 dark:text-gray-100"
                                    >
                                        {{ s.name }}
                                        <span
                                            class="ms-2 text-xs font-normal text-gray-600 dark:text-gray-400"
                                        >
                                            {{ s.graded }} of
                                            {{ s.assessments_count }} marked
                                        </span>
                                    </span>
                                    <span class="text-right">
                                        <span
                                            class="font-semibold text-gray-900 dark:text-gray-100"
                                        >
                                            {{ s.grade ?? '—' }}
                                        </span>
                                        <span
                                            class="ms-2 text-sm text-gray-600 tabular-nums dark:text-gray-400"
                                        >
                                            {{ formatPercent(s.percentage) }}
                                        </span>
                                    </span>
                                </summary>
                                <table
                                    v-if="s.assessments.length > 0"
                                    class="mb-3 w-full text-sm"
                                >
                                    <caption class="sr-only">
                                        Assessments in
                                        {{
                                            s.name
                                        }}
                                    </caption>
                                    <thead>
                                        <tr
                                            class="text-left text-xs text-gray-600 dark:text-gray-400"
                                        >
                                            <th scope="col" class="px-6 py-1">
                                                Assessment
                                            </th>
                                            <th scope="col" class="px-3 py-1">
                                                Date
                                            </th>
                                            <th
                                                scope="col"
                                                class="px-3 py-1 text-right"
                                            >
                                                Marks
                                            </th>
                                            <th
                                                scope="col"
                                                class="px-6 py-1 text-right"
                                            >
                                                Weight
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="text-gray-800 dark:text-gray-200"
                                    >
                                        <tr
                                            v-for="a in s.assessments"
                                            :key="a.id"
                                        >
                                            <td class="px-6 py-1">
                                                {{ a.name }}
                                                <span
                                                    v-if="a.remarks"
                                                    class="block text-xs text-gray-600 dark:text-gray-400"
                                                >
                                                    {{ a.remarks }}
                                                </span>
                                            </td>
                                            <td
                                                class="px-3 py-1 whitespace-nowrap"
                                            >
                                                {{ shortDate(a.held_on) }}
                                            </td>
                                            <td
                                                class="px-3 py-1 text-right tabular-nums"
                                            >
                                                {{ formatMarks(a.marks) }} /
                                                {{ a.max_marks }}
                                            </td>
                                            <td
                                                class="px-6 py-1 text-right tabular-nums"
                                            >
                                                × {{ formatMarks(a.weight) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <p
                                    v-else
                                    class="px-6 pb-3 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    No assessments yet.
                                </p>
                            </details>
                        </li>
                        <li
                            v-if="report.subjects.length === 0"
                            class="px-4 py-6 text-sm text-gray-600 dark:text-gray-400"
                        >
                            No subjects are taught in this class yet.
                        </li>
                    </ul>
                </section>

                <section
                    class="bg-white p-4 text-sm shadow-xs sm:rounded-lg dark:bg-gray-800"
                    aria-labelledby="scale-title"
                >
                    <h3
                        id="scale-title"
                        class="font-medium text-gray-900 dark:text-gray-100"
                    >
                        Grade scale
                    </h3>
                    <ul
                        class="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-gray-700 dark:text-gray-300"
                    >
                        <li
                            v-for="band in report.grade_scale"
                            :key="band.label"
                        >
                            <span class="font-semibold">{{ band.label }}</span>
                            {{ band.min_percent }}–{{ band.max_percent }} %
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
