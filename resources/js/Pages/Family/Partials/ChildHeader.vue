<script setup lang="ts">
import type { Child } from '@/types/family';
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

/**
 * The header of the parent portal's child pages: the child, a switcher to the same page for
 * a sibling, and tabs for attendance, results and report cards.
 */
type ChildPage = 'family.attendance' | 'family.results' | 'family.report-cards';

defineProps<{
    child: Child;
    children: Child[];
    page: ChildPage;
}>();

const tabs: { route: ChildPage; label: string }[] = [
    { route: 'family.attendance', label: trans('Attendance') },
    { route: 'family.results', label: trans('Results') },
    { route: 'family.report-cards', label: trans('Report cards') },
];
</script>

<template>
    <nav :aria-label="$t('Breadcrumb')" class="text-sm">
        <Link
            :href="route('dashboard')"
            class="text-gray-600 hover:underline dark:text-gray-400"
        >
            {{ $t('My family') }}
        </Link>
    </nav>
    <div class="mt-1 flex flex-wrap items-baseline justify-between gap-3">
        <h2
            class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
        >
            {{ child.name }}
            <span
                v-if="child.class"
                class="text-base font-normal text-gray-600 dark:text-gray-400"
            >
                · {{ child.class.name }}
            </span>
        </h2>
        <nav
            v-if="children.length > 1"
            :aria-label="$t('Children')"
            class="flex flex-wrap gap-2"
        >
            <Link
                v-for="sibling in children"
                :key="sibling.id"
                :href="route(page, sibling.id)"
                :aria-current="sibling.id === child.id ? 'page' : undefined"
                class="rounded-full px-3 py-1 text-sm font-medium focus:ring-2 focus:ring-indigo-500 focus:outline-hidden"
                :class="
                    sibling.id === child.id
                        ? 'bg-indigo-600 text-white'
                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600'
                "
            >
                {{ sibling.first_name }}
            </Link>
        </nav>
    </div>
    <nav
        :aria-label="$t('Child pages')"
        class="mt-4 flex gap-6 border-b border-gray-200 dark:border-gray-700"
    >
        <Link
            v-for="tab in tabs"
            :key="tab.route"
            :href="route(tab.route, child.id)"
            :aria-current="tab.route === page ? 'page' : undefined"
            class="-mb-px border-b-2 pb-2 text-sm font-medium focus:outline-hidden focus-visible:ring-2 focus-visible:ring-indigo-500"
            :class="
                tab.route === page
                    ? 'border-indigo-600 text-indigo-700 dark:text-indigo-300'
                    : 'border-transparent text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'
            "
        >
            {{ tab.label }}
        </Link>
    </nav>
</template>
