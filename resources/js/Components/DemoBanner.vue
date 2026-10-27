<script setup lang="ts">
import { PageProps } from '@/types';
import { usePage } from '@inertiajs/vue3';

// Only on the public demo (DEMO_MODE): everything visitors enter is deleted every night.
const page = usePage<PageProps>();
</script>

<template>
    <div
        v-if="page.props.demo"
        role="note"
        class="bg-amber-100 px-4 py-2 text-center text-sm text-amber-950 dark:bg-amber-900 dark:text-amber-50"
    >
        {{
            $t(
                'Public demo: all data is reset every night. Please do not enter real personal data.',
            )
        }}
    </div>
</template>
