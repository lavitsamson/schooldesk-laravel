<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { errorFor } from '@/composables/formErrors';
import SetupLayout from '@/Layouts/SetupLayout.vue';
import type { SetupStepProps, SubjectInput } from '@/types/setup';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<
    SetupStepProps & {
        subjects: SubjectInput[];
    }
>();

const form = useForm({
    subjects: props.subjects.length
        ? props.subjects.map((s) => ({ ...s }))
        : [{ name: '', code: '' }],
});

const addSubject = () => form.subjects.push({ name: '', code: '' });
const removeSubject = (index: number) => form.subjects.splice(index, 1);

const submit = () => form.put(route('setup.subjects'));
</script>

<template>
    <SetupLayout :steps="steps" :current="current">
        <form class="space-y-4" @submit.prevent="submit">
            <InputError :message="form.errors.subjects" />

            <div
                v-for="(subject, index) in form.subjects"
                :key="index"
                class="grid items-start gap-3 sm:grid-cols-[2fr_1fr_auto]"
            >
                <div>
                    <InputLabel
                        :for="`subject-${index}-name`"
                        :value="
                            $t('Subject :number', { number: String(index + 1) })
                        "
                    />
                    <TextInput
                        :id="`subject-${index}-name`"
                        v-model="subject.name"
                        type="text"
                        class="mt-1 block w-full"
                        :placeholder="$t('Mathematics')"
                        required
                    />
                    <InputError
                        class="mt-1"
                        :message="
                            errorFor(form.errors, `subjects.${index}.name`)
                        "
                    />
                </div>
                <div>
                    <InputLabel
                        :for="`subject-${index}-code`"
                        :value="$t('Code')"
                    />
                    <TextInput
                        :id="`subject-${index}-code`"
                        v-model="subject.code"
                        type="text"
                        class="mt-1 block w-full uppercase"
                        :placeholder="$t('MATH')"
                        required
                    />
                    <InputError
                        class="mt-1"
                        :message="
                            errorFor(form.errors, `subjects.${index}.code`)
                        "
                    />
                </div>
                <SecondaryButton
                    type="button"
                    class="sm:mt-6"
                    :disabled="form.subjects.length === 1"
                    :aria-label="
                        $t('Remove subject :number', {
                            number: String(index + 1),
                        })
                    "
                    @click="removeSubject(index)"
                >
                    {{ $t('Remove') }}
                </SecondaryButton>
            </div>

            <SecondaryButton type="button" @click="addSubject">
                {{ $t('Add subject') }}
            </SecondaryButton>

            <div class="flex justify-end">
                <PrimaryButton :disabled="form.processing">
                    {{ $t('Save and continue') }}
                </PrimaryButton>
            </div>
        </form>
    </SetupLayout>
</template>
