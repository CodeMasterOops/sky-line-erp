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
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Expense Lines</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" @click="addItem">
                            <i class="ti ti-plus me-1"></i> Add Line
                        </button>
                    </div>
                    <div class="table-responsive no-pagination">
                        <table class="table datanew table-bordered mb-0 expense-lines-table">
                            <thead>
                            <tr>
                                <th class="exp-col-sn">SN</th>
                                <th>Account</th>
                                <th class="exp-col-amount">Amount</th>
                                <th class="exp-col-tax">Tax</th>
                                <th class="exp-col-discount">Discount</th>
                                <th class="text-center exp-col-action">Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-if="!form.items.length">
                                <td colspan="6" class="text-center text-muted py-4">
                                    Add at least one expense line.
                                </td>
                            </tr>
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
                                        input-class="form-control form-control-sm"
                                        v-model="form.items[index].amount"
                                        @validate="validateField(`items[${index}].amount`)"
                                        :error="errors[`items[${index}].amount`]"
                                    />
                                </td>
                                <td>
                                    <VSelect
                                        v-model="form.items[index].tax_id"
                                        select-class="form-select form-select-sm line-item-tax-select"
                                        :options="lineTaxOptions"
                                        @validate="validateField(`items[${index}].tax_id`)"
                                        :error="errors[`items[${index}].tax_id`]"
                                    />
                                </td>
                                <td>
                                    <VInput
                                        input-type="number"
                                        input-class="form-control form-control-sm"
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
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="col-lg-6 ms-auto">
                    <div class="total-order w-100 max-widthauto m-auto mb-4">
                        <ul>
                            <li>
                                <h4>Sub Total</h4>
                                <h5>{{ formatMoney(summary.subtotal) }}</h5>
                            </li>
                            <li>
                                <h4>Discount</h4>
                                <h5>{{ formatMoney(summary.discount) }}</h5>
                            </li>
                            <li>
                                <h4>Tax</h4>
                                <h5>{{ formatMoney(summary.tax) }}</h5>
                            </li>
                            <li>
                                <h4>Grand Total</h4>
                                <h5>{{ formatMoney(summary.grandTotal) }}</h5>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input
                            type="checkbox"
                            class="form-check-input"
                            id="show_tds_create"
                            v-model="showTds"
                        />
                        <label class="form-check-label" for="show_tds_create">
                            Apply TDS (Tax Deducted at Source)
                        </label>
                    </div>
                </div>

                <div v-if="showTds" class="col-12">
                    <div class="card border">
                        <div class="card-header py-2 px-3">
                            <strong class="small">TDS Details</strong>
                        </div>
                        <div class="card-body border-0 py-2 px-3 row g-2">
                            <div class="col-md-6">
                                <VSelect
                                    label="TDS Category"
                                    select-class="form-select form-select-sm"
                                    v-model="form.tds_category"
                                    :options="tdsCategories"
                                />
                            </div>
                            <div class="col-md-3">
                                <VInput
                                    label="TDS Rate (%)"
                                    input-class="form-control form-control-sm"
                                    v-model="form.tds_rate"
                                    :disabled="true"
                                    placeholder="Auto"
                                />
                            </div>
                            <div class="col-md-3">
                                <VInput
                                    label="Gross Amount"
                                    input-type="number"
                                    input-class="form-control form-control-sm"
                                    v-model="form.gross_amount"
                                    placeholder="0.00"
                                />
                            </div>
                            <div v-if="form.tds_category" class="col-md-6">
                                <VInput
                                    label="TDS Amount (computed)"
                                    input-class="form-control form-control-sm bg-white"
                                    :model-value="computedTdsAmount"
                                    :disabled="true"
                                />
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

                <div class="col-12 d-flex justify-content-end gap-2">
                    <button @click="closeCreateModal" class="btn btn-cancel" type="button">
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline-primary"
                        :disabled="isSubmitting"
                        @click="storeExpenseWithStatus('draft')">
                        Save as Draft
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary"
                        :disabled="isSubmitting"
                        @click="storeExpenseWithStatus('approved')">
                        Save &amp; Approve
                    </button>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import {formatMoney} from '@/helpers/formatMoney.js';
import {computed, onMounted, reactive, ref, watch} from 'vue';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {array, object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {useAccountStore} from '@/stores/admin/accounting/account.js';
import {usePartyStore} from '@/stores/admin/party.js';
import {useTaxStore} from '@/stores/admin/settings/tax.js';
import {useExpenseStore} from '@/stores/admin/purchase/expense.js';
import {useEnumStore} from '@/stores/admin/enum.js';
import {useLineItemTaxOptions, parseTaxSelection, taxSelectionValue} from '@/composables/useLineItemTaxOptions.js';

const expenseStore = useExpenseStore();
const accountStore = useAccountStore();
const partyStore = usePartyStore();
const taxStore = useTaxStore();
const enumStore = useEnumStore();

const createModalOpened = defineModel('createModalOpened');

const {accounts} = storeToRefs(accountStore);
const {parties} = storeToRefs(partyStore);
const {taxes, taxGroups} = storeToRefs(taxStore);
const {tdsCategories} = storeToRefs(enumStore);

const lineTaxOptions = useLineItemTaxOptions(taxes, taxGroups);
const showTds = ref(false);

onMounted(() => {
    accountStore.getAccounts();
    partyStore.getParties({filter: {type: 'supplier'}});
    taxStore.getTaxes();
    taxStore.getTaxGroups();
    enumStore.getTdsCategories();
});

const initialState = {
    date: new Date().toISOString().slice(0, 10),
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

watch(() => form.tds_category, (value) => {
    const match = tdsCategories.value.find((c) => c.id === value);
    form.tds_rate = match ? String(match.rate) : '';
});

watch(showTds, (enabled) => {
    if (!enabled) {
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

const getLineTaxAmount = (item) => {
    const val = item.tax_id;
    if (!val) return 0;
    const amount = Number(item.amount || 0);
    const lineDiscount = Number(item.discount_amount || 0);
    const taxable = Math.max(amount - lineDiscount, 0);
    if (String(val).startsWith('group:')) {
        const groupId = parseInt(String(val).replace('group:', ''), 10);
        const group = (taxGroups.value?.data || []).find((g) => g.id === groupId);
        if (!group) return 0;
        const totalRate = (group.taxGroupMembers || []).reduce((sum, m) => sum + Number(m.tax?.rate || 0), 0);
        return taxable * totalRate / 100;
    }
    const numericId = parseInt(val, 10);
    const tax = (taxes.value?.data || []).find((t) => t.id === numericId);
    return taxable * Number(tax?.rate || 0) / 100;
};

const summary = computed(() => {
    let subtotal = 0;
    let discount = 0;
    let tax = 0;

    form.items.forEach((item) => {
        subtotal += Number(item.amount || 0);
        discount += Number(item.discount_amount || 0);
        tax += getLineTaxAmount(item);
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
    form.items = form.items.map((item) => ({
        ...item,
        tax_amount: getLineTaxAmount(item),
    }));
};

const buildExpensePayload = () => {
    syncLineItems();
    return {
        ...form,
        items: form.items.map((item) => ({
            account_id: item.account_id,
            amount: item.amount,
            ...parseTaxSelection(item.tax_id),
            tax_amount: item.tax_amount ?? 0,
            discount_amount: item.discount_amount || '0',
        })),
    };
};

const storeExpenseWithStatus = async (status) => {
    form.status = status;
    let validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await expenseStore.storeExpense(buildExpensePayload());
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
    showTds.value = false;
}
</script>

<style scoped>
.expense-lines-table th,
.expense-lines-table td {
    vertical-align: middle;
}

.exp-col-sn {
    width: 2.5rem;
}

.exp-col-amount,
.exp-col-discount {
    width: 10rem;
}

.exp-col-tax {
    min-width: 8rem;
    width: 10rem;
}

.exp-col-action {
    width: 3.5rem;
}
</style>
