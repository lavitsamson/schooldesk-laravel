<script setup lang="ts">
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { useMoney } from '@/composables/money';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { Option } from '@/types';
import type { IdName } from '@/types/school';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface FeeStructure {
    id: number;
    name: string;
    class_id: number | null;
    class: string | null;
    amount: number;
    amount_input: string;
    frequency: string;
    frequency_label: string;
    due_day_offset: number;
}

const props = defineProps<{
    year: IdName;
    feeStructures: FeeStructure[];
    classes: IdName[];
    frequencies: Option[];
}>();

const money = useMoney();

const blank = () => ({
    academic_year_id: props.year.id,
    class_id: null as number | null,
    name: '',
    amount: '',
    frequency: 'term',
    due_day_offset: '14',
});

// One form for adding and editing: editing fills it with the fee's values.
const editingId = ref<number | null>(null);
const form = useForm(blank());

const edit = (fee: FeeStructure) => {
    editingId.value = fee.id;
    form.defaults({
        academic_year_id: props.year.id,
        class_id: fee.class_id,
        name: fee.name,
        amount: fee.amount_input,
        frequency: fee.frequency,
        due_day_offset: String(fee.due_day_offset),
    });
    form.reset();
    form.clearErrors();
    document.getElementById('fee-name')?.focus();
};

const cancel = () => {
    editingId.value = null;
    form.defaults(blank());
    form.reset();
    form.clearErrors();
};

const submit = () => {
    const options = { preserveScroll: true, onSuccess: cancel };

    if (editingId.value === null) {
        form.post(route('fee-structures.store'), options);
    } else {
        form.put(route('fee-structures.update', editingId.value), options);
    }
};

const deleting = ref<FeeStructure | null>(null);
const destroy = () => {
    if (!deleting.value) return;
    router.delete(route('fee-structures.destroy', deleting.value.id), {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
};
</script>

<template>
    <Head :title="$t('Fee structures')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2
                        class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
                    >
                        {{ $t('Fee structures') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {{ year.name }}
                    </p>
                </div>
                <Link
                    :href="route('fees.index')"
                    class="text-sm text-indigo-700 hover:underline dark:text-indigo-300"
                >
                    {{ $t('Back to fees') }}
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section
                    class="bg-white p-6 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    aria-labelledby="fee-form-title"
                >
                    <h3
                        id="fee-form-title"
                        class="text-lg font-medium text-gray-900 dark:text-gray-100"
                    >
                        {{
                            editingId === null
                                ? $t('Add a fee')
                                : $t('Edit fee')
                        }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Changes apply to invoices generated from now on; issued
                        {{ $t('invoices keep their amounts.') }}
                    </p>
                    <form
                        class="mt-4 grid items-start gap-4 sm:grid-cols-2 lg:grid-cols-3"
                        @submit.prevent="submit"
                    >
                        <div>
                            <InputLabel for="fee-name" :value="$t('Name')" />
                            <TextInput
                                id="fee-name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError
                                class="mt-1"
                                :message="form.errors.name"
                            />
                        </div>
                        <div>
                            <InputLabel
                                for="fee-amount"
                                :value="$t('Amount')"
                            />
                            <TextInput
                                id="fee-amount"
                                v-model="form.amount"
                                type="text"
                                inputmode="decimal"
                                placeholder="1200.00"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError
                                class="mt-1"
                                :message="form.errors.amount"
                            />
                        </div>
                        <div>
                            <InputLabel for="fee-class" :value="$t('Class')" />
                            <SelectInput
                                id="fee-class"
                                v-model="form.class_id"
                                class="mt-1 block w-full"
                            >
                                <option :value="null">
                                    {{ $t('Every class') }}
                                </option>
                                <option
                                    v-for="c in classes"
                                    :key="c.id"
                                    :value="c.id"
                                >
                                    {{ c.name }}
                                </option>
                            </SelectInput>
                            <InputError
                                class="mt-1"
                                :message="form.errors.class_id"
                            />
                        </div>
                        <div>
                            <InputLabel
                                for="fee-frequency"
                                :value="$t('Charged')"
                            />
                            <SelectInput
                                id="fee-frequency"
                                v-model="form.frequency"
                                class="mt-1 block w-full"
                            >
                                <option
                                    v-for="f in frequencies"
                                    :key="f.value"
                                    :value="f.value"
                                >
                                    {{ f.label }}
                                </option>
                            </SelectInput>
                            <InputError
                                class="mt-1"
                                :message="form.errors.frequency"
                            />
                        </div>
                        <div>
                            <InputLabel
                                for="fee-due"
                                :value="$t('Due (days after the term starts)')"
                            />
                            <TextInput
                                id="fee-due"
                                v-model="form.due_day_offset"
                                type="number"
                                min="0"
                                max="180"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError
                                class="mt-1"
                                :message="form.errors.due_day_offset"
                            />
                        </div>
                        <div class="flex gap-3 sm:mt-6">
                            <PrimaryButton :disabled="form.processing">
                                {{
                                    editingId === null ? $t('Add') : $t('Save')
                                }}
                            </PrimaryButton>
                            <SecondaryButton
                                v-if="editingId !== null"
                                type="button"
                                @click="cancel"
                            >
                                {{ $t('Cancel') }}
                            </SecondaryButton>
                        </div>
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
                                $t('Fees of the year')
                            }}
                        </caption>
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr
                                class="text-left font-medium text-gray-600 dark:text-gray-300"
                            >
                                <th scope="col" class="px-4 py-3">
                                    {{ $t('Fee') }}
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    {{ $t('Class') }}
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    {{ $t('Charged') }}
                                </th>
                                <th scope="col" class="px-4 py-3 text-right">
                                    {{ $t('Amount') }}
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    {{ $t('Due') }}
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    <span class="sr-only">{{
                                        $t('Actions')
                                    }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-200 text-gray-900 dark:divide-gray-700 dark:text-gray-100"
                        >
                            <tr v-for="fee in feeStructures" :key="fee.id">
                                <td class="px-4 py-3 font-medium">
                                    {{ fee.name }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ fee.class ?? $t('Every class') }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ fee.frequency_label }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    {{ money(fee.amount) }}
                                </td>
                                <td class="px-4 py-3">
                                    {{
                                        $t(
                                            ':due_day_offset days after the term',
                                            {
                                                due_day_offset:
                                                    fee.due_day_offset,
                                            },
                                        )
                                    }}
                                    {{ $t('starts') }}
                                </td>
                                <td
                                    class="space-x-2 px-4 py-3 text-right whitespace-nowrap"
                                >
                                    <SecondaryButton
                                        type="button"
                                        :aria-label="
                                            $t('Edit :name', { name: fee.name })
                                        "
                                        @click="edit(fee)"
                                    >
                                        {{ $t('Edit') }}
                                    </SecondaryButton>
                                    <DangerButton
                                        type="button"
                                        :aria-label="
                                            $t('Delete :name', {
                                                name: fee.name,
                                            })
                                        "
                                        @click="deleting = fee"
                                    >
                                        {{ $t('Delete') }}
                                    </DangerButton>
                                </td>
                            </tr>
                            <tr v-if="feeStructures.length === 0">
                                <td
                                    colspan="6"
                                    class="px-4 py-10 text-center text-gray-600 dark:text-gray-400"
                                >
                                    {{ $t('No fees for this year yet.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>
        </div>

        <Modal
            :show="deleting !== null"
            max-width="md"
            @close="deleting = null"
        >
            <div class="p-6">
                <h2
                    class="text-lg font-medium text-gray-900 dark:text-gray-100"
                >
                    {{ $t('Delete :name?', { name: deleting?.name }) }}
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{
                        $t(
                            'New invoices will no longer include it. Invoices already issued keep it.',
                        )
                    }}
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="deleting = null">
                        {{ $t('Cancel') }}
                    </SecondaryButton>
                    <DangerButton type="button" @click="destroy">
                        {{ $t('Delete') }}
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
