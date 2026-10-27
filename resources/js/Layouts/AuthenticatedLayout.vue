<script setup lang="ts">
import { ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DemoBanner from '@/Components/DemoBanner.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import SchoolSwitcher from '@/Components/SchoolSwitcher.vue';
import { usePermissions } from '@/composables/usePermissions';
import { Link, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const showingNavigationDropdown = ref(false);
const { can } = usePermissions();
const unread = computed(() => usePage().props.unreadNotifications);

interface NavItem {
    label: string;
    route: string;
    active: string | string[];
    permission?: string;
    badge?: number;
}

const links = computed<NavItem[]>(() =>
    [
        {
            label: trans('Dashboard'),
            route: 'dashboard',
            active: ['dashboard', 'family.*'],
        },
        {
            label: trans('Attendance'),
            route: 'attendance.index',
            active: 'attendance.*',
            permission: 'attendance.take',
        },
        {
            label: trans('Grades'),
            route: 'grades.index',
            active: ['grades.*', 'assessments.*', 'results.*', 'grade-scale.*'],
            permission: 'grades.enter',
        },
        {
            label: trans('Report cards'),
            route: 'report-cards.index',
            active: 'report-cards.*',
            permission: 'report_cards.generate',
        },
        {
            label: trans('Fees'),
            route: 'fees.index',
            active: ['fees.*', 'fee-structures.*', 'invoices.*'],
            permission: 'fees.manage',
        },
        {
            label: trans('Invoices'),
            route: 'family.invoices',
            active: ['family.invoices', 'invoices.show'],
            permission: 'children.view',
        },
        {
            label: trans('Notifications'),
            route: 'notifications.index',
            active: 'notifications.*',
            permission: 'children.view',
            badge: unread.value,
        },
        {
            label: trans('Students'),
            route: 'students.index',
            active: 'students.*',
            permission: 'students.manage',
        },
        {
            label: trans('Guardians'),
            route: 'guardians.index',
            active: 'guardians.*',
            permission: 'students.manage',
        },
        {
            label: trans('Classes'),
            route: 'classes.index',
            active: 'classes.*',
            permission: 'school.manage',
        },
        {
            label: trans('Subjects'),
            route: 'subjects.index',
            active: 'subjects.*',
            permission: 'school.manage',
        },
        {
            label: trans('Academic years'),
            route: 'academic-years.index',
            active: 'academic-years.*',
            permission: 'school.manage',
        },
        {
            label: trans('Members'),
            route: 'members.index',
            active: 'members.*',
            permission: 'members.manage',
        },
        {
            label: trans('Audit log'),
            route: 'audit-log.index',
            active: 'audit-log.*',
            permission: 'audit.view',
        },
        {
            label: trans('Data protection'),
            route: 'data-protection.edit',
            active: 'data-protection.*',
            permission: 'data_protection.manage',
        },
    ].filter((link) => !link.permission || can(link.permission)),
);

const isActive = (patterns: string | string[]) =>
    [patterns].flat().some((pattern) => route().current(pattern));
</script>

<template>
    <div>
        <!-- First stop for keyboard users: past the navigation to the page itself. -->
        <a
            href="#main"
            class="sr-only rounded-md bg-white px-4 py-2 font-medium text-indigo-700 focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:ring-2 focus:ring-indigo-500 dark:bg-gray-800 dark:text-indigo-300"
        >
            {{ $t('Skip to main content') }}
        </a>
        <DemoBanner />
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
            <nav
                :aria-label="$t('Main')"
                class="border-b border-gray-100 bg-white dark:border-gray-700 dark:bg-gray-800"
            >
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link
                                    :href="route('dashboard')"
                                    :aria-label="$t('Dashboard')"
                                >
                                    <ApplicationLogo
                                        class="block h-9 w-auto fill-current text-gray-800 dark:text-gray-200"
                                    />
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div
                                class="hidden space-x-6 lg:-my-px lg:ms-8 lg:flex"
                            >
                                <NavLink
                                    v-for="link in links"
                                    :key="link.route"
                                    :href="route(link.route)"
                                    :active="isActive(link.active)"
                                >
                                    {{ link.label }}
                                    <span
                                        v-if="link.badge"
                                        class="ms-1.5 rounded-full bg-indigo-600 px-1.5 py-0.5 text-xs font-semibold text-white tabular-nums"
                                    >
                                        {{ link.badge }}
                                        <span class="sr-only">{{
                                            $t('unread')
                                        }}</span>
                                    </span>
                                </NavLink>
                            </div>
                        </div>

                        <div class="hidden lg:ms-6 lg:flex lg:items-center">
                            <SchoolSwitcher />
                            <!-- Settings Dropdown -->
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm leading-4 font-medium text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-hidden dark:bg-gray-800 dark:text-gray-400 dark:hover:text-gray-300"
                                            >
                                                {{ $page.props.auth.user.name }}

                                                <svg
                                                    class="ms-2 -me-0.5 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink
                                            :href="route('profile.edit')"
                                        >
                                            {{ $t('Profile') }}
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            {{ $t('Log Out') }}
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center lg:hidden">
                            <button
                                type="button"
                                :aria-label="$t('Menu')"
                                :aria-expanded="showingNavigationDropdown"
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-hidden dark:text-gray-500 dark:hover:bg-gray-900 dark:hover:text-gray-400 dark:focus:bg-gray-900 dark:focus:text-gray-400"
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="lg:hidden"
                >
                    <div class="space-y-1 pt-2 pb-3">
                        <ResponsiveNavLink
                            v-for="link in links"
                            :key="link.route"
                            :href="route(link.route)"
                            :active="isActive(link.active)"
                        >
                            {{ link.label }}
                            <span v-if="link.badge" class="tabular-nums">
                                ({{
                                    $t(':count unread', {
                                        count: String(link.badge),
                                    })
                                }})
                            </span>
                        </ResponsiveNavLink>
                    </div>

                    <div class="px-4 pb-3">
                        <SchoolSwitcher />
                    </div>

                    <!-- Responsive Settings Options -->
                    <div
                        class="border-t border-gray-200 pt-4 pb-1 dark:border-gray-600"
                    >
                        <div class="px-4">
                            <div
                                class="text-base font-medium text-gray-800 dark:text-gray-200"
                            >
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-gray-500">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">
                                {{ $t('Profile') }}
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                {{ $t('Log Out') }}
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header
                v-if="$slots.header"
                class="bg-white shadow-sm dark:bg-gray-800"
            >
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main id="main" tabindex="-1" class="focus:outline-hidden">
                <FlashMessage />
                <slot />
            </main>

            <footer
                class="mx-auto max-w-7xl px-4 pb-8 text-sm text-gray-600 sm:px-6 lg:px-8 dark:text-gray-400"
            >
                <Link :href="route('privacy')" class="hover:underline">
                    {{ $t('Privacy notice') }}
                </Link>
            </footer>
        </div>
    </div>
</template>
