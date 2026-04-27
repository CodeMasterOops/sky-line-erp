<template>
    <VModal
        :show-modal="!!createModalOpened"
        @close-click="createModalOpened = false"
        size="xl"
        title="Add Invoice">
        <template #modal-body>
            <div class="card">
                <div class="card-body border-0 p-0">
                    <form @submit.prevent="storeInvoiceWithStatus('draft')" class="row g-3">
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
                                @update:model-value="onWarehouseChange"
                            />
                        </div>
                        <div class="col-lg-6 col-sm-6 col-12">
                            <label class="form-label">Branch</label>
                            <select class="form-select" v-model="form.branch_id">
                                <option value="">— No Branch —</option>
                                <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }} ({{ b.code }})</option>
                            </select>
                        </div>

                        <div class="col-lg-6 col-sm-6 col-12">
                            <VInput
                                id="bijak_no"
                                v-model="form.bijak_no"
                                label="Bijak No (Invoice No)"
                                placeholder="Sequential bill number"
                            />
                        </div>
                        <div class="col-lg-6 col-sm-6 col-12">
                            <VInput
                                id="buyer_pan"
                                v-model="form.buyer_pan"
                                label="Buyer PAN"
                                placeholder="Buyer PAN (required for VAT invoices)"
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
                                <table class="table datanew table-bordered mb-0 invoice-lines-table">
                                    <thead>
                                    <tr>
                                        <th class="inv-col-sn">SN</th>
                                        <th class="inv-col-product">Product</th>
                                        <th class="inv-col-qty">Qty</th>
                                        <th class="inv-col-rate">Rate (sale)</th>
                                        <th class="inv-col-disc">Discount</th>
                                        <th class="inv-col-tax">Tax</th>
                                        <th>Tax Type</th>
                                        <th style="min-width:9rem">Batch (FEFO)</th>
                                        <th class="text-center inv-col-action">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-if="!form.items.length">
                                        <td colspan="9" class="text-center text-muted py-4">
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
                                            item.tax_line_type,
                                        ]">
                                        <td>{{ index + 1 }}</td>
                                        <td
                                            class="text-start text-truncate inv-col-product"
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
                                        <td class="inv-discount-cell">
                                            <VDiscountAmountTypeGroup
                                                :input-id="`inv_line_disc_${index}`"
                                                :input-aria-label="`Line ${index + 1} discount`"
                                                v-model="form.items[index].line_discount_value"
                                                v-model:discount-type="form.items[index].line_discount_type"
                                                :error="errors[`items[${index}].line_discount_value`]"
                                                :disabled="isSubmitting"
                                                extra-group-class="inv-discount-input-group"
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
                                                select-class="form-select form-select-sm"
                                                :options="taxes.data"
                                                @validate="validateField(`items[${index}].tax_id`)"
                                                :error="errors[`items[${index}].tax_id`]"
                                            />
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm" v-model="form.items[index].tax_line_type">
                                                <option value="taxable">Taxable</option>
                                                <option value="exempt">Exempt</option>
                                                <option value="zero_rated">Zero Rated</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm"
                                                v-model="form.items[index].batch_id"
                                                :disabled="!batchOptions[`${item.product_variant_id}-${form.warehouse_id}`]?.length">
                                                <option value="">— none —</option>
                                                <option
                                                    v-for="b in (batchOptions[`${item.product_variant_id}-${form.warehouse_id}`] ?? [])"
                                                    :key="b.id"
                                                    :value="b.id">
                                                    {{ b.batch_no }}
                                                    <template v-if="b.expiry_date"> · exp {{ b.expiry_date }}</template>
                                                    · {{ b.remaining_qty }} left
                                                </option>
                                            </select>
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
                                                input-id="inv_order_discount_value"
                                                input-aria-label="Order-level discount"
                                                :disabled="isSubmitting"
                                                extra-group-class="inv-order-disc-input-group w-100"
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
                                @click="storeInvoiceWithStatus('draft')">
                                Create
                            </button>
                            <button
                                type="button"
                                class="btn btn-submit add-sale btn-primary"
                                :disabled="isSubmitting"
                                @click="storeInvoiceWithStatus('approved')">
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
import {useTaxStore} from '@/stores/admin/setting/tax.js';
import {useWarehouseStore} from '@/stores/admin/inventory/warehouse.js';
import {useInvoiceStore} from '@/stores/admin/sales/invoice.js';
import {useDateHelper} from '@/composables/dateHelper.js';
import {apiAdmin} from '@/helpers/api.js';
import {lineDiscountMoneyFromItem} from '@/composables/purchaseOrderTotals.js';
import {useLineOrderDiscountTotals} from '@/composables/useLineOrderDiscountTotals.js';
import VDiscountAmountTypeGroup from '@/components/base/VDiscountAmountTypeGroup.vue';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';

const invoiceStore = useInvoiceStore();
const partyStore = usePartyStore();
const taxStore = useTaxStore();
const warehouseStore = useWarehouseStore();

// --- Branch & Batch (Phase 3/6) ---
const branches = ref([]);
const batchOptions = ref({}); // key: `${variant_id}-${warehouse_id}` → batches[]

const loadBranches = async () => {
    try {
        const res = await apiAdmin('branch');
        branches.value = res.data.data ?? [];
    } catch { /* branches are optional */ }
};

const fetchBatchOptions = async (variantId, warehouseId) => {
    if (!variantId || !warehouseId) return;
    const key = `${variantId}-${warehouseId}`;
    if (batchOptions.value[key]) return; // already loaded
    try {
        const res = await apiAdmin(`batch/fefo?product_variant_id=${variantId}&warehouse_id=${warehouseId}`);
        batchOptions.value = { ...batchOptions.value, [key]: res.data.data ?? [] };
    } catch { /* no batches for this variant/warehouse */ }
};

const onWarehouseChange = async (warehouseId) => {
    // Reload batch options for all existing line items with the new warehouse
    form.items.forEach((item) => {
        if (item.product_variant_id) {
            batchOptions.value = { ...batchOptions.value };
            delete batchOptions.value[`${item.product_variant_id}-${warehouseId}`];
            fetchBatchOptions(item.product_variant_id, warehouseId);
        }
        item.batch_id = '';
    });
};

const createModalOpened = defineModel('createModalOpened');

const {currentAdDate} = useDateHelper();

const {parties} = storeToRefs(partyStore);
const {taxes} = storeToRefs(taxStore);
const {warehouses} = storeToRefs(warehouseStore);

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
            taxStore.getTaxes();
            warehouseStore.getWarehouses();
            loadBranches();
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

const getInitialState = () => ({
    invoice_date: currentAdDate,
    due_date: '',
    party_id: '',
    warehouse_id: '',
    branch_id: '',
    buyer_pan: '',
    bijak_no: '',
    remarks: '',
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
        batch_id: '',
    });
    // Load FEFO batches for this variant+warehouse combo
    if (form.warehouse_id) {
        fetchBatchOptions(vid, form.warehouse_id);
    }
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
    return {
        invoice_date: form.invoice_date,
        due_date: form.due_date || null,
        party_id: form.party_id || null,
        branch_id: form.branch_id || null,
        buyer_pan: form.buyer_pan || null,
        bijak_no: form.bijak_no || null,
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
            batch_id: item.batch_id || null,
        })),
    };
};

const storeInvoiceWithStatus = async (status) => {
    form.status = status;
    const validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            const res = await invoiceStore.storeInvoice(buildInvoicePayload());
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
    Object.assign(form, getInitialState());
    errors.value = {};
    batchOptions.value = {};
}
</script>

<style scoped>
.invoice-lines-table :deep(.form-control),
.invoice-lines-table :deep(.form-select) {
    min-width: 4.25rem;
}
.invoice-lines-table th,
.invoice-lines-table td {
    vertical-align: middle;
}
.invoice-lines-table .inv-col-product {
    min-width: 11rem;
    max-width: 16rem;
}
.invoice-lines-table .inv-col-tax {
    min-width: 7.5rem;
}
.invoice-lines-table .inv-col-sn {
    width: 2.5rem;
}
.invoice-lines-table .inv-col-action {
    width: 3rem;
}

.invoice-lines-table .inv-discount-cell {
    min-width: 9rem;
    position: relative;
    z-index: 2;
    overflow: visible;
}
</style>
