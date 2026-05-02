<template>
    <VModal
        :show-modal="!!open"
        @close-click="closeModal"
        size="xl"
        title="Record Receipt">
        <template #modal-body>
            <VLoader v-if="loading" loader-type="progress"/>
            <div v-else class="card border-0 shadow-none">
                <div class="card-body border-0 p-0">
                    <form @submit.prevent="storeReceipt('draft')" class="row g-3">
                        <div class="col-12">
                            <p class="text-muted small mb-0">
                                Choose the customer first to load outstanding invoices, then enter how you received payment and allocate amounts.
                            </p>
                        </div>

                        <div class="col-lg-6 col-sm-6 col-12">
                            <VDatepicker
                                id="receipt_date"
                                v-model="form.receipt_date"
                                input-type="date"
                                label="Receipt Date"
                                @validate="validateField('receipt_date')"
                                :error="errors.receipt_date"
                            />
                        </div>
                        <div class="col-lg-6 col-sm-6 col-12">
                            <VInput
                                id="reference_no"
                                v-model="form.reference_no"
                                label="Reference No"
                                placeholder="Cheque no., transfer ref., etc."
                                @validate="validateField('reference_no')"
                                :error="errors.reference_no"
                            />
                        </div>

                        <div class="col-12">
                            <VMultiselect
                                id="party_id"
                                v-model="form.party_id"
                                :options="parties.data"
                                label="Customer"
                                required
                                :filter-results="false"
                                @validate="validateField('party_id')"
                                @search-change="debouncedPartySearch"
                                :error="errors.party_id"
                            />
                            <PartyMetaPanel
                                v-if="resolvedParty"
                                :party="resolvedParty"
                                pan-heading="Customer PAN"
                            />
                        </div>

                        <div class="col-lg-6 col-sm-6 col-12">
                            <VMultiselect
                                id="payment_mode_id"
                                v-model="form.payment_mode_id"
                                :options="activePaymentModes"
                                label="Payment method"
                                required
                                @validate="validateField('payment_mode_id')"
                                :error="errors.payment_mode_id"
                            />
                            <span class="form-text text-muted small">
                                Methods are managed in Settings → Payment modes.
                            </span>
                        </div>
                        <div class="col-lg-6 col-sm-6 col-12">
                            <VMultiselect
                                id="account_id"
                                v-model="form.account_id"
                                :options="accounts.data"
                                label="Received into account"
                                required
                                @validate="validateField('account_id')"
                                :error="errors.account_id"
                            />
                        </div>

                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                <span class="fw-medium">Allocate to invoices</span>
                                <div class="d-flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        :disabled="!dueInvoices.length"
                                        @click="fillFullDueAmounts">
                                        Fill all due amounts
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        :disabled="!dueInvoices.length"
                                        @click="clearAllocations">
                                        Clear allocations
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive no-pagination">
                                <table class="table datanew table-bordered mb-0 receipt-alloc-table">
                                    <thead>
                                    <tr>
                                        <th class="receipt-col-sn">SN</th>
                                        <th>Invoice No</th>
                                        <th>Invoice Date</th>
                                        <th>Due Date</th>
                                        <th class="text-end">Grand Total</th>
                                        <th class="text-end">Paid</th>
                                        <th class="text-end">Due</th>
                                        <th class="receipt-col-alloc">Allocate</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-for="(invoice, index) in dueInvoices" :key="invoice.id">
                                        <td>{{ index + 1 }}</td>
                                        <td class="text-truncate" :title="invoice.invoice_no">{{ invoice.invoice_no }}</td>
                                        <td>{{ invoice.invoice_date }}</td>
                                        <td>{{ invoice.due_date }}</td>
                                        <td class="text-end">{{ formatMoney(invoice.grand_total) }}</td>
                                        <td class="text-end">{{ formatMoney(invoice.paid_total) }}</td>
                                        <td class="text-end fw-medium">{{ formatMoney(invoice.due_amount) }}</td>
                                        <td>
                                            <VInput
                                                input-type="number"
                                                input-class="form-control form-control-sm"
                                                :min="0"
                                                :max="invoice.due_amount"
                                                step="0.01"
                                                v-model="invoice.allocate_amount"
                                            />
                                        </td>
                                    </tr>
                                    <tr v-if="!dueInvoices.length">
                                        <td colspan="8" class="text-center text-muted py-4">
                                            <template v-if="form.party_id">
                                                No unpaid invoices found for this customer.
                                            </template>
                                            <template v-else>
                                                Select a customer to load unpaid invoices.
                                            </template>
                                        </td>
                                    </tr>
                                    </tbody>
                                    <tfoot v-if="dueInvoices.length">
                                    <tr class="border-top bg-light">
                                        <td colspan="7" class="text-end fw-medium py-2">Total allocated</td>
                                        <td class="fw-semibold py-2">{{ formatMoney(totalAllocated) }}</td>
                                    </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <VTextarea
                                id="remarks"
                                v-model="form.remarks"
                                label="Remarks"
                                @validate="validateField('remarks')"
                                :error="errors.remarks"
                            />
                        </div>

                        <div class="col-12 text-end border-top pt-3 mt-1">
                            <button @click="closeModal" class="btn btn-cancel me-2" type="button">
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="btn btn-outline-primary me-2"
                                :disabled="isSubmitting"
                                @click="storeReceipt('draft')">
                                Create
                            </button>
                            <button
                                type="button"
                                class="btn btn-primary"
                                :disabled="isSubmitting"
                                @click="storeReceipt('approved')">
                                Create &amp; Approve
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </VModal>
</template>

<script setup>
import {computed, reactive, ref, toRef, watch} from 'vue';
import debounce from 'lodash/debounce';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import {usePartyStore} from '@/stores/admin/party.js';
import {useAccountStore} from '@/stores/admin/accounting/account.js';
import {usePaymentModeStore} from '@/stores/admin/settings/payment-mode.js';
import {useReceiptStore} from '@/stores/admin/sales/receipt.js';
import {useDateHelper} from '@/composables/dateHelper.js';
import {useResolvedParty} from '@/composables/useResolvedParty.js';
import PartyMetaPanel from '@/components/party/PartyMetaPanel.vue';
import {formatMoney} from '@/helpers/formatMoney';

const emit = defineEmits(['saved']);

const open = defineModel('open', {default: false});
const invoiceId = defineModel('invoiceId', {default: ''});

const receiptStore = useReceiptStore();
const partyStore = usePartyStore();
const accountStore = useAccountStore();
const paymentModeStore = usePaymentModeStore();

const {parties} = storeToRefs(partyStore);
const {accounts} = storeToRefs(accountStore);
const {paymentModes} = storeToRefs(paymentModeStore);

const {currentAdDate} = useDateHelper();

const loading = ref(false);
const isSubmitting = ref(false);
const dueInvoices = ref([]);

const activePaymentModes = computed(() =>
    (paymentModes.value?.data || []).filter((m) => m.is_active !== false),
);

const debouncedPartySearch = debounce((query) => {
    partyStore.getParties({
        filter: {
            type: 'customer',
            limit: 50,
            search: query || '',
        },
    });
}, 300);

watch(
    open,
    (isOpen) => {
        if (isOpen) {
            paymentModeStore.getPaymentModes();
            accountStore.getAccounts();
            partyStore.getParties({
                filter: {
                    type: 'customer',
                    limit: 50,
                    search: '',
                },
            });
        }
    },
    {flush: 'post'},
);

const initialState = {
    receipt_date: currentAdDate,
    party_id: '',
    payment_mode_id: '',
    account_id: '',
    reference_no: '',
    remarks: '',
    status: 'draft',
};

const form = reactive({...initialState});

const resolvedParty = useResolvedParty(toRef(form, 'party_id'), parties);

const validations = object({
    receipt_date: string().required('Receipt date is required.'),
    party_id: string().required('Customer is required.'),
    payment_mode_id: string().required('Payment method is required.'),
    account_id: string().required('Account is required.'),
    reference_no: string().nullable(),
    remarks: string().nullable(),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const totalAllocated = computed(() =>
    dueInvoices.value.reduce((sum, inv) => sum + Number(inv.allocate_amount || 0), 0),
);

function paymentMethodFromModeId(modeId) {
    const list = paymentModes.value?.data || [];
    const row = list.find((m) => String(m.id) === String(modeId));
    return row?.name?.trim() || '';
}

const loadDueInvoices = async () => {
    if (!form.party_id) {
        dueInvoices.value = [];
        return;
    }
    try {
        const res = await apiAdmin(`invoice/due?party_id=${form.party_id}`);
        dueInvoices.value = (res.data.data || []).map(invoice => ({
            ...invoice,
            allocate_amount: 0,
        }));
    } catch (e) {
        showErrors(e);
    }
};

const fillFullDueAmounts = () => {
    dueInvoices.value.forEach((inv) => {
        inv.allocate_amount = inv.due_amount;
    });
};

const clearAllocations = () => {
    dueInvoices.value.forEach((inv) => {
        inv.allocate_amount = 0;
    });
};

const setPrefillAllocation = (id) => {
    if (!id) return;
    const target = dueInvoices.value.find(item => item.id === id);
    if (target) {
        target.allocate_amount = target.due_amount;
    }
};

const loadInvoicePrefill = async () => {
    if (!invoiceId.value) {
        return;
    }
    loading.value = true;
    try {
        const res = await apiAdmin(`invoice/${invoiceId.value}`);
        const data = res.data.data || {};
        form.party_id = data.party_id || '';
        const partyPayload = data.party;
        if (partyPayload?.id && !parties.value.data.some((p) => String(p.id) === String(partyPayload.id))) {
            partyStore.parties.data = [partyPayload, ...partyStore.parties.data];
        }
        await loadDueInvoices();
        setPrefillAllocation(invoiceId.value);
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

watch(() => form.party_id, async () => {
    await loadDueInvoices();
});

watch(() => [invoiceId.value, open.value], ([id, isOpen]) => {
    if (id && isOpen) {
        loadInvoicePrefill();
    }
});

const storeReceipt = async (status = 'draft') => {
    form.status = status;
    const validated = await validateForm(validations, form);
    if (!validated) {
        return;
    }

    const paymentMethod = paymentMethodFromModeId(form.payment_mode_id);
    if (!paymentMethod) {
        toast('error', 'Could not resolve payment method. Try reselecting payment method.');
        return;
    }

    const allocations = dueInvoices.value
        .filter(invoice => Number(invoice.allocate_amount || 0) > 0)
        .map(invoice => ({
            invoice_id: invoice.id,
            amount: invoice.allocate_amount,
        }));

    if (!allocations.length) {
        toast('error', 'Please allocate amount to at least one invoice.');
        return;
    }

    isSubmitting.value = true;
    try {
        const payload = {
            receipt_date: form.receipt_date,
            party_id: form.party_id,
            payment_method: paymentMethod,
            account_id: form.account_id,
            reference_no: form.reference_no,
            remarks: form.remarks,
            status: form.status,
            allocations,
        };
        const res = await receiptStore.storeReceipt(payload);
        toast(res.status, res.data.message);
        emit('saved');
        closeModal();
    } catch (e) {
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
};

const closeModal = () => {
    resetForm();
    open.value = false;
    invoiceId.value = '';
};

function resetForm() {
    Object.assign(form, {
        ...initialState,
        receipt_date: currentAdDate,
    });
    dueInvoices.value = [];
    errors.value = {};
}
</script>

<style scoped>
.receipt-alloc-table th,
.receipt-alloc-table td {
    vertical-align: middle;
}
.receipt-alloc-table .receipt-col-sn {
    width: 2.5rem;
}
.receipt-alloc-table .receipt-col-alloc {
    min-width: 7rem;
    max-width: 9rem;
}
</style>
