<script setup lang="ts">
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Subject {
    id: number;
    name: string;
    code: string;
    classes_count: number;
}

defineProps<{ subjects: Subject[] }>();

const addForm = useForm({ name: '', code: '' });
const add = () =>
    addForm.post(route('subjects.store'), {
        preserveScroll: true,
        onSuccess: () => addForm.reset(),
    });

const editingId = ref<number | null>(null);
const editForm = useForm({ name: '', code: '' });
const edit = (subject: Subject) => {
    editingId.value = subject.id;
    editForm.defaults({ name: subject.name, code: subject.code });
    editForm.reset();
    editForm.clearErrors();
};
const save = () => {
    if (editingId.value === null) return;
    editForm.put(route('subjects.update', editingId.value), {
        preserveScroll: true,
        onSuccess: () => (editingId.value = null),
    });
};

const pageErrors = computed(() => usePage().props.errors);

const deleting = ref<Subject | null>(null);
const destroy = () => {
    if (!deleting.value) return;
    router.delete(route('subjects.destroy', deleting.value.id), {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
};
</script>

<template>
    <Head title="Subjects" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
            >
                Subjects
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section
                    class="bg-white p-6 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    aria-labelledby="add-subject-title"
                >
                    <h3
                        id="add-subject-title"
                        class="text-lg font-medium text-gray-900 dark:text-gray-100"
                    >
                        Add a subject
                    </h3>
                    <form
                        class="mt-4 grid items-start gap-4 sm:grid-cols-[1fr_10rem_auto]"
                        @submit.prevent="add"
                    >
                        <div>
                            <InputLabel for="subject-name" value="Name" />
                            <TextInput
                                id="subject-name"
                                v-model="addForm.name"
                                type="text"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError
                                class="mt-1"
                                :message="addForm.errors.name"
                            />
                        </div>
                        <div>
                            <InputLabel for="subject-code" value="Code" />
                            <TextInput
                                id="subject-code"
                                v-model="addForm.code"
                                type="text"
                                class="mt-1 block w-full uppercase"
                                required
                            />
                            <InputError
                                class="mt-1"
                                :message="addForm.errors.code"
                            />
                        </div>
                        <PrimaryButton
                            class="sm:mt-6"
                            :disabled="addForm.processing"
                        >
                            Add
                        </PrimaryButton>
                    </form>
                </section>

                <InputError :message="pageErrors.subject" />

                <section
                    class="overflow-x-auto bg-white shadow-xs sm:rounded-lg dark:bg-gray-800"
                >
                    <table
                        class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700"
                    >
                        <caption class="sr-only">
                            Subjects
                        </caption>
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr
                                class="text-left font-medium text-gray-600 dark:text-gray-300"
                            >
                                <th scope="col" class="px-4 py-3">Name</th>
                                <th scope="col" class="px-4 py-3">Code</th>
                                <th scope="col" class="px-4 py-3">Taught in</th>
                                <th scope="col" class="px-4 py-3">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-200 text-gray-900 dark:divide-gray-700 dark:text-gray-100"
                        >
                            <tr v-for="subject in subjects" :key="subject.id">
                                <template v-if="editingId === subject.id">
                                    <td class="px-4 py-2">
                                        <InputLabel
                                            :for="`edit-name-${subject.id}`"
                                            value="Name"
                                            class="sr-only"
                                        />
                                        <TextInput
                                            :id="`edit-name-${subject.id}`"
                                            v-model="editForm.name"
                                            type="text"
                                            class="block w-full"
                                            required
                                        />
                                        <InputError
                                            class="mt-1"
                                            :message="editForm.errors.name"
                                        />
                                    </td>
                                    <td class="px-4 py-2">
                                        <InputLabel
                                            :for="`edit-code-${subject.id}`"
                                            value="Code"
                                            class="sr-only"
                                        />
                                        <TextInput
                                            :id="`edit-code-${subject.id}`"
                                            v-model="editForm.code"
                                            type="text"
                                            class="block w-28 uppercase"
                                            required
                                        />
                                        <InputError
                                            class="mt-1"
                                            :message="editForm.errors.code"
                                        />
                                    </td>
                                    <td class="px-4 py-2">
                                        {{ subject.classes_count }} classes
                                    </td>
                                    <td
                                        class="space-x-2 px-4 py-2 text-right whitespace-nowrap"
                                    >
                                        <SecondaryButton
                                            type="button"
                                            @click="editingId = null"
                                        >
                                            Cancel
                                        </SecondaryButton>
                                        <PrimaryButton
                                            type="button"
                                            :disabled="editForm.processing"
                                            @click="save"
                                        >
                                            Save
                                        </PrimaryButton>
                                    </td>
                                </template>
                                <template v-else>
                                    <td class="px-4 py-3 font-medium">
                                        {{ subject.name }}
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs">
                                        {{ subject.code }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ subject.classes_count }} classes
                                    </td>
                                    <td
                                        class="space-x-2 px-4 py-3 text-right whitespace-nowrap"
                                    >
                                        <SecondaryButton
                                            type="button"
                                            :aria-label="`Edit ${subject.name}`"
                                            @click="edit(subject)"
                                        >
                                            Edit
                                        </SecondaryButton>
                                        <DangerButton
                                            type="button"
                                            :aria-label="`Delete ${subject.name}`"
                                            @click="deleting = subject"
                                        >
                                            Delete
                                        </DangerButton>
                                    </td>
                                </template>
                            </tr>
                            <tr v-if="subjects.length === 0">
                                <td
                                    colspan="4"
                                    class="px-4 py-10 text-center text-gray-600 dark:text-gray-400"
                                >
                                    No subjects yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>
        </div>

        <Modal
            :show="deleting !== null"
            max-width="md"
            @close="deleting = null"
        >
            <div class="p-6">
                <h2
                    class="text-lg font-medium text-gray-900 dark:text-gray-100"
                >
                    Delete {{ deleting?.name }}?
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    It is removed from every class it is taught in.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="deleting = null">
                        Cancel
                    </SecondaryButton>
                    <DangerButton type="button" @click="destroy">
                        Delete
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
