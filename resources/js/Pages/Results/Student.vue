<script setup lang="ts">
import ReportCardResult from '@/Components/ReportCardResult.vue';
import TermSelect from '@/Components/TermSelect.vue';
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
                <ReportCardResult :report="report" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
