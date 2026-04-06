<template>
    <VModal
        :show-modal="!!createModalOpened"
        @close-click="createModalOpened = false"
        modal-class="large-modal"
        title="Add Bill">
        <template #modal-body>
            <form @submit.prevent="storeBillWithStatus('draft')" class="row g-3">
                <div class="col-md-6">
                    <VInput
                        id="bill_date"
                        input-type="date"
                        v-model="form.bill_date"
                        label="Bill Date"
                        @validate="validateField('bill_date')"
                        :error="errors.bill_date"
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        id="due_date"
                        input-type="date"
                        v-model="form.due_date"
                        label="Due Date"
                        @validate="validateField('due_date')"
                        :error="errors.due_date"
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
                                <th style="width: 160px;">Unit</th>
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
                                    <VSelect
                                        v-model="form.items[index].unit_id"
                                        :options="units.data"
                                        @validate="validateField(`items[${index}].unit_id`)"
                                        :error="errors[`items[${index}].unit_id`]"
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
                        @click="storeBillWithStatus('draft')">
                        Create
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary"
                        :disabled="isSubmitting"
                        @click="storeBillWithStatus('approved')">
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
import {useUnitStore} from '@/stores/admin/inventory/unit.js';
import {useProductStore} from '@/stores/admin/inventory/product.js';
import {usePartyStore} from '@/stores/admin/party.js';
import {useTaxStore} from '@/stores/admin/setting/tax.js';
import {useWarehouseStore} from '@/stores/admin/inventory/warehouse.js';
import {useBillStore} from '@/stores/admin/purchase/bill.js';

const billStore = useBillStore();
const unitStore = useUnitStore();
const productStore = useProductStore();
const partyStore = usePartyStore();
const taxStore = useTaxStore();
const warehouseStore = useWarehouseStore();

const createModalOpened = defineModel('createModalOpened');

const {units} = storeToRefs(unitStore);
const {productVariants} = storeToRefs(productStore);
const {parties} = storeToRefs(partyStore);
const {taxes} = storeToRefs(taxStore);
const {warehouses} = storeToRefs(warehouseStore);

onMounted(() => {
    unitStore.getUnits();
    productStore.getProductVariants();
    partyStore.getParties({filter: {type: 'supplier'}});
    taxStore.getTaxes();
    warehouseStore.getWarehouses();
});

const initialState = {
    bill_date: new Date().toISOString().slice(0, 10),
    due_date: '',
    party_id: '',
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

const validations = object({
    bill_date: string().required('Bill date is required.'),
    due_date: string().nullable(),
    party_id: string().nullable(),
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

const storeBillWithStatus = async (status) => {
    form.status = status;
    let validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            syncLineItems();
            let res = await billStore.storeBill(form);
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
