<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { IdName } from '@/types/school';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

type Target = number | 'graduate' | null;

interface SourceClass {
    id: number;
    name: string;
    suggested_target: Target;
    students: {
        id: number;
        name: string;
        admission_no: string;
        already_enrolled: boolean;
    }[];
}

const props = defineProps<{
    fromYear: IdName;
    years: IdName[];
    toYearId: number | null;
    preview: { source_classes: SourceClass[]; target_classes: IdName[] } | null;
}>();

const toYearId = ref(props.toYearId);
watch(toYearId, (id) =>
    router.get(route('promotions.create'), { to_year_id: id ?? undefined }),
);

const targets = reactive<Record<number, Target>>({});
const excluded = ref<number[]>([]);
const open = ref<number[]>([]);

watch(
    () => props.preview,
    (preview) => {
        excluded.value = [];
        for (const c of preview?.source_classes ?? []) {
            targets[c.id] = c.suggested_target;
        }
    },
    { immediate: true },
);

const toggleStudent = (id: number) =>
    (excluded.value = excluded.value.includes(id)
        ? excluded.value.filter((s) => s !== id)
        : [...excluded.value, id]);
const toggleOpen = (id: number) =>
    (open.value = open.value.includes(id)
        ? open.value.filter((c) => c !== id)
        : [...open.value, id]);

const movable = (c: SourceClass) =>
    c.students.filter(
        (s) => !s.already_enrolled && !excluded.value.includes(s.id),
    ).length;

const summary = computed(() => {
    const result = { promoted: 0, graduated: 0, staying: 0 };
    for (const c of props.preview?.source_classes ?? []) {
        const target = targets[c.id] ?? null;
        const count = movable(c);
        if (target === 'graduate') result.graduated += count;
        else if (target === null) result.staying += c.students.length;
        else result.promoted += count;
        if (target !== null) result.staying += c.students.length - count;
    }
    return result;
});

const confirming = ref(false);
const processing = ref(false);
const errors = computed(() => usePage().props.errors);

const promote = () =>
    router.post(
        route('promotions.store'),
        {
            to_year_id: toYearId.value,
            mappings: (props.preview?.source_classes ?? []).map((c) => ({
                from_class_id: c.id,
                target: targets[c.id] ?? null,
            })),
            excluded_student_ids: excluded.value,
        },
        {
            onStart: () => (processing.value = true),
            onFinish: () => {
                processing.value = false;
                confirming.value = false;
            },
        },
    );
</script>

<template>
    <Head title="Promote students" />

    <AuthenticatedLayout>
        <template #header>
            <nav aria-label="Breadcrumb" class="text-sm">
                <Link
                    :href="route('academic-years.index')"
                    class="text-gray-600 hover:underline dark:text-gray-400"
                >
                    Academic years
                </Link>
            </nav>
            <h2
                class="mt-1 text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
            >
                Promote students from {{ fromYear.name }}
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section
                    class="bg-white p-6 shadow-xs sm:rounded-lg dark:bg-gray-800"
                >
                    <p
                        v-if="years.length === 0"
                        class="text-sm text-gray-700 dark:text-gray-300"
                    >
                        Add next year's academic year (with its classes) first.
                    </p>
                    <div v-else class="flex flex-wrap items-end gap-4">
                        <div>
                            <InputLabel for="to-year" value="Promote into" />
                            <SelectInput
                                id="to-year"
                                v-model="toYearId"
                                class="mt-1 block"
                            >
                                <option
                                    v-for="y in years"
                                    :key="y.id"
                                    :value="y.id"
                                >
                                    {{ y.name }}
                                </option>
                            </SelectInput>
                        </div>
                        <p
                            class="text-sm text-gray-600 dark:text-gray-400"
                            role="status"
                        >
                            {{ summary.promoted }} promoted ·
                            {{ summary.graduated }} graduate ·
                            {{ summary.staying }} not promoted
                        </p>
                    </div>
                    <InputError class="mt-2" :message="errors.to_year_id" />
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                        Targets are suggested from the class names (Grade 7 A →
                        Grade 8 A). Check each class, untick students who repeat
                        the year, then promote. Nothing changes before you
                        confirm.
                    </p>
                </section>

                <template v-if="preview">
                    <p
                        v-if="preview.target_classes.length === 0"
                        class="rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:bg-amber-950/50 dark:text-amber-100"
                    >
                        The target year has no classes yet. Add them under
                        Classes, or graduate students only.
                    </p>

                    <section
                        v-for="c in preview.source_classes"
                        :key="c.id"
                        class="bg-white p-4 shadow-xs sm:rounded-lg dark:bg-gray-800"
                        :aria-labelledby="`class-${c.id}`"
                    >
                        <div
                            class="grid items-center gap-3 sm:grid-cols-[1fr_16rem_auto]"
                        >
                            <h3
                                :id="`class-${c.id}`"
                                class="font-medium text-gray-900 dark:text-gray-100"
                            >
                                {{ c.name }}
                                <span
                                    class="text-sm font-normal text-gray-600 dark:text-gray-400"
                                >
                                    ({{ c.students.length }} students)
                                </span>
                            </h3>
                            <div>
                                <InputLabel
                                    :for="`target-${c.id}`"
                                    :value="`Target for ${c.name}`"
                                    class="sr-only"
                                />
                                <SelectInput
                                    :id="`target-${c.id}`"
                                    v-model="targets[c.id]!"
                                    class="block w-full"
                                >
                                    <option :value="null">Not promoted</option>
                                    <option
                                        v-for="t in preview.target_classes"
                                        :key="t.id"
                                        :value="t.id"
                                    >
                                        → {{ t.name }}
                                    </option>
                                    <option value="graduate">Graduate</option>
                                </SelectInput>
                            </div>
                            <SecondaryButton
                                type="button"
                                :aria-expanded="open.includes(c.id)"
                                :aria-controls="`students-${c.id}`"
                                :disabled="c.students.length === 0"
                                @click="toggleOpen(c.id)"
                            >
                                {{
                                    open.includes(c.id)
                                        ? 'Hide students'
                                        : 'Students'
                                }}
                            </SecondaryButton>
                        </div>
                        <ul
                            v-if="open.includes(c.id)"
                            :id="`students-${c.id}`"
                            class="mt-3 grid gap-1 text-sm sm:grid-cols-2"
                        >
                            <li v-for="s in c.students" :key="s.id">
                                <label
                                    class="flex items-center gap-2 text-gray-800 dark:text-gray-200"
                                    :class="{
                                        'opacity-60': s.already_enrolled,
                                    }"
                                >
                                    <input
                                        type="checkbox"
                                        class="rounded-sm border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                                        :checked="
                                            !s.already_enrolled &&
                                            !excluded.includes(s.id)
                                        "
                                        :disabled="
                                            s.already_enrolled ||
                                            targets[c.id] === null
                                        "
                                        @change="toggleStudent(s.id)"
                                    />
                                    {{ s.name }}
                                    <span
                                        class="font-mono text-xs text-gray-500 dark:text-gray-400"
                                        >{{ s.admission_no }}</span
                                    >
                                    <span
                                        v-if="s.already_enrolled"
                                        class="text-xs text-gray-600 dark:text-gray-400"
                                        >(already enrolled)</span
                                    >
                                </label>
                            </li>
                        </ul>
                    </section>

                    <div class="flex justify-end">
                        <PrimaryButton
                            type="button"
                            :disabled="
                                summary.promoted + summary.graduated === 0
                            "
                            @click="confirming = true"
                        >
                            Promote students
                        </PrimaryButton>
                    </div>
                </template>
            </div>
        </div>

        <Modal :show="confirming" max-width="md" @close="confirming = false">
            <div class="p-6">
                <h2
                    class="text-lg font-medium text-gray-900 dark:text-gray-100"
                >
                    Promote {{ summary.promoted }} and graduate
                    {{ summary.graduated }} students?
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Promoted students are enrolled in next year's classes.
                    Graduates are marked as graduated at the end of
                    {{ fromYear.name }}. Running this again skips students who
                    are already enrolled.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="confirming = false">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton
                        type="button"
                        :disabled="processing"
                        @click="promote"
                    >
                        Promote
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
