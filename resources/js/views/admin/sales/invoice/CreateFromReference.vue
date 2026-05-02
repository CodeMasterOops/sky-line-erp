<template>
    <VModal
        :show-modal="!!open"
        @close-click="closeModal"
        size="xl"
        title="Create Invoice">
        <template #modal-body>
            <VLoader v-if="loading" loader-type="progress"/>
            <div v-else class="card">
                <div class="card-body border-0 p-0">
                    <form @submit.prevent="storeInvoice('draft')" class="row g-3">
                        <div class="col-lg-6 col-sm-6 col-12">
                            <VDatepicker
                                id="invoice_date"
                                input-type="date"
                                v-model="form.invoice_date"
                                label="Invoice Date"
                                @validate="validateField('invoice_date')"
                                :error="errors.invoice_date"
                            />
                        </div>
                        <div class="col-lg-6 col-sm-6 col-12">
                            <VDatepicker
                                id="due_date"
                                input-type="date"
                                v-model="form.due_date"
                                label="Due Date"
                                @validate="validateField('due_date')"
                                :error="errors.due_date"
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
                        <div class="col-lg-6 col-sm-6 col-12">
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
                            <ProductVariantSearchInput
                                label="Product"
                                required
                                @select="onVariantSelected"
                            />
                        </div>

                        <div class="col-12">
                            <div class="table-responsive no-pagination">
                                <table class="table datanew table-bordered mb-0 invoice-ref-lines-table">
                                    <thead>
                                    <tr>
                                        <th class="inv-ref-col-sn">SN</th>
                                        <th class="inv-ref-col-product">Product</th>
                                        <th class="inv-ref-col-qty">Qty</th>
                                        <th class="inv-ref-col-rate">Rate (sale)</th>
                                        <th class="inv-ref-col-disc">Discount</th>
                                        <th class="inv-ref-col-tax">Tax</th>
                                        <th class="text-center inv-ref-col-action">Action</th>
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
                                            class="text-start text-truncate inv-ref-col-product"
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
                                        <td class="inv-ref-discount-cell">
                                            <VDiscountAmountTypeGroup
                                                :input-id="`inv_ref_line_disc_${index}`"
                                                :input-aria-label="`Line ${index + 1} discount`"
                                                v-model="form.items[index].line_discount_value"
                                                v-model:discount-type="form.items[index].line_discount_type"
                                                :error="errors[`items[${index}].line_discount_value`]"
                                                :disabled="isSubmitting"
                                                extra-group-class="inv-ref-discount-input-group"
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
                                                input-id="inv_ref_order_discount_value"
                                                input-aria-label="Order-level discount"
                                                :disabled="isSubmitting"
                                                extra-group-class="inv-ref-order-disc-input-group w-100"
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
                            <button @click="closeModal" class="btn btn-cancel add-cancel me-2" type="button">
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="btn btn-outline-primary me-2"
                                :disabled="isSubmitting"
                                @click="storeInvoice('draft')">
                                Create
                            </button>
                            <button
                                type="button"
                                class="btn btn-submit add-sale btn-primary"
                                :disabled="isSubmitting"
                                @click="storeInvoice('approved')">
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
import {apiAdmin} from '@/helpers/api.js';
import {usePartyStore} from '@/stores/admin/party.js';
import {useTaxStore} from '@/stores/admin/settings/tax.js';
import {useWarehouseStore} from '@/stores/admin/inventory/warehouse.js';
import {useInvoiceStore} from '@/stores/admin/sales/invoice.js';
import {useDateHelper} from '@/composables/dateHelper.js';
import {lineDiscountMoneyFromItem} from '@/composables/purchaseOrderTotals.js';
import {useLineOrderDiscountTotals} from '@/composables/useLineOrderDiscountTotals.js';
import {useLineItemTaxOptions} from '@/composables/useLineItemTaxOptions.js';
import VDiscountAmountTypeGroup from '@/components/base/VDiscountAmountTypeGroup.vue';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';

const invoiceStore = useInvoiceStore();
const partyStore = usePartyStore();
const taxStore = useTaxStore();
const warehouseStore = useWarehouseStore();

const open = defineModel('open');
const referenceId = defineModel('referenceId');
const referenceType = defineModel('referenceType');

const {currentAdDate} = useDateHelper();

const {parties} = storeToRefs(partyStore);
const {taxes} = storeToRefs(taxStore);
const {warehouses} = storeToRefs(warehouseStore);

const lineTaxOptions = useLineItemTaxOptions(taxes);

const loading = ref(false);

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
    open,
    (isOpen) => {
        if (isOpen) {
            taxStore.getTaxes();
            warehouseStore.getWarehouses();
            partyStore.getParties({
                filter: {
                    type: 'customer',
                    limit: 50,
                    search: '',
                },
            });
        }
    },
    {flush: 'post'}
);

function lineLabelFromRefItem(item) {
    const pv = item.product_variant;
    if (pv && pv.name) {
        return pv.sku ? `${pv.name} (${pv.sku})` : pv.name;
    }
    return item.product_variant_id ? `Variant #${item.product_variant_id}` : '';
}

function purchaseSnapshotFromRefItem(item) {
    const pv = item.product_variant;
    if (pv && pv.purchase_price != null) {
        return pv.purchase_price;
    }
    return 0;
}

const getInitialState = () => ({
    invoice_date: currentAdDate,
    due_date: '',
    party_id: '',
    warehouse_id: '',
    remarks: '',
    reference_type: '',
    reference_id: '',
    status: 'draft',
    order_discount_type: 'fixed',
    order_discount_value: '0',
    items: [],
});

const form = reactive({...getInitialState()});
const isSubmitting = ref(false);

function variantLabel(variant) {
    let label = variant.name || '';
    if (variant.sku) {
        label += ` (${variant.sku})`;
    }
    return label;
}

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
        tax_line_type: 'taxable',
        line_discount_type: 'fixed',
        line_discount_value: '0',
    });
};

const removeItem = (index) => {
    form.items.splice(index, 1);
};

const validations = object({
    invoice_date: string().required('Invoice date is required.'),
    due_date: string().nullable(),
    party_id: string().nullable(),
    warehouse_id: string().required('Warehouse is required.'),
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

const buildInvoicePayload = () => {
    syncTaxAmounts();
    const wid = form.warehouse_id || null;
    const refId = form.reference_id;
    const parsedRefId = refId !== '' && refId != null ? parseInt(String(refId), 10) : null;
    return {
        invoice_date: form.invoice_date,
        due_date: form.due_date || null,
        party_id: form.party_id || null,
        reference_type: form.reference_type || null,
        reference_id: Number.isFinite(parsedRefId) ? parsedRefId : null,
        remarks: form.remarks,
        status: form.status,
        order_discount_type: form.order_discount_type || 'fixed',
        order_discount_value: form.order_discount_value ?? '0',
        items: form.items.map((item, index) => ({
            product_variant_id: item.product_variant_id,
            warehouse_id: wid,
            unit_id: item.unit_id || null,
            quantity: item.quantity,
            rate: item.rate,
            tax_id: item.tax_id || null,
            tax_line_type: item.tax_line_type || 'taxable',
            line_discount_type: item.line_discount_type || 'fixed',
            line_discount_value: item.line_discount_value ?? '0',
            tax_amount: calcLineTax(item, index),
            discount_amount: String(lineDiscountMoneyFromItem(item)),
        })),
    };
};

const loadReference = async () => {
    if (!referenceId.value || !referenceType.value) {
        return;
    }
    loading.value = true;
    try {
        const endpoint = referenceType.value === 'quotation' ? `quotation/${referenceId.value}` : `sales-order/${referenceId.value}`;
        const res = await apiAdmin(endpoint);
        const data = res.data.data || {};

        form.invoice_date = currentAdDate;
        form.due_date = '';
        form.party_id = data.party_id ? String(data.party_id) : '';
        form.remarks = data.remarks || '';
        form.reference_type = referenceType.value === 'quotation' ? 'App\\Models\\Quotation' : 'App\\Models\\SalesOrder';
        form.reference_id = referenceId.value;
        const odv = data.order_discount_value;
        form.order_discount_type = data.order_discount_type || 'fixed';
        form.order_discount_value = odv != null && odv !== '' ? String(odv) : '0';
        form.items = (data.items || []).map((item) => ({
            product_variant_id: item.product_variant_id || '',
            product_label: lineLabelFromRefItem(item),
            purchase_snapshot: purchaseSnapshotFromRefItem(item),
            unit_id: item.unit_id ?? '',
            quantity: item.quantity != null ? String(item.quantity) : '',
            rate: item.rate != null ? String(item.rate) : '',
            tax_id: item.tax_id || '',
            tax_line_type: item.tax_line_type || 'taxable',
            line_discount_type: item.line_discount_type || 'fixed',
            line_discount_value:
                item.line_discount_value != null && item.line_discount_value !== ''
                    ? String(item.line_discount_value)
                    : '0',
        }));
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

watch(
    () => [referenceId.value, referenceType.value, open.value],
    ([id, type, isOpen]) => {
        if (id && type && isOpen) {
            loadReference();
        }
    }
);

const storeInvoice = async (status = 'draft') => {
    form.status = status;
    const validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            const res = await invoiceStore.storeInvoice(buildInvoicePayload());
            toast(res.status, res.data.message);
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
    referenceId.value = '';
    referenceType.value = '';
};

function resetForm() {
    Object.assign(form, getInitialState());
    errors.value = {};
}
</script>

<style scoped>
.invoice-ref-lines-table :deep(.form-control),
.invoice-ref-lines-table :deep(.form-select) {
    min-width: 4.25rem;
}
.invoice-ref-lines-table th,
.invoice-ref-lines-table td {
    vertical-align: middle;
}
.invoice-ref-lines-table .inv-ref-col-product {
    min-width: 11rem;
    max-width: 16rem;
}
.invoice-ref-lines-table .inv-ref-col-tax {
    min-width: 7.5rem;
}
.invoice-ref-lines-table .inv-ref-col-sn {
    width: 2.5rem;
}
.invoice-ref-lines-table .inv-ref-col-action {
    width: 3rem;
}
.invoice-ref-lines-table .inv-ref-discount-cell {
    min-width: 9rem;
    position: relative;
    z-index: 2;
    overflow: visible;
}
</style>
