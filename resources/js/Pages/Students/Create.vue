<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { Option } from '@/types';
import type { IdName, StudentFormData } from '@/types/school';
import { Head, Link, useForm } from '@inertiajs/vue3';
import StudentForm from './Partials/StudentForm.vue';

defineProps<{ classes: IdName[]; genders: Option[] }>();

const form = useForm<StudentFormData>({
    admission_no: '',
    first_name: '',
    last_name: '',
    date_of_birth: '',
    gender: null,
    address: '',
    photo: null,
    class_id: null,
    status: '',
    remove_photo: false,
});

const submit = () =>
    form.post(route('students.store'), { forceFormData: true });
</script>

<template>
    <Head title="Add student" />

    <AuthenticatedLayout>
        <template #header>
            <nav aria-label="Breadcrumb" class="text-sm">
                <Link
                    :href="route('students.index')"
                    class="text-gray-600 hover:underline dark:text-gray-400"
                >
                    Students
                </Link>
            </nav>
            <h2
                class="mt-1 text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
            >
                Add student
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
                        :classes="classes"
                        submit-label="Add student"
                        @submit="submit"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
