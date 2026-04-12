<template>
    <VModal
        :show-modal="!!createModalOpened"
        @close-click="createModalOpened = false"
        size="xl"
        title="Add Expense">
        <template #modal-body>
            <form @submit.prevent="storeExpenseWithStatus('draft')" class="row g-3">
                <div class="col-md-6">
                    <VDatepicker
                        id="date"
                        v-model="form.date"
                        label="Date"
                        @validate="validateField('date')"
                        :error="errors.date"
                    />
                </div>
                <div class="col-md-6">
                    <VDatepicker
                        id="due_date"
                        v-model="form.due_date"
                        label="Due Date"
                        @validate="validateField('due_date')"
                        :error="errors.due_date"
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
                <div class="col-md-6">
                    <VInput
                        id="reference_no"
                        v-model="form.reference_no"
                        label="Reference No"
                        @validate="validateField('reference_no')"
                        :error="errors.reference_no"
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
                                <th style="width: 160px;">Tax</th>
                                <th style="width: 170px;">Discount Amount</th>
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
                                    <VSelect
                                        v-model="form.items[index].tax_id"
                                        :options="taxes.data"
                                        @validate="validateField(`items[${index}].tax_id`)"
                                        :error="errors[`items[${index}].tax_id`]"
                                    />
                                </td>
                                <td>
                                    <VInput
                                        input-type="number"
                                        v-model="form.items[index].discount_amount"
                                        @validate="validateField(`items[${index}].discount_amount`)"
                                        :error="errors[`items[${index}].discount_amount`]"
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
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="addItem">
                        Add Item
                    </button>
                </div>

                <div class="col-12">
                    <div class="card bg-light">
                        <div class="card-body py-2">
                            <div class="d-flex justify-content-between">
                                <span>Sub Total</span>
                                <strong>{{ summary.subtotal }}</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Discount</span>
                                <strong>{{ summary.discount }}</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Tax</span>
                                <strong>{{ summary.tax }}</strong>
                            </div>
                            <div class="d-flex justify-content-between border-top pt-2 mt-2">
                                <span>Grand Total</span>
                                <strong>{{ summary.grandTotal }}</strong>
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

                <div class="col-12 text-end">
                    <button @click="closeCreateModal" class="btn btn-danger me-1" type="button">
                        Close
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline-primary me-1"
                        :disabled="isSubmitting"
                        @click="storeExpenseWithStatus('draft')">
                        Create
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary"
                        :disabled="isSubmitting"
                        @click="storeExpenseWithStatus('approved')">
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
import {array, object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {useAccountStore} from '@/stores/admin/accounting/account.js';
import {usePartyStore} from '@/stores/admin/party.js';
import {useTaxStore} from '@/stores/admin/setting/tax.js';
import {useExpenseStore} from '@/stores/admin/purchase/expense.js';

const expenseStore = useExpenseStore();
const accountStore = useAccountStore();
const partyStore = usePartyStore();
const taxStore = useTaxStore();

const createModalOpened = defineModel('createModalOpened');

const {accounts} = storeToRefs(accountStore);
const {parties} = storeToRefs(partyStore);
const {taxes} = storeToRefs(taxStore);

onMounted(() => {
    accountStore.getAccounts();
    partyStore.getParties({filter: {type: 'supplier'}});
    taxStore.getTaxes();
});

const initialState = {
    date: new Date().toISOString().slice(0, 10),
    due_date: '',
    party_id: '',
    reference_no: '',
    remarks: '',
    status: 'draft',
    items: [
        {
            account_id: '',
            amount: '',
            tax_id: '',
            discount_amount: '',
        }
    ],
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

const addItem = () => {
    form.items.push({
        account_id: '',
        amount: '',
        tax_id: '',
        discount_amount: '',
    });
};

const removeItem = (index) => {
    if (form.items.length === 1) return;
    form.items.splice(index, 1);
};

const validations = object({
    date: string().required('Date is required.'),
    due_date: string().nullable(),
    party_id: string().nullable(),
    reference_no: string().nullable(),
    items: array().of(
        object({
            account_id: string().required('Account is required.'),
            amount: string().required('Amount is required.'),
            tax_id: string().nullable(),
            discount_amount: string().nullable(),
        })
    ).min(1, 'At least one item is required.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const getTaxRate = (taxId) => {
    if (!taxId) return 0;
    const numericId = parseInt(taxId, 10);
    const tax = taxes.value.data.find(t => t.id === numericId);
    return tax ? Number(tax.rate || 0) : 0;
};

const summary = computed(() => {
    let subtotal = 0;
    let discount = 0;
    let tax = 0;

    form.items.forEach((item) => {
        const amount = Number(item.amount || 0);
        const lineDiscount = Number(item.discount_amount || 0);
        const taxRate = getTaxRate(item.tax_id);
        const taxable = Math.max(amount - lineDiscount, 0);
        const lineTax = taxable * taxRate / 100;

        subtotal += amount;
        discount += lineDiscount;
        tax += lineTax;
    });

    const grandTotal = subtotal - discount + tax;

    return {
        subtotal: subtotal.toFixed(2),
        discount: discount.toFixed(2),
        tax: tax.toFixed(2),
        grandTotal: grandTotal.toFixed(2),
    };
});

const syncLineItems = () => {
    form.items = form.items.map((item) => {
        const amount = Number(item.amount || 0);
        const lineDiscount = Number(item.discount_amount || 0);
        const taxRate = getTaxRate(item.tax_id);
        const taxable = Math.max(amount - lineDiscount, 0);
        const lineTax = taxable * taxRate / 100;

        return {
            ...item,
            tax_amount: lineTax,
        };
    });
};

const storeExpenseWithStatus = async (status) => {
    form.status = status;
    let validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            syncLineItems();
            let res = await expenseStore.storeExpense(form);
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
