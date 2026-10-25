<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { IdName, SchoolClassRow } from '@/types/school';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps<{
    years: (IdName & { is_current: boolean })[];
    yearId: number | null;
    classes: SchoolClassRow[];
    teachers: IdName[];
}>();

const yearId = ref(props.yearId);
watch(yearId, (id) =>
    router.get(
        route('classes.index'),
        { year_id: id ?? undefined },
        { preserveState: true },
    ),
);

const form = useForm({
    academic_year_id: props.yearId,
    name: '',
    section: '',
    class_teacher_id: null as number | null,
});
watch(
    () => props.yearId,
    (id) => (form.academic_year_id = id),
);

const add = () =>
    form.post(route('classes.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('name', 'section', 'class_teacher_id'),
    });
</script>

<template>
    <Head :title="$t('Classes')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2
                    class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
                >
                    {{ $t('Classes') }}
                </h2>
                <div class="flex items-center gap-2">
                    <InputLabel
                        for="year"
                        :value="$t('Academic year')"
                        class="sr-only"
                    />
                    <SelectInput id="year" v-model="yearId">
                        <option v-for="y in years" :key="y.id" :value="y.id">
                            {{ y.name }}{{ y.is_current ? ' (current)' : '' }}
                        </option>
                    </SelectInput>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section
                    v-if="yearId"
                    class="bg-white p-6 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    aria-labelledby="add-class-title"
                >
                    <h3
                        id="add-class-title"
                        class="text-lg font-medium text-gray-900 dark:text-gray-100"
                    >
                        {{ $t('Add a class') }}
                    </h3>
                    <form
                        class="mt-4 grid items-start gap-4 sm:grid-cols-[1fr_8rem_1fr_auto]"
                        @submit.prevent="add"
                    >
                        <div>
                            <InputLabel for="class-name" :value="$t('Name')" />
                            <TextInput
                                id="class-name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full"
                                :placeholder="$t('Grade 7')"
                                required
                            />
                            <InputError
                                class="mt-1"
                                :message="form.errors.name"
                            />
                        </div>
                        <div>
                            <InputLabel
                                for="class-section"
                                :value="$t('Section')"
                            />
                            <TextInput
                                id="class-section"
                                v-model="form.section"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="A"
                            />
                            <InputError
                                class="mt-1"
                                :message="form.errors.section"
                            />
                        </div>
                        <div>
                            <InputLabel
                                for="class-teacher"
                                :value="$t('Class teacher')"
                            />
                            <SelectInput
                                id="class-teacher"
                                v-model="form.class_teacher_id"
                                class="mt-1 block w-full"
                            >
                                <option :value="null">
                                    {{ $t('None yet') }}
                                </option>
                                <option
                                    v-for="t in teachers"
                                    :key="t.id"
                                    :value="t.id"
                                >
                                    {{ t.name }}
                                </option>
                            </SelectInput>
                            <InputError
                                class="mt-1"
                                :message="form.errors.class_teacher_id"
                            />
                        </div>
                        <PrimaryButton
                            class="sm:mt-6"
                            :disabled="form.processing"
                        >
                            {{ $t('Add') }}
                        </PrimaryButton>
                    </form>
                </section>

                <section
                    class="overflow-x-auto bg-white shadow-xs sm:rounded-lg dark:bg-gray-800"
                >
                    <table
                        class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700"
                    >
                        <caption class="sr-only">
                            {{
                                $t('Classes')
                            }}
                        </caption>
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr
                                class="text-left font-medium text-gray-600 dark:text-gray-300"
                            >
                                <th scope="col" class="px-4 py-3">
                                    {{ $t('Class') }}
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    {{ $t('Class teacher') }}
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    {{ $t('Students') }}
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    {{ $t('Subjects with a teacher') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-200 text-gray-900 dark:divide-gray-700 dark:text-gray-100"
                        >
                            <tr v-for="c in classes" :key="c.id">
                                <td class="px-4 py-3 font-medium">
                                    <Link
                                        :href="route('classes.show', c.id)"
                                        class="hover:underline focus:underline focus:outline-hidden"
                                    >
                                        {{ c.display_name }}
                                    </Link>
                                </td>
                                <td class="px-4 py-3">
                                    {{ c.class_teacher?.name ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ c.students_count }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ c.subjects_count }}
                                </td>
                            </tr>
                            <tr v-if="classes.length === 0">
                                <td
                                    colspan="4"
                                    class="px-4 py-10 text-center text-gray-600 dark:text-gray-400"
                                >
                                    {{
                                        $t(
                                            'No classes in this academic year yet.',
                                        )
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
