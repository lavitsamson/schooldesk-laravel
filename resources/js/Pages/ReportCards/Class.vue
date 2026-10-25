<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TermSelect from '@/Components/TermSelect.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ReportCardStatusBadge from '@/Pages/ReportCards/Partials/ReportCardStatusBadge.vue';
import type {
    ClassReportCardStudent,
    IdName,
    ReportCardBatch,
    TermOption,
} from '@/types/school';
import { Head, Link, useForm, usePage, usePoll } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps<{
    schoolClass: IdName;
    terms: TermOption[];
    term: IdName;
    students: ClassReportCardStudent[];
    batch: ReportCardBatch | null;
}>();

const running = computed(
    () => props.batch !== null && !props.batch.is_finished,
);

// While a batch runs, reload its progress and the list every 2 seconds.
const { start, stop } = usePoll(
    2000,
    { only: ['batch', 'students'] },
    { autoStart: running.value },
);
watch(running, (isRunning) => (isRunning ? start() : stop()));

const page = usePage();
const generateForm = useForm({ term: props.term.id });
const publishForm = useForm({ term: props.term.id });

const generate = () =>
    generateForm.post(route('report-cards.generate', props.schoolClass.id), {
        preserveScroll: true,
    });

const approved = computed(
    () => props.students.filter((s) => s.card?.status === 'approved').length,
);
const generated = computed(
    () => props.students.filter((s) => s.card !== null).length,
);

const publish = () => {
    if (
        !confirm(
            `Publish ${approved.value} approved report cards? Parents are notified and can read them.`,
        )
    ) {
        return;
    }
    publishForm.post(
        route('report-cards.publish-class', props.schoolClass.id),
        { preserveScroll: true },
    );
};
</script>

<template>
    <Head :title="$t('Report cards :name', { name: schoolClass.name })" />

    <AuthenticatedLayout>
        <template #header>
            <nav :aria-label="$t('Breadcrumb')" class="text-sm">
                <Link
                    :href="route('report-cards.index', { term: term.id })"
                    class="text-gray-600 hover:underline dark:text-gray-400"
                >
                    {{ $t('Report cards') }}
                </Link>
            </nav>
            <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
                <h2
                    class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
                >
                    {{ schoolClass.name }} · {{ term.name }}
                </h2>
                <TermSelect
                    :terms="terms"
                    :current="term.id"
                    :url="route('report-cards.class', schoolClass.id)"
                />
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section
                    class="space-y-4 bg-white p-5 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    aria-labelledby="generate-title"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-4"
                    >
                        <div class="max-w-2xl">
                            <h3
                                id="generate-title"
                                class="font-medium text-gray-900 dark:text-gray-100"
                            >
                                {{ generated }} of {{ students.length }} report
                                {{ $t('cards generated') }}
                            </h3>
                            <p
                                class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                            >
                                {{
                                    $t(
                                        "Generating calculates each student's results from the current marks and attendance. Drafts",
                                    )
                                }}
                                are updated and keep their comment; approved and
                                {{
                                    $t(
                                        'published report cards stay as they are.',
                                    )
                                }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <PrimaryButton
                                type="button"
                                :disabled="generateForm.processing || running"
                                :class="{
                                    'opacity-25':
                                        generateForm.processing || running,
                                }"
                                @click="generate"
                            >
                                {{
                                    generated > 0
                                        ? $t('Regenerate')
                                        : $t('Generate')
                                }}
                                {{ $t('report cards') }}
                            </PrimaryButton>
                            <button
                                v-if="approved > 0"
                                type="button"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold tracking-widest text-gray-700 uppercase shadow-xs hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden disabled:opacity-50 dark:border-gray-500 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                :disabled="publishForm.processing"
                                @click="publish"
                            >
                                {{
                                    $t('Publish :approved approved', {
                                        approved: String(approved),
                                    })
                                }}
                            </button>
                        </div>
                    </div>
                    <InputError :message="page.props.errors.generate" />

                    <div
                        v-if="batch && !batch.is_finished"
                        role="status"
                        aria-live="polite"
                    >
                        <div
                            class="h-3 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"
                            role="progressbar"
                            :aria-valuenow="batch.progress"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            :aria-label="$t('Report card generation progress')"
                        >
                            <div
                                class="h-full rounded-full bg-indigo-600 transition-all"
                                :style="{ width: `${batch.progress}%` }"
                            />
                        </div>
                        <p
                            class="mt-2 text-sm text-gray-600 dark:text-gray-400"
                        >
                            {{
                                $t('Generating: :processed of', {
                                    processed: String(batch.processed),
                                })
                            }}
                            {{
                                $t(':total done', {
                                    total: String(batch.total),
                                })
                            }}
                        </p>
                    </div>
                    <p
                        v-else-if="batch && batch.failed > 0"
                        class="text-sm text-red-700 dark:text-red-400"
                        role="status"
                    >
                        {{ batch.failed }} of {{ batch.total }} report cards
                        could not be generated. Try again; if it keeps failing,
                        {{ $t('check the queue dashboard.') }}
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
                                $t('Report cards of')
                            }}
                            {{
                                schoolClass.name
                            }}
                        </caption>
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr
                                class="text-left font-medium text-gray-600 dark:text-gray-300"
                            >
                                <th scope="col" class="px-4 py-3">
                                    {{ $t('No.') }}
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    {{ $t('Student') }}
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    {{ $t('Status') }}
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    {{ $t('Comment') }}
                                </th>
                                <th scope="col" class="px-4 py-3 text-right">
                                    {{ $t('Overall') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-200 text-gray-900 dark:divide-gray-700 dark:text-gray-100"
                        >
                            <tr v-for="s in students" :key="s.id">
                                <td class="px-4 py-2 tabular-nums">
                                    {{ s.roll_no ?? '—' }}
                                </td>
                                <th
                                    scope="row"
                                    class="px-4 py-2 text-left font-medium whitespace-nowrap"
                                >
                                    <Link
                                        v-if="s.card"
                                        :href="
                                            route(
                                                'report-cards.show',
                                                s.card.id,
                                            )
                                        "
                                        class="text-indigo-700 hover:underline focus:ring-2 focus:ring-indigo-500 focus:outline-hidden dark:text-indigo-300"
                                    >
                                        {{ s.name }}
                                    </Link>
                                    <span v-else>{{ s.name }}</span>
                                </th>
                                <td class="px-4 py-2">
                                    <ReportCardStatusBadge
                                        :status="s.card?.status ?? null"
                                    />
                                </td>
                                <td
                                    class="px-4 py-2 text-gray-600 dark:text-gray-400"
                                >
                                    <template v-if="s.card?.has_comment">
                                        {{ $t('Written') }}
                                        <span
                                            v-if="
                                                s.card.comment_source ===
                                                'ai_draft_edited'
                                            "
                                            class="text-xs"
                                        >
                                            ({{ $t('from AI draft') }})
                                        </span>
                                    </template>
                                    <template v-else-if="s.card">
                                        {{ $t('Missing') }}
                                    </template>
                                    <template v-else>—</template>
                                </td>
                                <td class="px-4 py-2 text-right font-semibold">
                                    {{ s.card?.overall_grade ?? '—' }}
                                </td>
                            </tr>
                            <tr v-if="students.length === 0">
                                <td
                                    colspan="5"
                                    class="px-4 py-10 text-center text-gray-600 dark:text-gray-400"
                                >
                                    {{
                                        $t(
                                            'No students are enrolled in this class in this term.',
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
