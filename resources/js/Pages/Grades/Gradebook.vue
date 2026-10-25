<script setup lang="ts">
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TermSelect from '@/Components/TermSelect.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatDate } from '@/composables/dates';
import { formatMarks, formatPercent } from '@/composables/grades';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type {
    AssessmentColumn,
    AssessmentType,
    GradebookStudent,
    IdName,
    Result,
    TermOption,
} from '@/types/school';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    schoolClass: IdName;
    subject: IdName;
    terms: TermOption[];
    term: TermOption;
    assessments: AssessmentColumn[];
    students: GradebookStudent[];
    average: Result;
    types: { value: AssessmentType; label: string; weight: string }[];
    canViewResults: boolean;
}>();

const shortDate = (date: string) =>
    formatDate(date, { day: 'numeric', month: 'short' });

// One dialog for adding and editing.
const editing = ref<AssessmentColumn | null>(null);
const showForm = ref(false);
const form = useForm({
    term_id: props.term.id as number | null,
    name: '',
    type: 'test' as AssessmentType,
    held_on: '',
    max_marks: '20',
    weight: '1.00',
});

const add = () => {
    editing.value = null;
    form.defaults({
        term_id: props.term.id,
        name: '',
        type: 'test',
        held_on: '',
        max_marks: '20',
        weight: props.types.find((t) => t.value === 'test')?.weight ?? '1.00',
    });
    form.reset();
    form.clearErrors();
    showForm.value = true;
};

const edit = (assessment: AssessmentColumn) => {
    editing.value = assessment;
    form.defaults({
        term_id: null,
        name: assessment.name,
        type: assessment.type,
        held_on: assessment.held_on,
        max_marks: String(assessment.max_marks),
        weight: assessment.weight,
    });
    form.reset();
    form.clearErrors();
    showForm.value = true;
};

// A new assessment takes its type's usual weight, unless the weight was changed by hand.
const changeType = () => {
    if (editing.value !== null) return;
    if (props.types.some((t) => t.weight === form.weight)) {
        form.weight =
            props.types.find((t) => t.value === form.type)?.weight ??
            form.weight;
    }
};

const save = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => (showForm.value = false),
    };

    // The term is chosen once, when the assessment is added.
    form.transform((data) =>
        editing.value === null ? data : { ...data, term_id: undefined },
    );

    if (editing.value === null) {
        form.post(
            route('assessments.store', [
                props.schoolClass.id,
                props.subject.id,
            ]),
            options,
        );
    } else {
        form.put(route('assessments.update', editing.value.id), options);
    }
};

const deleting = ref<AssessmentColumn | null>(null);
const destroy = () => {
    if (!deleting.value) return;
    router.delete(route('assessments.destroy', deleting.value.id), {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
};

const marked = computed(
    () => props.students.filter((s) => s.percentage !== null).length,
);

const buttonClasses =
    'inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold tracking-widest text-gray-700 uppercase shadow-xs hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden dark:border-gray-500 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700';
</script>

<template>
    <Head :title="`${subject.name} ${schoolClass.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <nav :aria-label="$t('Breadcrumb')" class="text-sm">
                <Link
                    :href="route('grades.index', { term: term.id })"
                    class="text-gray-600 hover:underline dark:text-gray-400"
                >
                    {{ $t('Grades') }}
                </Link>
            </nav>
            <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
                <h2
                    class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
                >
                    {{ subject.name }} · {{ schoolClass.name }}
                </h2>
                <div class="flex flex-wrap items-center gap-3">
                    <TermSelect
                        :terms="terms"
                        :current="term.id"
                        :url="
                            route('grades.gradebook', [
                                schoolClass.id,
                                subject.id,
                            ])
                        "
                    />
                    <Link
                        v-if="canViewResults"
                        :href="
                            route('results.show', {
                                schoolClass: schoolClass.id,
                                term: term.id,
                            })
                        "
                        :class="buttonClasses"
                    >
                        {{ $t('Class results') }}
                    </Link>
                    <PrimaryButton type="button" @click="add">
                        {{ $t('Add assessment') }}
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section
                    class="overflow-x-auto bg-white shadow-xs sm:rounded-lg dark:bg-gray-800"
                    aria-labelledby="assessments-title"
                >
                    <h3
                        id="assessments-title"
                        class="px-4 pt-4 text-lg font-medium text-gray-900 dark:text-gray-100"
                    >
                        {{ $t('Assessments in :name', { name: term.name }) }}
                    </h3>
                    <table
                        v-if="assessments.length > 0"
                        class="mt-2 min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700"
                    >
                        <caption class="sr-only">
                            {{
                                $t('Assessments')
                            }}
                        </caption>
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr
                                class="text-left font-medium text-gray-600 dark:text-gray-300"
                            >
                                <th scope="col" class="px-4 py-3">
                                    {{ $t('Name') }}
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    {{ $t('Date') }}
                                </th>
                                <th scope="col" class="px-4 py-3 text-right">
                                    {{ $t('Marks') }}
                                </th>
                                <th scope="col" class="px-4 py-3 text-right">
                                    {{ $t('Weight') }}
                                </th>
                                <th scope="col" class="px-4 py-3 text-right">
                                    {{ $t('Marked') }}
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    <span class="sr-only">{{
                                        $t('Actions')
                                    }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-200 text-gray-900 dark:divide-gray-700 dark:text-gray-100"
                        >
                            <tr v-for="a in assessments" :key="a.id">
                                <th
                                    scope="row"
                                    class="px-4 py-3 text-left font-medium"
                                >
                                    {{ a.name }}
                                    <span
                                        class="ms-1 text-xs font-normal text-gray-600 dark:text-gray-400"
                                    >
                                        {{ a.type_label }}
                                    </span>
                                </th>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ shortDate(a.held_on) }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    {{ a.max_marks }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    × {{ formatMarks(a.weight) }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    {{ a.graded_count }} / {{ students.length }}
                                </td>
                                <td
                                    class="space-x-2 px-4 py-3 text-right whitespace-nowrap"
                                >
                                    <Link
                                        :href="
                                            route('assessments.grades', a.id)
                                        "
                                        class="inline-flex items-center rounded-md bg-gray-800 px-3 py-2 text-xs font-semibold tracking-widest text-white uppercase hover:bg-gray-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden dark:bg-gray-200 dark:text-gray-800 dark:hover:bg-white"
                                        :aria-label="
                                            $t('Enter marks for :name', {
                                                name: a.name,
                                            })
                                        "
                                    >
                                        {{ $t('Enter marks') }}
                                    </Link>
                                    <SecondaryButton
                                        type="button"
                                        :aria-label="
                                            $t('Edit :name', { name: a.name })
                                        "
                                        @click="edit(a)"
                                    >
                                        {{ $t('Edit') }}
                                    </SecondaryButton>
                                    <DangerButton
                                        type="button"
                                        :aria-label="
                                            $t('Delete :name', { name: a.name })
                                        "
                                        @click="deleting = a"
                                    >
                                        {{ $t('Delete') }}
                                    </DangerButton>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p
                        v-else
                        class="px-4 pt-2 pb-6 text-sm text-gray-600 dark:text-gray-400"
                    >
                        {{
                            $t(
                                'No assessments in this term yet. Add a test, exam or assignment to enter marks.',
                            )
                        }}
                    </p>
                </section>

                <section
                    v-if="assessments.length > 0"
                    class="overflow-x-auto bg-white shadow-xs sm:rounded-lg dark:bg-gray-800"
                    aria-labelledby="results-title"
                >
                    <div
                        class="flex flex-wrap items-baseline justify-between gap-2 px-4 pt-4"
                    >
                        <h3
                            id="results-title"
                            class="text-lg font-medium text-gray-900 dark:text-gray-100"
                        >
                            {{ $t('Results so far') }}
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $t('Class average') }}
                            <span
                                class="font-semibold text-gray-900 dark:text-gray-100"
                            >
                                {{ formatPercent(average.percentage) }}
                                <template v-if="average.grade">
                                    ({{ average.grade }})
                                </template>
                            </span>
                            ·
                            {{
                                $t(':marked of :count with marks', {
                                    marked: String(marked),
                                    count: String(students.length),
                                })
                            }}
                        </p>
                    </div>
                    <table
                        class="mt-2 min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700"
                    >
                        <caption class="sr-only">
                            {{
                                $t('Results by student')
                            }}
                        </caption>
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr
                                class="text-left font-medium text-gray-600 dark:text-gray-300"
                            >
                                <th scope="col" class="w-14 px-4 py-3">
                                    {{ $t('Roll') }}
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    {{ $t('Student') }}
                                </th>
                                <th
                                    v-for="a in assessments"
                                    :key="a.id"
                                    scope="col"
                                    class="px-3 py-3 text-right whitespace-nowrap"
                                >
                                    {{ a.name }}
                                    <span
                                        class="block text-xs font-normal text-gray-600 dark:text-gray-400"
                                    >
                                        / {{ a.max_marks }} ×
                                        {{ formatMarks(a.weight) }}
                                    </span>
                                </th>
                                <th scope="col" class="px-4 py-3 text-right">
                                    {{ $t('Result') }}
                                </th>
                                <th scope="col" class="px-4 py-3 text-right">
                                    {{ $t('Grade') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-200 text-gray-900 dark:divide-gray-700 dark:text-gray-100"
                        >
                            <tr v-for="s in students" :key="s.id">
                                <td
                                    class="px-4 py-2 text-gray-600 dark:text-gray-400"
                                >
                                    {{ s.roll_no ?? '—' }}
                                </td>
                                <th
                                    scope="row"
                                    class="px-4 py-2 text-left font-medium whitespace-nowrap"
                                >
                                    {{ s.name }}
                                </th>
                                <td
                                    v-for="a in assessments"
                                    :key="a.id"
                                    class="px-3 py-2 text-right tabular-nums"
                                >
                                    {{ formatMarks(s.marks[a.id] ?? null) }}
                                </td>
                                <td
                                    class="px-4 py-2 text-right whitespace-nowrap tabular-nums"
                                >
                                    {{ formatPercent(s.percentage) }}
                                </td>
                                <td class="px-4 py-2 text-right font-semibold">
                                    {{ s.grade ?? '—' }}
                                </td>
                            </tr>
                            <tr v-if="students.length === 0">
                                <td
                                    :colspan="assessments.length + 4"
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
                    <p
                        class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400"
                    >
                        {{
                            $t(
                                'The result is the weighted average of the marked assessments. Assessments without marks do not count.',
                            )
                        }}
                    </p>
                </section>
            </div>
        </div>

        <Modal :show="showForm" max-width="lg" @close="showForm = false">
            <form class="space-y-4 p-6" @submit.prevent="save">
                <h2
                    class="text-lg font-medium text-gray-900 dark:text-gray-100"
                >
                    {{
                        editing
                            ? $t('Edit :name', { name: editing.name })
                            : $t('Add an assessment')
                    }}
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ subject.name }} · {{ schoolClass.name }} ·
                    {{ term.name }}
                </p>

                <div>
                    <InputLabel for="assessment-name" :value="$t('Name')" />
                    <TextInput
                        id="assessment-name"
                        v-model="form.name"
                        type="text"
                        class="mt-1 block w-full"
                        maxlength="100"
                        required
                    />
                    <InputError class="mt-1" :message="form.errors.name" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="assessment-type" :value="$t('Type')" />
                        <SelectInput
                            id="assessment-type"
                            v-model="form.type"
                            class="mt-1 block w-full"
                            @change="changeType"
                        >
                            <option
                                v-for="t in types"
                                :key="t.value"
                                :value="t.value"
                            >
                                {{ t.label }}
                            </option>
                        </SelectInput>
                        <InputError class="mt-1" :message="form.errors.type" />
                    </div>
                    <div>
                        <InputLabel for="assessment-date" :value="$t('Date')" />
                        <TextInput
                            id="assessment-date"
                            v-model="form.held_on"
                            type="date"
                            class="mt-1 block w-full"
                            :min="term.starts_on"
                            :max="term.ends_on"
                            required
                        />
                        <InputError
                            class="mt-1"
                            :message="form.errors.held_on"
                        />
                    </div>
                    <div>
                        <InputLabel
                            for="assessment-max"
                            :value="$t('Max marks')"
                        />
                        <TextInput
                            id="assessment-max"
                            v-model="form.max_marks"
                            type="number"
                            min="1"
                            max="1000"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError
                            class="mt-1"
                            :message="form.errors.max_marks"
                        />
                    </div>
                    <div>
                        <InputLabel
                            for="assessment-weight"
                            :value="$t('Weight')"
                        />
                        <TextInput
                            id="assessment-weight"
                            v-model="form.weight"
                            type="text"
                            inputmode="decimal"
                            class="mt-1 block w-full"
                            aria-describedby="assessment-weight-help"
                            required
                        />
                        <p
                            id="assessment-weight-help"
                            class="mt-1 text-xs text-gray-600 dark:text-gray-400"
                        >
                            {{
                                $t(
                                    'How much it counts: 2 counts twice as much as 1.',
                                )
                            }}
                        </p>
                        <InputError
                            class="mt-1"
                            :message="form.errors.weight"
                        />
                    </div>
                </div>
                <InputError :message="form.errors.term_id" />

                <div class="flex justify-end gap-3 pt-2">
                    <SecondaryButton type="button" @click="showForm = false">
                        {{ $t('Cancel') }}
                    </SecondaryButton>
                    <PrimaryButton :disabled="form.processing">
                        {{ editing ? $t('Save') : $t('Add and enter marks') }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal
            :show="deleting !== null"
            max-width="md"
            @close="deleting = null"
        >
            <div class="p-6">
                <h2
                    class="text-lg font-medium text-gray-900 dark:text-gray-100"
                >
                    {{ $t('Delete :name?', { name: deleting?.name ?? '' }) }}
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    <template v-if="deleting && deleting.graded_count > 0">
                        {{
                            $t(
                                'The marks of :count students are deleted with it.',
                                { count: String(deleting.graded_count) },
                            )
                        }}
                    </template>
                    <template v-else>{{
                        $t('No marks have been entered yet.')
                    }}</template>
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="deleting = null">
                        {{ $t('Cancel') }}
                    </SecondaryButton>
                    <DangerButton type="button" @click="destroy">
                        {{ $t('Delete') }}
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
