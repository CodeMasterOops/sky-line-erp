<template>
    <VModal
        :show-modal="!!open"
        @close-click="closeModal"
        modal-class="large-modal"
        title="Record Payment">
        <template #modal-body>
            <VLoader v-if="loading" loader-type="progress"/>
            <form v-else @submit.prevent="storePayment" class="row g-3">
                <div class="col-md-6">
                    <VDatepicker
                        id="payment_date"
                        v-model="form.payment_date"
                        label="Payment Date"
                        @validate="validateField('payment_date')"
                        :error="errors.payment_date"
                    />
                </div>
                <div class="col-md-6">
                    <VMultiselect
                        id="payment_mode_id"
                        v-model="form.payment_mode_id"
                        :options="paymentModes.data"
                        label="Payment Mode"
                        @validate="validateField('payment_mode_id')"
                        :error="errors.payment_mode_id"
                    />
                </div>
                <div class="col-md-6">
                    <VMultiselect
                        id="account_id"
                        v-model="form.account_id"
                        :options="accounts.data"
                        label="Paid Account"
                        @validate="validateField('account_id')"
                        :error="errors.account_id"
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        id="reference_no"
                        v-model="form.reference_no"
                        label="Reference No"
                        @validate="validateField('reference_no')"
                        :error="errors.reference_no"
                    />
                </div>
                <div class="col-md-6">
                    <VMultiselect
                        id="party_id"
                        v-model="form.party_id"
                        :options="parties.data"
                        label="Supplier"
                        @validate="validateField('party_id')"
                        :error="errors.party_id"
                    />
                </div>
                <div v-if="!lockPayableType" class="col-md-6">
                    <VSelect
                        id="payable_type"
                        v-model="selectedPayableType"
                        :options="payableTypeOptions"
                        label="Payable Type"
                    />
                </div>

                <div class="col-12">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                            <tr>
                                <th style="width: 50px;">SN</th>
                                <th>{{ payableLabels.ref }}</th>
                                <th>{{ payableLabels.date }}</th>
                                <th>Due Date</th>
                                <th>Grand Total</th>
                                <th>Paid</th>
                                <th>Due</th>
                                <th style="width: 140px;">Allocate Amount</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="(item, index) in dueItems" :key="item.id">
                                <td>{{ index + 1 }}</td>
                                <td>{{ item.reference_no }}</td>
                                <td>{{ item.date }}</td>
                                <td>{{ item.due_date }}</td>
                                <td>{{ item.grand_total }}</td>
                                <td>{{ item.paid_total }}</td>
                                <td>{{ item.due_amount }}</td>
                                <td>
                                    <VInput
                                        input-type="number"
                                        v-model="item.allocate_amount"
                                        :max="item.due_amount"
                                    />
                                </td>
                            </tr>
                            <tr v-if="!dueItems.length">
                                <td colspan="8" class="text-center">No due items found.</td>
                            </tr>
                            </tbody>
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

                <div class="col-12 text-end">
                    <button @click="closeModal" class="btn btn-danger me-1" type="button">
                        Close
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline-primary me-1"
                        :disabled="isSubmitting"
                        @click="storePayment">
                        Create
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary"
                        :disabled="isSubmitting"
                        @click="storePayment('approved')">
                        Create & Approve
                    </button>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import {computed, onMounted, reactive, ref, watch} from 'vue';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import {usePartyStore} from '@/stores/admin/party.js';
import {useAccountStore} from '@/stores/admin/accounting/account.js';
import {usePaymentStore} from '@/stores/admin/purchase/payment.js';
import {usePaymentModeStore} from '@/stores/admin/setting/payment-mode.js';
import {useDateHelper} from "@/composables/dateHelper.js";

const emit = defineEmits(['saved']);

const open = defineModel('open', {default: false});
const payableId = defineModel('payableId', {default: ''});

const paymentStore = usePaymentStore();
const partyStore = usePartyStore();
const accountStore = useAccountStore();
const paymentModeStore = usePaymentModeStore();

const {currentAdDate} = useDateHelper();

const {parties} = storeToRefs(partyStore);
const {accounts} = storeToRefs(accountStore);
const {paymentModes} = storeToRefs(paymentModeStore);

const props = defineProps({
    payableType: {
        type: String,
        default: ''
    },
    lockPayableType: {
        type: Boolean,
        default: false
    }
});

const loading = ref(false);
const isSubmitting = ref(false);
const dueItems = ref([]);

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

onMounted(() => {
    partyStore.getParties({filter: {type: 'supplier'}});
    accountStore.getAccounts();
    paymentModeStore.getPaymentModes();
});

const initialState = {
    payment_date: currentAdDate,
    party_id: '',
    payment_mode_id: '',
    account_id: '',
    reference_no: '',
    remarks: '',
    status: 'draft',
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
        await loadDueItems();
        setPrefillAllocation(payableId.value);
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

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
    let validated = await validateForm(validations, form);
    if (validated) {
        const allocations = dueItems.value
            .filter(item => Number(item.allocate_amount || 0) > 0)
            .map(item => ({
                payable_type: selectedPayableType.value,
                payable_id: item.id,
                amount: item.allocate_amount,
            }));

        if (!allocations.length) {
            toast('error', 'Please allocate amount to at least one item.');
            return;
        }

        isSubmitting.value = true;
        try {
            const payload = {
                ...form,
                allocations,
            };
            let res = await paymentStore.storePayment(payload);
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
    Object.assign(form, {...initialState});
    selectedPayableType.value = props.payableType || 'bill';
    dueItems.value = [];
    errors.value = {};
}
</script>
