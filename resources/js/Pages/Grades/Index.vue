<script setup lang="ts">
import TermSelect from '@/Components/TermSelect.vue';
import { formatDate } from '@/composables/dates';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { GradebookClass, TermOption } from '@/types/school';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    year: string;
    terms: TermOption[];
    term: TermOption;
    classes: GradebookClass[];
    /** School admins see every class-subject, so they also see who teaches it. */
    allClasses: boolean;
    canEditScale: boolean;
}>();

const shortDate = (date: string) =>
    formatDate(date, { day: 'numeric', month: 'short' });

const buttonClasses =
    'inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold tracking-widest text-gray-700 uppercase shadow-xs hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden dark:border-gray-500 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700';
</script>

<template>
    <Head :title="$t('Grades')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2
                        class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
                    >
                        {{ $t('Grades') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {{ year }} · {{ term.name }},
                        {{ shortDate(term.starts_on) }} –
                        {{ shortDate(term.ends_on) }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <TermSelect
                        :terms="terms"
                        :current="term.id"
                        :url="route('grades.index')"
                    />
                    <Link
                        v-if="canEditScale"
                        :href="route('grade-scale.edit')"
                        :class="buttonClasses"
                    >
                        {{ $t('Grade scale') }}
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <ul
                    v-if="classes.length > 0"
                    class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                    :aria-label="$t('Classes')"
                >
                    <li
                        v-for="c in classes"
                        :key="c.id"
                        class="flex flex-col gap-3 bg-white p-5 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <h3
                                class="text-lg font-medium text-gray-900 dark:text-gray-100"
                            >
                                {{ c.name }}
                            </h3>
                            <span
                                v-if="c.is_class_teacher"
                                class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium whitespace-nowrap text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200"
                            >
                                {{ $t('Your class') }}
                            </span>
                        </div>

                        <ul
                            v-if="c.subjects.length > 0"
                            class="divide-y divide-gray-100 text-sm dark:divide-gray-700"
                            :aria-label="
                                $t('Subjects in :name', { name: c.name })
                            "
                        >
                            <li
                                v-for="s in c.subjects"
                                :key="s.id"
                                class="flex items-baseline justify-between gap-3 py-2"
                            >
                                <Link
                                    :href="
                                        route('grades.gradebook', {
                                            schoolClass: c.id,
                                            subject: s.id,
                                            term: term.id,
                                        })
                                    "
                                    class="font-medium text-indigo-700 hover:underline focus:ring-2 focus:ring-indigo-500 focus:outline-hidden dark:text-indigo-300"
                                    :aria-label="`${s.name} in ${c.name}`"
                                >
                                    {{ s.name }}
                                </Link>
                                <span
                                    class="text-right text-gray-600 dark:text-gray-400"
                                >
                                    {{ s.assessments_count }}
                                    {{
                                        s.assessments_count === 1
                                            ? 'assessment'
                                            : 'assessments'
                                    }}
                                    <span
                                        v-if="allClasses && s.teacher"
                                        class="block text-xs"
                                    >
                                        {{ s.teacher }}
                                    </span>
                                </span>
                            </li>
                        </ul>
                        <p
                            v-else
                            class="text-sm text-gray-600 dark:text-gray-400"
                        >
                            {{ $t('You teach no subjects in this class.') }}
                        </p>

                        <div v-if="c.can_view_results" class="mt-auto">
                            <Link
                                :href="
                                    route('results.show', {
                                        schoolClass: c.id,
                                        term: term.id,
                                    })
                                "
                                :class="buttonClasses"
                                :aria-label="
                                    $t('Results of :name', { name: c.name })
                                "
                            >
                                {{ $t('Results') }}
                            </Link>
                        </div>
                    </li>
                </ul>

                <p
                    v-else
                    class="bg-white p-6 text-gray-600 shadow-xs sm:rounded-lg dark:bg-gray-800 dark:text-gray-400"
                >
                    {{
                        $t(
                            'You teach no classes this year. A school admin assigns subject teachers under Classes.',
                        )
                    }}
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
