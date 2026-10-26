<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Head, Link } from '@inertiajs/vue3';

// Rendered on the server from resources/privacy/{locale}.md (raw HTML stripped).
defineProps<{ html: string }>();
</script>

<template>
    <Head :title="$t('Privacy notice')" />

    <div class="min-h-screen bg-gray-100 py-8 dark:bg-gray-900">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <Link href="/" :aria-label="$t('Home')">
                <ApplicationLogo class="h-12 w-12 fill-current text-gray-500" />
            </Link>

            <!-- The app's own Markdown, rendered on the server with raw HTML stripped. -->
            <!-- eslint-disable vue/no-v-html -->
            <article
                class="privacy mt-6 bg-white p-6 text-sm leading-relaxed text-gray-800 shadow-xs sm:rounded-lg dark:bg-gray-800 dark:text-gray-200"
                v-html="html"
            />
            <!-- eslint-enable vue/no-v-html -->
        </div>
    </div>
</template>

<style scoped>
.privacy :deep(h1) {
    font-size: 1.5rem;
    font-weight: 600;
}
.privacy :deep(h2) {
    margin-top: 1.5rem;
    font-size: 1.125rem;
    font-weight: 600;
}
.privacy :deep(p),
.privacy :deep(ul),
.privacy :deep(table) {
    margin-top: 0.75rem;
}
.privacy :deep(ul) {
    list-style: disc;
    padding-inline-start: 1.25rem;
}
.privacy :deep(table) {
    display: block;
    overflow-x: auto;
    border-collapse: collapse;
}
.privacy :deep(th),
.privacy :deep(td) {
    border: 1px solid rgb(209 213 219);
    padding: 0.375rem 0.5rem;
    text-align: start;
    vertical-align: top;
}
</style>
