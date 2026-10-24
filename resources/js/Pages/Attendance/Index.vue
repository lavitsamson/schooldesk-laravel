<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatDate } from '@/composables/dates';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { AttendanceClass } from '@/types/school';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    date: string;
    today: string;
    classes: AttendanceClass[];
}>();

const date = ref(props.date);
watch(date, (value) => {
    if (value) {
        router.get(
            route('attendance.index'),
            { date: value === props.today ? undefined : value },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }
});

const errors = computed(() => usePage().props.errors);

const progress = (c: AttendanceClass) =>
    c.recorded_count === 0
        ? {
              label: 'Not taken',
              classes:
                  'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
          }
        : c.recorded_count >= c.students_count
          ? {
                label: 'Taken',
                classes:
                    'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200',
            }
          : {
                label: `${c.recorded_count} of ${c.students_count} recorded`,
                classes:
                    'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200',
            };
</script>

<template>
    <Head title="Attendance" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
            >
                Attendance
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section
                    class="flex flex-wrap items-end gap-4 bg-white p-4 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    aria-label="Date"
                >
                    <div>
                        <InputLabel for="date" value="Date" />
                        <TextInput
                            id="date"
                            v-model="date"
                            type="date"
                            class="mt-1 block"
                            :max="today"
                        />
                    </div>
                    <p class="pb-2 text-sm text-gray-600 dark:text-gray-400">
                        {{ formatDate(props.date) }}
                    </p>
                    <InputError class="w-full" :message="errors.date" />
                </section>

                <ul
                    v-if="classes.length > 0"
                    class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                    aria-label="Classes"
                >
                    <li
                        v-for="c in classes"
                        :key="c.id"
                        class="flex flex-col gap-3 bg-white p-5 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h3
                                    class="text-lg font-medium text-gray-900 dark:text-gray-100"
                                >
                                    {{ c.name }}
                                </h3>
                                <p
                                    class="text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ c.students_count }} students<span
                                        v-if="c.is_class_teacher"
                                    >
                                        · your class</span
                                    >
                                </p>
                            </div>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap"
                                :class="progress(c).classes"
                            >
                                {{ progress(c).label }}
                            </span>
                        </div>
                        <div class="mt-auto flex flex-wrap gap-3">
                            <Link
                                :href="
                                    route('attendance.show', [c.id, props.date])
                                "
                                class="inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase hover:bg-gray-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden dark:bg-gray-200 dark:text-gray-800 dark:hover:bg-white"
                                :aria-label="`Take attendance for ${c.name}`"
                            >
                                {{
                                    c.recorded_count === 0
                                        ? 'Take attendance'
                                        : 'Edit attendance'
                                }}
                            </Link>
                            <Link
                                :href="route('attendance.report', c.id)"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold tracking-widest text-gray-700 uppercase shadow-xs hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden dark:border-gray-500 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                :aria-label="`Monthly report for ${c.name}`"
                            >
                                Monthly report
                            </Link>
                        </div>
                    </li>
                </ul>

                <p
                    v-else
                    class="bg-white p-6 text-gray-600 shadow-xs sm:rounded-lg dark:bg-gray-800 dark:text-gray-400"
                >
                    You have no classes this year. A school admin assigns class
                    teachers and subject teachers under Classes.
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
