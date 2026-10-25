<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import SetupLayout from '@/Layouts/SetupLayout.vue';
import type { Option } from '@/types';
import type { SetupStepProps } from '@/types/setup';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<
    SetupStepProps & {
        school: {
            name: string;
            country: string;
            timezone: string;
            locale: string;
            currency: string;
        };
        locales: Option[];
        currencies: string[];
        timezones: string[];
    }
>();

const countries: Option[] = [
    { value: 'AT', label: 'Austria' },
    { value: 'BE', label: 'Belgium' },
    { value: 'FR', label: 'France' },
    { value: 'DE', label: 'Germany' },
    { value: 'IN', label: 'India' },
    { value: 'IE', label: 'Ireland' },
    { value: 'LU', label: 'Luxembourg' },
    { value: 'NL', label: 'Netherlands' },
    { value: 'CH', label: 'Switzerland' },
    { value: 'GB', label: 'United Kingdom' },
    { value: 'US', label: 'United States' },
];

const form = useForm({ ...props.school });

const submit = () => form.put(route('setup.school'));
</script>

<template>
    <SetupLayout :steps="steps" :current="current">
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <InputLabel for="name" :value="$t('School name')" />
                <TextInput
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full"
                    required
                    autofocus
                />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="country" :value="$t('Country')" />
                    <SelectInput
                        id="country"
                        v-model="form.country"
                        class="mt-1 block w-full"
                    >
                        <option
                            v-for="country in countries"
                            :key="country.value"
                            :value="country.value"
                        >
                            {{ country.label }}
                        </option>
                    </SelectInput>
                    <InputError class="mt-2" :message="form.errors.country" />
                </div>

                <div>
                    <InputLabel for="timezone" :value="$t('Time zone')" />
                    <SelectInput
                        id="timezone"
                        v-model="form.timezone"
                        class="mt-1 block w-full"
                    >
                        <option v-for="tz in timezones" :key="tz" :value="tz">
                            {{ tz }}
                        </option>
                    </SelectInput>
                    <InputError class="mt-2" :message="form.errors.timezone" />
                </div>

                <div>
                    <InputLabel for="locale" :value="$t('Language')" />
                    <SelectInput
                        id="locale"
                        v-model="form.locale"
                        class="mt-1 block w-full"
                    >
                        <option
                            v-for="locale in locales"
                            :key="locale.value"
                            :value="locale.value"
                        >
                            {{ locale.label }}
                        </option>
                    </SelectInput>
                    <InputError class="mt-2" :message="form.errors.locale" />
                </div>

                <div>
                    <InputLabel for="currency" :value="$t('Currency')" />
                    <SelectInput
                        id="currency"
                        v-model="form.currency"
                        class="mt-1 block w-full"
                    >
                        <option
                            v-for="currency in currencies"
                            :key="currency"
                            :value="currency"
                        >
                            {{ currency }}
                        </option>
                    </SelectInput>
                    <InputError class="mt-2" :message="form.errors.currency" />
                </div>
            </div>

            <div class="flex justify-end">
                <PrimaryButton :disabled="form.processing">
                    {{ $t('Save and continue') }}
                </PrimaryButton>
            </div>
        </form>
    </SetupLayout>
</template>
