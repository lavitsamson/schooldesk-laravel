<script setup lang="ts">
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
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Member {
    id: number;
    name: string;
    email: string;
    status: 'active' | 'invited';
    invited_at: string | null;
    roles: string[];
    can: { resend: boolean; remove: boolean };
}

const props = defineProps<{
    members: Member[];
    roles: Option[];
    invitationExpiresAfterDays: number;
}>();

const form = useForm({
    name: '',
    email: '',
    role: props.roles.find((r) => r.value === 'teacher')?.value ?? '',
});

const invite = () =>
    form.post(route('members.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('name', 'email'),
    });

const resend = (member: Member) =>
    router.post(
        route('members.resend', member.id),
        {},
        { preserveScroll: true },
    );

const removing = ref<Member | null>(null);
const remove = () => {
    if (!removing.value) return;

    router.delete(route('members.destroy', removing.value.id), {
        preserveScroll: true,
        onFinish: () => (removing.value = null),
    });
};
</script>

<template>
    <Head title="Members" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
            >
                Members
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section
                    class="bg-white p-6 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    aria-labelledby="invite-title"
                >
                    <h3
                        id="invite-title"
                        class="text-lg font-medium text-gray-900 dark:text-gray-100"
                    >
                        Invite someone
                    </h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        They get an email with a link that is valid for
                        {{ invitationExpiresAfterDays }} days.
                    </p>

                    <form
                        class="mt-4 grid items-start gap-4 sm:grid-cols-[1fr_1fr_12rem_auto]"
                        @submit.prevent="invite"
                    >
                        <div>
                            <InputLabel for="invite-name" value="Name" />
                            <TextInput
                                id="invite-name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError
                                class="mt-1"
                                :message="form.errors.name"
                            />
                        </div>
                        <div>
                            <InputLabel for="invite-email" value="Email" />
                            <TextInput
                                id="invite-email"
                                v-model="form.email"
                                type="email"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError
                                class="mt-1"
                                :message="form.errors.email"
                            />
                        </div>
                        <div>
                            <InputLabel for="invite-role" value="Role" />
                            <SelectInput
                                id="invite-role"
                                v-model="form.role"
                                class="mt-1 block w-full"
                            >
                                <option
                                    v-for="role in roles"
                                    :key="role.value"
                                    :value="role.value"
                                >
                                    {{ role.label }}
                                </option>
                            </SelectInput>
                            <InputError
                                class="mt-1"
                                :message="form.errors.role"
                            />
                        </div>
                        <PrimaryButton
                            class="sm:mt-6"
                            :disabled="form.processing"
                        >
                            Send invitation
                        </PrimaryButton>
                    </form>
                </section>

                <section
                    class="overflow-x-auto bg-white shadow-xs sm:rounded-lg dark:bg-gray-800"
                >
                    <table
                        class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700"
                    >
                        <caption class="sr-only">
                            School members
                        </caption>
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr
                                class="text-left font-medium text-gray-600 dark:text-gray-300"
                            >
                                <th scope="col" class="px-4 py-3">Name</th>
                                <th scope="col" class="px-4 py-3">Email</th>
                                <th scope="col" class="px-4 py-3">Role</th>
                                <th scope="col" class="px-4 py-3">Status</th>
                                <th scope="col" class="px-4 py-3">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-200 text-gray-900 dark:divide-gray-700 dark:text-gray-100"
                        >
                            <tr v-for="member in members" :key="member.id">
                                <td class="px-4 py-3 font-medium">
                                    {{ member.name }}
                                </td>
                                <td class="px-4 py-3">{{ member.email }}</td>
                                <td class="px-4 py-3">
                                    {{ member.roles.join(', ') }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="
                                            member.status === 'active'
                                                ? 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200'
                                                : 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200'
                                        "
                                    >
                                        {{
                                            member.status === 'active'
                                                ? 'Active'
                                                : 'Invited'
                                        }}
                                    </span>
                                </td>
                                <td class="space-x-2 px-4 py-3 text-right">
                                    <SecondaryButton
                                        v-if="member.can.resend"
                                        type="button"
                                        @click="resend(member)"
                                    >
                                        Resend
                                    </SecondaryButton>
                                    <DangerButton
                                        v-if="member.can.remove"
                                        type="button"
                                        :aria-label="`Remove ${member.name}`"
                                        @click="removing = member"
                                    >
                                        {{
                                            member.status === 'active'
                                                ? 'Remove'
                                                : 'Revoke'
                                        }}
                                    </DangerButton>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>
        </div>

        <Modal
            :show="removing !== null"
            max-width="md"
            @close="removing = null"
        >
            <div class="p-6">
                <h2
                    class="text-lg font-medium text-gray-900 dark:text-gray-100"
                >
                    Remove {{ removing?.name }}?
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    They lose access to this school straight away. Their account
                    stays, in case they belong to other schools.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="removing = null">
                        Cancel
                    </SecondaryButton>
                    <DangerButton type="button" @click="remove">
                        Remove
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
