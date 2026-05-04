<template>
    <VModal
        :show-modal="!!createModalOpened"
        @close-click="closeCreateModal"
        size="xl"
        title="Add Sales Order">
        <template #modal-body>
            <div class="card">
                <div class="card-body border-0 p-0">
                    <form @submit.prevent="storeOrderWithStatus('draft')" class="row g-3">
                        <div class="col-lg-6 col-sm-6 col-12">
                                <VDatepicker
                                    id="order_date"
                                    input-type="date"
                                    v-model="form.order_date"
                                    label="Order Date"
                                    @validate="validateField('order_date')"
                                    :error="errors.order_date"
                                />
                        </div>
                        <div class="col-lg-6 col-sm-6 col-12">
                                <VMultiselect
                                    id="party_id"
                                    v-model="form.party_id"
                                    :options="parties.data"
                                    label="Customer"
                                    :filter-results="false"
                                    @validate="validateField('party_id')"
                                    @search-change="debouncedPartySearch"
                                    :error="errors.party_id"
                                />
                        </div>

                        <div class="col-12">
                            <ProductVariantSearchInput
                                label="Product"
                                required
                                @select="onVariantSelected"
                            />
                        </div>

                        <div class="col-12">
                            <div class="table-responsive no-pagination">
                                <table class="table datanew table-bordered mb-0 sales-order-lines-table">
                                    <thead>
                                    <tr>
                                        <th class="so-col-sn">SN</th>
                                        <th class="so-col-product">Product</th>
                                        <th class="so-col-qty">Qty</th>
                                        <th class="so-col-rate">Rate (sale)</th>
                                        <th class="so-col-disc">Discount</th>
                                        <th class="so-col-tax">Tax</th>
                                        <th class="text-center so-col-action">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-if="!form.items.length">
                                        <td colspan="7" class="text-center text-muted py-4">
                                            Search and select a product to add lines.
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="(item, index) in form.items"
                                        :key="`n-${index}-${item.product_variant_id}`"
                                        v-memo="[
                                            item.quantity,
                                            item.rate,
                                            item.line_discount_type,
                                            item.line_discount_value,
                                            item.tax_id,
                                        ]">
                                        <td>{{ index + 1 }}</td>
                                        <td
                                            class="text-start text-truncate so-col-product"
                                            :title="item.product_label">
                                            {{ item.product_label }}
                                        </td>
                                        <td>
                                            <VInput
                                                input-type="number"
                                                input-class="form-control form-control-sm"
                                                v-model="form.items[index].quantity"
                                                @validate="validateField(`items[${index}].quantity`)"
                                                :error="errors[`items[${index}].quantity`]"
                                            />
                                        </td>
                                        <td>
                                            <VInput
                                                input-type="number"
                                                input-class="form-control form-control-sm"
                                                v-model="form.items[index].rate"
                                                @validate="validateField(`items[${index}].rate`)"
                                                :error="errors[`items[${index}].rate`]"
                                            />
                                        </td>
                                        <td class="so-discount-cell">
                                            <VDiscountAmountTypeGroup
                                                :input-id="`so_create_line_disc_${index}`"
                                                :input-aria-label="`Line ${index + 1} discount`"
                                                v-model="form.items[index].line_discount_value"
                                                v-model:discount-type="form.items[index].line_discount_type"
                                                :error="errors[`items[${index}].line_discount_value`]"
                                                :disabled="isSubmitting"
                                                extra-group-class="so-discount-input-group"
                                                compact-toggle
                                                @blur="validateField(`items[${index}].line_discount_value`)"
                                                @update:discount-type="
                                                    () => {
                                                        validateField(`items[${index}].line_discount_type`);
                                                        validateField(`items[${index}].line_discount_value`);
                                                    }
                                                "
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
                                        <td class="text-center">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-danger"
                                                @click="removeItem(index)">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="col-lg-6 ms-auto">
                            <div class="card bg-light mb-4">
                                <div class="card-body py-2">
                                    <div class="d-flex justify-content-between">
                                        <span>Sub total</span>
                                        <strong>{{ summary.subtotal }}</strong>
                                    </div>
                                    <div
                                        class="d-flex flex-wrap align-items-center justify-content-between gap-2 border-top pt-2 mt-2">
                                        <span>Discount</span>
                                        <div class="flex-grow-1" style="max-width: 14rem; min-width: 0">
                                            <VDiscountAmountTypeGroup
                                                v-model="form.order_discount_value"
                                                v-model:discount-type="form.order_discount_type"
                                                :error="errors.order_discount_value"
                                                input-id="so_create_order_discount_value"
                                                input-aria-label="Order-level discount"
                                                :disabled="isSubmitting"
                                                extra-group-class="so-order-disc-input-group w-100"
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
                            <div class="input-blocks">
                                <VTextarea
                                    id="remarks"
                                    v-model="form.remarks"
                                    label="Remarks"
                                    @validate="validateField('remarks')"
                                    :error="errors.remarks"
                                />
                            </div>
                        </div>

                        <div class="col-12 text-end">
                            <button @click="closeCreateModal" class="btn btn-cancel add-cancel me-2" type="button">
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="btn btn-outline-primary me-2"
                                :disabled="isSubmitting"
                                @click="storeOrderWithStatus('draft')">
                                Create
                            </button>
                            <button
                                type="button"
                                class="btn btn-submit add-sale btn-primary"
                                :disabled="isSubmitting"
                                @click="storeOrderWithStatus('approved')">
                                Create &amp; Approve
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </VModal>
</template>

<script setup>
import {reactive, ref, watch} from 'vue';
import debounce from 'lodash/debounce';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {array, object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {usePartyStore} from '@/stores/admin/party.js';
import {useTaxStore} from '@/stores/admin/settings/tax.js';
import {useSalesOrderStore} from '@/stores/admin/sales/sales-order.js';
import {useQuotationStore} from '@/stores/admin/sales/quotation.js';
import {useDateHelper} from '@/composables/dateHelper.js';
import {lineDiscountMoneyFromItem, mergePoOrderDiscountIntoLineDiscounts} from '@/composables/purchaseOrderTotals.js';
import {useLineOrderDiscountTotals} from '@/composables/useLineOrderDiscountTotals.js';
import {useLineItemTaxOptions} from '@/composables/useLineItemTaxOptions.js';
import VDiscountAmountTypeGroup from '@/components/base/VDiscountAmountTypeGroup.vue';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';

const salesOrderStore = useSalesOrderStore();
const quotationStore = useQuotationStore();
const partyStore = usePartyStore();
const taxStore = useTaxStore();

const {currentAdDate} = useDateHelper();

const createModalOpened = defineModel('createModalOpened');
const quotationId = defineModel('quotationId');
const emit = defineEmits(['created']);

const {parties} = storeToRefs(partyStore);
const {taxes} = storeToRefs(taxStore);
const {quotation} = storeToRefs(quotationStore);

const lineTaxOptions = useLineItemTaxOptions(taxes);

const debouncedPartySearch = debounce((query) => {
    partyStore.getParties({
        filter: {
            type: 'customer',
            limit: 50,
            search: query || '',
        },
    });
}, 300);

watch(
    createModalOpened,
    (opened) => {
        if (opened) {
            resetForm();
            taxStore.getTaxes();
            partyStore.getParties({
                filter: {
                    type: 'customer',
                    limit: 50,
                    search: '',
                },
            });
            if (quotationId.value) {
                loadFromQuotation();
            }
        }
    },
    {flush: 'post'}
);

const getInitialState = () => ({
    order_date: currentAdDate,
    party_id: '',
    quotation_id: '',
    remarks: '',
    status: 'draft',
    order_discount_type: 'fixed',
    order_discount_value: '0',
    items: [],
});

const form = reactive({...getInitialState()});
const isSubmitting = ref(false);

const loadFromQuotation = async () => {
    await quotationStore.getQuotation(quotationId.value);
    const data = quotation.value.data;
    form.party_id = data.party_id || '';
    form.quotation_id = quotationId.value;
    form.remarks = data.remarks || '';

    const items = data.items || [];
    const hasLineTypes = items.some((it) => it.line_discount_type != null);

    if (hasLineTypes) {
        form.order_discount_type = data.order_discount_type || 'fixed';
        form.order_discount_value =
            data.order_discount_value != null && data.order_discount_value !== ''
                ? String(data.order_discount_value)
                : '0';

        items.forEach((item) => {
            form.items.push({
                product_variant_id: item.product_variant_id,
                product_label: variantLabel(item.product_variant),
                purchase_snapshot: item.product_variant?.purchase_price || 0,
                unit_id: item.unit_id ?? '',
                quantity: String(item.quantity),
                rate: String(item.rate),
                tax_id: item.tax_id || '',
                line_discount_type: item.line_discount_type || 'fixed',
                line_discount_value: String(item.line_discount_value ?? 0),
            });
        });

        return;
    }

    const mergedDiscounts = mergePoOrderDiscountIntoLineDiscounts(items, data.order_discount_amount);
    form.order_discount_type = 'fixed';
    form.order_discount_value = '0';
    items.forEach((item, i) => {
        form.items.push({
            product_variant_id: item.product_variant_id,
            product_label: variantLabel(item.product_variant),
            purchase_snapshot: item.product_variant?.purchase_price || 0,
            unit_id: item.unit_id ?? '',
            quantity: String(item.quantity),
            rate: String(item.rate),
            tax_id: item.tax_id || '',
            line_discount_type: 'fixed',
            line_discount_value: String(mergedDiscounts[i] ?? item.discount_amount ?? 0),
        });
    });
};

function variantLabel(variant) {
    let label = variant.name || '';
    if (variant.sku) {
        label += ` (${variant.sku})`;
    }
    return label;
}

/** Selling rate default: sales price, else purchase price, else 0 — always string for VInput. */
function defaultLineRateString(variant) {
    const n = Number(variant.sales_price ?? variant.purchase_price ?? 0);
    return String(Number.isFinite(n) ? n : 0);
}

const onVariantSelected = (variant) => {
    const vid = variant.id;
    const existing = form.items.findIndex((i) => String(i.product_variant_id) === String(vid));
    if (existing !== -1) {
        const nextQty = Number(form.items[existing].quantity || 0) + 1;
        form.items[existing].quantity = String(nextQty);
        return;
    }
    form.items.push({
        product_variant_id: vid,
        product_label: variantLabel(variant),
        purchase_snapshot: variant.purchase_price ?? 0,
        unit_id: variant.unit_id ?? '',
        quantity: '1',
        rate: defaultLineRateString(variant),
        tax_id: '',
        line_discount_type: 'fixed',
        line_discount_value: '0',
    });
};

const removeItem = (index) => {
    form.items.splice(index, 1);
};

const validations = object({
    order_date: string().required('Order date is required.'),
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

const {calcLineTax, summary, syncTaxAmounts} = useLineOrderDiscountTotals({
    form,
    taxes,
});

const buildOrderPayload = () => {
    syncTaxAmounts();
    return {
        order_date: form.order_date,
        party_id: form.party_id || null,
        quotation_id: form.quotation_id || null,
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

const storeOrderWithStatus = async (status) => {
    form.status = status;
    const validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            const res = await salesOrderStore.storeOrder(buildOrderPayload());
            toast(res.status, res.data.message);
            emit('created', res.data.data);
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
    quotationId.value = '';
    createModalOpened.value = false;
};

function resetForm() {
    Object.assign(form, getInitialState());
    errors.value = {};
}
</script>

<style scoped>
.sales-order-lines-table :deep(.form-control),
.sales-order-lines-table :deep(.form-select) {
    min-width: 4.25rem;
}
.sales-order-lines-table th,
.sales-order-lines-table td {
    vertical-align: middle;
}
.sales-order-lines-table .so-col-product {
    min-width: 11rem;
    max-width: 16rem;
}
.sales-order-lines-table .so-col-tax {
    min-width: 7.5rem;
}
.sales-order-lines-table .so-col-sn {
    width: 2.5rem;
}
.sales-order-lines-table .so-col-action {
    width: 3rem;
}
.sales-order-lines-table .so-discount-cell {
    min-width: 9rem;
    position: relative;
    z-index: 2;
    overflow: visible;
}
</style>
