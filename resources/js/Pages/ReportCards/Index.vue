<script setup lang="ts">
import TermSelect from '@/Components/TermSelect.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { IdName, ReportCardClass, TermOption } from '@/types/school';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    year: string;
    terms: TermOption[];
    term: IdName;
    classes: ReportCardClass[];
    aiDrafts: { enabled: boolean; canChange: boolean };
}>();

const saving = ref(false);

const toggleAiDrafts = () =>
    router.put(
        route('report-cards.settings.ai-drafts'),
        { enabled: !props.aiDrafts.enabled },
        {
            preserveScroll: true,
            onStart: () => (saving.value = true),
            onFinish: () => (saving.value = false),
        },
    );

const share = (count: number, total: number) =>
    total === 0 ? 0 : Math.round((count / total) * 100);
</script>

<template>
    <Head :title="$t('Report cards')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2
                        class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
                    >
                        {{ $t('Report cards') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {{ year }} · {{ term.name }}
                    </p>
                </div>
                <TermSelect
                    :terms="terms"
                    :current="term.id"
                    :url="route('report-cards.index')"
                />
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section
                    class="bg-white p-5 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    aria-labelledby="ai-title"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-4"
                    >
                        <div class="max-w-3xl">
                            <h3
                                id="ai-title"
                                class="font-medium text-gray-900 dark:text-gray-100"
                            >
                                {{ $t('AI comment drafts') }}:
                                {{ aiDrafts.enabled ? 'on' : 'off' }}
                            </h3>
                            <p
                                class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                            >
                                {{
                                    $t(
                                        "Teachers can ask for a 2–3 sentence draft of a comment, which they review and edit before approving the report card. Only the student's first name, grades, attendance and trend are sent to the AI provider: no surname, date of birth, address or remarks.",
                                    )
                                }}
                            </p>
                        </div>
                        <button
                            v-if="aiDrafts.canChange"
                            type="button"
                            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold tracking-widest text-gray-700 uppercase shadow-xs hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden disabled:opacity-50 dark:border-gray-500 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                            :disabled="saving"
                            :aria-pressed="aiDrafts.enabled"
                            @click="toggleAiDrafts"
                        >
                            {{
                                aiDrafts.enabled
                                    ? $t('Turn off')
                                    : $t('Turn on')
                            }}
                        </button>
                    </div>
                </section>

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
                        <div>
                            <h3
                                class="text-lg font-medium text-gray-900 dark:text-gray-100"
                            >
                                <Link
                                    :href="
                                        route('report-cards.class', {
                                            schoolClass: c.id,
                                            term: term.id,
                                        })
                                    "
                                    class="text-indigo-700 hover:underline focus:ring-2 focus:ring-indigo-500 focus:outline-hidden dark:text-indigo-300"
                                >
                                    {{ c.name }}
                                </Link>
                            </h3>
                            <p
                                v-if="c.class_teacher"
                                class="text-sm text-gray-600 dark:text-gray-400"
                            >
                                {{ c.class_teacher }}
                            </p>
                        </div>

                        <div
                            class="flex h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"
                            aria-hidden="true"
                        >
                            <div
                                class="bg-green-600"
                                :style="{
                                    width: `${share(c.published, c.students)}%`,
                                }"
                            />
                            <div
                                class="bg-indigo-500"
                                :style="{
                                    width: `${share(c.approved, c.students)}%`,
                                }"
                            />
                            <div
                                class="bg-amber-400"
                                :style="{
                                    width: `${share(c.draft, c.students)}%`,
                                }"
                            />
                        </div>
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            {{
                                $t(
                                    ':students students · :published published · :approved approved · :draft drafts',
                                    {
                                        students: String(c.students),
                                        published: String(c.published),
                                        approved: String(c.approved),
                                        draft: String(c.draft),
                                    },
                                )
                            }}
                        </p>
                    </li>
                </ul>
                <p
                    v-else
                    class="bg-white p-6 text-sm text-gray-600 shadow-xs sm:rounded-lg dark:bg-gray-800 dark:text-gray-400"
                >
                    {{
                        $t(
                            'You write the report cards of the classes you are class teacher of. You have no class this year.',
                        )
                    }}
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
