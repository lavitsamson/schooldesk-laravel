<script setup lang="ts">
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { errorFor } from '@/composables/formErrors';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { GradeBand } from '@/types/setup';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    bands: GradeBand[];
    presets: { value: string; label: string; bands: GradeBand[] }[];
}>();

const form = useForm({ bands: props.bands.map((b) => ({ ...b })) });

const usePreset = (bands: GradeBand[]) => {
    form.bands = bands.map((b) => ({ ...b }));
};

const addBand = () =>
    form.bands.push({ label: '', min_percent: '', max_percent: '' });

const removeBand = (index: number) => form.bands.splice(index, 1);

const save = () =>
    form.put(route('grade-scale.update'), {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });

const inputClasses =
    'block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300';
</script>

<template>
    <Head :title="$t('Grade scale')" />

    <AuthenticatedLayout>
        <template #header>
            <nav :aria-label="$t('Breadcrumb')" class="text-sm">
                <Link
                    :href="route('grades.index')"
                    class="text-gray-600 hover:underline dark:text-gray-400"
                >
                    {{ $t('Grades') }}
                </Link>
            </nav>
            <h2
                class="mt-1 text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
            >
                {{ $t('Grade scale') }}
            </h2>
        </template>

        <div class="py-8">
            <form
                class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 lg:px-8"
                @submit.prevent="save"
            >
                <section
                    class="space-y-3 bg-white p-4 text-sm text-gray-700 shadow-xs sm:rounded-lg dark:bg-gray-800 dark:text-gray-300"
                    :aria-label="$t('About the grade scale')"
                >
                    <p>
                        {{
                            $t(
                                'Each band turns a percentage into a grade. Bands include both ends and must cover 0 to 100 % without gaps: each band ends 0.01 below where the next one starts. Results follow the scale straight away.',
                            )
                        }}
                    </p>
                    <div class="flex flex-wrap items-center gap-2">
                        <span>{{ $t('Start from:') }}</span>
                        <SecondaryButton
                            v-for="preset in presets"
                            :key="preset.value"
                            type="button"
                            @click="usePreset(preset.bands)"
                        >
                            {{ preset.label }}
                        </SecondaryButton>
                    </div>
                </section>

                <InputError :message="form.errors.bands" />

                <section
                    class="overflow-x-auto bg-white shadow-xs sm:rounded-lg dark:bg-gray-800"
                >
                    <table
                        class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700"
                    >
                        <caption class="sr-only">
                            {{
                                $t('Grade bands')
                            }}
                        </caption>
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr
                                class="text-left font-medium text-gray-600 dark:text-gray-300"
                            >
                                <th scope="col" class="px-4 py-3">
                                    {{ $t('Grade') }}
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    {{ $t('From %') }}
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    {{ $t('To %') }}
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    <span class="sr-only">{{
                                        $t('Actions')
                                    }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-200 dark:divide-gray-700"
                        >
                            <tr
                                v-for="(band, index) in form.bands"
                                :key="index"
                            >
                                <td class="px-4 py-2">
                                    <input
                                        v-model="band.label"
                                        type="text"
                                        maxlength="20"
                                        :aria-label="
                                            $t('Grade :number', {
                                                number: String(index + 1),
                                            })
                                        "
                                        :class="inputClasses"
                                        required
                                    />
                                    <InputError
                                        class="mt-1"
                                        :message="
                                            errorFor(
                                                form.errors,
                                                `bands.${index}.label`,
                                            )
                                        "
                                    />
                                </td>
                                <td class="px-4 py-2">
                                    <input
                                        v-model="band.min_percent"
                                        type="text"
                                        inputmode="decimal"
                                        :aria-label="
                                            $t(
                                                'From percentage for grade :grade',
                                                {
                                                    grade: String(
                                                        band.label || index + 1,
                                                    ),
                                                },
                                            )
                                        "
                                        :class="[
                                            inputClasses,
                                            'text-right tabular-nums',
                                        ]"
                                        required
                                    />
                                    <InputError
                                        class="mt-1"
                                        :message="
                                            errorFor(
                                                form.errors,
                                                `bands.${index}.min_percent`,
                                            )
                                        "
                                    />
                                </td>
                                <td class="px-4 py-2">
                                    <input
                                        v-model="band.max_percent"
                                        type="text"
                                        inputmode="decimal"
                                        :aria-label="
                                            $t(
                                                'To percentage for grade :grade',
                                                {
                                                    grade: String(
                                                        band.label || index + 1,
                                                    ),
                                                },
                                            )
                                        "
                                        :class="[
                                            inputClasses,
                                            'text-right tabular-nums',
                                        ]"
                                        required
                                    />
                                    <InputError
                                        class="mt-1"
                                        :message="
                                            errorFor(
                                                form.errors,
                                                `bands.${index}.max_percent`,
                                            )
                                        "
                                    />
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <DangerButton
                                        type="button"
                                        :aria-label="
                                            $t('Remove grade :grade', {
                                                grade: String(
                                                    band.label || index + 1,
                                                ),
                                            })
                                        "
                                        :disabled="form.bands.length <= 2"
                                        @click="removeBand(index)"
                                    >
                                        {{ $t('Remove') }}
                                    </DangerButton>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <div class="flex items-center justify-between gap-3">
                    <SecondaryButton type="button" @click="addBand">
                        {{ $t('Add band') }}
                    </SecondaryButton>
                    <div class="flex items-center gap-3">
                        <p
                            v-if="form.isDirty"
                            class="text-sm text-gray-600 dark:text-gray-400"
                        >
                            {{ $t('Unsaved changes') }}
                        </p>
                        <PrimaryButton :disabled="form.processing">
                            {{ $t('Save grade scale') }}
                        </PrimaryButton>
                    </div>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
