<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { Option } from '@/types';
import type {
    Enrollment,
    Guardian,
    IdName,
    StudentProfile,
} from '@/types/school';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    student: StudentProfile;
    guardians: Guardian[];
    enrollments: Enrollment[];
    classes: IdName[];
    relationships: Option[];
}>();

const errors = computed(() => usePage().props.errors);
const initials = computed(
    () =>
        `${props.student.first_name[0] ?? ''}${props.student.last_name[0] ?? ''}`,
);

// Class change reuses the bulk "move to class" action for a single student.
const classId = ref<number | null>(props.student.class?.id ?? null);
const changeClass = () =>
    router.post(
        route('students.bulk'),
        {
            action: 'move_to_class',
            student_ids: [props.student.id],
            class_id: classId.value,
        },
        { preserveScroll: true },
    );

const addingGuardian = ref(false);
const guardianForm = useForm({
    mode: 'new' as 'new' | 'existing',
    name: '',
    email: '',
    phone: '',
    relationship: props.relationships[0]?.value ?? '',
    is_primary: false,
});
const addGuardian = () =>
    guardianForm.post(route('students.guardians.store', props.student.id), {
        preserveScroll: true,
        onSuccess: () => {
            guardianForm.reset();
            addingGuardian.value = false;
        },
    });

const makePrimary = (guardian: Guardian) =>
    router.put(
        route('students.guardians.update', [props.student.id, guardian.id]),
        {},
        { preserveScroll: true },
    );
const invite = (guardian: Guardian) =>
    router.post(
        route('guardians.invite', guardian.id),
        {},
        { preserveScroll: true },
    );

const unlinking = ref<Guardian | null>(null);
const unlink = () => {
    if (!unlinking.value) return;
    router.delete(
        route('students.guardians.destroy', [
            props.student.id,
            unlinking.value.id,
        ]),
        { preserveScroll: true, onFinish: () => (unlinking.value = null) },
    );
};

const confirmingDelete = ref(false);
const destroy = () =>
    router.delete(route('students.destroy', props.student.id));
</script>

<template>
    <Head :title="student.name" />

    <AuthenticatedLayout>
        <template #header>
            <nav :aria-label="$t('Breadcrumb')" class="text-sm">
                <Link
                    :href="route('students.index')"
                    class="text-gray-600 hover:underline dark:text-gray-400"
                >
                    {{ $t('Students') }}
                </Link>
            </nav>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <img
                        v-if="student.photo_url"
                        :src="student.photo_url"
                        :alt="$t('Photo of :name', { name: student.name })"
                        class="h-16 w-16 rounded-full object-cover"
                    />
                    <span
                        v-else
                        class="flex h-16 w-16 items-center justify-center rounded-full bg-indigo-100 text-xl font-semibold text-indigo-800 dark:bg-indigo-900 dark:text-indigo-100"
                        aria-hidden="true"
                    >
                        {{ initials }}
                    </span>
                    <div>
                        <h2
                            class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
                        >
                            {{ student.name }}
                        </h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            <span class="font-mono">{{
                                student.admission_no
                            }}</span>
                            · {{ student.class?.name ?? $t('Not enrolled') }} ·
                            {{ student.status_label }}
                        </p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Link
                        v-if="student.can.update"
                        :href="route('students.edit', student.id)"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold tracking-widest text-gray-700 uppercase shadow-xs hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden dark:border-gray-500 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        {{ $t('Edit') }}
                    </Link>
                    <DangerButton
                        v-if="student.can.delete"
                        type="button"
                        @click="confirmingDelete = true"
                    >
                        {{ $t('Delete') }}
                    </DangerButton>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div
                class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-3 lg:px-8"
            >
                <div class="space-y-6 lg:col-span-1">
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
                        <dl class="mt-4 space-y-3 text-sm">
                            <div>
                                <dt class="text-gray-600 dark:text-gray-400">
                                    {{ $t('Date of birth') }}
                                </dt>
                                <dd class="text-gray-900 dark:text-gray-100">
                                    {{ student.date_of_birth }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-600 dark:text-gray-400">
                                    {{ $t('Gender') }}
                                </dt>
                                <dd class="text-gray-900 dark:text-gray-100">
                                    {{
                                        student.gender_label ??
                                        $t('Not specified')
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-600 dark:text-gray-400">
                                    {{ $t('Address') }}
                                </dt>
                                <dd
                                    class="whitespace-pre-line text-gray-900 dark:text-gray-100"
                                >
                                    {{ student.address || '—' }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section
                        v-if="student.can.update && student.status === 'active'"
                        class="bg-white p-6 shadow-xs sm:rounded-lg dark:bg-gray-800"
                        aria-labelledby="class-title"
                    >
                        <h3
                            id="class-title"
                            class="text-lg font-medium text-gray-900 dark:text-gray-100"
                        >
                            {{ $t('Class this year') }}
                        </h3>
                        <form
                            class="mt-4 flex items-end gap-3"
                            @submit.prevent="changeClass"
                        >
                            <div class="grow">
                                <InputLabel
                                    for="change-class"
                                    :value="$t('Class')"
                                />
                                <SelectInput
                                    id="change-class"
                                    v-model="classId"
                                    class="mt-1 block w-full"
                                >
                                    <option :value="null" disabled>
                                        {{ $t('Choose a class') }}
                                    </option>
                                    <option
                                        v-for="c in classes"
                                        :key="c.id"
                                        :value="c.id"
                                    >
                                        {{ c.name }}
                                    </option>
                                </SelectInput>
                            </div>
                            <PrimaryButton
                                :disabled="
                                    !classId || classId === student.class?.id
                                "
                            >
                                {{ student.class ? $t('Move') : $t('Enrol') }}
                            </PrimaryButton>
                        </form>
                        <InputError
                            class="mt-2"
                            :message="errors.class_id ?? errors.student_ids"
                        />
                    </section>
                </div>

                <div class="space-y-6 lg:col-span-2">
                    <section
                        class="bg-white p-6 shadow-xs sm:rounded-lg dark:bg-gray-800"
                        aria-labelledby="guardians-title"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <h3
                                id="guardians-title"
                                class="text-lg font-medium text-gray-900 dark:text-gray-100"
                            >
                                {{ $t('Guardians') }}
                            </h3>
                            <SecondaryButton
                                v-if="student.can.update && !addingGuardian"
                                type="button"
                                @click="addingGuardian = true"
                            >
                                {{ $t('Add guardian') }}
                            </SecondaryButton>
                        </div>

                        <p
                            v-if="guardians.length === 0 && !addingGuardian"
                            class="mt-4 text-sm text-gray-600 dark:text-gray-400"
                        >
                            {{
                                $t(
                                    'No guardians yet. Add one so the school can reach the family.',
                                )
                            }}
                        </p>

                        <ul
                            class="mt-4 divide-y divide-gray-200 dark:divide-gray-700"
                        >
                            <li
                                v-for="guardian in guardians"
                                :key="guardian.id"
                                class="flex flex-wrap items-start justify-between gap-3 py-3"
                            >
                                <div class="text-sm">
                                    <p
                                        class="font-medium text-gray-900 dark:text-gray-100"
                                    >
                                        {{ guardian.name }}
                                        <span
                                            class="text-gray-600 dark:text-gray-400"
                                        >
                                            ({{ guardian.relationship_label }})
                                        </span>
                                        <span
                                            v-if="guardian.is_primary"
                                            class="ms-2 rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200"
                                        >
                                            {{ $t('Primary contact') }}
                                        </span>
                                    </p>
                                    <p class="text-gray-600 dark:text-gray-400">
                                        {{
                                            [guardian.email, guardian.phone]
                                                .filter(Boolean)
                                                .join(' · ') ||
                                            $t('No contact details')
                                        }}
                                    </p>
                                    <p
                                        class="text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{
                                            guardian.has_portal_access
                                                ? 'Parent portal: invited'
                                                : 'Parent portal: not invited'
                                        }}
                                    </p>
                                </div>
                                <div
                                    v-if="student.can.update"
                                    class="flex flex-wrap gap-2"
                                >
                                    <SecondaryButton
                                        v-if="!guardian.is_primary"
                                        type="button"
                                        @click="makePrimary(guardian)"
                                    >
                                        {{ $t('Make primary') }}
                                    </SecondaryButton>
                                    <SecondaryButton
                                        v-if="guardian.can.invite"
                                        type="button"
                                        @click="invite(guardian)"
                                    >
                                        {{ $t('Invite to portal') }}
                                    </SecondaryButton>
                                    <DangerButton
                                        type="button"
                                        :aria-label="
                                            $t(
                                                'Remove :name from this student',
                                                { name: guardian.name },
                                            )
                                        "
                                        @click="unlinking = guardian"
                                    >
                                        {{ $t('Remove') }}
                                    </DangerButton>
                                </div>
                            </li>
                        </ul>

                        <form
                            v-if="addingGuardian"
                            class="mt-4 space-y-4 rounded-md border border-gray-200 p-4 dark:border-gray-700"
                            @submit.prevent="addGuardian"
                        >
                            <fieldset class="flex gap-6 text-sm">
                                <legend class="sr-only">
                                    {{ $t('Guardian') }}
                                </legend>
                                <label
                                    class="flex items-center gap-2 text-gray-700 dark:text-gray-300"
                                >
                                    <input
                                        v-model="guardianForm.mode"
                                        type="radio"
                                        value="new"
                                        class="text-indigo-600 focus:ring-indigo-500"
                                    />
                                    {{ $t('New guardian') }}
                                </label>
                                <label
                                    class="flex items-center gap-2 text-gray-700 dark:text-gray-300"
                                >
                                    <input
                                        v-model="guardianForm.mode"
                                        type="radio"
                                        value="existing"
                                        class="text-indigo-600 focus:ring-indigo-500"
                                    />
                                    {{
                                        $t(
                                            'Existing guardian (e.g. of a sibling)',
                                        )
                                    }}
                                </label>
                            </fieldset>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div v-if="guardianForm.mode === 'new'">
                                    <InputLabel
                                        for="guardian-name"
                                        :value="$t('Name')"
                                    />
                                    <TextInput
                                        id="guardian-name"
                                        v-model="guardianForm.name"
                                        type="text"
                                        class="mt-1 block w-full"
                                        required
                                    />
                                    <InputError
                                        class="mt-1"
                                        :message="guardianForm.errors.name"
                                    />
                                </div>
                                <div>
                                    <InputLabel
                                        for="guardian-email"
                                        :value="
                                            guardianForm.mode === 'new'
                                                ? 'Email (optional)'
                                                : $t(
                                                      'Email of the existing guardian',
                                                  )
                                        "
                                    />
                                    <TextInput
                                        id="guardian-email"
                                        v-model="guardianForm.email"
                                        type="email"
                                        class="mt-1 block w-full"
                                        :required="
                                            guardianForm.mode === 'existing'
                                        "
                                    />
                                    <InputError
                                        class="mt-1"
                                        :message="guardianForm.errors.email"
                                    />
                                </div>
                                <template v-if="guardianForm.mode === 'new'">
                                    <div>
                                        <InputLabel
                                            for="guardian-phone"
                                            :value="$t('Phone (optional)')"
                                        />
                                        <TextInput
                                            id="guardian-phone"
                                            v-model="guardianForm.phone"
                                            type="tel"
                                            class="mt-1 block w-full"
                                        />
                                        <InputError
                                            class="mt-1"
                                            :message="guardianForm.errors.phone"
                                        />
                                    </div>
                                    <div>
                                        <InputLabel
                                            for="guardian-relationship"
                                            :value="$t('Relationship')"
                                        />
                                        <SelectInput
                                            id="guardian-relationship"
                                            v-model="guardianForm.relationship"
                                            class="mt-1 block w-full"
                                        >
                                            <option
                                                v-for="r in relationships"
                                                :key="r.value"
                                                :value="r.value"
                                            >
                                                {{ r.label }}
                                            </option>
                                        </SelectInput>
                                        <InputError
                                            class="mt-1"
                                            :message="
                                                guardianForm.errors.relationship
                                            "
                                        />
                                    </div>
                                </template>
                            </div>

                            <label
                                class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                            >
                                <Checkbox
                                    v-model:checked="guardianForm.is_primary"
                                />
                                {{
                                    $t(
                                        'Primary contact (gets absence notifications)',
                                    )
                                }}
                            </label>

                            <div class="flex justify-end gap-3">
                                <SecondaryButton
                                    type="button"
                                    @click="addingGuardian = false"
                                >
                                    {{ $t('Cancel') }}
                                </SecondaryButton>
                                <PrimaryButton
                                    :disabled="guardianForm.processing"
                                >
                                    {{ $t('Add guardian') }}
                                </PrimaryButton>
                            </div>
                        </form>
                    </section>

                    <section
                        class="overflow-x-auto bg-white shadow-xs sm:rounded-lg dark:bg-gray-800"
                    >
                        <table
                            class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700"
                        >
                            <caption
                                class="px-6 pt-6 text-left text-lg font-medium text-gray-900 dark:text-gray-100"
                            >
                                {{
                                    $t('Enrolment history')
                                }}
                            </caption>
                            <thead>
                                <tr
                                    class="text-left font-medium text-gray-600 dark:text-gray-300"
                                >
                                    <th scope="col" class="px-6 py-3">
                                        {{ $t('Year') }}
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        {{ $t('Class') }}
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        {{ $t('Roll no.') }}
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        {{ $t('From') }}
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        {{ $t('To') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-gray-200 text-gray-900 dark:divide-gray-700 dark:text-gray-100"
                            >
                                <tr v-for="e in enrollments" :key="e.id">
                                    <td class="px-6 py-3">
                                        {{ e.academic_year }}
                                    </td>
                                    <td class="px-6 py-3">
                                        {{ e.class.name }}
                                    </td>
                                    <td class="px-6 py-3">
                                        {{ e.roll_no ?? '—' }}
                                    </td>
                                    <td class="px-6 py-3">
                                        {{ e.enrolled_on }}
                                    </td>
                                    <td class="px-6 py-3">
                                        {{ e.left_on ?? '—' }}
                                    </td>
                                </tr>
                                <tr v-if="enrollments.length === 0">
                                    <td
                                        colspan="5"
                                        class="px-6 py-6 text-gray-600 dark:text-gray-400"
                                    >
                                        {{
                                            $t('Not enrolled in any class yet.')
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </section>
                </div>
            </div>
        </div>

        <Modal
            :show="unlinking !== null"
            max-width="md"
            @close="unlinking = null"
        >
            <div class="p-6">
                <h2
                    class="text-lg font-medium text-gray-900 dark:text-gray-100"
                >
                    {{
                        $t('Remove :name as a guardian?', {
                            name: unlinking?.name ?? '',
                        })
                    }}
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{
                        $t(
                            'Only the link to :first_name is removed. The guardian stays linked to any other children.',
                            { first_name: student.first_name },
                        )
                    }}
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="unlinking = null">
                        {{ $t('Cancel') }}
                    </SecondaryButton>
                    <DangerButton type="button" @click="unlink">
                        {{ $t('Remove') }}
                    </DangerButton>
                </div>
            </div>
        </Modal>

        <Modal
            :show="confirmingDelete"
            max-width="md"
            @close="confirmingDelete = false"
        >
            <div class="p-6">
                <h2
                    class="text-lg font-medium text-gray-900 dark:text-gray-100"
                >
                    {{ $t('Delete :name?', { name: student.name }) }}
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{
                        $t(
                            'Only for records created by mistake. If the student left the school, edit them and set the status to "Left" instead.',
                        )
                    }}
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
