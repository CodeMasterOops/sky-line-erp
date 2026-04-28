<template>
    <VModal
        :show-modal="!!edit_quotation_id"
        @close-click="closeEditModal"
        modal-class="large-modal"
        title="Update Quotation">
        <template #modal-body>
            <VLoader v-if="quotation.loading" loader-type="progress"/>
            <form @submit.prevent="updateQuotation(quotation.data.id)" class="row g-3">
                <div class="col-md-6">
                    <VInput
                        id="quotation_date"
                        input-type="date"
                        v-model="form.quotation_date"
                        label="Quotation Date"
                        @validate="validateField('quotation_date')"
                        :error="errors.quotation_date"
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        id="expiry_date"
                        input-type="date"
                        v-model="form.expiry_date"
                        label="Expiry Date"
                        @validate="validateField('expiry_date')"
                        :error="errors.expiry_date"
                    />
                </div>
                <div class="col-md-6">
                    <VSelect
                        id="party_id"
                        v-model="form.party_id"
                        :options="parties.data"
                        label="Customer"
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
                                <th>Product Variant</th>
                                <th style="width: 120px;">Quantity</th>
                                <th style="width: 140px;" title="Unit selling rate">Rate</th>
                                <th style="width: 160px;">Tax</th>
                                <th style="width: 200px;" title="Line discount (fixed or %)">Discount</th>
                                <th style="width: 60px;">Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr
                                v-for="(item, index) in form.items"
                                :key="item.id != null && item.id !== '' ? `line-${item.id}` : `n-${index}`"
                                v-memo="[
                                    item.id,
                                    item.product_variant_id,
                                    item.quantity,
                                    item.rate,
                                    item.line_discount_type,
                                    item.line_discount_value,
                                    item.tax_id,
                                    isDraft,
                                ]">
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
                                <td :class="{'qt-discount-cell': isDraft}">
                                    <template v-if="isDraft">
                                        <VDiscountAmountTypeGroup
                                            :input-id="`qt_edit_line_disc_${item.id ?? index}`"
                                            :input-aria-label="`Line ${index + 1} discount`"
                                            v-model="form.items[index].line_discount_value"
                                            v-model:discount-type="form.items[index].line_discount_type"
                                            :error="errors[`items[${index}].line_discount_value`]"
                                            :disabled="isSubmitting"
                                            extra-group-class="qt-discount-input-group"
                                            compact-toggle
                                            @blur="validateField(`items[${index}].line_discount_value`)"
                                            @update:discount-type="
                                                () => {
                                                    validateField(`items[${index}].line_discount_type`);
                                                    validateField(`items[${index}].line_discount_value`);
                                                }
                                            "
                                        />
                                    </template>
                                    <span v-else>{{ formatMoney(lineDiscountMoneyFromItem(item)) }}</span>
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
                                <span>Sub total</span>
                                <strong>{{ summary.subtotal }}</strong>
                            </div>
                            <template v-if="isDraft">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border-top pt-2 mt-2">
                                    <span>Order discount</span>
                                    <div class="qt-order-disc">
                                        <VDiscountAmountTypeGroup
                                            v-model="form.order_discount_value"
                                            v-model:discount-type="form.order_discount_type"
                                            :error="errors.order_discount_value"
                                            input-id="qt_edit_order_discount_value"
                                            input-aria-label="Order-level discount"
                                            :disabled="isSubmitting"
                                            extra-group-class="qt-order-disc-input-group"
                                            compact-toggle
                                            @blur="validateField('order_discount_value')"
                                            @update:discount-type="
                                                () => {
                                                    validateField('order_discount_type');
                                                    validateField('order_discount_value');
                                                }
                                            "
                                        />
                                    </div>
                                    <strong class="ms-auto">{{ summary.totalDiscount }}</strong>
                                </div>
                            </template>
                            <div v-else class="d-flex justify-content-between border-top pt-2 mt-2">
                                <span>Discount (lines + order)</span>
                                <strong>{{ summary.totalDiscount }}</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Tax</span>
                                <strong>{{ summary.tax }}</strong>
                            </div>
                            <div class="d-flex justify-content-between border-top pt-2 mt-2">
                                <span>Grand total</span>
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
                    <VButton v-if="isDraft" :loading="isSubmitting" :disabled="isSubmitting"/>
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
import {useProductStore} from '@/stores/admin/inventory/product.js';
import {usePartyStore} from '@/stores/admin/party.js';
import {useTaxStore} from '@/stores/admin/setting/tax.js';
import {useQuotationStore} from '@/stores/admin/sales/quotation.js';
import {lineDiscountMoneyFromItem} from '@/composables/purchaseOrderTotals.js';
import {useLineOrderDiscountTotals} from '@/composables/useLineOrderDiscountTotals.js';
import {useLineItemTaxOptions} from '@/composables/useLineItemTaxOptions.js';
import VDiscountAmountTypeGroup from '@/components/base/VDiscountAmountTypeGroup.vue';

const quotationStore = useQuotationStore();
const productStore = useProductStore();
const partyStore = usePartyStore();
const taxStore = useTaxStore();

const edit_quotation_id = defineModel('quotation_id');

const {quotation} = storeToRefs(quotationStore);
const {productVariants} = storeToRefs(productStore);
const {parties} = storeToRefs(partyStore);
const {taxes} = storeToRefs(taxStore);

const lineTaxOptions = useLineItemTaxOptions(taxes);

onMounted(() => {
    productStore.getProductVariants();
    partyStore.getParties({filter: {type: 'customer'}});
    taxStore.getTaxes();
});

const emptyLine = () => ({
    id: '',
    product_variant_id: '',
    unit_id: '',
    quantity: '',
    rate: '',
    tax_id: '',
    line_discount_type: 'fixed',
    line_discount_value: '0',
});

const initialState = {
    quotation_date: '',
    expiry_date: '',
    party_id: '',
    remarks: '',
    status: 'draft',
    order_discount_type: 'fixed',
    order_discount_value: '0',
    items: [emptyLine()],
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

const {calcLineTax, summary, syncTaxAmounts} = useLineOrderDiscountTotals({
    form,
    taxes,
});

const formatMoney = (value) => {
    if (value === '' || value === null || value === undefined) {
        return '—';
    }
    return Number(value).toFixed(2);
};

const addItem = () => {
    form.items.push(emptyLine());
};

const removeItem = (index) => {
    if (form.items.length === 1) {
        return;
    }
    form.items.splice(index, 1);
};

watch(
    () => edit_quotation_id.value,
    async (id) => {
        if (id) {
            await quotationStore.getQuotation(id);
            const data = quotation.value.data;
            const odv = data.order_discount_value;
            form.quotation_date = data.quotation_date || '';
            form.expiry_date = data.expiry_date || '';
            form.party_id = data.party_id || '';
            form.remarks = data.remarks || '';
            form.status = data.status || 'draft';
            form.order_discount_type = data.order_discount_type || 'fixed';
            form.order_discount_value = odv != null && odv !== '' ? String(odv) : '0';
            const rows = (data.items || []).length ? data.items : [{}];
            form.items = rows.map((item) => ({
                id: item.id ?? '',
                product_variant_id: item.product_variant_id || '',
                unit_id: item.unit_id || '',
                quantity: item.quantity != null && item.quantity !== '' ? String(item.quantity) : '',
                rate: item.rate != null && item.rate !== '' ? String(item.rate) : '',
                tax_id: item.tax_id || '',
                line_discount_type: item.line_discount_type || 'fixed',
                line_discount_value:
                    item.line_discount_value != null && item.line_discount_value !== ''
                        ? String(item.line_discount_value)
                        : '0',
            }));
        }
    }
);

const isDraft = computed(() => quotation.value.data.status === 'draft');

const validations = object({
    quotation_date: string().required('Quotation date is required.'),
    expiry_date: string().nullable(),
    party_id: string().nullable(),
    order_discount_type: string().nullable(),
    order_discount_value: string().nullable(),
    items: array()
        .of(
            object({
                product_variant_id: string().required('Product is required.'),
                quantity: string().required('Quantity is required.'),
                rate: string().required('Rate is required.'),
                unit_id: string().nullable(),
                tax_id: string().nullable(),
                line_discount_type: string().nullable(),
                line_discount_value: string().nullable(),
            })
        )
        .min(1, 'At least one item is required.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const getVariantById = (id) => {
    const numericId = parseInt(id, 10);
    return productVariants.value.data.find((v) => v.id === numericId);
};

const setRate = (index, value) => {
    const variant = getVariantById(value);
    if (variant) {
        form.items[index].rate = variant.sales_price ?? '';
        form.items[index].unit_id = variant.unit_id ?? '';
    }
};

const buildQuotationPayload = () => {
    syncTaxAmounts();
    return {
        quotation_date: form.quotation_date,
        expiry_date: form.expiry_date || null,
        party_id: form.party_id || null,
        remarks: form.remarks,
        status: form.status,
        order_discount_type: form.order_discount_type || 'fixed',
        order_discount_value: form.order_discount_value ?? '0',
        items: form.items.map((item, index) => ({
            product_variant_id: item.product_variant_id,
            unit_id: item.unit_id || null,
            quantity: item.quantity,
            rate: item.rate,
            tax_id: item.tax_id || null,
            tax_amount: calcLineTax(item, index),
            line_discount_type: item.line_discount_type || 'fixed',
            line_discount_value: item.line_discount_value ?? '0',
            discount_amount: String(lineDiscountMoneyFromItem(item)),
        })),
    };
};

const updateQuotation = async (id) => {
    if (!isDraft.value) {
        return;
    }
    const validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            const res = await quotationStore.updateQuotation(id, buildQuotationPayload());
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
    edit_quotation_id.value = '';
};

function resetForm() {
    Object.assign(form, {...initialState, items: [emptyLine()]});
    errors.value = {};
}
</script>

<style scoped>
.qt-discount-cell {
    min-width: 9rem;
    position: relative;
    z-index: 2;
    overflow: visible;
    vertical-align: middle;
}
.qt-order-disc {
    min-width: 0;
    max-width: 12rem;
    flex: 1 1 auto;
}
</style>
