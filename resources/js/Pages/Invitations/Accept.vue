<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    mode: 'register' | 'login' | 'accept' | 'wrong_account';
    schoolName: string;
    email: string;
    name: string;
    roles: string[];
    acceptUrl: string;
}>();

const form = useForm({
    name: props.name,
    password: '',
    password_confirmation: '',
});

const submit = () =>
    form.post(props.acceptUrl, {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
</script>

<template>
    <GuestLayout>
        <Head title="Accept invitation" />

        <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
            Join {{ schoolName }}
        </h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            You were invited as {{ roles.join(', ') || 'a member' }} with
            <strong>{{ email }}</strong
            >.
        </p>

        <form
            v-if="mode === 'register'"
            class="mt-6 space-y-4"
            @submit.prevent="submit"
        >
            <div>
                <InputLabel for="name" value="Your name" />
                <TextInput
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full"
                    required
                    autocomplete="name"
                />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>
            <div>
                <InputLabel for="password" value="Choose a password" />
                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>
            <div>
                <InputLabel
                    for="password_confirmation"
                    value="Confirm password"
                />
                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="new-password"
                />
            </div>
            <div class="flex justify-end">
                <PrimaryButton :disabled="form.processing">
                    Accept invitation
                </PrimaryButton>
            </div>
        </form>

        <div v-else-if="mode === 'accept'" class="mt-6 flex justify-end">
            <PrimaryButton :disabled="form.processing" @click="submit">
                Accept invitation
            </PrimaryButton>
        </div>

        <div v-else-if="mode === 'login'" class="mt-6">
            <p class="text-sm text-gray-700 dark:text-gray-300">
                You already have a SchoolDesk account. Log in to accept.
            </p>
            <Link
                :href="route('login')"
                class="mt-4 inline-flex rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase hover:bg-gray-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden dark:bg-gray-200 dark:text-gray-800"
            >
                Log in
            </Link>
        </div>

        <div v-else class="mt-6 space-y-4">
            <p class="text-sm text-gray-700 dark:text-gray-300">
                You are logged in with a different account. Log out, then open
                the invitation link again.
            </p>
            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden dark:text-gray-400"
            >
                Log out
            </Link>
        </div>
    </GuestLayout>
</template>
