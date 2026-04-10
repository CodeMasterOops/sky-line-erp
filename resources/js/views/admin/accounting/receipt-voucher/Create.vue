<template>
    <VModal
        :show-modal="!!createModalOpened"
        @close-click="createModalOpened = false"
        modal-class="large-modal"
        title="Add Receipt Voucher">
        <template #modal-body>
            <form @submit.prevent="storeVoucherWithStatus('draft')" class="row g-3">
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
                    <VDatepicker
                        id="date"
                        v-model="form.date"
                        :show-switcher="false"
                        label="Date"
                        @validate="validateField('date')"
                        :error="errors.date"
                    />
                </div>
                <div class="col-md-6">
                    <VSelect
                        id="deposited_to_account_id"
                        v-model="form.deposited_to_account_id"
                        :options="accounts.data"
                        label="Deposited To Account"
                        @validate="validateField('deposited_to_account_id')"
                        :error="errors.deposited_to_account_id"
                    />
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

                <div class="col-12">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                            <tr>
                                <th style="width: 50px;">SN</th>
                                <th>Account</th>
                                <th style="width: 160px;">Amount</th>
                                <th>Remarks</th>
                                <th style="width: 60px;">Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="(item, index) in form.items" :key="index">
                                <td>{{ index + 1 }}</td>
                                <td>
                                    <VSelect
                                        v-model="form.items[index].account_id"
                                        :options="accounts.data"
                                        @validate="validateField(`items[${index}].account_id`)"
                                        :error="errors[`items[${index}].account_id`]"
                                    />
                                </td>
                                <td>
                                    <VInput
                                        input-type="number"
                                        v-model="form.items[index].amount"
                                        @validate="validateField(`items[${index}].amount`)"
                                        :error="errors[`items[${index}].amount`]"
                                    />
                                </td>
                                <td>
                                    <VInput
                                        v-model="form.items[index].remarks"
                                        @validate="validateField(`items[${index}].remarks`)"
                                        :error="errors[`items[${index}].remarks`]"
                                    />
                                </td>
                                <td class="text-center">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        @click="removeItem(index)"
                                        :disabled="form.items.length === 1">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" @click="addItem">
                            Add Item
                        </button>
                        <div class="text-muted small">
                            Total Amount: {{ totalAmount.toFixed(2) }}
                        </div>
                    </div>
                    <div v-if="errors.items" class="text-danger small mt-2">
                        {{ errors.items }}
                    </div>
                </div>

                <div class="col-12 text-end">
                    <button @click="closeCreateModal" class="btn btn-danger me-1" type="button">
                        Close
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline-primary me-1"
                        :disabled="isSubmitting"
                        @click="storeVoucherWithStatus('draft')">
                        Create
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary"
                        :disabled="isSubmitting"
                        @click="storeVoucherWithStatus('approved')">
                        Create & Approve
                    </button>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import {computed, onMounted, reactive, ref} from 'vue';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {array, number, object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {useAccountStore} from '@/stores/admin/accounting/account.js';
import {useReceiptVoucherStore} from '@/stores/admin/accounting/receipt-voucher.js';

const receiptVoucherStore = useReceiptVoucherStore();
const accountStore = useAccountStore();

const createModalOpened = defineModel('createModalOpened');

const {accounts} = storeToRefs(accountStore);

onMounted(() => {
    accountStore.getAccounts();
});

const initialState = {
    reference_no: '',
    date: new Date().toISOString().slice(0, 10),
    deposited_to_account_id: '',
    remarks: '',
    status: 'draft',
    items: [
        {
            account_id: '',
            amount: '',
            remarks: '',
        }
    ],
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

const addItem = () => {
    form.items.push({
        account_id: '',
        amount: '',
        remarks: '',
    });
};

const removeItem = (index) => {
    if (form.items.length === 1) return;
    form.items.splice(index, 1);
};

const parseAmount = (value) => {
    const parsed = parseFloat(value);
    return Number.isFinite(parsed) ? parsed : 0;
};

const totalAmount = computed(() => form.items.reduce((sum, item) => sum + parseAmount(item.amount), 0));

const itemSchema = object({
    account_id: string().required('Account is required.'),
    amount: number().typeError('Amount must be a number.').required('Amount is required.').min(0.01, 'Amount must be greater than zero.'),
    remarks: string().nullable(),
});

const validations = object({
    date: string().required('Date is required.'),
    deposited_to_account_id: string().required('Deposited to account is required.'),
    items: array().of(itemSchema).min(1, 'At least one item is required.'),
}).test('total', 'Total amount must be greater than zero.', function (value) {
    const items = value?.items || [];
    const total = items.reduce((sum, item) => sum + parseAmount(item.amount), 0);
    if (total <= 0) {
        return this.createError({path: 'items', message: 'Total amount must be greater than zero.'});
    }
    return true;
});

const {errors, validateField, validateForm} = useYup(form, validations);

const storeVoucherWithStatus = async (status) => {
    form.status = status;
    let validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await receiptVoucherStore.storeVoucher(form);
            toast(res.status, res.data.message);
            closeCreateModal();
        } catch (e) {
            showErrors(e);
        } finally {
            isSubmitting.value = false;
        }
    }
};

const closeCreateModal = () => {
    resetForm();
    createModalOpened.value = false;
};

function resetForm() {
    Object.assign(form, {...initialState});
    errors.value = {};
}
</script>
