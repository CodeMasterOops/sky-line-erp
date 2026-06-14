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

                        <div class="col-12">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="fw-medium">Payment Methods</span>
                                <button type="button" class="btn btn-sm btn-outline-secondary" @click="addPaymentLeg">
                                    + Add Method
                                </button>
                            </div>
                            <div class="table-responsive no-pagination">
                                <table class="table table-bordered mb-0 receipt-pay-table">
                                    <thead>
                                    <tr>
                                        <th>Method</th>
                                        <th>Account</th>
                                        <th>Amount</th>
                                        <th style="width:2rem"></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-for="(leg, idx) in form.payments" :key="idx">
                                        <td>
                                            <select class="form-select form-select-sm" v-model="leg.payment_method">
                                                <option v-for="m in paymentMethodOptions" :key="m.value" :value="m.value">
                                                    {{ m.label }}
                                                </option>
                                            </select>
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm" v-model="leg.account_id">
                                                <option value="">Select account</option>
                                                <option v-for="acc in accounts.data" :key="acc.id" :value="acc.id">
                                                    {{ acc.name }}
                                                </option>
                                            </select>
                                        </td>
                                        <td>
                                            <input
                                                type="number"
                                                class="form-control form-control-sm text-end"
                                                v-model="leg.amount"
                                                min="0"
                                                step="0.01"
                                                placeholder="0.00"
                                            />
                                            <template v-if="leg.payment_method === 'cheque'">
                                                <input
                                                    type="text"
                                                    class="form-control form-control-sm mt-1"
                                                    v-model="leg.reference_no"
                                                    placeholder="Cheque no."
                                                />
                                                <input
                                                    type="date"
                                                    class="form-control form-control-sm mt-1"
                                                    v-model="leg.cheque_date"
                                                />
                                            </template>
                                        </td>
                                        <td class="text-center">
                                            <button
                                                v-if="form.payments.length > 1"
                                                type="button"
                                                class="btn btn-sm btn-link text-danger p-0"
                                                @click="removePaymentLeg(idx)">
                                                &times;
                                            </button>
                                        </td>
                                    </tr>
                                    </tbody>
                                    <tfoot>
                                    <tr class="bg-light">
                                        <td colspan="2" class="text-end fw-medium py-2">Total Payment</td>
                                        <td class="fw-semibold py-2">{{ formatMoney(totalPayments) }}</td>
                                        <td></td>
                                    </tr>
                                    <tr v-if="totalTdsDeducted > 0">
                                        <td colspan="4" class="py-1 px-2 small text-muted bg-warning bg-opacity-10">
                                            <i class="ti ti-info-circle me-1"></i>
                                            TDS of <strong>{{ formatMoney(totalTdsDeducted) }}</strong> will be recorded separately.
                                            Payment amount should be net cash received: <strong>{{ formatMoney(Math.max(0, totalAllocated - totalTdsDeducted)) }}</strong>
                                        </td>
                                    </tr>
                                    </tfoot>
                                </table>
                            </div>
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
                                        <th class="receipt-col-tds">TDS</th>
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
                                            <input
                                                type="number"
                                                class="form-control form-control-sm text-end"
                                                v-model="invoice.allocate_amount"
                                                min="0"
                                                :max="invoice.due_amount"
                                                step="0.01"
                                                placeholder="0.00"
                                                @input="recalcInvTds(invoice)"
                                            />
                                        </td>
                                        <td class="receipt-col-tds">
                                            <div v-if="invoice.has_multiple_tds_categories && Number(invoice.allocate_amount) > 0" class="small text-warning mb-1">
                                                <i class="ti ti-alert-triangle me-1"></i>Multiple TDS categories — select manually
                                            </div>
                                            <div v-else-if="invoice.has_tds_items && !Number(invoice.allocate_amount)" class="small text-warning">
                                                <i class="ti ti-receipt-tax"></i> TDS applicable
                                            </div>
                                            <div v-if="Number(invoice.allocate_amount) > 0">
                                                <div class="form-check mb-1">
                                                    <input
                                                        type="checkbox"
                                                        class="form-check-input"
                                                        :id="`tds_inv_${index}`"
                                                        v-model="invoice.tds_applicable"
                                                        @change="onInvTdsToggle(invoice)"
                                                    />
                                                    <label :for="`tds_inv_${index}`" class="form-check-label small">TDS deducted by customer</label>
                                                </div>
                                                <template v-if="invoice.tds_applicable">
                                                    <select
                                                        class="form-select form-select-sm mb-1"
                                                        v-model="invoice.tds_id"
                                                        @change="onInvTdsTaxChange(invoice)">
                                                        <option value="">Select TDS category</option>
                                                        <option v-for="t in tdsTaxes" :key="String(t.id)" :value="String(t.id)">
                                                            {{ t.name }} ({{ t.rate }}%)
                                                        </option>
                                                    </select>
                                                    <input
                                                        type="number"
                                                        class="form-control form-control-sm text-end"
                                                        v-model="invoice.tds_deducted"
                                                        min="0"
                                                        step="0.01"
                                                        placeholder="TDS amount"
                                                    />
                                                    <div v-if="Number(invoice.tds_deducted) > 0" class="small text-muted mt-1">
                                                        Net cash: <strong>{{ formatMoney(Number(invoice.allocate_amount) - Number(invoice.tds_deducted)) }}</strong>
                                                    </div>
                                                </template>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!dueInvoices.length">
                                        <td colspan="9" class="text-center text-muted py-4">
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
                                        <td class="fw-semibold py-2 small text-muted">
                                            <span v-if="totalTdsDeducted > 0">TDS: {{ formatMoney(totalTdsDeducted) }}</span>
                                        </td>
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
import {formatMoney} from '@/helpers/formatMoney.js';
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
import {useReceiptStore} from '@/stores/admin/sales/receipt.js';
import {useDateHelper} from '@/composables/dateHelper.js';
import {useResolvedParty} from '@/composables/useResolvedParty.js';
import PartyMetaPanel from '@/components/party/PartyMetaPanel.vue';

const paymentMethodOptions = [
    {value: 'cash', label: 'Cash'},
    {value: 'bank_transfer', label: 'Bank Transfer'},
    {value: 'cheque', label: 'Cheque'},
    {value: 'card', label: 'Card'},
    {value: 'online', label: 'Online'},
    {value: 'other', label: 'Other'},
];

const emit = defineEmits(['saved']);

const open = defineModel('open', {default: false});
const invoiceId = defineModel('invoiceId', {default: ''});

const receiptStore = useReceiptStore();
const partyStore = usePartyStore();
const accountStore = useAccountStore();

const {parties} = storeToRefs(partyStore);
const {accounts} = storeToRefs(accountStore);

const {currentAdDate} = useDateHelper();

const loading = ref(false);
const isSubmitting = ref(false);
const dueInvoices = ref([]);
const tdsTaxes = ref([]);

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
            accountStore.getAccounts();
            partyStore.getParties({
                filter: {
                    type: 'customer',
                    limit: 50,
                    search: '',
                },
            });
            apiAdmin('tax?for=tds&limit=100').then((r) => { tdsTaxes.value = r.data.data ?? []; });
        }
    },
    {flush: 'post'},
);

const emptyPaymentLeg = () => ({payment_method: 'cash', account_id: '', amount: '', reference_no: '', cheque_date: ''});

const initialState = {
    receipt_date: currentAdDate,
    party_id: '',
    remarks: '',
    status: 'draft',
    payments: [emptyPaymentLeg()],
};

const form = reactive({...initialState});

const resolvedParty = useResolvedParty(toRef(form, 'party_id'), parties);

const validations = object({
    receipt_date: string().required('Receipt date is required.'),
    party_id: string().required('Customer is required.'),
    remarks: string().nullable(),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const totalAllocated = computed(() =>
    dueInvoices.value.reduce((sum, inv) => sum + Number(inv.allocate_amount || 0), 0),
);

const totalPayments = computed(() =>
    form.payments.reduce((sum, leg) => sum + Number(leg.amount || 0), 0),
);

function addPaymentLeg() {
    form.payments.push(emptyPaymentLeg());
}

function removePaymentLeg(index) {
    form.payments.splice(index, 1);
}

const totalTdsDeducted = computed(() =>
    dueInvoices.value.reduce((sum, inv) => sum + (inv.tds_applicable ? Number(inv.tds_deducted || 0) : 0), 0),
);

// Auto-fill payment leg with net cash (allocated − TDS) for single-leg receipts
watch([totalAllocated, totalTdsDeducted], ([allocated, tds]) => {
    if (form.payments.length !== 1) {
        return;
    }
    const net = Math.max(0, Math.round((allocated - tds) * 100) / 100);
    form.payments[0].amount = net > 0 ? net : '';
});

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
            tds_applicable: !!invoice.has_tds_items,
            tds_id: invoice.tds_id ? String(invoice.tds_id) : '',
            tds_deducted: 0,
            effective_tds_base: 0,
        }));
    } catch (e) {
        showErrors(e);
    }
};

const fillFullDueAmounts = () => {
    dueInvoices.value.forEach((inv) => {
        inv.allocate_amount = inv.due_amount;
        recalcInvTds(inv);
    });
};

const clearAllocations = () => {
    dueInvoices.value.forEach((inv) => {
        inv.allocate_amount = 0;
        inv.tds_applicable = false;
        inv.tds_id = '';
        inv.tds_deducted = 0;
        inv.effective_tds_base = 0;
    });
};

function onInvTdsToggle(inv) {
    if (!inv.tds_applicable) {
        inv.tds_id = '';
        inv.tds_deducted = 0;
    } else {
        recalcInvTds(inv);
    }
}

function onInvTdsTaxChange(inv) {
    recalcInvTds(inv);
}

function recalcInvTds(inv) {
    if (!inv.tds_applicable || !inv.tds_id) {
        inv.tds_deducted = 0;
        inv.effective_tds_base = 0;
        return;
    }
    const tax = tdsTaxes.value.find((t) => String(t.id) === String(inv.tds_id));
    const tdsBase = Number(inv.tds_base_amount) || 0;
    const grandTotal = Number(inv.grand_total) || 0;
    const allocated = Number(inv.allocate_amount) || 0;
    // Pro-rate the pre-VAT TDS base proportionally to the amount being paid
    const effectiveBase = grandTotal > 0 && tdsBase > 0
        ? Math.min(tdsBase, tdsBase * (allocated / grandTotal))
        : 0;
    inv.effective_tds_base = Math.round(effectiveBase * 100) / 100;
    inv.tds_deducted = tax && effectiveBase > 0
        ? Math.round(effectiveBase * (tax.rate / 100) * 100) / 100
        : 0;
}

const setPrefillAllocation = (id) => {
    if (!id) return;
    const target = dueInvoices.value.find(item => item.id === id);
    if (target) {
        target.allocate_amount = target.due_amount;
        recalcInvTds(target);
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

    const validLegs = form.payments.filter((leg) => Number(leg.amount || 0) > 0 && leg.account_id);
    if (!validLegs.length) {
        toast(422, 'Please add at least one payment method with amount and account.');
        return;
    }

    const allocations = dueInvoices.value
        .filter(invoice => Number(invoice.allocate_amount || 0) > 0)
        .map(invoice => ({
            invoice_id: invoice.id,
            amount: invoice.allocate_amount,
            tds_id: (invoice.tds_applicable && invoice.tds_id) ? Number(invoice.tds_id) : null,
            tds_deducted: (invoice.tds_applicable && invoice.tds_deducted) ? Number(invoice.tds_deducted) : 0,
            tds_base_amount: (invoice.tds_applicable && invoice.effective_tds_base) ? Number(invoice.effective_tds_base) : 0,
        }));

    if (!allocations.length) {
        toast(422, 'Please allocate amount to at least one invoice.');
        return;
    }

    isSubmitting.value = true;
    try {
        const payload = {
            receipt_date: form.receipt_date,
            party_id: form.party_id,
            remarks: form.remarks,
            status: form.status,
            payments: validLegs.map((leg) => ({
                payment_method: leg.payment_method,
                account_id: Number(leg.account_id),
                amount: Number(leg.amount),
                reference_no: leg.reference_no || null,
                cheque_date: leg.cheque_date || null,
            })),
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
        payments: [emptyPaymentLeg()],
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
.receipt-alloc-table .receipt-col-tds {
    min-width: 10rem;
}
.receipt-pay-table th,
.receipt-pay-table td {
    vertical-align: middle;
}
</style>
