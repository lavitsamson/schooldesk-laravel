<script setup lang="ts">
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { formatDateTime } from '@/composables/dates';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { AppNotification, CursorPaginated } from '@/types/family';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{ notifications: CursorPaginated<AppNotification> }>();

const unread = computed(() => usePage().props.unreadNotifications);

/** Where a notification leads: the record it is about. */
const target = (n: AppNotification): string | null => {
    switch (n.subject?.type) {
        case 'student':
            return route('family.attendance', n.subject.id);
        case 'report_card':
            return n.subject.student_id
                ? route('family.report-cards', n.subject.student_id)
                : null;
        case 'invoice':
            return route('invoices.show', n.subject.id);
        default:
            return null;
    }
};

const open = (n: AppNotification) => {
    const url = target(n);
    const visit = () => url && router.visit(url);

    if (n.read_at) {
        visit();
    } else {
        router.post(
            route('notifications.read', n.id),
            {},
            { preserveScroll: true, onSuccess: visit },
        );
    }
};

const markAllRead = () =>
    router.post(route('notifications.read-all'), {}, { preserveScroll: true });
</script>

<template>
    <Head title="Notifications" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2
                    class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
                >
                    Notifications
                </h2>
                <SecondaryButton v-if="unread > 0" @click="markAllRead">
                    Mark all as read
                </SecondaryButton>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <ul
                    v-if="notifications.data.length > 0"
                    class="divide-y divide-gray-100 bg-white shadow-xs sm:rounded-lg dark:divide-gray-700 dark:bg-gray-800"
                    aria-label="Notifications"
                >
                    <li v-for="n in notifications.data" :key="n.id">
                        <button
                            type="button"
                            class="flex w-full items-start gap-3 px-4 py-3 text-start hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden focus:ring-inset dark:hover:bg-gray-700"
                            @click="open(n)"
                        >
                            <span
                                class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                                :class="
                                    n.read_at
                                        ? 'bg-transparent'
                                        : 'bg-indigo-600'
                                "
                                aria-hidden="true"
                            />
                            <span class="flex-1">
                                <span
                                    class="block text-sm text-gray-900 dark:text-gray-100"
                                    :class="{ 'font-semibold': !n.read_at }"
                                >
                                    {{ n.title }}
                                    <span v-if="!n.read_at" class="sr-only"
                                        >(unread)</span
                                    >
                                </span>
                                <span
                                    v-if="n.created_at"
                                    class="block text-xs text-gray-600 dark:text-gray-400"
                                >
                                    {{ formatDateTime(n.created_at) }}
                                </span>
                            </span>
                        </button>
                    </li>
                </ul>
                <p
                    v-else
                    class="bg-white p-6 text-sm text-gray-600 shadow-xs sm:rounded-lg dark:bg-gray-800 dark:text-gray-400"
                >
                    No notifications yet.
                </p>

                <nav
                    v-if="notifications.links.prev || notifications.links.next"
                    aria-label="Pages"
                    class="mt-4 flex justify-between text-sm font-medium"
                >
                    <Link
                        v-if="notifications.links.prev"
                        :href="notifications.links.prev"
                        class="text-indigo-700 hover:underline dark:text-indigo-300"
                    >
                        ‹ Newer
                    </Link>
                    <span v-else />
                    <Link
                        v-if="notifications.links.next"
                        :href="notifications.links.next"
                        class="text-indigo-700 hover:underline dark:text-indigo-300"
                    >
                        Older ›
                    </Link>
                </nav>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
