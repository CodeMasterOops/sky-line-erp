<template>
    <VModal
        :show-modal="!!edit_voucher_id"
        @close-click="closeEditModal"
        modal-class="large-modal"
        title="Update Journal Voucher">
        <template #modal-body>
            <VLoader v-if="voucher.loading" loader-type="progress"/>
            <form @submit.prevent="updateVoucher(voucher.data.id)" class="row g-3">
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
                                <th style="width: 140px;">Dr Amount</th>
                                <th style="width: 140px;">Cr Amount</th>
                                <th>Remarks</th>
                                <th style="width: 60px;">Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="(item, index) in form.items" :key="index">
                                <td>{{ index + 1 }}</td>
                                <td style="width: 300px;min-width: 300px;">
                                    <VMultiselect
                                        v-model="form.items[index].account_id"
                                        :options="accounts.data"
                                        @validate="validateField(`items[${index}].account_id`)"
                                        :error="errors[`items[${index}].account_id`]"
                                    />
                                </td>
                                <td>
                                    <VInput
                                        input-type="number"
                                        v-model="form.items[index].dr_amount"
                                        @on-input="setLedgerAmount('dr',index)"
                                        @validate="validateField(`items[${index}].dr_amount`)"
                                        :error="errors[`items[${index}].dr_amount`]"
                                    />
                                </td>
                                <td>
                                    <VInput
                                        input-type="number"
                                        v-model="form.items[index].cr_amount"
                                        @on-input="setLedgerAmount('cr',index)"
                                        @validate="validateField(`items[${index}].cr_amount`)"
                                        :error="errors[`items[${index}].cr_amount`]"
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
                                        :disabled="form.items.length === 2">
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
                            Total Dr: {{ totalDr.toFixed(2) }} | Total Cr: {{ totalCr.toFixed(2) }}
                        </div>
                    </div>
                    <div v-if="errors.items" class="text-danger small mt-2">
                        {{ errors.items }}
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
import {useJournalVoucherStore} from '@/stores/admin/accounting/journal-voucher.js';

const journalVoucherStore = useJournalVoucherStore();
const accountStore = useAccountStore();

const edit_voucher_id = defineModel('voucher_id');

const {voucher} = storeToRefs(journalVoucherStore);
const {accounts} = storeToRefs(accountStore);

onMounted(() => {
    accountStore.getAccounts();
});

const initialState = {
    reference_no: '',
    date: '',
    remarks: '',
    status: 'draft',
    items: [
        {
            account_id: '',
            dr_amount: '',
            cr_amount: '',
            remarks: '',
        },
        {
            account_id: '',
            dr_amount: '',
            cr_amount: '',
            remarks: '',
        }
    ],
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

const addItem = () => {
    form.items.push({
        account_id: '',
        dr_amount: '',
        cr_amount: '',
        remarks: '',
    });
};

const removeItem = (index) => {
    if (form.items.length === 2) return;
    form.items.splice(index, 1);
};

const setLedgerAmount = (type, index) => {
    const drAmount = form.items[index].dr_amount;
    const crAmount = form.items[index].cr_amount;
    if (type === "dr" && drAmount > 0) {
        form.items[index].cr_amount = 0;
    } else if (type === 'cr' && crAmount > 0) {
        form.items[index].dr_amount = 0;
    }
};

const parseAmount = (value) => {
    const parsed = parseFloat(value);
    return Number.isFinite(parsed) ? parsed : 0;
};

const totalDr = computed(() => form.items.reduce((sum, item) => sum + parseAmount(item.dr_amount), 0));
const totalCr = computed(() => form.items.reduce((sum, item) => sum + parseAmount(item.cr_amount), 0));

watch(() => edit_voucher_id.value, async (id) => {
    if (id) {
        await journalVoucherStore.getVoucher(id);
        Object.keys(form).forEach(key => {
            if (key === 'items') {
                form.items = (voucher.value.data.items || []).map(item => ({
                    account_id: item.account_id || '',
                    dr_amount: item.dr_amount || '',
                    cr_amount: item.cr_amount || '',
                    remarks: item.remarks || '',
                }));
            } else {
                form[key] = voucher.value.data[key] || '';
            }
        });
    }
});

const isDraft = computed(() => voucher.value.data.status === 'draft');

const itemSchema = object({
    account_id: string().required('Account is required.'),
    dr_amount: string().nullable(),
    cr_amount: string().nullable(),
    remarks: string().nullable(),
}).test('dr-cr', 'Each line must have either Dr or Cr amount.', (value) => {
    const dr = parseAmount(value?.dr_amount);
    const cr = parseAmount(value?.cr_amount);
    return (dr > 0 && cr === 0) || (cr > 0 && dr === 0);
});

const validations = object({
    date: string().required('Date is required.'),
    reference_no: string().nullable(),
    items: array().of(itemSchema).min(2, 'At least two items are required.'),
}).test('balance', 'Total Dr amount must be equal to Total Cr amount.', function (value) {
    const items = value?.items || [];
    const totalDr = items.reduce((sum, item) => sum + parseAmount(item.dr_amount), 0);
    const totalCr = items.reduce((sum, item) => sum + parseAmount(item.cr_amount), 0);

    if (totalDr <= 0 || totalCr <= 0 || Math.abs(totalDr - totalCr) > 0.0001) {
        return this.createError({path: 'items', message: 'Total Dr amount must be equal to Total Cr amount.'});
    }
    return true;
});

const {errors, validateField, validateForm} = useYup(form, validations);

const updateVoucher = async (id) => {
    if (!isDraft.value) {
        return;
    }
    let validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await journalVoucherStore.updateVoucher(id, form);
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
    edit_voucher_id.value = '';
};

function resetForm() {
    Object.assign(form, {...initialState});
    errors.value = {};
}
</script>
