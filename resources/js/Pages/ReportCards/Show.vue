<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ReportCardResult from '@/Components/ReportCardResult.vue';
import { formatDateTime } from '@/composables/dates';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ReportCardStatusBadge from '@/Pages/ReportCards/Partials/ReportCardStatusBadge.vue';
import type {
    AiCommentDraft,
    IdName,
    ReportCardDetails,
    ReportCardSnapshot,
} from '@/types/school';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    schoolClass: IdName;
    card: ReportCardDetails;
    report: ReportCardSnapshot;
    siblings: { previous: number | null; next: number | null };
    draft: AiCommentDraft | null;
    ai: { enabled: boolean };
    commentMaxLength: number;
}>();

const page = usePage();
const errors = computed(() => page.props.errors);
const isDraft = computed(() => props.card.status === 'draft');
const name = computed(
    () =>
        `${props.report.student.first_name} ${props.report.student.last_name}`,
);

const form = useForm<{ comment: string; ai_draft_id: number | null }>({
    comment: props.card.comment ?? '',
    ai_draft_id: null,
});

// A new card (previous/next) or a reopened one brings its own comment.
watch(
    () => [props.card.id, props.card.comment] as const,
    ([, comment]) => {
        form.defaults({ comment: comment ?? '', ai_draft_id: null });
        form.reset();
    },
);

const saveComment = () =>
    form.put(route('report-cards.comment', props.card.id), {
        preserveScroll: true,
    });

/** Takes the AI draft into the editor; nothing is saved until the teacher saves. */
const useDraft = (draft: AiCommentDraft) => {
    form.comment = draft.text;
    form.ai_draft_id = draft.id;
    document.getElementById('comment')?.focus();
};

const busy = ref<string | null>(null);
const post = (action: string, routeName: string, confirmText?: string) => {
    if (confirmText && !confirm(confirmText)) return;

    router.post(
        route(routeName, props.card.id),
        {},
        {
            preserveScroll: true,
            onStart: () => (busy.value = action),
            onFinish: () => (busy.value = null),
        },
    );
};

const requestDraft = () => post('draft', 'report-cards.ai-draft');

const approve = () => {
    if (form.isDirty) {
        alert('Save the comment before approving the report card.');
        return;
    }
    post('approve', 'report-cards.approve');
};

const dateTime = formatDateTime;

const secondaryButton =
    'inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold tracking-widest text-gray-700 uppercase shadow-xs hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden disabled:opacity-50 dark:border-gray-500 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700';
</script>

<template>
    <Head :title="$t('Report card :name', { name })" />

    <AuthenticatedLayout>
        <template #header>
            <nav :aria-label="$t('Breadcrumb')" class="text-sm">
                <Link
                    :href="
                        route('report-cards.index', { term: report.term.id })
                    "
                    class="text-gray-600 hover:underline dark:text-gray-400"
                >
                    {{ $t('Report cards') }}
                </Link>
                <span class="mx-1 text-gray-400" aria-hidden="true">›</span>
                <Link
                    :href="
                        route('report-cards.class', {
                            schoolClass: schoolClass.id,
                            term: report.term.id,
                        })
                    "
                    class="text-gray-600 hover:underline dark:text-gray-400"
                >
                    {{ schoolClass.name }}
                </Link>
            </nav>
            <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3">
                    <h2
                        class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
                    >
                        {{ name }} · {{ report.term.name }}
                    </h2>
                    <ReportCardStatusBadge :status="card.status" />
                </div>
                <nav
                    :aria-label="$t('Other students')"
                    class="flex gap-3 text-sm font-medium"
                >
                    <Link
                        v-if="siblings.previous"
                        :href="route('report-cards.show', siblings.previous)"
                        class="text-indigo-700 hover:underline focus:ring-2 focus:ring-indigo-500 focus:outline-hidden dark:text-indigo-300"
                    >
                        {{ $t('← Previous student') }}
                    </Link>
                    <Link
                        v-if="siblings.next"
                        :href="route('report-cards.show', siblings.next)"
                        class="text-indigo-700 hover:underline focus:ring-2 focus:ring-indigo-500 focus:outline-hidden dark:text-indigo-300"
                    >
                        {{ $t('Next student →') }}
                    </Link>
                </nav>
            </div>
        </template>

        <div class="py-8">
            <div
                class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-5 lg:px-8"
            >
                <div class="space-y-4 lg:col-span-3">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Calculated {{ dateTime(report.calculated_at) }}.
                        <template v-if="isDraft">
                            {{
                                $t(
                                    'Marks entered since then are not included until you recalculate.',
                                )
                            }}
                        </template>
                        <template v-else>
                            {{
                                $t(
                                    'Frozen since approval: later changes to marks do not change this report card.',
                                )
                            }}
                        </template>
                    </p>
                    <ReportCardResult :report="report" />
                </div>

                <div class="space-y-6 lg:col-span-2">
                    <section
                        class="space-y-4 bg-white p-5 shadow-xs sm:rounded-lg dark:bg-gray-800"
                        aria-labelledby="comment-title"
                    >
                        <h3
                            id="comment-title"
                            class="text-lg font-medium text-gray-900 dark:text-gray-100"
                        >
                            {{ $t("Teacher's comment") }}
                        </h3>

                        <form class="space-y-3" @submit.prevent="saveComment">
                            <div>
                                <InputLabel
                                    for="comment"
                                    :value="$t('Comment')"
                                    class="sr-only"
                                />
                                <textarea
                                    id="comment"
                                    v-model="form.comment"
                                    rows="7"
                                    :maxlength="commentMaxLength"
                                    :readonly="!isDraft"
                                    class="block w-full rounded-md border-gray-300 shadow-xs read-only:bg-gray-50 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:read-only:bg-gray-900/50"
                                    aria-describedby="comment-help"
                                />
                                <p
                                    id="comment-help"
                                    class="mt-1 flex justify-between gap-3 text-xs text-gray-600 dark:text-gray-400"
                                >
                                    <span v-if="form.ai_draft_id">
                                        {{
                                            $t(
                                                'From the AI draft: check and edit it before saving.',
                                            )
                                        }}
                                    </span>
                                    <span
                                        v-else-if="
                                            card.comment_source ===
                                            'ai_draft_edited'
                                        "
                                    >
                                        {{ $t('Started from an AI draft.') }}
                                    </span>
                                    <span v-else />
                                    <span class="tabular-nums">
                                        {{ form.comment.length }} /
                                        {{ commentMaxLength }}
                                    </span>
                                </p>
                                <InputError
                                    :message="form.errors.comment"
                                    class="mt-1"
                                />
                            </div>
                            <PrimaryButton
                                v-if="isDraft"
                                :disabled="form.processing || !form.isDirty"
                                :class="{
                                    'opacity-25':
                                        form.processing || !form.isDirty,
                                }"
                            >
                                {{ $t('Save comment') }}
                            </PrimaryButton>
                        </form>

                        <div
                            v-if="ai.enabled && isDraft"
                            class="space-y-3 border-t border-gray-200 pt-4 dark:border-gray-700"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-3"
                            >
                                <h4
                                    class="font-medium text-gray-900 dark:text-gray-100"
                                >
                                    {{ $t('AI draft') }}
                                </h4>
                                <button
                                    type="button"
                                    :class="secondaryButton"
                                    :disabled="busy !== null"
                                    @click="requestDraft"
                                >
                                    {{
                                        busy === 'draft'
                                            ? 'Drafting…'
                                            : draft
                                              ? 'New draft'
                                              : 'Draft comment'
                                    }}
                                </button>
                            </div>
                            <p class="text-xs text-gray-600 dark:text-gray-400">
                                {{
                                    $t(
                                        'Sends only the first name, grades, attendance and trend. A draft is a suggestion: nothing is saved or published until you use it, edit it and approve the report card.',
                                    )
                                }}
                            </p>
                            <InputError :message="errors.draft" />
                            <figure
                                v-if="draft"
                                class="rounded-md border border-dashed border-indigo-300 bg-indigo-50 p-3 text-sm text-gray-900 dark:border-indigo-700 dark:bg-indigo-950/40 dark:text-gray-100"
                            >
                                <blockquote
                                    class="whitespace-pre-line"
                                    data-testid="ai-draft"
                                >
                                    {{ draft.text }}
                                </blockquote>
                                <figcaption
                                    class="mt-3 flex flex-wrap items-center justify-between gap-2"
                                >
                                    <span
                                        class="text-xs text-gray-600 dark:text-gray-400"
                                    >
                                        Drafted {{ dateTime(draft.created_at) }}
                                    </span>
                                    <button
                                        type="button"
                                        :class="secondaryButton"
                                        @click="useDraft(draft)"
                                    >
                                        {{ $t('Use this draft') }}
                                    </button>
                                </figcaption>
                            </figure>
                        </div>
                    </section>

                    <section
                        class="space-y-4 bg-white p-5 shadow-xs sm:rounded-lg dark:bg-gray-800"
                        aria-labelledby="status-title"
                    >
                        <h3
                            id="status-title"
                            class="text-lg font-medium text-gray-900 dark:text-gray-100"
                        >
                            {{ $t('Approval') }}
                        </h3>
                        <p
                            v-if="card.approved_by && card.approved_at"
                            class="text-sm text-gray-700 dark:text-gray-300"
                        >
                            Approved by {{ card.approved_by }},
                            {{ dateTime(card.approved_at) }}.
                            <template v-if="card.published_at">
                                {{ $t('Published to parents') }}
                                {{ dateTime(card.published_at) }}.
                            </template>
                        </p>
                        <p
                            v-else
                            class="text-sm text-gray-600 dark:text-gray-400"
                        >
                            {{
                                $t(
                                    'Approving freezes the results and renders the PDF. Parents see the report card once it is published.',
                                )
                            }}
                        </p>
                        <InputError :message="errors.status" />

                        <div class="flex flex-wrap gap-3">
                            <template v-if="isDraft">
                                <PrimaryButton
                                    type="button"
                                    :disabled="busy !== null"
                                    @click="approve"
                                >
                                    {{ $t('Approve') }}
                                </PrimaryButton>
                                <button
                                    type="button"
                                    :class="secondaryButton"
                                    :disabled="busy !== null"
                                    @click="
                                        post('refresh', 'report-cards.refresh')
                                    "
                                >
                                    {{ $t('Recalculate') }}
                                </button>
                            </template>
                            <template v-else-if="card.status === 'approved'">
                                <PrimaryButton
                                    type="button"
                                    :disabled="busy !== null"
                                    @click="
                                        post(
                                            'publish',
                                            'report-cards.publish',
                                            'Publish this report card? Parents are notified and can read it.',
                                        )
                                    "
                                >
                                    {{ $t('Publish') }}
                                </PrimaryButton>
                                <button
                                    type="button"
                                    :class="secondaryButton"
                                    :disabled="busy !== null"
                                    @click="
                                        post('reopen', 'report-cards.reopen')
                                    "
                                >
                                    {{ $t('Reopen') }}
                                </button>
                            </template>
                            <a
                                :href="card.pdf_url"
                                :class="secondaryButton"
                                target="_blank"
                                rel="noopener"
                            >
                                {{ isDraft ? 'Preview PDF' : 'Download PDF' }}
                            </a>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
