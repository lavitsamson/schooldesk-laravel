<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const school = computed(() => page.props.school);
const schools = computed(() => page.props.schools);

const switchTo = (event: Event) => {
    const id = Number((event.target as HTMLSelectElement).value);

    if (id !== school.value?.id) {
        router.post(route('schools.switch', id));
    }
};
</script>

<template>
    <div v-if="schools.length > 1" class="flex items-center">
        <label for="school-switcher" class="sr-only">{{
            $t('Current school')
        }}</label>
        <select
            id="school-switcher"
            :value="school?.id ?? ''"
            class="rounded-md border-gray-300 py-1.5 text-sm text-gray-700 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
            @change="switchTo"
        >
            <option v-if="!school" value="" disabled>
                {{ $t('Choose a school') }}
            </option>
            <option v-for="item in schools" :key="item.id" :value="item.id">
                {{ item.name }}
            </option>
        </select>
    </div>
    <span
        v-else-if="school"
        class="text-sm font-medium text-gray-600 dark:text-gray-300"
        data-testid="current-school"
    >
        {{ school.name }}
    </span>
</template>
