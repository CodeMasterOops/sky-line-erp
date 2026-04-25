<template>
    <VModal
        :show-modal="!!edit_expense_id"
        @close-click="closeEditModal"
        size="xl"
        title="Update Expense">
        <template #modal-body>
            <VLoader v-if="expense.loading" loader-type="progress"/>
            <form @submit.prevent="updateExpense(expense.data.id)" class="row g-3">
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

                <div class="col-12">
                    <div class="card border bg-light">
                        <div class="card-header py-2 px-3">
                            <strong class="small">TDS (Optional)</strong>
                        </div>
                        <div class="card-body py-2 px-3 row g-2">
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

                <div class="col-12 text-end">
                    <button @click="closeEditModal" class="btn btn-danger me-1" type="button">
                        Close
                    </button>
                    <VButton v-if="isDraft" :loading="isSubmitting"/>
                    <button v-else type="button" class="btn btn-secondary" disabled>
                        Approved
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

const edit_expense_id = defineModel('expense_id');

const {expense} = storeToRefs(expenseStore);
const {accounts} = storeToRefs(accountStore);
const {parties} = storeToRefs(partyStore);
const {taxes} = storeToRefs(taxStore);

onMounted(() => {
    accountStore.getAccounts();
    partyStore.getParties({filter: {type: 'supplier'}});
    taxStore.getTaxes();
});

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
    date: '',
    due_date: '',
    party_id: '',
    reference_no: '',
    remarks: '',
    status: 'draft',
    tds_category: '',
    tds_rate: '',
    gross_amount: '',
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

watch(() => form.tds_category, (cat) => {
    form.tds_rate = cat && TDS_RATES[cat] != null ? String(TDS_RATES[cat]) : '';
});

const computedTdsAmount = computed(() => {
    const gross = Number(form.gross_amount || 0);
    const rate = Number(form.tds_rate || 0);
    if (!gross || !rate) return '0.00';
    return (gross * rate / 100).toFixed(2);
});

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

watch(() => edit_expense_id.value, async (id) => {
    if (id) {
        await expenseStore.getExpense(id);
        Object.keys(form).forEach(key => {
            if (key === 'items') {
                form.items = (expense.value.data.items || []).map(item => ({
                    account_id: item.account_id || '',
                    amount: item.amount || '',
                    tax_id: item.tax_id || '',
                    discount_amount: item.discount_amount || '',
                }));
            } else {
                form[key] = expense.value.data[key] || '';
            }
        });
    }
});

const isDraft = computed(() => expense.value.data.status === 'draft');

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

const updateExpense = async (id) => {
    if (!isDraft.value) {
        return;
    }
    let validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            syncLineItems();
            let res = await expenseStore.updateExpense(id, form);
            toast(res.status, res.data.message);
            closeEditModal();
        } catch (e) {
            showErrors(e);
        } finally {
            isSubmitting.value = false;
        }
    }
};

const closeEditModal = () => {
    resetForm();
    edit_expense_id.value = '';
};

function resetForm() {
    Object.assign(form, {...initialState});
    errors.value = {};
}
</script>
