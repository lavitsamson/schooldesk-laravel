<script setup lang="ts">
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { errorFor } from '@/composables/formErrors';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { IdName, SchoolClassRow } from '@/types/school';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface SubjectRow {
    id: number;
    name: string;
    code: string;
    teacher_id: number | null;
}

interface ClassStudent {
    id: number;
    roll_no: number | null;
    name: string;
    admission_no: string;
    status_label: string;
    left_on: string | null;
}

const props = defineProps<{
    schoolClass: SchoolClassRow & { academic_year: IdName };
    subjects: SubjectRow[];
    students: ClassStudent[];
    teachers: IdName[];
}>();

const details = useForm({
    name: props.schoolClass.name,
    section: props.schoolClass.section ?? '',
    class_teacher_id: props.schoolClass.class_teacher?.id ?? null,
});
const saveDetails = () =>
    details.put(route('classes.update', props.schoolClass.id), {
        preserveScroll: true,
    });

const assignments = useForm({
    assignments: props.subjects.map((s) => ({
        subject_id: s.id,
        teacher_id: s.teacher_id,
    })),
});
const saveAssignments = () =>
    assignments.put(route('classes.assignments', props.schoolClass.id), {
        preserveScroll: true,
    });

const pageErrors = computed(() => usePage().props.errors);
const confirmingDelete = ref(false);
const destroy = () =>
    router.delete(route('classes.destroy', props.schoolClass.id), {
        onFinish: () => (confirmingDelete.value = false),
    });
</script>

<template>
    <Head :title="schoolClass.display_name" />

    <AuthenticatedLayout>
        <template #header>
            <nav :aria-label="$t('Breadcrumb')" class="text-sm">
                <Link
                    :href="
                        route('classes.index', {
                            year_id: schoolClass.academic_year.id,
                        })
                    "
                    class="text-gray-600 hover:underline dark:text-gray-400"
                >
                    Classes {{ schoolClass.academic_year.name }}
                </Link>
            </nav>
            <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
                <h2
                    class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
                >
                    {{ schoolClass.display_name }}
                </h2>
                <DangerButton type="button" @click="confirmingDelete = true">
                    {{ $t('Delete class') }}
                </DangerButton>
            </div>
        </template>

        <div class="py-8">
            <div
                class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-2 lg:px-8"
            >
                <div class="space-y-6">
                    <p
                        v-if="pageErrors.class"
                        class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 dark:bg-red-950/50 dark:text-red-200"
                        role="alert"
                    >
                        {{ pageErrors.class }}
                    </p>

                    <section
                        class="bg-white p-6 shadow-xs sm:rounded-lg dark:bg-gray-800"
                        aria-labelledby="details-title"
                    >
                        <h3
                            id="details-title"
                            class="text-lg font-medium text-gray-900 dark:text-gray-100"
                        >
                            {{ $t('Details') }}
                        </h3>
                        <form
                            class="mt-4 space-y-4"
                            @submit.prevent="saveDetails"
                        >
                            <div class="grid gap-4 sm:grid-cols-[1fr_8rem]">
                                <div>
                                    <InputLabel
                                        for="name"
                                        :value="$t('Name')"
                                    />
                                    <TextInput
                                        id="name"
                                        v-model="details.name"
                                        type="text"
                                        class="mt-1 block w-full"
                                        required
                                    />
                                    <InputError
                                        class="mt-1"
                                        :message="details.errors.name"
                                    />
                                </div>
                                <div>
                                    <InputLabel
                                        for="section"
                                        :value="$t('Section')"
                                    />
                                    <TextInput
                                        id="section"
                                        v-model="details.section"
                                        type="text"
                                        class="mt-1 block w-full"
                                    />
                                    <InputError
                                        class="mt-1"
                                        :message="details.errors.section"
                                    />
                                </div>
                            </div>
                            <div>
                                <InputLabel
                                    for="class-teacher"
                                    :value="$t('Class teacher')"
                                />
                                <SelectInput
                                    id="class-teacher"
                                    v-model="details.class_teacher_id"
                                    class="mt-1 block w-full"
                                >
                                    <option :value="null">
                                        {{ $t('None') }}
                                    </option>
                                    <option
                                        v-for="t in teachers"
                                        :key="t.id"
                                        :value="t.id"
                                    >
                                        {{ t.name }}
                                    </option>
                                </SelectInput>
                                <InputError
                                    class="mt-1"
                                    :message="details.errors.class_teacher_id"
                                />
                            </div>
                            <div class="flex justify-end">
                                <PrimaryButton :disabled="details.processing">
                                    {{ $t('Save') }}
                                </PrimaryButton>
                            </div>
                        </form>
                    </section>

                    <section
                        class="bg-white p-6 shadow-xs sm:rounded-lg dark:bg-gray-800"
                        aria-labelledby="subjects-title"
                    >
                        <h3
                            id="subjects-title"
                            class="text-lg font-medium text-gray-900 dark:text-gray-100"
                        >
                            {{ $t('Subject teachers') }}
                        </h3>
                        <p
                            class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                        >
                            {{
                                $t(
                                    'Teachers see only the classes and subjects they are assigned to here.',
                                )
                            }}
                        </p>
                        <form
                            class="mt-4 space-y-3"
                            @submit.prevent="saveAssignments"
                        >
                            <div
                                v-for="(subject, index) in subjects"
                                :key="subject.id"
                                class="grid items-center gap-2 sm:grid-cols-[1fr_1fr]"
                            >
                                <InputLabel
                                    :for="`subject-${subject.id}`"
                                    :value="`${subject.name} (${subject.code})`"
                                />
                                <div>
                                    <SelectInput
                                        :id="`subject-${subject.id}`"
                                        v-model="
                                            assignments.assignments[index]!
                                                .teacher_id
                                        "
                                        class="block w-full"
                                    >
                                        <option :value="null">
                                            {{ $t('Not taught') }}
                                        </option>
                                        <option
                                            v-for="t in teachers"
                                            :key="t.id"
                                            :value="t.id"
                                        >
                                            {{ t.name }}
                                        </option>
                                    </SelectInput>
                                    <InputError
                                        class="mt-1"
                                        :message="
                                            errorFor(
                                                assignments.errors,
                                                `assignments.${index}.teacher_id`,
                                            )
                                        "
                                    />
                                </div>
                            </div>
                            <p
                                v-if="subjects.length === 0"
                                class="text-sm text-gray-600 dark:text-gray-400"
                            >
                                {{ $t('Add subjects first.') }}
                            </p>
                            <p
                                v-if="teachers.length === 0"
                                class="text-sm text-gray-600 dark:text-gray-400"
                            >
                                {{
                                    $t(
                                        'No teachers yet. Invite them under Members.',
                                    )
                                }}
                            </p>
                            <div class="flex justify-end">
                                <PrimaryButton
                                    :disabled="
                                        assignments.processing ||
                                        subjects.length === 0
                                    "
                                >
                                    {{ $t('Save subject teachers') }}
                                </PrimaryButton>
                            </div>
                        </form>
                    </section>
                </div>

                <section
                    class="overflow-x-auto bg-white shadow-xs sm:rounded-lg dark:bg-gray-800"
                >
                    <table
                        class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700"
                    >
                        <caption
                            class="px-6 pt-6 text-left text-lg font-medium text-gray-900 dark:text-gray-100"
                        >
                            Students ({{
                                students.length
                            }})
                        </caption>
                        <thead>
                            <tr
                                class="text-left font-medium text-gray-600 dark:text-gray-300"
                            >
                                <th scope="col" class="px-6 py-3">
                                    {{ $t('Roll') }}
                                </th>
                                <th scope="col" class="px-6 py-3">
                                    {{ $t('Name') }}
                                </th>
                                <th scope="col" class="px-6 py-3">
                                    {{ $t('Admission no.') }}
                                </th>
                                <th scope="col" class="px-6 py-3">
                                    {{ $t('Status') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-200 text-gray-900 dark:divide-gray-700 dark:text-gray-100"
                        >
                            <tr v-for="s in students" :key="s.id">
                                <td class="px-6 py-3">
                                    {{ s.roll_no ?? '—' }}
                                </td>
                                <td class="px-6 py-3">
                                    <Link
                                        :href="route('students.show', s.id)"
                                        class="hover:underline"
                                    >
                                        {{ s.name }}
                                    </Link>
                                </td>
                                <td class="px-6 py-3 font-mono text-xs">
                                    {{ s.admission_no }}
                                </td>
                                <td class="px-6 py-3">
                                    {{ s.status_label
                                    }}{{ s.left_on ? ` (${s.left_on})` : '' }}
                                </td>
                            </tr>
                            <tr v-if="students.length === 0">
                                <td
                                    colspan="4"
                                    class="px-6 py-6 text-gray-600 dark:text-gray-400"
                                >
                                    {{ $t('No students in this class.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>
        </div>

        <Modal
            :show="confirmingDelete"
            max-width="md"
            @close="confirmingDelete = false"
        >
            <div class="p-6">
                <h2
                    class="text-lg font-medium text-gray-900 dark:text-gray-100"
                >
                    Delete {{ schoolClass.display_name }}?
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ $t('Only classes without students can be deleted.') }}
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton
                        type="button"
                        @click="confirmingDelete = false"
                    >
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
