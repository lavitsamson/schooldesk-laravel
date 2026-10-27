<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import DemoBanner from '@/Components/DemoBanner.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

defineProps<{ canRegister: boolean }>();

const page = usePage();
// Null for guests, whatever the shared type says.
const signedIn = computed(() => Boolean(page.props.auth.user));

const features = computed(() => [
    trans('Attendance in a few clicks, with absence notices to parents'),
    trans('Marks, weighted results and report cards as PDFs'),
    trans('Report-card comments drafted by AI, always reviewed by the teacher'),
    trans('Fees and invoices, paid online by card'),
    trans('A portal and an app API for parents'),
    trans('GDPR exports and erasure, in English, German and Hindi'),
]);
</script>

<template>
    <Head title="SchoolDesk" />
    <DemoBanner />

    <div
        class="flex min-h-screen flex-col bg-gray-100 text-gray-900 dark:bg-gray-900 dark:text-gray-100"
    >
        <header
            class="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 px-4 py-6 sm:px-6"
        >
            <span class="flex items-center gap-3 text-lg font-semibold">
                <ApplicationLogo
                    class="h-9 w-9 fill-current text-indigo-700 dark:text-indigo-300"
                />
                SchoolDesk
            </span>
            <nav :aria-label="$t('Account')" class="flex gap-2 text-sm">
                <Link
                    v-if="signedIn"
                    :href="route('dashboard')"
                    class="rounded-md px-3 py-2 font-medium hover:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:hover:bg-gray-800"
                >
                    {{ $t('Dashboard') }}
                </Link>
                <template v-else>
                    <Link
                        :href="route('login')"
                        class="rounded-md px-3 py-2 font-medium hover:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none dark:hover:bg-gray-800"
                    >
                        {{ $t('Log in') }}
                    </Link>
                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="rounded-md bg-indigo-700 px-3 py-2 font-medium text-white hover:bg-indigo-800 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none dark:focus:ring-offset-gray-900"
                    >
                        {{ $t('Register') }}
                    </Link>
                </template>
            </nav>
        </header>

        <main class="mx-auto w-full max-w-5xl flex-1 px-4 py-10 sm:px-6">
            <h1 class="max-w-2xl text-3xl font-semibold sm:text-4xl">
                {{
                    $t(
                        'Students, attendance, marks, report cards and fees, for one school or many.',
                    )
                }}
            </h1>
            <p class="mt-4 max-w-2xl text-lg text-gray-700 dark:text-gray-300">
                {{
                    $t(
                        'SchoolDesk keeps the school office, teachers and parents on the same page.',
                    )
                }}
            </p>

            <section aria-labelledby="features-title" class="mt-12">
                <h2 id="features-title" class="text-xl font-semibold">
                    {{ $t('What it does') }}
                </h2>
                <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                    <li
                        v-for="feature in features"
                        :key="feature"
                        class="rounded-lg bg-white p-4 shadow-xs dark:bg-gray-800"
                    >
                        {{ feature }}
                    </li>
                </ul>
            </section>
        </main>

        <footer
            class="mx-auto w-full max-w-5xl px-4 py-6 text-sm text-gray-700 sm:px-6 dark:text-gray-300"
        >
            <Link :href="route('privacy')" class="hover:underline">
                {{ $t('Privacy notice') }}
            </Link>
        </footer>
    </div>
</template>
