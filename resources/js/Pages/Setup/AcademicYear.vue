<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { errorFor } from '@/composables/formErrors';
import SetupLayout from '@/Layouts/SetupLayout.vue';
import type { SetupStepProps, TermInput } from '@/types/setup';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<
    SetupStepProps & {
        academicYear: {
            name: string;
            starts_on: string;
            ends_on: string;
            terms: TermInput[];
        } | null;
    }
>();

const year = new Date().getFullYear();

const form = useForm({
    name: props.academicYear?.name ?? `${year}/${String(year + 1).slice(2)}`,
    starts_on: props.academicYear?.starts_on ?? `${year}-08-01`,
    ends_on: props.academicYear?.ends_on ?? `${year + 1}-07-31`,
    terms: props.academicYear?.terms ?? [
        { name: 'Term 1', starts_on: '', ends_on: '' },
        { name: 'Term 2', starts_on: '', ends_on: '' },
        { name: 'Term 3', starts_on: '', ends_on: '' },
    ],
});

const addTerm = () =>
    form.terms.push({
        name: `Term ${form.terms.length + 1}`,
        starts_on: '',
        ends_on: '',
    });
const removeTerm = (index: number) => form.terms.splice(index, 1);

const submit = () => form.put(route('setup.academic-year'));
</script>

<template>
    <SetupLayout :steps="steps" :current="current">
        <form class="space-y-6" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <InputLabel for="name" value="Academic year" />
                    <TextInput
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="2026/27"
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>
                <div>
                    <InputLabel for="starts_on" value="Starts on" />
                    <TextInput
                        id="starts_on"
                        v-model="form.starts_on"
                        type="date"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.starts_on" />
                </div>
                <div>
                    <InputLabel for="ends_on" value="Ends on" />
                    <TextInput
                        id="ends_on"
                        v-model="form.ends_on"
                        type="date"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.ends_on" />
                </div>
            </div>

            <fieldset>
                <legend
                    class="text-sm font-medium text-gray-900 dark:text-gray-100"
                >
                    Terms
                </legend>
                <InputError class="mt-2" :message="form.errors.terms" />

                <div
                    v-for="(term, index) in form.terms"
                    :key="index"
                    class="mt-3 grid items-start gap-3 sm:grid-cols-[1fr_1fr_1fr_auto]"
                >
                    <div>
                        <InputLabel
                            :for="`term-${index}-name`"
                            :value="`Term ${index + 1} name`"
                        />
                        <TextInput
                            :id="`term-${index}-name`"
                            v-model="term.name"
                            type="text"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError
                            class="mt-1"
                            :message="
                                errorFor(form.errors, `terms.${index}.name`)
                            "
                        />
                    </div>
                    <div>
                        <InputLabel
                            :for="`term-${index}-starts`"
                            value="Starts on"
                        />
                        <TextInput
                            :id="`term-${index}-starts`"
                            v-model="term.starts_on"
                            type="date"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError
                            class="mt-1"
                            :message="
                                errorFor(
                                    form.errors,
                                    `terms.${index}.starts_on`,
                                )
                            "
                        />
                    </div>
                    <div>
                        <InputLabel
                            :for="`term-${index}-ends`"
                            value="Ends on"
                        />
                        <TextInput
                            :id="`term-${index}-ends`"
                            v-model="term.ends_on"
                            type="date"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError
                            class="mt-1"
                            :message="
                                errorFor(form.errors, `terms.${index}.ends_on`)
                            "
                        />
                    </div>
                    <SecondaryButton
                        type="button"
                        class="sm:mt-6"
                        :disabled="form.terms.length === 1"
                        :aria-label="`Remove term ${index + 1}`"
                        @click="removeTerm(index)"
                    >
                        Remove
                    </SecondaryButton>
                </div>

                <SecondaryButton
                    type="button"
                    class="mt-3"
                    :disabled="form.terms.length >= 6"
                    @click="addTerm"
                >
                    Add term
                </SecondaryButton>
            </fieldset>

            <div class="flex justify-end">
                <PrimaryButton :disabled="form.processing">
                    Save and continue
                </PrimaryButton>
            </div>
        </form>
    </SetupLayout>
</template>
