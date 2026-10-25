<script setup lang="ts">
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { Option } from '@/types';
import type { Guardian, Paginated } from '@/types/school';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps<{
    guardians: Paginated<Guardian>;
    filters: { search: string };
    relationships: Option[];
}>();

const search = ref(props.filters.search);
let timer: ReturnType<typeof setTimeout> | undefined;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(
        () =>
            router.get(
                route('guardians.index'),
                { search: search.value || undefined },
                { preserveState: true, replace: true },
            ),
        300,
    );
});

const editing = ref<Guardian | null>(null);
const form = useForm({ name: '', email: '', phone: '', relationship: '' });
const edit = (guardian: Guardian) => {
    editing.value = guardian;
    form.defaults({
        name: guardian.name,
        email: guardian.email ?? '',
        phone: guardian.phone ?? '',
        relationship: guardian.relationship,
    });
    form.reset();
    form.clearErrors();
};
const save = () => {
    if (!editing.value) return;
    form.put(route('guardians.update', editing.value.id), {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    });
};

const invite = (guardian: Guardian) =>
    router.post(
        route('guardians.invite', guardian.id),
        {},
        { preserveScroll: true },
    );

const deleting = ref<Guardian | null>(null);
const destroy = () => {
    if (!deleting.value) return;
    router.delete(route('guardians.destroy', deleting.value.id), {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
};
</script>

<template>
    <Head :title="$t('Guardians')" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
            >
                {{ $t('Guardians') }}
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                <div
                    class="bg-white p-4 shadow-xs sm:rounded-lg dark:bg-gray-800"
                >
                    <InputLabel for="search" :value="$t('Search')" />
                    <TextInput
                        id="search"
                        v-model="search"
                        type="search"
                        class="mt-1 block w-full sm:max-w-md"
                        :placeholder="$t('Name, email or phone')"
                    />
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        {{ $t("Guardians are added from a student's page.") }}
                    </p>
                </div>

                <section
                    class="overflow-x-auto bg-white shadow-xs sm:rounded-lg dark:bg-gray-800"
                >
                    <table
                        class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700"
                    >
                        <caption class="sr-only">
                            {{
                                $t('Guardians')
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
                                    {{ $t('Contact') }}
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    {{ $t('Children') }}
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    {{ $t('Parent portal') }}
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
                            <tr
                                v-for="guardian in guardians.data"
                                :key="guardian.id"
                            >
                                <td class="px-4 py-3">
                                    <span class="font-medium">{{
                                        guardian.name
                                    }}</span>
                                    <span
                                        class="block text-xs text-gray-600 dark:text-gray-400"
                                    >
                                        {{ guardian.relationship_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="block">{{
                                        guardian.email ?? '—'
                                    }}</span>
                                    <span
                                        class="block text-gray-600 dark:text-gray-400"
                                        >{{ guardian.phone }}</span
                                    >
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        v-for="(
                                            child, index
                                        ) in guardian.students"
                                        :key="child.id"
                                    >
                                        <Link
                                            :href="
                                                route('students.show', child.id)
                                            "
                                            class="hover:underline"
                                        >
                                            {{ child.name }}</Link
                                        ><span
                                            v-if="
                                                index <
                                                (guardian.students?.length ??
                                                    0) -
                                                    1
                                            "
                                            >,
                                        </span>
                                    </span>
                                    <span
                                        v-if="!guardian.students?.length"
                                        class="text-gray-600 dark:text-gray-400"
                                    >
                                        {{ $t('None') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    {{
                                        guardian.has_portal_access
                                            ? $t('Invited')
                                            : $t('Not invited')
                                    }}
                                </td>
                                <td
                                    class="space-x-2 px-4 py-3 text-right whitespace-nowrap"
                                >
                                    <SecondaryButton
                                        v-if="guardian.can.invite"
                                        type="button"
                                        @click="invite(guardian)"
                                    >
                                        {{ $t('Invite') }}
                                    </SecondaryButton>
                                    <SecondaryButton
                                        v-if="guardian.can.update"
                                        type="button"
                                        :aria-label="
                                            $t('Edit :name', {
                                                name: guardian.name,
                                            })
                                        "
                                        @click="edit(guardian)"
                                    >
                                        {{ $t('Edit') }}
                                    </SecondaryButton>
                                    <DangerButton
                                        v-if="guardian.can.delete"
                                        type="button"
                                        :aria-label="
                                            $t('Delete :name', {
                                                name: guardian.name,
                                            })
                                        "
                                        @click="deleting = guardian"
                                    >
                                        {{ $t('Delete') }}
                                    </DangerButton>
                                </td>
                            </tr>
                            <tr v-if="guardians.data.length === 0">
                                <td
                                    colspan="5"
                                    class="px-4 py-10 text-center text-gray-600 dark:text-gray-400"
                                >
                                    {{ $t('No guardians found.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <Pagination
                    :meta="guardians.meta"
                    :label="$t('Guardian pages')"
                />
            </div>
        </div>

        <Modal :show="editing !== null" max-width="lg" @close="editing = null">
            <form class="space-y-4 p-6" @submit.prevent="save">
                <h2
                    class="text-lg font-medium text-gray-900 dark:text-gray-100"
                >
                    {{ $t('Edit :name', { name: editing?.name }) }}
                </h2>
                <div>
                    <InputLabel for="edit-name" :value="$t('Name')" />
                    <TextInput
                        id="edit-name"
                        v-model="form.name"
                        type="text"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-1" :message="form.errors.name" />
                </div>
                <div>
                    <InputLabel for="edit-email" :value="$t('Email')" />
                    <TextInput
                        id="edit-email"
                        v-model="form.email"
                        type="email"
                        class="mt-1 block w-full"
                        :disabled="editing?.has_portal_access"
                    />
                    <p
                        v-if="editing?.has_portal_access"
                        class="mt-1 text-xs text-gray-600 dark:text-gray-400"
                    >
                        {{
                            $t(
                                'This is their parent portal login, so it cannot be changed here.',
                            )
                        }}
                    </p>
                    <InputError class="mt-1" :message="form.errors.email" />
                </div>
                <div>
                    <InputLabel for="edit-phone" :value="$t('Phone')" />
                    <TextInput
                        id="edit-phone"
                        v-model="form.phone"
                        type="tel"
                        class="mt-1 block w-full"
                    />
                    <InputError class="mt-1" :message="form.errors.phone" />
                </div>
                <div>
                    <InputLabel
                        for="edit-relationship"
                        :value="$t('Relationship')"
                    />
                    <SelectInput
                        id="edit-relationship"
                        v-model="form.relationship"
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
                        :message="form.errors.relationship"
                    />
                </div>
                <div class="flex justify-end gap-3">
                    <SecondaryButton type="button" @click="editing = null">
                        {{ $t('Cancel') }}
                    </SecondaryButton>
                    <PrimaryButton :disabled="form.processing">{{
                        $t('Save')
                    }}</PrimaryButton>
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
                    {{ $t('Delete :name?', { name: deleting?.name }) }}
                </h2>
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
