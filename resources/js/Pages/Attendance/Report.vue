<script setup lang="ts">
import InputLabel from '@/Components/InputLabel.vue';
import SelectInput from '@/Components/SelectInput.vue';
import { formatDate } from '@/composables/dates';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { Option } from '@/types';
import type { AttendanceReport, IdName } from '@/types/school';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import DailyAttendanceChart from './Partials/DailyAttendanceChart.vue';

const props = defineProps<{
    schoolClass: IdName;
    month: string;
    months: Option[];
    report: AttendanceReport;
}>();

const month = ref(props.month);
watch(month, (value) =>
    router.get(
        route('attendance.report', props.schoolClass.id),
        { month: value },
        { preserveState: true, preserveScroll: true, replace: true },
    ),
);

const monthLabel = computed(
    () => props.months.find((m) => m.value === props.month)?.label ?? '',
);

const rate = (value: number | null) =>
    value === null
        ? '—'
        : `${value.toLocaleString(undefined, { maximumFractionDigits: 1 })} %`;

const codeClasses: Record<string, string> = {
    P: 'text-green-700 dark:text-green-400',
    A: 'bg-red-100 font-semibold text-red-800 dark:bg-red-900/50 dark:text-red-200',
    L: 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200',
    E: 'bg-sky-100 text-sky-800 dark:bg-sky-900/50 dark:text-sky-200',
};

const buttonClasses =
    'inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold tracking-widest text-gray-700 uppercase shadow-xs hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden dark:border-gray-500 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700';
</script>

<template>
    <Head :title="`Attendance report ${schoolClass.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <nav aria-label="Breadcrumb" class="text-sm">
                <Link
                    :href="route('attendance.index')"
                    class="text-gray-600 hover:underline dark:text-gray-400"
                >
                    Attendance
                </Link>
            </nav>
            <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
                <h2
                    class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
                >
                    {{ schoolClass.name }} · {{ monthLabel }}
                </h2>
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <InputLabel for="month" value="Month" class="sr-only" />
                        <SelectInput id="month" v-model="month" class="block">
                            <option
                                v-for="m in months"
                                :key="m.value"
                                :value="m.value"
                            >
                                {{ m.label }}
                            </option>
                        </SelectInput>
                    </div>
                    <a
                        :href="
                            route('attendance.export', {
                                schoolClass: schoolClass.id,
                                month,
                            })
                        "
                        :class="buttonClasses"
                    >
                        Export XLSX
                    </a>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <dl class="grid gap-4 sm:grid-cols-3">
                    <div
                        class="bg-white p-5 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    >
                        <dt class="text-sm text-gray-600 dark:text-gray-400">
                            Attendance rate
                        </dt>
                        <dd
                            class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100"
                        >
                            {{ rate(report.totals.rate) }}
                        </dd>
                    </div>
                    <div
                        class="bg-white p-5 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    >
                        <dt class="text-sm text-gray-600 dark:text-gray-400">
                            Days recorded
                        </dt>
                        <dd
                            class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100"
                        >
                            {{ report.days.length }}
                        </dd>
                    </div>
                    <div
                        class="bg-white p-5 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    >
                        <dt class="text-sm text-gray-600 dark:text-gray-400">
                            Absences (late, excused)
                        </dt>
                        <dd
                            class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100"
                        >
                            {{ report.totals.absent }}
                            <span
                                class="text-base font-normal text-gray-600 dark:text-gray-400"
                            >
                                ({{ report.totals.late }},
                                {{ report.totals.excused }})
                            </span>
                        </dd>
                    </div>
                </dl>

                <template v-if="report.days.length > 0">
                    <section
                        class="bg-white p-6 shadow-xs sm:rounded-lg dark:bg-gray-800"
                        aria-labelledby="chart-title"
                    >
                        <h3
                            id="chart-title"
                            class="text-lg font-medium text-gray-900 dark:text-gray-100"
                        >
                            Daily attendance
                        </h3>
                        <DailyAttendanceChart
                            class="mt-4"
                            :daily="report.daily"
                        />
                    </section>

                    <section
                        class="overflow-x-auto bg-white shadow-xs sm:rounded-lg dark:bg-gray-800"
                    >
                        <table
                            class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700"
                        >
                            <caption
                                class="px-4 pt-4 text-left text-lg font-medium text-gray-900 dark:text-gray-100"
                            >
                                Students
                                <span
                                    class="block text-sm font-normal text-gray-600 dark:text-gray-400"
                                >
                                    P present, A absent, L late, E excused
                                </span>
                            </caption>
                            <thead class="bg-gray-50 dark:bg-gray-900/50">
                                <tr
                                    class="text-left font-medium text-gray-600 dark:text-gray-300"
                                >
                                    <th
                                        scope="col"
                                        class="sticky left-0 bg-gray-50 px-4 py-2 dark:bg-gray-900"
                                    >
                                        Student
                                    </th>
                                    <th
                                        v-for="day in report.days"
                                        :key="day"
                                        scope="col"
                                        class="px-1.5 py-2 text-center text-xs font-medium"
                                    >
                                        <abbr
                                            :title="formatDate(day)"
                                            class="no-underline"
                                        >
                                            {{
                                                formatDate(day, {
                                                    day: 'numeric',
                                                })
                                            }}
                                        </abbr>
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-2 py-2 text-right"
                                    >
                                        A
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-2 py-2 text-right"
                                    >
                                        L
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-2 py-2 text-right"
                                    >
                                        E
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-4 py-2 text-right"
                                    >
                                        Rate
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-gray-200 text-gray-900 dark:divide-gray-700 dark:text-gray-100"
                            >
                                <tr
                                    v-for="student in report.students"
                                    :key="student.id"
                                >
                                    <th
                                        scope="row"
                                        class="sticky left-0 bg-white px-4 py-1.5 text-left font-medium whitespace-nowrap dark:bg-gray-800"
                                    >
                                        {{ student.name }}
                                    </th>
                                    <td
                                        v-for="day in report.days"
                                        :key="day"
                                        class="px-1.5 py-1.5 text-center text-xs"
                                        :class="
                                            codeClasses[
                                                student.statuses[day] ?? ''
                                            ]
                                        "
                                    >
                                        {{ student.statuses[day] ?? '' }}
                                    </td>
                                    <td class="px-2 py-1.5 text-right">
                                        {{ student.totals.absent }}
                                    </td>
                                    <td class="px-2 py-1.5 text-right">
                                        {{ student.totals.late }}
                                    </td>
                                    <td class="px-2 py-1.5 text-right">
                                        {{ student.totals.excused }}
                                    </td>
                                    <td
                                        class="px-4 py-1.5 text-right whitespace-nowrap"
                                    >
                                        {{ rate(student.rate) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </section>
                </template>

                <p
                    v-else
                    class="bg-white p-6 text-gray-600 shadow-xs sm:rounded-lg dark:bg-gray-800 dark:text-gray-400"
                >
                    No attendance was taken in {{ schoolClass.name }} in
                    {{ monthLabel }}.
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
