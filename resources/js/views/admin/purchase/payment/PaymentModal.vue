<template>
    <VModal
        :show-modal="!!open"
        @close-click="closeModal"
        size="xl"
        modal-class="large-modal add-centered"
        title="Record Payment">
        <template #modal-body>
            <VLoader v-if="loading" loader-type="progress"/>
            <div v-else class="card border-0 shadow-none mb-0">
                <div class="card-body p-0 border-0">
                    <form @submit.prevent="storePayment('draft')" class="row g-3">
                        <div class="col-12">
                            <p class="text-muted small mb-0">
                                Select supplier first to load open payables, then choose how you pay and allocate amounts to bills or expenses.
                            </p>
                        </div>

                        <div class="col-12 col-md-4">
                            <VDatepicker
                                id="payment_date"
                                input-type="date"
                                v-model="form.payment_date"
                                label="Payment Date"
                                @validate="validateField('payment_date')"
                                :error="errors.payment_date"
                            />
                        </div>
                        <div class="col-12 col-md-4">
                            <VMultiselect
                                id="payment_mode_id"
                                v-model="form.payment_mode_id"
                                :options="activePaymentModes"
                                label="Payment mode"
                                :filter-results="false"
                                @validate="validateField('payment_mode_id')"
                                :error="errors.payment_mode_id"
                            />
                            <span class="form-text text-muted small">Modes are managed in Settings → Payment modes.</span>
                        </div>
                        <div class="col-12 col-md-4">
                            <VMultiselect
                                id="account_id"
                                v-model="form.account_id"
                                :options="accounts.data"
                                label="Paid from account"
                                :filter-results="false"
                                @validate="validateField('account_id')"
                                :error="errors.account_id"
                            />
                        </div>

                        <div class="col-12 col-md-4">
                            <VInput
                                id="reference_no"
                                v-model="form.reference_no"
                                label="Reference No"
                                placeholder="Cheque no., transfer ref., etc."
                                @validate="validateField('reference_no')"
                                :error="errors.reference_no"
                            />
                        </div>
                        <div v-if="!lockPayableType" class="col-12 col-md-4">
                            <VSelect
                                id="payable_type"
                                v-model="selectedPayableType"
                                :options="payableTypeOptions"
                                label="Payable type"
                            />
                        </div>

                        <div class="col-12">
                            <div class="d-flex gap-2 align-items-end">
                                <div class="flex-grow-1 min-w-0">
                                    <VMultiselect
                                        id="party_id"
                                        v-model="form.party_id"
                                        :options="parties.data"
                                        label="Supplier"
                                        :filter-results="false"
                                        @validate="validateField('party_id')"
                                        @search-change="debouncedSupplierSearch"
                                        :error="errors.party_id"
                                    />
                                </div>
                                <div class="ps-0 flex-shrink-0">
                                    <div class="add-icon">
                                        <a
                                            href="#"
                                            class="bg-dark text-white p-2 rounded d-inline-flex align-items-center justify-content-center"
                                            title="Add supplier"
                                            @click.prevent="createSupplierOpened = true">
                                            <vue-feather type="plus-circle" class="plus"></vue-feather>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <PartyMetaPanel
                                v-if="resolvedParty"
                                :party="resolvedParty"
                                pan-heading="Seller PAN"
                            />
                        </div>

                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                <span class="fw-medium">Allocate to open payables</span>
                                <div class="d-flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        :disabled="!dueItems.length"
                                        @click="fillFullDueAmounts">
                                        Fill all due amounts
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        :disabled="!dueItems.length"
                                        @click="clearAllocations">
                                        Clear allocations
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive no-pagination">
                                <table class="table datanew table-bordered mb-0 payment-alloc-table">
                                    <thead>
                                    <tr>
                                        <th class="pay-col-sn">SN</th>
                                        <th>{{ payableLabels.ref }}</th>
                                        <th>{{ payableLabels.date }}</th>
                                        <th>Due Date</th>
                                        <th class="text-end">Grand Total</th>
                                        <th class="text-end">Paid</th>
                                        <th class="text-end">Due</th>
                                        <th class="pay-col-alloc">Allocate</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-for="(item, index) in dueItems" :key="item.id">
                                        <td>{{ index + 1 }}</td>
                                        <td class="text-truncate" :title="item.reference_no">{{ item.reference_no }}</td>
                                        <td>{{ item.date }}</td>
                                        <td>{{ item.due_date }}</td>
                                        <td class="text-end">{{ formatMoney(item.grand_total) }}</td>
                                        <td class="text-end">{{ formatMoney(item.paid_total) }}</td>
                                        <td class="text-end fw-medium">{{ formatMoney(item.due_amount) }}</td>
                                        <td>
                                            <VInput
                                                input-type="number"
                                                input-class="form-control form-control-sm"
                                                v-model="item.allocate_amount"
                                                :max="item.due_amount"
                                                min="0"
                                                step="0.01"
                                            />
                                        </td>
                                    </tr>
                                    <tr v-if="!dueItems.length">
                                        <td colspan="8" class="text-center text-muted py-4">
                                            <template v-if="form.party_id">
                                                No open payables found for this supplier.
                                            </template>
                                            <template v-else>
                                                Select a supplier to load bills or expenses with a balance due.
                                            </template>
                                        </td>
                                    </tr>
                                    </tbody>
                                    <tfoot v-if="dueItems.length">
                                    <tr class="border-top bg-light">
                                        <td colspan="7" class="text-end fw-medium py-2">Total allocated</td>
                                        <td class="fw-semibold py-2">{{ formatMoney(totalAllocated) }}</td>
                                    </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-check mb-2">
                                <input
                                    id="show_tds_currency"
                                    v-model="showTdsCurrencySection"
                                    class="form-check-input"
                                    type="checkbox"
                                />
                                <label class="form-check-label small" for="show_tds_currency">
                                    Include TDS / currency details
                                </label>
                            </div>
                            <div v-show="showTdsCurrencySection" class="card border bg-light">
                                <div class="card-header py-2 px-3">
                                    <strong class="small">TDS / Currency</strong>
                                </div>
                                <div class="card-body py-2 px-3 row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label small">Currency</label>
                                        <select class="form-select form-select-sm" v-model="form.currency_code">
                                            <option value="">NPR (default)</option>
                                            <option value="USD">USD</option>
                                            <option value="EUR">EUR</option>
                                            <option value="INR">INR</option>
                                            <option value="GBP">GBP</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small">Exchange Rate</label>
                                        <input type="number" class="form-control form-control-sm" v-model="form.exchange_rate" placeholder="1.00" min="0" step="0.0001" />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small">TDS Category</label>
                                        <select class="form-select form-select-sm" v-model="form.tds_category">
                                            <option value="">None</option>
                                            <option value="rent">Rent (10%)</option>
                                            <option value="service_payment">Service Payment (15%)</option>
                                            <option value="commission">Commission (15%)</option>
                                            <option value="dividend">Dividend (5%)</option>
                                            <option value="interest">Interest (15%)</option>
                                            <option value="contract">Contract / Work (1.5%)</option>
                                            <option value="royalty">Royalty (15%)</option>
                                            <option value="others">Others</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small">TDS Rate (%)</label>
                                        <input type="number" class="form-control form-control-sm" v-model="form.tds_rate" placeholder="Auto" readonly />
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small">Gross Amount</label>
                                        <input type="number" class="form-control form-control-sm" v-model="form.gross_amount" placeholder="0.00" min="0" step="0.01" />
                                    </div>
                                    <div class="col-md-6" v-if="form.tds_category">
                                        <label class="form-label small">TDS Amount (computed)</label>
                                        <input type="text" class="form-control form-control-sm bg-white" :value="computedTdsAmount" readonly />
                                    </div>
                                </div>
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
                                @click="storePayment('draft')">
                                Create
                            </button>
                            <button
                                type="button"
                                class="btn btn-primary"
                                :disabled="isSubmitting"
                                @click="storePayment('approved')">
                                Create &amp; Approve
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </VModal>
    <CreateSupplier
        v-if="createSupplierOpened"
        v-model:create-modal-opened="createSupplierOpened"
        type="supplier"
    />
</template>

<script setup>
import {computed, reactive, ref, toRef, watch} from 'vue';
import debounce from 'lodash/debounce';
import {useToast} from 'vue-toastification';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import {usePartyStore} from '@/stores/admin/party.js';
import {useAccountStore} from '@/stores/admin/accounting/account.js';
import {usePaymentStore} from '@/stores/admin/purchase/payment.js';
import {usePaymentModeStore} from '@/stores/admin/settings/payment-mode.js';
import {useDateHelper} from '@/composables/dateHelper.js';
import {useResolvedParty} from '@/composables/useResolvedParty.js';
import PartyMetaPanel from '@/components/party/PartyMetaPanel.vue';
import CreateSupplier from '@/views/admin/party/Create.vue';
import {formatMoney} from '@/helpers/formatMoney';

const emit = defineEmits(['saved']);

const open = defineModel('open', {default: false});
const payableId = defineModel('payableId', {default: ''});

const paymentStore = usePaymentStore();
const partyStore = usePartyStore();
const accountStore = useAccountStore();
const paymentModeStore = usePaymentModeStore();

const notifier = useToast();

const {currentAdDate} = useDateHelper();

const {parties} = storeToRefs(partyStore);
const {accounts} = storeToRefs(accountStore);
const {paymentModes} = storeToRefs(paymentModeStore);

const props = defineProps({
    payableType: {
        type: String,
        default: '',
    },
    lockPayableType: {
        type: Boolean,
        default: false,
    },
});

const loading = ref(false);
const isSubmitting = ref(false);
const dueItems = ref([]);
const createSupplierOpened = ref(false);
/** When false, TDS/currency fields are hidden and cleared for submit. */
const showTdsCurrencySection = ref(false);

const payableTypeOptions = [
    {id: 'bill', name: 'Bill'},
    {id: 'expense', name: 'Expense'},
];

const selectedPayableType = ref(props.payableType || 'bill');

const payableLabels = computed(() => {
    return selectedPayableType.value === 'expense'
        ? {ref: 'Reference No', date: 'Expense Date'}
        : {ref: 'Bill No', date: 'Bill Date'};
});

const activePaymentModes = computed(() =>
    (paymentModes.value?.data || []).filter((m) => m.is_active !== false),
);

const debouncedSupplierSearch = debounce((query) => {
    partyStore.getParties({
        filter: {
            type: 'supplier',
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
                    type: 'supplier',
                    limit: 50,
                    search: '',
                },
            });
        }
    },
    {flush: 'post'},
);

const TDS_RATES = {
    rent: 10,
    service_payment: 15,
    commission: 15,
    dividend: 5,
    interest: 15,
    contract: 1.5,
    royalty: 15,
    others: 0,
};

const initialState = {
    payment_date: currentAdDate,
    party_id: '',
    payment_mode_id: '',
    account_id: '',
    reference_no: '',
    remarks: '',
    status: 'draft',
    tds_category: '',
    tds_rate: '',
    gross_amount: '',
    currency_code: '',
    exchange_rate: '',
};

const form = reactive({...initialState});

const validations = object({
    payment_date: string().required('Payment date is required.'),
    party_id: string().required('Supplier is required.'),
    payment_mode_id: string().required('Payment mode is required.'),
    account_id: string().required('Account is required.'),
    reference_no: string().nullable(),
    remarks: string().nullable(),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const resolvedParty = useResolvedParty(toRef(form, 'party_id'), parties);

function mergePartyFromPayload(party) {
    if (party?.id && !partyStore.parties.data.some((p) => String(p.id) === String(party.id))) {
        partyStore.parties.data = [party, ...partyStore.parties.data];
    }
}

const totalAllocated = computed(() =>
    dueItems.value.reduce((sum, row) => sum + Number(row.allocate_amount || 0), 0),
);

function fillFullDueAmounts() {
    dueItems.value.forEach((row) => {
        row.allocate_amount = row.due_amount;
    });
}

function clearAllocations() {
    dueItems.value.forEach((row) => {
        row.allocate_amount = 0;
    });
}

const loadDueItems = async () => {
    if (!form.party_id) {
        dueItems.value = [];
        return;
    }
    try {
        const endpoint = selectedPayableType.value === 'expense' ? 'expense/due' : 'bill/due';
        const res = await apiAdmin(`${endpoint}?party_id=${form.party_id}`);
        dueItems.value = (res.data.data || []).map(item => {
            const normalized = selectedPayableType.value === 'expense'
                ? {
                    ...item,
                    reference_no: item.expense_no ?? item.reference_no,
                }
                : {
                    ...item,
                    reference_no: item.bill_no ?? item.reference_no,
                    date: item.bill_date ?? item.date,
                };
            return {
                ...normalized,
                allocate_amount: 0,
            };
        });
    } catch (e) {
        showErrors(e);
    }
};

const setPrefillAllocation = (id) => {
    if (!id) return;
    const target = dueItems.value.find(item => item.id === id);
    if (target) {
        target.allocate_amount = target.due_amount;
    }
};

const loadPrefill = async () => {
    if (!payableId.value) {
        return;
    }
    loading.value = true;
    try {
        const endpoint = selectedPayableType.value === 'expense' ? 'expense' : 'bill';
        const res = await apiAdmin(`${endpoint}/${payableId.value}`);
        const data = res.data.data || {};
        form.party_id = data.party_id || '';
        if (data.party) {
            mergePartyFromPayload(data.party);
        }
        await loadDueItems();
        setPrefillAllocation(payableId.value);
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

watch(() => form.tds_category, (cat) => {
    form.tds_rate = cat && TDS_RATES[cat] != null ? String(TDS_RATES[cat]) : '';
});

watch(showTdsCurrencySection, (show) => {
    if (!show) {
        form.currency_code = '';
        form.exchange_rate = '';
        form.tds_category = '';
        form.tds_rate = '';
        form.gross_amount = '';
    }
});

const computedTdsAmount = computed(() => {
    const gross = Number(form.gross_amount || 0);
    const rate = Number(form.tds_rate || 0);
    if (!gross || !rate) return '0.00';
    return (gross * rate / 100).toFixed(2);
});

watch(() => form.party_id, async () => {
    await loadDueItems();
});

watch(() => [payableId.value, open.value], ([id, isOpen]) => {
    if (id && isOpen) {
        loadPrefill();
    }
});

watch(() => selectedPayableType.value, async () => {
    await loadDueItems();
    if (props.lockPayableType) {
        const id = payableId.value;
        if (id && open.value) {
            setPrefillAllocation(id);
        }
    }
});

watch(() => props.payableType, (val) => {
    if (val) {
        selectedPayableType.value = val;
    }
});

const storePayment = async (status = 'draft') => {
    form.status = status;
    const validated = await validateForm(validations, form);
    if (validated) {
        const allocations = dueItems.value
            .filter(item => Number(item.allocate_amount || 0) > 0)
            .map(item => ({
                payable_type: selectedPayableType.value,
                payable_id: item.id,
                amount: item.allocate_amount,
            }));

        if (!allocations.length) {
            notifier.warning('Please allocate amount to at least one line.');
            return;
        }

        isSubmitting.value = true;
        try {
            const payload = {
                ...form,
                allocations,
                tds_amount:
                    showTdsCurrencySection.value && form.tds_category
                        ? computedTdsAmount.value
                        : null,
            };
            if (!showTdsCurrencySection.value) {
                payload.currency_code = '';
                payload.exchange_rate = '';
                payload.tds_category = '';
                payload.tds_rate = '';
                payload.gross_amount = '';
            }
            const res = await paymentStore.storePayment(payload);
            toast(res.status, res.data.message);
            emit('saved');
            closeModal();
        } catch (e) {
            showErrors(e);
        } finally {
            isSubmitting.value = false;
        }
    }
};

const closeModal = () => {
    resetForm();
    open.value = false;
    payableId.value = '';
};

function resetForm() {
    Object.assign(form, {
        ...initialState,
        payment_date: currentAdDate,
    });
    selectedPayableType.value = props.payableType || 'bill';
    dueItems.value = [];
    createSupplierOpened.value = false;
    showTdsCurrencySection.value = false;
    errors.value = {};
}
</script>

<style scoped>
.payment-alloc-table th,
.payment-alloc-table td {
    vertical-align: middle;
}
.payment-alloc-table .pay-col-sn {
    width: 2.5rem;
}
.payment-alloc-table .pay-col-alloc {
    min-width: 7rem;
    max-width: 9rem;
}
</style>
