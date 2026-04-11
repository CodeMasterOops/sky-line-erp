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
                    <VInput
                        id="payment_method"
                        v-model="form.payment_method"
                        label="Payment Method"
                        @validate="validateField('payment_method')"
                        :error="errors.payment_method"
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

                <div class="col-12">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                            <tr>
                                <th style="width: 50px;">SN</th>
                                <th>Bill No</th>
                                <th>Bill Date</th>
                                <th>Due Date</th>
                                <th>Grand Total</th>
                                <th>Paid</th>
                                <th>Due</th>
                                <th style="width: 140px;">Allocate Amount</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="(bill, index) in dueBills" :key="bill.id">
                                <td>{{ index + 1 }}</td>
                                <td>{{ bill.bill_no }}</td>
                                <td>{{ bill.bill_date }}</td>
                                <td>{{ bill.due_date }}</td>
                                <td>{{ bill.grand_total }}</td>
                                <td>{{ bill.paid_total }}</td>
                                <td>{{ bill.due_amount }}</td>
                                <td>
                                    <VInput
                                        input-type="number"
                                        v-model="bill.allocate_amount"
                                        :max="bill.due_amount"
                                    />
                                </td>
                            </tr>
                            <tr v-if="!dueBills.length">
                                <td colspan="8" class="text-center">No due bills found.</td>
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
import {onMounted, reactive, ref, watch} from 'vue';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import {usePartyStore} from '@/stores/admin/party.js';
import {useAccountStore} from '@/stores/admin/accounting/account.js';
import {usePaymentStore} from '@/stores/admin/purchase/payment.js';
import {useDateHelper} from "@/composables/dateHelper.js";

const emit = defineEmits(['saved']);

const open = defineModel('open', {default: false});
const billId = defineModel('billId', {default: ''});

const paymentStore = usePaymentStore();
const partyStore = usePartyStore();
const accountStore = useAccountStore();

const {currentAdDate} = useDateHelper();

const {parties} = storeToRefs(partyStore);
const {accounts} = storeToRefs(accountStore);

const loading = ref(false);
const isSubmitting = ref(false);
const dueBills = ref([]);

onMounted(() => {
    partyStore.getParties({filter: {type: 'supplier'}});
    accountStore.getAccounts();
});

const initialState = {
    payment_date: currentAdDate,
    party_id: '',
    payment_method: '',
    account_id: '',
    reference_no: '',
    remarks: '',
    status: 'draft',
};

const form = reactive({...initialState});

const validations = object({
    payment_date: string().required('Payment date is required.'),
    party_id: string().required('Supplier is required.'),
    payment_method: string().required('Payment method is required.'),
    account_id: string().required('Account is required.'),
    reference_no: string().nullable(),
    remarks: string().nullable(),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const loadDueBills = async () => {
    if (!form.party_id) {
        dueBills.value = [];
        return;
    }
    try {
        const res = await apiAdmin(`bill/due?party_id=${form.party_id}`);
        dueBills.value = (res.data.data || []).map(bill => ({
            ...bill,
            allocate_amount: 0,
        }));
    } catch (e) {
        showErrors(e);
    }
};

const setPrefillAllocation = (id) => {
    if (!id) return;
    const target = dueBills.value.find(item => item.id === id);
    if (target) {
        target.allocate_amount = target.due_amount;
    }
};

const loadBillPrefill = async () => {
    if (!billId.value) {
        return;
    }
    loading.value = true;
    try {
        const res = await apiAdmin(`bill/${billId.value}`);
        const data = res.data.data || {};
        form.party_id = data.party_id || '';
        await loadDueBills();
        setPrefillAllocation(billId.value);
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

watch(() => form.party_id, async () => {
    await loadDueBills();
});

watch(() => [billId.value, open.value], ([id, isOpen]) => {
    if (id && isOpen) {
        loadBillPrefill();
    }
});

const storePayment = async (status = 'draft') => {
    form.status = status;
    let validated = await validateForm(validations, form);
    if (validated) {
        const allocations = dueBills.value
            .filter(bill => Number(bill.allocate_amount || 0) > 0)
            .map(bill => ({
                bill_id: bill.id,
                amount: bill.allocate_amount,
            }));

        if (!allocations.length) {
            toast('error', 'Please allocate amount to at least one bill.');
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
    billId.value = '';
};

function resetForm() {
    Object.assign(form, {...initialState});
    dueBills.value = [];
    errors.value = {};
}
</script>
