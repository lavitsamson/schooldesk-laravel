<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    attendanceNoteRetentionMonths: number | null;
    retentionOptions: number[];
    exportLinkHours: number;
    anonymisedStudents: number;
}>();

const form = useForm({
    attendance_note_retention_months: props.attendanceNoteRetentionMonths,
});

const save = () =>
    form.put(route('data-protection.update'), {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });
</script>

<template>
    <Head :title="$t('Data protection')" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
            >
                {{ $t('Data protection') }}
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section
                    class="bg-white p-6 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    aria-labelledby="retention-title"
                >
                    <h3
                        id="retention-title"
                        class="text-lg font-medium text-gray-900 dark:text-gray-100"
                    >
                        {{ $t('Attendance notes') }}
                    </h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        {{
                            $t(
                                'Notes on absences often give a reason, such as an illness. They are deleted every night once they are older than the period you choose. Attendance itself is kept: report cards need it.',
                            )
                        }}
                    </p>
                    <form
                        class="mt-4 flex flex-wrap items-end gap-3"
                        @submit.prevent="save"
                    >
                        <div>
                            <InputLabel
                                for="retention"
                                :value="$t('Delete notes after')"
                            />
                            <SelectInput
                                id="retention"
                                v-model="form.attendance_note_retention_months"
                                class="mt-1 block"
                            >
                                <option :value="null">
                                    {{ $t('Keep them') }}
                                </option>
                                <option
                                    v-for="months in retentionOptions"
                                    :key="months"
                                    :value="months"
                                >
                                    {{
                                        $tChoice(
                                            ':count month|:count months',
                                            months,
                                            { count: String(months) },
                                        )
                                    }}
                                </option>
                            </SelectInput>
                        </div>
                        <PrimaryButton
                            :disabled="form.processing || !form.isDirty"
                        >
                            {{ $t('Save') }}
                        </PrimaryButton>
                    </form>
                    <InputError
                        class="mt-2"
                        :message="form.errors.attendance_note_retention_months"
                    />
                </section>

                <section
                    class="bg-white p-6 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    aria-labelledby="requests-title"
                >
                    <h3
                        id="requests-title"
                        class="text-lg font-medium text-gray-900 dark:text-gray-100"
                    >
                        {{ $t('Requests about a student') }}
                    </h3>
                    <ul
                        class="mt-2 list-disc space-y-2 ps-5 text-sm text-gray-600 dark:text-gray-400"
                    >
                        <li>
                            {{
                                $t(
                                    'Access: "Export data" on a student\'s page collects everything held about them (details, guardians, attendance, marks, report cards, invoices and history) into a ZIP. You get an email with a download link that works for :hours hours.',
                                    { hours: String(exportLinkHours) },
                                )
                            }}
                        </li>
                        <li>
                            {{
                                $t(
                                    'Erasure: "Erase personal data" on a student\'s page anonymises them. Marks, attendance, invoices and payments are kept without anything that identifies the student. This cannot be undone.',
                                )
                            }}
                        </li>
                    </ul>
                    <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                        {{
                            $tChoice(
                                ':count student anonymised so far.|:count students anonymised so far.',
                                anonymisedStudents,
                                { count: String(anonymisedStudents) },
                            )
                        }}
                    </p>
                    <div class="mt-4 flex flex-wrap gap-4 text-sm">
                        <Link
                            :href="route('students.index')"
                            class="text-indigo-700 hover:underline dark:text-indigo-300"
                        >
                            {{ $t('Students') }}
                        </Link>
                        <Link
                            :href="route('audit-log.index', { area: 'gdpr' })"
                            class="text-indigo-700 hover:underline dark:text-indigo-300"
                        >
                            {{ $t('Data protection history') }}
                        </Link>
                        <Link
                            :href="route('privacy')"
                            class="text-indigo-700 hover:underline dark:text-indigo-300"
                        >
                            {{ $t('Privacy notice template') }}
                        </Link>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
