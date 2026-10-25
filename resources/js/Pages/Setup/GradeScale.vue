<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SetupLayout from '@/Layouts/SetupLayout.vue';
import type { GradeBand, SetupStepProps } from '@/types/setup';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<
    SetupStepProps & {
        presets: { value: string; label: string; bands: GradeBand[] }[];
    }
>();

const form = useForm({ preset: props.presets[0]?.value ?? '' });

const submit = () => form.put(route('setup.grade-scale'));
</script>

<template>
    <SetupLayout :steps="steps" :current="current">
        <form class="space-y-4" @submit.prevent="submit">
            <fieldset>
                <legend class="text-sm text-gray-600 dark:text-gray-400">
                    {{
                        $t(
                            'Choose how grades are shown on report cards. You can adjust the bands later.',
                        )
                    }}
                </legend>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label
                        v-for="preset in presets"
                        :key="preset.value"
                        class="cursor-pointer rounded-lg border p-4 focus-within:ring-2 focus-within:ring-indigo-500"
                        :class="
                            form.preset === preset.value
                                ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-900/30'
                                : 'border-gray-300 dark:border-gray-600'
                        "
                    >
                        <span class="flex items-center gap-2">
                            <input
                                v-model="form.preset"
                                type="radio"
                                name="preset"
                                :value="preset.value"
                                class="text-indigo-600 focus:ring-indigo-500"
                            />
                            <span
                                class="font-medium text-gray-900 dark:text-gray-100"
                            >
                                {{ preset.label }}
                            </span>
                        </span>
                        <table class="mt-3 w-full text-sm">
                            <thead class="sr-only">
                                <tr>
                                    <th scope="col">{{ $t('Grade') }}</th>
                                    <th scope="col">
                                        {{ $t('Percentage range') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="band in preset.bands"
                                    :key="band.label"
                                    class="text-gray-700 dark:text-gray-300"
                                >
                                    <td class="py-0.5 font-semibold">
                                        {{ band.label }}
                                    </td>
                                    <td class="py-0.5 text-right tabular-nums">
                                        {{ band.min_percent }}–{{
                                            band.max_percent
                                        }}
                                        %
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </label>
                </div>
            </fieldset>

            <InputError :message="form.errors.preset" />

            <div class="flex justify-end">
                <PrimaryButton :disabled="form.processing">
                    {{ $t('Finish setup') }}
                </PrimaryButton>
            </div>
        </form>
    </SetupLayout>
</template>
