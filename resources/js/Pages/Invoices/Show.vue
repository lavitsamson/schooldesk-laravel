<script setup lang="ts">
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InvoiceStatusBadge from '@/Components/InvoiceStatusBadge.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatDate, formatDateTime } from '@/composables/dates';
import { useMoney } from '@/composables/money';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { Option } from '@/types';
import type { IdName, InvoiceStatus } from '@/types/school';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Payment {
    id: number;
    amount: number;
    refunded_amount: number;
    method: string;
    method_label: string;
    reference: string | null;
    recorded_by: string | null;
    paid_at: string;
    receipt_url: string;
}

const props = defineProps<{
    invoice: {
        id: number;
        number: string;
        status: InvoiceStatus;
        status_label: string;
        student: IdName;
        class: string | null;
        term: IdName;
        total: number;
        paid: number;
        balance: number;
        balance_input: string;
        due_on: string;
        issued_at: string;
        voided_at: string | null;
        overdue: boolean;
        reminders_sent: number;
        pdf_url: string;
        items: { id: number; description: string; amount: number }[];
        payments: Payment[];
    };
    can: {
        manage: boolean;
        recordPayment: boolean;
        void: boolean;
        pay: boolean;
    };
    methods: Option[];
    today: string;
    checkout: 'success' | 'cancelled' | null;
}>();

const money = useMoney();
const errors = computed(() => usePage().props.errors);

// Prefilled with the open balance, as a plain decimal from the server ("150.00").
const paymentForm = useForm({
    amount: props.invoice.balance_input,
    method: props.methods[0]?.value ?? 'cash',
    paid_on: props.today,
    reference: '',
});
const recordPayment = () =>
    paymentForm.post(route('invoices.payments.store', props.invoice.id), {
        preserveScroll: true,
        onSuccess: () => {
            paymentForm.defaults({
                amount: props.invoice.balance_input,
                method: paymentForm.method,
                paid_on: props.today,
                reference: '',
            });
            paymentForm.reset();
        },
    });

const confirmingVoid = ref(false);
const voidInvoice = () =>
    router.post(
        route('invoices.void', props.invoice.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => (confirmingVoid.value = false),
        },
    );

const paying = ref(false);
const pay = () =>
    router.post(
        route('invoices.checkout', props.invoice.id),
        {},
        {
            onStart: () => (paying.value = true),
            onFinish: () => (paying.value = false),
        },
    );

const backUrl = computed(() =>
    props.can.manage
        ? route('invoices.index', { term: props.invoice.term.id })
        : route('family.invoices'),
);
</script>

<template>
    <Head :title="`Invoice ${invoice.number}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2
                        class="flex flex-wrap items-center gap-3 text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200"
                    >
                        Invoice {{ invoice.number }}
                        <InvoiceStatusBadge
                            :status="invoice.status"
                            :label="invoice.status_label"
                            :overdue="invoice.overdue"
                        />
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {{ invoice.student.name }}
                        <template v-if="invoice.class">
                            · {{ invoice.class }}</template
                        >
                        · {{ invoice.term.name }}
                    </p>
                </div>
                <Link
                    :href="backUrl"
                    class="text-sm text-indigo-700 hover:underline dark:text-indigo-300"
                >
                    Back to invoices
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                <p
                    v-if="checkout === 'success'"
                    role="status"
                    class="rounded-md bg-green-50 p-4 text-sm text-green-800 dark:bg-green-900/40 dark:text-green-200"
                >
                    Thank you. Your payment is being confirmed by Stripe and
                    will show here in a moment; the receipt follows by email.
                </p>
                <p
                    v-else-if="checkout === 'cancelled'"
                    role="status"
                    class="rounded-md bg-amber-50 p-4 text-sm text-amber-800 dark:bg-amber-900/40 dark:text-amber-200"
                >
                    The payment was cancelled. Nothing was charged.
                </p>

                <section
                    class="grid gap-6 bg-white p-6 shadow-xs sm:grid-cols-[1fr_auto] sm:rounded-lg dark:bg-gray-800"
                    aria-labelledby="summary-title"
                >
                    <div>
                        <h3 id="summary-title" class="sr-only">Summary</h3>
                        <dl
                            class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-4"
                        >
                            <div>
                                <dt class="text-gray-600 dark:text-gray-400">
                                    Total
                                </dt>
                                <dd
                                    class="text-lg font-semibold text-gray-900 tabular-nums dark:text-gray-100"
                                >
                                    {{ money(invoice.total) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-600 dark:text-gray-400">
                                    Paid
                                </dt>
                                <dd
                                    class="text-lg font-semibold text-gray-900 tabular-nums dark:text-gray-100"
                                >
                                    {{ money(invoice.paid) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-600 dark:text-gray-400">
                                    {{
                                        invoice.balance < 0
                                            ? 'Credit'
                                            : 'Balance due'
                                    }}
                                </dt>
                                <dd
                                    class="text-lg font-semibold tabular-nums"
                                    :class="
                                        invoice.overdue
                                            ? 'text-red-700 dark:text-red-300'
                                            : 'text-gray-900 dark:text-gray-100'
                                    "
                                    data-testid="balance"
                                >
                                    {{ money(Math.abs(invoice.balance)) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-600 dark:text-gray-400">
                                    Due
                                </dt>
                                <dd class="text-gray-900 dark:text-gray-100">
                                    {{ formatDate(invoice.due_on) }}
                                </dd>
                            </div>
                        </dl>
                        <p
                            class="mt-4 text-sm text-gray-600 dark:text-gray-400"
                        >
                            Issued {{ formatDateTime(invoice.issued_at) }}
                            <template v-if="invoice.voided_at">
                                · voided
                                {{
                                    formatDateTime(invoice.voided_at)
                                }}</template
                            >
                            <template
                                v-if="can.manage && invoice.reminders_sent"
                            >
                                · {{ invoice.reminders_sent }}
                                {{
                                    invoice.reminders_sent === 1
                                        ? 'reminder'
                                        : 'reminders'
                                }}
                                sent</template
                            >
                        </p>
                    </div>
                    <div class="flex flex-col items-stretch gap-3 sm:items-end">
                        <a
                            :href="invoice.pdf_url"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold tracking-widest text-gray-700 uppercase shadow-xs hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden dark:border-gray-500 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                        >
                            Download PDF
                        </a>
                        <PrimaryButton
                            v-if="can.pay"
                            type="button"
                            class="justify-center"
                            :disabled="paying"
                            @click="pay"
                        >
                            Pay {{ money(invoice.balance) }} online
                        </PrimaryButton>
                        <DangerButton
                            v-if="can.void"
                            type="button"
                            class="justify-center"
                            @click="confirmingVoid = true"
                        >
                            Void invoice
                        </DangerButton>
                    </div>
                    <InputError
                        class="sm:col-span-2"
                        :message="errors.payment ?? errors.status"
                    />
                </section>

                <section
                    class="overflow-x-auto bg-white shadow-xs sm:rounded-lg dark:bg-gray-800"
                >
                    <table
                        class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700"
                    >
                        <caption
                            class="px-4 pt-4 text-left font-medium text-gray-900 dark:text-gray-100"
                        >
                            Fees
                        </caption>
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr
                                class="text-left font-medium text-gray-600 dark:text-gray-300"
                            >
                                <th scope="col" class="px-4 py-3">
                                    Description
                                </th>
                                <th scope="col" class="px-4 py-3 text-right">
                                    Amount
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-200 text-gray-900 dark:divide-gray-700 dark:text-gray-100"
                        >
                            <tr v-for="item in invoice.items" :key="item.id">
                                <td class="px-4 py-3">
                                    {{ item.description }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    {{ money(item.amount) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr
                                class="border-t border-gray-300 font-semibold text-gray-900 dark:border-gray-600 dark:text-gray-100"
                            >
                                <td class="px-4 py-3">Total</td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    {{ money(invoice.total) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </section>

                <section
                    class="overflow-x-auto bg-white shadow-xs sm:rounded-lg dark:bg-gray-800"
                >
                    <table
                        class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700"
                    >
                        <caption
                            class="px-4 pt-4 text-left font-medium text-gray-900 dark:text-gray-100"
                        >
                            Payments
                        </caption>
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr
                                class="text-left font-medium text-gray-600 dark:text-gray-300"
                            >
                                <th scope="col" class="px-4 py-3">Date</th>
                                <th scope="col" class="px-4 py-3">Method</th>
                                <th scope="col" class="px-4 py-3 text-right">
                                    Amount
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    <span class="sr-only">Receipt</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-200 text-gray-900 dark:divide-gray-700 dark:text-gray-100"
                        >
                            <tr
                                v-for="payment in invoice.payments"
                                :key="payment.id"
                            >
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ formatDateTime(payment.paid_at) }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ payment.method_label }}
                                    <span
                                        v-if="payment.reference"
                                        class="text-gray-600 dark:text-gray-400"
                                    >
                                        · {{ payment.reference }}</span
                                    >
                                    <span
                                        v-if="payment.recorded_by"
                                        class="block text-xs text-gray-600 dark:text-gray-400"
                                    >
                                        Recorded by {{ payment.recorded_by }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    {{ money(payment.amount) }}
                                    <span
                                        v-if="payment.refunded_amount > 0"
                                        class="block text-xs text-gray-600 dark:text-gray-400"
                                    >
                                        {{ money(payment.refunded_amount) }}
                                        refunded
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a
                                        :href="payment.receipt_url"
                                        class="text-indigo-700 hover:underline dark:text-indigo-300"
                                    >
                                        Receipt
                                    </a>
                                </td>
                            </tr>
                            <tr v-if="invoice.payments.length === 0">
                                <td
                                    colspan="4"
                                    class="px-4 py-6 text-center text-gray-600 dark:text-gray-400"
                                >
                                    No payments yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section
                    v-if="can.recordPayment"
                    class="bg-white p-6 shadow-xs sm:rounded-lg dark:bg-gray-800"
                    aria-labelledby="record-title"
                >
                    <h3
                        id="record-title"
                        class="text-lg font-medium text-gray-900 dark:text-gray-100"
                    >
                        Record a payment
                    </h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Cash at the office or a bank transfer. Card payments are
                        recorded automatically by Stripe.
                    </p>
                    <form
                        class="mt-4 grid items-start gap-4 sm:grid-cols-2 lg:grid-cols-4"
                        @submit.prevent="recordPayment"
                    >
                        <div>
                            <InputLabel for="payment-amount" value="Amount" />
                            <TextInput
                                id="payment-amount"
                                v-model="paymentForm.amount"
                                type="text"
                                inputmode="decimal"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError
                                class="mt-1"
                                :message="paymentForm.errors.amount"
                            />
                        </div>
                        <div>
                            <InputLabel for="payment-method" value="Method" />
                            <SelectInput
                                id="payment-method"
                                v-model="paymentForm.method"
                                class="mt-1 block w-full"
                            >
                                <option
                                    v-for="m in methods"
                                    :key="m.value"
                                    :value="m.value"
                                >
                                    {{ m.label }}
                                </option>
                            </SelectInput>
                            <InputError
                                class="mt-1"
                                :message="paymentForm.errors.method"
                            />
                        </div>
                        <div>
                            <InputLabel for="payment-date" value="Paid on" />
                            <TextInput
                                id="payment-date"
                                v-model="paymentForm.paid_on"
                                type="date"
                                :max="today"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError
                                class="mt-1"
                                :message="paymentForm.errors.paid_on"
                            />
                        </div>
                        <div>
                            <InputLabel
                                for="payment-reference"
                                value="Reference (optional)"
                            />
                            <TextInput
                                id="payment-reference"
                                v-model="paymentForm.reference"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError
                                class="mt-1"
                                :message="paymentForm.errors.reference"
                            />
                        </div>
                        <div class="sm:col-span-2 lg:col-span-4">
                            <PrimaryButton :disabled="paymentForm.processing">
                                Record payment
                            </PrimaryButton>
                        </div>
                    </form>
                </section>
            </div>
        </div>

        <Modal
            :show="confirmingVoid"
            max-width="md"
            @close="confirmingVoid = false"
        >
            <div class="p-6">
                <h2
                    class="text-lg font-medium text-gray-900 dark:text-gray-100"
                >
                    Void invoice {{ invoice.number }}?
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ invoice.student.name }} will not have to pay it, and no
                    new invoice is generated for the {{ invoice.term.name }}.
                    This cannot be undone.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton
                        type="button"
                        @click="confirmingVoid = false"
                    >
                        Cancel
                    </SecondaryButton>
                    <DangerButton type="button" @click="voidInvoice">
                        Void invoice
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
