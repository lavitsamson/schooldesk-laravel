<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { formatRate } from '@/composables/numbers';
import { formatDate } from '@/composables/dates';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ChildHeader from '@/Pages/Family/Partials/ChildHeader.vue';
import type { AttendanceMonth, Child } from '@/types/family';
import type { AttendanceStatus } from '@/types/school';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    child: Child;
    children: Child[];
    attendance: AttendanceMonth;
}>();

const monthTitle = computed(() =>
    formatDate(`${props.attendance.month}-01`, {
        month: 'long',
        year: 'numeric',
    }),
);

// Monday to Sunday in the active language (1 January 2024 was a Monday).
const weekdays = computed(() =>
    [1, 2, 3, 4, 5, 6, 7].map((day) =>
        formatDate(`2024-01-0${day}`, { weekday: 'short' }),
    ),
);

// Empty cells before the 1st, so the days line up under their weekday.
const leadingBlanks = computed(
    () => (props.attendance.days[0]?.weekday ?? 1) - 1,
);

const statuses: Record<
    AttendanceStatus,
    { label: string; code: string; classes: string }
> = {
    present: {
        label: trans('Present'),
        code: 'P',
        classes:
            'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200',
    },
    absent: {
        label: trans('Absent'),
        code: 'A',
        classes: 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200',
    },
    late: {
        label: trans('Late'),
        code: 'L',
        classes:
            'bg-amber-100 text-amber-900 dark:bg-amber-900/50 dark:text-amber-200',
    },
    excused: {
        label: trans('Excused'),
        code: 'E',
        classes: 'bg-sky-100 text-sky-800 dark:bg-sky-900/50 dark:text-sky-200',
    },
};

const dayLabel = (date: string, status: AttendanceStatus | null) =>
    `${formatDate(date)}: ${status ? statuses[status].label : trans('no attendance taken')}`;
</script>

<template>
    <Head
        :title="$t('Attendance :first_name', { first_name: child.first_name })"
    />

    <AuthenticatedLayout>
        <template #header>
            <ChildHeader
                :child="child"
                :children="children"
                page="family.attendance"
            />
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div
                    class="bg-white p-4 shadow-xs sm:rounded-lg sm:p-6 dark:bg-gray-800"
                >
                    <div class="flex items-center justify-between gap-3">
                        <Link
                            :href="
                                route('family.attendance', {
                                    student: child.id,
                                    month: attendance.previous_month,
                                })
                            "
                            class="rounded-md px-2 py-1 text-sm font-medium text-indigo-700 hover:bg-gray-100 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden dark:text-indigo-300 dark:hover:bg-gray-700"
                            preserve-scroll
                        >
                            {{ $t('‹ Previous') }}
                        </Link>
                        <h3
                            class="text-lg font-semibold text-gray-900 dark:text-gray-100"
                        >
                            {{ monthTitle }}
                        </h3>
                        <Link
                            v-if="attendance.next_month"
                            :href="
                                route('family.attendance', {
                                    student: child.id,
                                    month: attendance.next_month,
                                })
                            "
                            class="rounded-md px-2 py-1 text-sm font-medium text-indigo-700 hover:bg-gray-100 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden dark:text-indigo-300 dark:hover:bg-gray-700"
                            preserve-scroll
                        >
                            {{ $t('Next ›') }}
                        </Link>
                        <span v-else class="w-16" aria-hidden="true" />
                    </div>

                    <div
                        class="mt-4 grid grid-cols-7 gap-1 text-center text-sm"
                        role="list"
                        :aria-label="
                            $t('Attendance in :month', { month: monthTitle })
                        "
                    >
                        <div
                            v-for="weekday in weekdays"
                            :key="weekday"
                            class="pb-1 text-xs font-medium text-gray-500 dark:text-gray-400"
                            aria-hidden="true"
                        >
                            {{ weekday }}
                        </div>
                        <div
                            v-for="n in leadingBlanks"
                            :key="`blank-${n}`"
                            aria-hidden="true"
                        />
                        <div
                            v-for="day in attendance.days"
                            :key="day.date"
                            role="listitem"
                            :aria-label="dayLabel(day.date, day.status)"
                            :title="dayLabel(day.date, day.status)"
                            class="flex aspect-square flex-col items-center justify-center rounded-md sm:aspect-auto sm:h-16"
                            :class="
                                day.status
                                    ? statuses[day.status].classes
                                    : 'text-gray-500 dark:text-gray-400'
                            "
                        >
                            <span class="tabular-nums">
                                {{ Number(day.date.slice(8)) }}
                            </span>
                            <span
                                v-if="day.status"
                                class="text-xs font-semibold"
                                aria-hidden="true"
                            >
                                {{ statuses[day.status].code }}
                            </span>
                        </div>
                    </div>
                </div>

                <dl class="grid grid-cols-2 gap-4 sm:grid-cols-5">
                    <div
                        class="bg-white p-4 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    >
                        <dt class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $t('Attendance rate') }}
                        </dt>
                        <dd
                            class="mt-1 text-xl font-semibold text-gray-900 tabular-nums dark:text-gray-100"
                        >
                            {{ formatRate(attendance.attendance_rate) }}
                        </dd>
                    </div>
                    <div
                        v-for="(meta, status) in statuses"
                        :key="status"
                        class="bg-white p-4 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    >
                        <dt class="text-sm text-gray-600 dark:text-gray-400">
                            {{ meta.label }}
                        </dt>
                        <dd
                            class="mt-1 text-xl font-semibold text-gray-900 tabular-nums dark:text-gray-100"
                        >
                            {{ attendance.totals[status] }}
                        </dd>
                    </div>
                </dl>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{
                        $t(
                            'Late counts as attended. Days without a colour had no attendance taken (weekends, holidays, or not yet recorded).',
                        )
                    }}
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
