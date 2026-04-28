<template>
    <VModal
        :show-modal="!!edit_debit_note_id"
        @close-click="closeEditModal"
        modal-class="large-modal"
        title="Update Debit Note">
        <template #modal-body>
            <VLoader v-if="debitNote.loading" loader-type="progress"/>
            <form @submit.prevent="updateDebitNote(debitNote.data.id)" class="row g-3">
                <div class="col-md-6">
                    <VInput
                        id="debit_note_date"
                        input-type="date"
                        v-model="form.debit_note_date"
                        label="Debit Note Date"
                        @validate="validateField('debit_note_date')"
                        :error="errors.debit_note_date"
                    />
                </div>
                <div class="col-md-6">
                    <VSelect
                        id="party_id"
                        v-model="form.party_id"
                        :options="parties.data"
                        label="Supplier"
                        @validate="validateField('party_id')"
                        :error="errors.party_id"
                    />
                </div>
                <div class="col-md-6">
                    <VSelect
                        id="bill_id"
                        v-model="form.bill_id"
                        :options="billOptions"
                        name-prop="bill_no"
                        label="Bill"
                        @validate="validateField('bill_id')"
                        :error="errors.bill_id"
                    />
                </div>
                <div class="col-md-6">
                    <VSelect
                        id="warehouse_id"
                        v-model="form.warehouse_id"
                        :options="warehouses.data"
                        label="Warehouse"
                        @validate="validateField('warehouse_id')"
                        :error="errors.warehouse_id"
                    />
                </div>

                <div class="col-12">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                            <tr>
                                <th style="width: 50px;">SN</th>
                                <th>Product Variant</th>
                                <th style="width: 120px;">Quantity</th>
                                <th style="width: 140px;">Rate</th>
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
                                        v-model="form.items[index].product_variant_id"
                                        :options="productVariants.data"
                                        @onInput="setRate(index, $event)"
                                        @validate="validateField(`items[${index}].product_variant_id`)"
                                        :error="errors[`items[${index}].product_variant_id`]"
                                    />
                                </td>
                                <td>
                                    <VInput
                                        input-type="number"
                                        v-model="form.items[index].quantity"
                                        @validate="validateField(`items[${index}].quantity`)"
                                        :error="errors[`items[${index}].quantity`]"
                                    />
                                </td>
                                <td>
                                    <VInput
                                        input-type="number"
                                        v-model="form.items[index].rate"
                                        @validate="validateField(`items[${index}].rate`)"
                                        :error="errors[`items[${index}].rate`]"
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
import {computed, nextTick, onMounted, reactive, ref, watch} from 'vue';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {array, object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {useProductStore} from '@/stores/admin/inventory/product.js';
import {usePartyStore} from '@/stores/admin/party.js';
import {useTaxStore} from '@/stores/admin/setting/tax.js';
import {useWarehouseStore} from '@/stores/admin/inventory/warehouse.js';
import {useBillStore} from '@/stores/admin/purchase/bill.js';
import {useDebitNoteStore} from '@/stores/admin/purchase/debit-note.js';
import {useLineItemTaxOptions} from '@/composables/useLineItemTaxOptions.js';

const billStore = useBillStore();
const debitNoteStore = useDebitNoteStore();
const productStore = useProductStore();
const partyStore = usePartyStore();
const taxStore = useTaxStore();
const warehouseStore = useWarehouseStore();

const edit_debit_note_id = defineModel('debit_note_id');

const {debitNote} = storeToRefs(debitNoteStore);
const {productVariants} = storeToRefs(productStore);
const {parties} = storeToRefs(partyStore);
const {taxes} = storeToRefs(taxStore);
const {warehouses} = storeToRefs(warehouseStore);
const {bills} = storeToRefs(billStore);

const lineTaxOptions = useLineItemTaxOptions(taxes);

onMounted(() => {
    productStore.getProductVariants();
    partyStore.getParties({filter: {type: 'supplier'}});
    taxStore.getTaxes();
    warehouseStore.getWarehouses();
    billStore.getBills({filter: {limit: 1000}});
});

const initialState = {
    debit_note_date: '',
    party_id: '',
    bill_id: '',
    warehouse_id: '',
    remarks: '',
    status: 'draft',
    items: [
        {
            product_variant_id: '',
            unit_id: '',
            quantity: '',
            rate: '',
            tax_id: '',
            discount_amount: '',
        }
    ],
};

const form = reactive({...initialState});
const isSubmitting = ref(false);
const isHydratingDebit = ref(false);

const addItem = () => {
    form.items.push({
        product_variant_id: '',
        unit_id: '',
        quantity: '',
        rate: '',
        tax_id: '',
        discount_amount: '',
    });
};

const removeItem = (index) => {
    if (form.items.length === 1) return;
    form.items.splice(index, 1);
};

watch(() => edit_debit_note_id.value, async (id) => {
    if (id) {
        isHydratingDebit.value = true;
        await debitNoteStore.getDebitNote(id);
        const d = debitNote.value.data;
        const whId = d.items?.[0]?.warehouse_id;
        Object.keys(form).forEach(key => {
            if (key === 'items') {
                form.items = (d.items || []).map(item => ({
                    product_variant_id: item.product_variant_id || '',
                    unit_id: item.unit_id || '',
                    quantity: item.quantity || '',
                    rate: item.rate || '',
                    tax_id: item.tax_id || '',
                    discount_amount: item.discount_amount || '',
                }));
            } else if (key === 'warehouse_id') {
                form.warehouse_id = whId || '';
            } else {
                form[key] = d[key] || '';
            }
        });
        await nextTick();
        isHydratingDebit.value = false;
    }
});

const isDraft = computed(() => debitNote.value.data.status === 'draft');

const validations = object({
    debit_note_date: string().required('Debit note date is required.'),
    party_id: string().nullable(),
    bill_id: string().nullable(),
    warehouse_id: string().required('Warehouse is required.'),
    items: array().of(
        object({
            product_variant_id: string().required('Product is required.'),
            quantity: string().required('Quantity is required.'),
            rate: string().required('Rate is required.'),
            unit_id: string().nullable(),
            tax_id: string().nullable(),
            discount_amount: string().nullable(),
        })
    ).min(1, 'At least one item is required.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const getVariantById = (id) => {
    const numericId = parseInt(id, 10);
    return productVariants.value.data.find(v => v.id === numericId);
};

const setRate = (index, value) => {
    const variant = getVariantById(value);
    if (variant) {
        form.items[index].rate = variant.purchase_price ?? '';
        form.items[index].unit_id = variant.unit_id ?? '';
    }
};

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
        const qty = Number(item.quantity || 0);
        const rate = Number(item.rate || 0);
        const lineSubtotal = qty * rate;
        const lineDiscount = Number(item.discount_amount || 0);
        const taxRate = getTaxRate(item.tax_id);
        const taxable = Math.max(lineSubtotal - lineDiscount, 0);
        const lineTax = taxable * taxRate / 100;

        subtotal += lineSubtotal;
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
        const qty = Number(item.quantity || 0);
        const rate = Number(item.rate || 0);
        const lineSubtotal = qty * rate;
        const lineDiscount = Number(item.discount_amount || 0);
        const taxRate = getTaxRate(item.tax_id);
        const taxable = Math.max(lineSubtotal - lineDiscount, 0);
        const lineTax = taxable * taxRate / 100;

        return {
            ...item,
            warehouse_id: form.warehouse_id,
            tax_amount: lineTax,
        };
    });
};

const updateDebitNote = async (id) => {
    if (!isDraft.value) {
        return;
    }
    let validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            syncLineItems();
            let res = await debitNoteStore.updateDebitNote(id, form);
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
    edit_debit_note_id.value = '';
};

function resetForm() {
    isHydratingDebit.value = false;
    Object.assign(form, {...initialState});
    errors.value = {};
}

const billOptions = computed(() => bills.value.data || []);
</script>
