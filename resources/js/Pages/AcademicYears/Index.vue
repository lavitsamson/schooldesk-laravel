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
import type { AcademicYear, Term } from '@/types/school';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{ years: AcademicYear[]; canPromote: boolean }>();

const pageErrors = computed(() => usePage().props.errors);

const form = useForm({
    name: '',
    starts_on: '',
    ends_on: '',
    terms: [] as Term[],
    copy_classes_from: null as number | null,
});
const editing = ref<AcademicYear | 'new' | null>(null);

const plusOneYear = (date: string) =>
    date ? `${Number(date.slice(0, 4)) + 1}${date.slice(4)}` : '';
const nextName = (name: string) => {
    const match = /^(\d{4})\/(\d{2})$/.exec(name);
    if (!match) return '';
    const start = Number(match[1]) + 1;
    return `${start}/${String(start + 1).slice(2)}`;
};

const openCreate = () => {
    // Suggest the year after the latest one, with the same term pattern.
    const latest = props.years[0];
    form.defaults({
        name: latest ? nextName(latest.name) : '',
        starts_on: latest ? plusOneYear(latest.starts_on) : '',
        ends_on: latest ? plusOneYear(latest.ends_on) : '',
        terms: (
            latest?.terms ?? [
                { id: null, name: 'Term 1', starts_on: '', ends_on: '' },
            ]
        ).map((t) => ({
            id: null,
            name: t.name,
            starts_on: plusOneYear(t.starts_on),
            ends_on: plusOneYear(t.ends_on),
        })),
        copy_classes_from: latest?.id ?? null,
    });
    form.reset();
    form.clearErrors();
    editing.value = 'new';
};

const openEdit = (year: AcademicYear) => {
    form.defaults({
        name: year.name,
        starts_on: year.starts_on,
        ends_on: year.ends_on,
        terms: year.terms.map((t) => ({ ...t })),
        copy_classes_from: null,
    });
    form.reset();
    form.clearErrors();
    editing.value = year;
};

const addTerm = () =>
    form.terms.push({
        id: null,
        name: `Term ${form.terms.length + 1}`,
        starts_on: '',
        ends_on: '',
    });

const save = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    };

    if (editing.value === 'new') {
        form.post(route('academic-years.store'), options);
    } else if (editing.value) {
        form.put(route('academic-years.update', editing.value.id), options);
    }
};

const makeCurrent = (year: AcademicYear) =>
    router.post(
        route('academic-years.make-current', year.id),
        {},
        { preserveScroll: true },
    );

const deleting = ref<AcademicYear | null>(null);
const destroy = () => {
    if (!deleting.value) return;
    router.delete(route('academic-years.destroy', deleting.value.id), {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
};
</script>

<template>
    <Head title="Academic years" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2
                    class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
                >
                    Academic years
                </h2>
                <div class="flex gap-2">
                    <Link
                        v-if="canPromote"
                        :href="route('promotions.create')"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold tracking-widest text-gray-700 uppercase shadow-xs hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden dark:border-gray-500 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        Promote students
                    </Link>
                    <PrimaryButton type="button" @click="openCreate">
                        Add academic year
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                <p
                    v-if="pageErrors.year"
                    class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 dark:bg-red-950/50 dark:text-red-200"
                    role="alert"
                >
                    {{ pageErrors.year }}
                </p>

                <article
                    v-for="year in years"
                    :key="year.id"
                    class="bg-white p-6 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    :aria-labelledby="`year-${year.id}`"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <h3
                                :id="`year-${year.id}`"
                                class="text-lg font-medium text-gray-900 dark:text-gray-100"
                            >
                                {{ year.name }}
                                <span
                                    v-if="year.is_current"
                                    class="ms-2 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/50 dark:text-green-200"
                                >
                                    Current
                                </span>
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ year.starts_on }} – {{ year.ends_on }} ·
                                {{ year.classes_count }} classes ·
                                {{ year.enrollments_count }} enrolments
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <SecondaryButton
                                v-if="year.can.update && !year.is_current"
                                type="button"
                                @click="makeCurrent(year)"
                            >
                                Make current
                            </SecondaryButton>
                            <SecondaryButton
                                v-if="year.can.update"
                                type="button"
                                :aria-label="`Edit ${year.name}`"
                                @click="openEdit(year)"
                            >
                                Edit
                            </SecondaryButton>
                            <DangerButton
                                v-if="year.can.delete"
                                type="button"
                                :aria-label="`Delete ${year.name}`"
                                @click="deleting = year"
                            >
                                Delete
                            </DangerButton>
                        </div>
                    </div>
                    <ul
                        class="mt-4 grid gap-2 text-sm sm:grid-cols-3"
                        :aria-label="`Terms of ${year.name}`"
                    >
                        <li
                            v-for="term in year.terms"
                            :key="term.id ?? term.name"
                            class="rounded-md bg-gray-50 px-3 py-2 dark:bg-gray-900/50"
                        >
                            <span
                                class="block font-medium text-gray-900 dark:text-gray-100"
                                >{{ term.name }}</span
                            >
                            <span class="text-gray-600 dark:text-gray-400"
                                >{{ term.starts_on }} – {{ term.ends_on }}</span
                            >
                        </li>
                    </ul>
                </article>
            </div>
        </div>

        <Modal :show="editing !== null" max-width="2xl" @close="editing = null">
            <form class="space-y-6 p-6" @submit.prevent="save">
                <h2
                    class="text-lg font-medium text-gray-900 dark:text-gray-100"
                >
                    {{
                        editing === 'new'
                            ? 'Add academic year'
                            : `Edit ${form.name}`
                    }}
                </h2>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <InputLabel for="year-name" value="Name" />
                        <TextInput
                            id="year-name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="2027/28"
                            required
                        />
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel for="year-starts" value="Starts on" />
                        <TextInput
                            id="year-starts"
                            v-model="form.starts_on"
                            type="date"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError
                            class="mt-1"
                            :message="form.errors.starts_on"
                        />
                    </div>
                    <div>
                        <InputLabel for="year-ends" value="Ends on" />
                        <TextInput
                            id="year-ends"
                            v-model="form.ends_on"
                            type="date"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError
                            class="mt-1"
                            :message="form.errors.ends_on"
                        />
                    </div>
                </div>

                <fieldset>
                    <legend
                        class="text-sm font-medium text-gray-900 dark:text-gray-100"
                    >
                        Terms
                    </legend>
                    <InputError class="mt-1" :message="form.errors.terms" />
                    <div
                        v-for="(term, index) in form.terms"
                        :key="index"
                        class="mt-3 grid items-start gap-3 sm:grid-cols-[1fr_1fr_1fr_auto]"
                    >
                        <div>
                            <InputLabel
                                :for="`term-${index}-name`"
                                :value="`Term ${index + 1} name`"
                            />
                            <TextInput
                                :id="`term-${index}-name`"
                                v-model="term.name"
                                type="text"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError
                                class="mt-1"
                                :message="
                                    errorFor(form.errors, `terms.${index}.name`)
                                "
                            />
                        </div>
                        <div>
                            <InputLabel
                                :for="`term-${index}-starts`"
                                value="Starts on"
                            />
                            <TextInput
                                :id="`term-${index}-starts`"
                                v-model="term.starts_on"
                                type="date"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError
                                class="mt-1"
                                :message="
                                    errorFor(
                                        form.errors,
                                        `terms.${index}.starts_on`,
                                    )
                                "
                            />
                        </div>
                        <div>
                            <InputLabel
                                :for="`term-${index}-ends`"
                                value="Ends on"
                            />
                            <TextInput
                                :id="`term-${index}-ends`"
                                v-model="term.ends_on"
                                type="date"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError
                                class="mt-1"
                                :message="
                                    errorFor(
                                        form.errors,
                                        `terms.${index}.ends_on`,
                                    )
                                "
                            />
                        </div>
                        <SecondaryButton
                            type="button"
                            class="sm:mt-6"
                            :disabled="form.terms.length === 1"
                            :aria-label="`Remove term ${index + 1}`"
                            @click="form.terms.splice(index, 1)"
                        >
                            Remove
                        </SecondaryButton>
                    </div>
                    <SecondaryButton
                        type="button"
                        class="mt-3"
                        :disabled="form.terms.length >= 6"
                        @click="addTerm"
                    >
                        Add term
                    </SecondaryButton>
                </fieldset>

                <div v-if="editing === 'new' && years.length > 0">
                    <InputLabel for="copy-classes" value="Classes" />
                    <SelectInput
                        id="copy-classes"
                        v-model="form.copy_classes_from"
                        class="mt-1 block w-full sm:w-auto"
                    >
                        <option :value="null">Start without classes</option>
                        <option v-for="y in years" :key="y.id" :value="y.id">
                            Copy the classes of {{ y.name }}
                        </option>
                    </SelectInput>
                    <InputError
                        class="mt-1"
                        :message="form.errors.copy_classes_from"
                    />
                </div>

                <div class="flex justify-end gap-3">
                    <SecondaryButton type="button" @click="editing = null">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton :disabled="form.processing"
                        >Save</PrimaryButton
                    >
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
                    Delete {{ deleting?.name }}?
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Its terms and classes are deleted too. A year with enrolled
                    students cannot be deleted.
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
