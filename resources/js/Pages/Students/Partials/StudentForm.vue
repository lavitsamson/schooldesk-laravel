<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- the parent's Inertia form is filled in here on purpose */
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import type { Option } from '@/types';
import type { IdName, StudentFormData } from '@/types/school';
import type { InertiaForm } from '@inertiajs/vue3';

defineProps<{
    form: InertiaForm<StudentFormData>;
    genders: Option[];
    classes?: IdName[];
    statuses?: Option[];
    photoUrl?: string | null;
    submitLabel: string;
}>();

defineEmits<{ submit: [] }>();

const today = new Date().toISOString().slice(0, 10);
</script>

<template>
    <form class="space-y-6" @submit.prevent="$emit('submit')">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <InputLabel for="first_name" :value="$t('First name')" />
                <TextInput
                    id="first_name"
                    v-model="form.first_name"
                    type="text"
                    class="mt-1 block w-full"
                    autocomplete="off"
                    required
                />
                <InputError class="mt-1" :message="form.errors.first_name" />
            </div>
            <div>
                <InputLabel for="last_name" :value="$t('Last name')" />
                <TextInput
                    id="last_name"
                    v-model="form.last_name"
                    type="text"
                    class="mt-1 block w-full"
                    autocomplete="off"
                    required
                />
                <InputError class="mt-1" :message="form.errors.last_name" />
            </div>
            <div>
                <InputLabel
                    for="admission_no"
                    :value="$t('Admission number')"
                />
                <TextInput
                    id="admission_no"
                    v-model="form.admission_no"
                    type="text"
                    class="mt-1 block w-full font-mono"
                    required
                />
                <InputError class="mt-1" :message="form.errors.admission_no" />
            </div>
            <div>
                <InputLabel for="date_of_birth" :value="$t('Date of birth')" />
                <TextInput
                    id="date_of_birth"
                    v-model="form.date_of_birth"
                    type="date"
                    :max="today"
                    class="mt-1 block w-full"
                    required
                />
                <InputError class="mt-1" :message="form.errors.date_of_birth" />
            </div>
            <div>
                <InputLabel for="gender" :value="$t('Gender (optional)')" />
                <SelectInput
                    id="gender"
                    v-model="form.gender"
                    class="mt-1 block w-full"
                >
                    <option :value="null">{{ $t('Not specified') }}</option>
                    <option
                        v-for="g in genders"
                        :key="g.value"
                        :value="g.value"
                    >
                        {{ g.label }}
                    </option>
                </SelectInput>
                <InputError class="mt-1" :message="form.errors.gender" />
            </div>
            <div v-if="classes">
                <InputLabel
                    for="class_id"
                    :value="$t('Class (current year)')"
                />
                <SelectInput
                    id="class_id"
                    v-model="form.class_id"
                    class="mt-1 block w-full"
                >
                    <option :value="null">{{ $t('Not enrolled yet') }}</option>
                    <option v-for="c in classes" :key="c.id" :value="c.id">
                        {{ c.name }}
                    </option>
                </SelectInput>
                <InputError class="mt-1" :message="form.errors.class_id" />
            </div>
            <div v-if="statuses">
                <InputLabel for="status" :value="$t('Status')" />
                <SelectInput
                    id="status"
                    v-model="form.status"
                    class="mt-1 block w-full"
                >
                    <option
                        v-for="s in statuses"
                        :key="s.value"
                        :value="s.value"
                    >
                        {{ s.label }}
                    </option>
                </SelectInput>
                <InputError class="mt-1" :message="form.errors.status" />
            </div>
        </div>

        <div>
            <InputLabel for="address" :value="$t('Address (optional)')" />
            <textarea
                id="address"
                v-model="form.address"
                rows="3"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
            />
            <InputError class="mt-1" :message="form.errors.address" />
        </div>

        <div class="flex flex-wrap items-start gap-4">
            <img
                v-if="photoUrl && !form.remove_photo"
                :src="photoUrl"
                :alt="$t('Current photo')"
                class="h-20 w-20 rounded-md object-cover"
            />
            <div>
                <InputLabel
                    for="photo"
                    :value="$t('Photo (optional, max 2 MB)')"
                />
                <input
                    id="photo"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="mt-1 block text-sm text-gray-700 file:me-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-medium dark:text-gray-300 dark:file:bg-gray-700 dark:file:text-gray-200"
                    @input="
                        form.photo =
                            ($event.target as HTMLInputElement).files?.[0] ??
                            null
                    "
                />
                <InputError class="mt-1" :message="form.errors.photo" />
                <label
                    v-if="photoUrl"
                    class="mt-2 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                >
                    <Checkbox
                        :checked="form.remove_photo"
                        @update:checked="
                            (checked: boolean) => (form.remove_photo = checked)
                        "
                    />
                    {{ $t('Remove photo') }}
                </label>
            </div>
        </div>

        <div class="flex justify-end">
            <PrimaryButton :disabled="form.processing">
                {{ submitLabel }}
            </PrimaryButton>
        </div>
    </form>
</template>
