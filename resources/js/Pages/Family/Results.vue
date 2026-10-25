<script setup lang="ts">
import ReportCardResult from '@/Components/ReportCardResult.vue';
import TermSelect from '@/Components/TermSelect.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ChildHeader from '@/Pages/Family/Partials/ChildHeader.vue';
import type { Child } from '@/types/family';
import type { IdName, ReportCardSnapshot, TermOption } from '@/types/school';
import { Head } from '@inertiajs/vue3';

defineProps<{
    child: Child;
    children: Child[];
    terms: TermOption[];
    term: IdName;
    report: ReportCardSnapshot;
}>();
</script>

<template>
    <Head
        :title="$t('Results :first_name', { first_name: child.first_name })"
    />

    <AuthenticatedLayout>
        <template #header>
            <ChildHeader
                :child="child"
                :children="children"
                page="family.results"
            />
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{
                            $t(
                                'Results so far in the :term. The report card is what counts; it may still change until then.',
                                { term: term.name },
                            )
                        }}
                    </p>
                    <TermSelect
                        :terms="terms"
                        :current="term.id"
                        :url="route('family.results', child.id)"
                    />
                </div>
                <ReportCardResult :report="report" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
