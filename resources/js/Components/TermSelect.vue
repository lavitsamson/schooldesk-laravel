<script setup lang="ts">
import InputLabel from '@/Components/InputLabel.vue';
import SelectInput from '@/Components/SelectInput.vue';
import type { TermOption } from '@/types/school';
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps<{
    terms: TermOption[];
    current: number;
    /** The page to reload with ?term=. */
    url: string;
}>();

const term = ref(props.current);
watch(term, (value) =>
    router.get(
        props.url,
        { term: value },
        { preserveScroll: true, replace: true },
    ),
);
</script>

<template>
    <div>
        <InputLabel for="term" :value="$t('Term')" class="sr-only" />
        <SelectInput id="term" v-model="term" class="block">
            <option v-for="t in terms" :key="t.id" :value="t.id">
                {{ t.name }}
            </option>
        </SelectInput>
    </div>
</template>
