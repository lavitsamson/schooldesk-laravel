<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { Option } from '@/types';
import type { StudentFormData, StudentProfile } from '@/types/school';
import { Head, Link, useForm } from '@inertiajs/vue3';
import StudentForm from './Partials/StudentForm.vue';

const props = defineProps<{
    student: StudentProfile;
    genders: Option[];
    statuses: Option[];
}>();

const form = useForm<StudentFormData>({
    admission_no: props.student.admission_no,
    first_name: props.student.first_name,
    last_name: props.student.last_name,
    date_of_birth: props.student.date_of_birth,
    gender: props.student.gender,
    address: props.student.address ?? '',
    photo: null,
    status: props.student.status,
    class_id: null,
    remove_photo: false,
});

// Files need multipart, which PHP only parses for POST: spoof the PUT.
const submit = () =>
    form
        .transform((data) => ({ ...data, _method: 'put' }))
        .post(route('students.update', props.student.id), {
            forceFormData: true,
        });
</script>

<template>
    <Head :title="$t('Edit :name', { name: student.name })" />

    <AuthenticatedLayout>
        <template #header>
            <nav :aria-label="$t('Breadcrumb')" class="text-sm">
                <Link
                    :href="route('students.index')"
                    class="text-gray-600 hover:underline dark:text-gray-400"
                >
                    {{ $t('Students') }}
                </Link>
                <span class="text-gray-400" aria-hidden="true"> / </span>
                <Link
                    :href="route('students.show', student.id)"
                    class="text-gray-600 hover:underline dark:text-gray-400"
                >
                    {{ student.name }}
                </Link>
            </nav>
            <h2
                class="mt-1 text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
            >
                Edit {{ student.name }}
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <div
                    class="bg-white p-6 shadow-xs sm:rounded-lg dark:bg-gray-800"
                >
                    <StudentForm
                        :form="form"
                        :genders="genders"
                        :statuses="statuses"
                        :photo-url="student.photo_url"
                        :submit-label="$t('Save')"
                        @submit="submit"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
