<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { errorFor } from '@/composables/formErrors';
import SetupLayout from '@/Layouts/SetupLayout.vue';
import type { ClassInput, SetupStepProps } from '@/types/setup';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps<
    SetupStepProps & {
        hasAcademicYear: boolean;
        classes: ClassInput[];
    }
>();

const form = useForm({
    classes: props.classes.length
        ? props.classes.map((c) => ({ name: c.name, section: c.section ?? '' }))
        : [{ name: '', section: '' }],
});

const addClass = () => form.classes.push({ name: '', section: '' });
const removeClass = (index: number) => form.classes.splice(index, 1);

const submit = () => form.put(route('setup.classes'));
</script>

<template>
    <SetupLayout :steps="steps" :current="current">
        <p
            v-if="!hasAcademicYear"
            class="rounded-md bg-amber-50 p-4 text-sm text-amber-800 dark:bg-amber-900/40 dark:text-amber-200"
        >
            {{ $t('Classes belong to an academic year.') }}
            <Link
                :href="route('setup.show', 'academic-year')"
                class="font-medium underline"
            >
                {{ $t('Add the academic year first.') }}
            </Link>
        </p>

        <form v-else class="space-y-4" @submit.prevent="submit">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{
                    $t(
                        'For example "Grade 7" with section "A". Leave the section empty if a grade has only one class.',
                    )
                }}
            </p>
            <InputError :message="form.errors.classes" />

            <div
                v-for="(schoolClass, index) in form.classes"
                :key="index"
                class="grid items-start gap-3 sm:grid-cols-[2fr_1fr_auto]"
            >
                <div>
                    <InputLabel
                        :for="`class-${index}-name`"
                        :value="
                            $t('Class :number', { number: String(index + 1) })
                        "
                    />
                    <TextInput
                        :id="`class-${index}-name`"
                        v-model="schoolClass.name"
                        type="text"
                        class="mt-1 block w-full"
                        :placeholder="$t('Grade 7')"
                        required
                    />
                    <InputError
                        class="mt-1"
                        :message="
                            errorFor(form.errors, `classes.${index}.name`)
                        "
                    />
                </div>
                <div>
                    <InputLabel
                        :for="`class-${index}-section`"
                        :value="$t('Section')"
                    />
                    <TextInput
                        :id="`class-${index}-section`"
                        v-model="schoolClass.section"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="A"
                    />
                    <InputError
                        class="mt-1"
                        :message="
                            errorFor(form.errors, `classes.${index}.section`)
                        "
                    />
                </div>
                <SecondaryButton
                    type="button"
                    class="sm:mt-6"
                    :disabled="form.classes.length === 1"
                    :aria-label="
                        $t('Remove class :number', {
                            number: String(index + 1),
                        })
                    "
                    @click="removeClass(index)"
                >
                    {{ $t('Remove') }}
                </SecondaryButton>
            </div>

            <SecondaryButton type="button" @click="addClass">
                {{ $t('Add class') }}
            </SecondaryButton>

            <div class="flex justify-end">
                <PrimaryButton :disabled="form.processing">
                    {{ $t('Save and continue') }}
                </PrimaryButton>
            </div>
        </form>
    </SetupLayout>
</template>
