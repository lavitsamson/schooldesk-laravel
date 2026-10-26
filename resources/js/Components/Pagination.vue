<script setup lang="ts">
import type { Paginated } from '@/types/school';
import { Link } from '@inertiajs/vue3';

defineProps<{ meta: Paginated<unknown>['meta']; label?: string }>();

// Laravel's labels come from lang/*/pagination.php (translated) and contain HTML entities
// ("&laquo; Previous"); show them as plain text.
const text = (label: string) =>
    label.replace('&laquo;', '‹').replace('&raquo;', '›');
</script>

<template>
    <nav
        v-if="meta.last_page > 1"
        class="flex flex-wrap items-center justify-between gap-3 text-sm"
        :aria-label="label ?? $t('Pagination')"
    >
        <p class="text-gray-600 dark:text-gray-400">
            {{
                $t(':from–:to of :total', {
                    from: String(meta.from),
                    to: String(meta.to),
                    total: String(meta.total),
                })
            }}
        </p>
        <ul class="flex flex-wrap gap-1">
            <li v-for="(link, index) in meta.links" :key="index">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    preserve-state
                    class="block rounded-md px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden"
                    :class="
                        link.active
                            ? 'bg-indigo-600 text-white'
                            : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'
                    "
                    :aria-current="link.active ? 'page' : undefined"
                >
                    {{ text(link.label) }}
                </Link>
                <span
                    v-else
                    class="block px-3 py-1.5 text-gray-600 dark:text-gray-400"
                    aria-hidden="true"
                >
                    {{ text(link.label) }}
                </span>
            </li>
        </ul>
    </nav>
</template>
