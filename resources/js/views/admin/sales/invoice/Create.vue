<template>
    <VModal
        :show-modal="!!createModalOpened"
        @close-click="closeCreateModal"
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
                                required
                                @search-change="debouncedPartySearch"
                                :error="errors.party_id"
                            />
                            <PartyMetaPanel
                                v-if="resolvedParty"
                                :party="resolvedParty"
                                pan-heading="Buyer PAN"
                            />
                        </div>
                        <div class="col-lg-6 col-sm-6 col-12">
                            <VInput
                                id="bijak_no"
                                v-model="form.bijak_no"
                                label="Bijak No (Invoice No)"
                                placeholder="Sequential bill number"
                            />
                        </div>

                        <div class="col-12">
                            <ProductVariantSearchInput
                                ref="productSearchRef"
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
                                        <th class="inv-col-rate">Rate</th>
                                        <th class="inv-col-disc">Discount</th>
                                        <th class="inv-col-tax">Tax</th>
                                        <th class="inv-col-total text-end">Total</th>
                                        <th class="text-center inv-col-action">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-if="!form.items.length">
                                        <td colspan="8" class="text-center text-muted py-4">
                                            Search and select a product to add lines.
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="(item, index) in form.items"
                                        :key="`n-${index}-${item.product_variant_id}-${item.warehouse_id}`"
                                        v-memo="[
                                            item.quantity,
                                            item.rate,
                                            item.line_discount_type,
                                            item.line_discount_value,
                                            item.tax_id,
                                            item.warehouse_id,
                                        ]">
                                        <td>{{ index + 1 }}</td>
                                        <td class="inv-col-product">
                                            <div class="inv-line-product">
                                                <span class="inv-line-product__name" :title="item.product_label">
                                                    {{ item.product_label }}
                                                </span>
                                                <span v-if="item.sku" class="inv-line-product__sku">{{ item.sku }}</span>
                                                <span v-if="item.warehouse_name" class="inv-line-product__wh">
                                                    <i class="ti ti-building-warehouse"></i>
                                                    {{ item.warehouse_name }}
                                                </span>
                                                <span
                                                    v-if="item.stock_qty != null"
                                                    class="inv-line-product__stock"
                                                    :class="{ 'is-over': Number(item.quantity) > item.stock_qty }">
                                                    Stock {{ item.stock_qty }}
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <VInput
                                                input-type="number"
                                                input-class="form-control form-control-sm text-center"
                                                v-model="form.items[index].quantity"
                                                :max="item.stock_qty ?? undefined"
                                                @validate="validateField(`items[${index}].quantity`)"
                                                :error="errors[`items[${index}].quantity`]"
                                            />
                                        </td>
                                        <td>
                                            <VInput
                                                input-type="number"
                                                input-class="form-control form-control-sm text-end"
                                                v-model="form.items[index].rate"
                                                @validate="validateField(`items[${index}].rate`)"
                                                :error="errors[`items[${index}].rate`]"
                                            />
                                        </td>
                                        <td class="inv-discount-cell">
                                            <VDiscountAmountTypeGroup selector-mode="toggle"
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
                                        <td class="inv-col-tax">
                                            <select
                                                class="form-select form-select-sm inv-line-tax-select"
                                                :value="item.tax_id ?? ''"
                                                @change="onLineTaxChange(index, $event.target.value)">
                                                <option value="">None</option>
                                                <option v-for="t in taxes.data" :key="t.id" :value="t.id">
                                                    {{ t.name }} ({{ t.rate }}%)
                                                </option>
                                            </select>
                                            <span v-if="calcLineTax(item, index) > 0" class="inv-line-tax-amt">
                                                {{ formatMoney(calcLineTax(item, index)) }}
                                            </span>
                                        </td>
                                        <td class="inv-col-total text-end">
                                            <span class="inv-line-total">{{ formatMoney(calcLineTotal(item, index)) }}</span>
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
                            <div class="card bg-light mb-4 inv-summary-card">
                                <div class="card-body py-2">
                                    <div class="d-flex justify-content-between">
                                        <span>Sub total</span>
                                        <strong>{{ formatMoney(summary.subtotal) }}</strong>
                                    </div>
                                    <div
                                        class="d-flex flex-wrap align-items-center justify-content-between gap-2 border-top pt-2 mt-2">
                                        <span>Discount</span>
                                        <div class="flex-grow-1" style="max-width: 14rem; min-width: 0">
                                            <VDiscountAmountTypeGroup selector-mode="toggle"
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
                                        <strong class="ms-auto">{{ formatMoney(summary.totalDiscount) }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Tax</span>
                                        <strong>{{ formatMoney(summary.tax) }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between border-top pt-2 mt-2">
                                        <span>Grand total</span>
                                        <strong>{{ formatMoney(summary.grandTotal) }}</strong>
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

    <WarehousePickerModal
        ref="warehousePickerRef"
        modal-id="invoice-create-warehouse-picker"
        confirm-label="Add to Invoice"
        @confirm="onWarehousePicked"
        @cancel="cancelWarehousePick"
    />
</template>

<script setup>
import {formatMoney, formatMoneyPlain} from '@/helpers/formatMoney.js';
import {reactive, ref, toRef, watch} from 'vue';
import debounce from 'lodash/debounce';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {array, object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {usePartyStore} from '@/stores/admin/party.js';
import {useTaxStore} from '@/stores/admin/settings/tax.js';
import {useInvoiceStore} from '@/stores/admin/sales/invoice.js';
import {useQuotationStore} from '@/stores/admin/sales/quotation.js';
import {useSalesOrderStore} from '@/stores/admin/sales/sales-order.js';
import {useDeliveryChallanStore} from '@/stores/admin/inventory/delivery-challan.js';
import {useDateHelper} from '@/composables/dateHelper.js';
import {
    buildOrderAllocations,
    lineDiscountMoneyFromItem,
    lineNetFromItem,
    mergePoOrderDiscountIntoLineDiscounts,
    orderDiscountMoney,
} from '@/composables/purchaseOrderTotals.js';
import {useLineOrderDiscountTotals} from '@/composables/useLineOrderDiscountTotals.js';
import {useProductLineWarehouse} from '@/composables/useProductLineWarehouse.js';
import VDiscountAmountTypeGroup from '@/components/base/VDiscountAmountTypeGroup.vue';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import PartyMetaPanel from '@/components/party/PartyMetaPanel.vue';
import WarehousePickerModal from '@/components/modal/WarehousePickerModal.vue';
import {useResolvedParty} from '@/composables/useResolvedParty.js';
import {usePartyDefaultOrderDiscount} from '@/composables/usePartyDefaultOrderDiscount.js';
import {warehouseIdForLineItem} from '@/helpers/productLineValidation.js';

const invoiceStore = useInvoiceStore();
const quotationStore = useQuotationStore();
const salesOrderStore = useSalesOrderStore();
const deliveryChallanStore = useDeliveryChallanStore();
const partyStore = usePartyStore();
const taxStore = useTaxStore();

const {
    warehousePickerRef,
    resolveWarehouse,
    confirmWarehousePick,
    cancelWarehousePick,
    warehouseErrorMessage,
    showWarehouseToast,
    buildLineWarehouseFields,
} = useProductLineWarehouse();

const createModalOpened = defineModel('createModalOpened');
const quotationId = defineModel('quotationId');
const salesOrderId = defineModel('salesOrderId');
const deliveryChallanId = defineModel('deliveryChallanId');
const emit = defineEmits(['created']);

const {currentAdDate} = useDateHelper();

const {parties} = storeToRefs(partyStore);
const {taxes} = storeToRefs(taxStore);
const {quotation} = storeToRefs(quotationStore);
const {order} = storeToRefs(salesOrderStore);

const productSearchRef = ref(null);

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
            taxStore.getTaxes(false, {for: 'line_item'});
            partyStore.getParties({
                filter: {
                    type: 'customer',
                    limit: 50,
                    search: '',
                },
            });
            if (salesOrderId.value) {
                loadFromSalesOrder();
            } else if (quotationId.value) {
                loadFromQuotation();
            } else if (deliveryChallanId.value) {
                loadFromDeliveryChallan();
            }
        }
    },
    {flush: 'post'}
);

const getInitialState = () => ({
    invoice_date: currentAdDate,
    due_date: '',
    party_id: '',
    quotation_id: '',
    sales_order_id: '',
    bijak_no: '',
    remarks: '',
    status: 'draft',
    order_discount_type: 'fixed',
    order_discount_value: '0',
    items: [],
});

const form = reactive({...getInitialState()});
const isSubmitting = ref(false);

const resolvedParty = useResolvedParty(toRef(form, 'party_id'), parties);
usePartyDefaultOrderDiscount(toRef(form, 'party_id'), resolvedParty, form);

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


const loadFromQuotation = async () => {
    await quotationStore.getQuotation(quotationId.value);
    await hydrateFromReference(quotation.value.data, {
        quotation_id: quotationId.value,
        sales_order_id: '',
    });
};

const loadFromSalesOrder = async () => {
    await salesOrderStore.getOrder(salesOrderId.value);
    await hydrateFromReference(order.value.data, {
        quotation_id: order.value.data?.quotation_id || '',
        sales_order_id: salesOrderId.value,
    });
};

const loadFromDeliveryChallan = async () => {
    await deliveryChallanStore.getChallan(deliveryChallanId.value);
    const data = deliveryChallanStore.challan.data;

    form.party_id = data.party_id || '';
    form.sales_order_id = data.sales_order_id ? String(data.sales_order_id) : '';
    form.remarks = data.remarks || '';
    form.items = (data.challan_items || []).map((item) => ({
        product_variant_id: item.product_variant_id,
        product_label: variantLabel(item.product_variant ?? {}),
        sku: item.product_variant?.sku ?? '',
        purchase_snapshot: item.product_variant?.purchase_price || 0,
        unit_id: item.unit_id ?? item.product_variant?.unit_id ?? '',
        quantity: String(item.quantity),
        rate: String(item.rate),
        tax_id: '',
        line_discount_type: 'fixed',
        line_discount_value: '0',
        delivery_challan_item_id: item.id,
        warehouse_id: data.warehouse_id ? String(data.warehouse_id) : '',
        warehouse_name: data.warehouse?.name ?? '',
        stock_qty: null,
        is_service: false,
    }));
};

function buildPrefillLine(item) {
    return {
        product_variant_id: item.product_variant_id,
        product_label: variantLabel(item.product_variant ?? {}),
        sku: item.product_variant?.sku ?? '',
        purchase_snapshot: item.product_variant?.purchase_price || 0,
        unit_id: item.unit_id ?? item.product_variant?.unit_id ?? '',
        quantity: String(item.quantity),
        rate: String(item.rate),
        tax_id: item.tax_id || '',
        line_discount_type: item.line_discount_type || 'fixed',
        line_discount_value: String(item.line_discount_value ?? 0),
        warehouse_id: '',
        warehouse_name: '',
        stock_qty: null,
    };
}

async function hydrateFromReference(data, sourceIds) {
    form.party_id = data.party_id || '';
    form.quotation_id = sourceIds.quotation_id || '';
    form.sales_order_id = sourceIds.sales_order_id || '';
    form.remarks = data.remarks || '';

    const items = data.items || [];
    const hasLineTypes = items.some((it) => it.line_discount_type != null);
    const prefillLines = [];

    if (hasLineTypes) {
        form.order_discount_type = data.order_discount_type || 'fixed';
        form.order_discount_value =
            data.order_discount_value != null && data.order_discount_value !== ''
                ? String(data.order_discount_value)
                : '0';

        items.forEach((item) => {
            prefillLines.push(buildPrefillLine(item));
        });
    } else {
        const mergedDiscounts = mergePoOrderDiscountIntoLineDiscounts(items, data.order_discount_amount);
        form.order_discount_type = 'fixed';
        form.order_discount_value = '0';
        items.forEach((item, i) => {
            const line = buildPrefillLine(item);
            line.line_discount_type = 'fixed';
            line.line_discount_value = String(mergedDiscounts[i] ?? item.discount_amount ?? 0);
            prefillLines.push(line);
        });
    }

    form.items = [];
    for (const line of prefillLines) {
        await commitPrefillLine(line);
    }
}

async function commitPrefillLine(line) {
    const result = await resolveWarehouse(line.product_variant_id, line.product_label);

    if (!result.success) {
        showWarehouseToast(result.error);

        return;
    }

    form.items.push({
        ...line,
        ...buildLineWarehouseFields(result.warehouse),
    });
}

function commitVariantLine(variant, warehouse) {
    const vid = variant.id;
    const wid = warehouse.warehouse_id;
    const isService = !!variant.is_service;
    const existing = form.items.findIndex(
        (i) => String(i.product_variant_id) === String(vid)
            && (isService ? i.is_service : String(i.warehouse_id) === String(wid)),
    );

    if (existing !== -1) {
        const nextQty = Number(form.items[existing].quantity || 0) + 1;
        if (!isService && warehouse.quantity != null && nextQty > warehouse.quantity) {
            showWarehouseToast('insufficient_stock', warehouse.quantity);

            return false;
        }
        form.items[existing].quantity = String(nextQty);

        return true;
    }

    form.items.push({
        product_variant_id: vid,
        product_label: variantLabel(variant),
        sku: variant.sku ?? '',
        purchase_snapshot: variant.purchase_price ?? 0,
        unit_id: variant.unit_id ?? '',
        quantity: '1',
        rate: defaultLineRateString(variant),
        tax_id: '',
        line_discount_type: 'fixed',
        line_discount_value: '0',
        is_service: isService,
        warehouse_id: wid ?? '',
        warehouse_name: warehouse.warehouse_name ?? '',
        stock_qty: isService ? null : (warehouse.quantity ?? null),
    });

    return true;
}

const onVariantSelected = async (variant) => {
    const result = await resolveWarehouse(variant.id, variantLabel(variant), {
        isService: !!variant.is_service,
    });

    if (!result.success) {
        showWarehouseToast(result.error);

        return;
    }

    if (commitVariantLine(variant, result.warehouse)) {
        productSearchRef.value?.focus?.();
    }
};

const onWarehousePicked = (warehouseOption) => {
    confirmWarehousePick(warehouseOption);
};

const removeItem = (index) => {
    form.items.splice(index, 1);
};

const onLineTaxChange = (index, taxId) => {
    form.items[index].tax_id = taxId || '';
    validateField(`items[${index}].tax_id`);
};

const validations = object({
    invoice_date: string().required('Invoice date is required.'),
    due_date: string().nullable(),
    party_id: string().required('Customer is required.'),
    order_discount_type: string().nullable(),
    order_discount_value: string().nullable(),
    items: array()
        .of(
            object({
                product_variant_id: string().required('Product is required.'),
                warehouse_id: warehouseIdForLineItem(),
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

function calcLineTotal(item, index) {
    const nets = form.items.map((i) => lineNetFromItem(i));
    const sumLineNet = nets.reduce((a, b) => a + b, 0);
    const orderDisc = orderDiscountMoney(
        sumLineNet,
        form.order_discount_type,
        form.order_discount_value,
    );
    const allocs = buildOrderAllocations(nets, orderDisc);
    const lineNet = lineNetFromItem(item);
    const afterOrder = Math.max(0, lineNet - (allocs[index] || 0));

    return afterOrder + calcLineTax(item, index);
}

const buildInvoicePayload = () => {
    syncTaxAmounts();

    return {
        invoice_date: form.invoice_date,
        due_date: form.due_date || null,
        party_id: form.party_id || null,
        quotation_id: form.quotation_id || null,
        sales_order_id: form.sales_order_id || null,
        bijak_no: form.bijak_no || null,
        remarks: form.remarks,
        status: form.status,
        order_discount_type: form.order_discount_type || 'fixed',
        order_discount_value: form.order_discount_value ?? '0',
        items: form.items.map((item, index) => ({
            product_variant_id: item.product_variant_id,
            delivery_challan_item_id: item.delivery_challan_item_id || null,
            warehouse_id: item.is_service ? null : (item.warehouse_id || null),
            unit_id: item.unit_id || null,
            quantity: item.quantity,
            rate: item.rate,
            tax_id: item.tax_id || null,
            tax_line_type: 'taxable',
            line_discount_type: item.line_discount_type || 'fixed',
            line_discount_value: item.line_discount_value ?? '0',
            tax_amount: calcLineTax(item, index),
            discount_amount: String(lineDiscountMoneyFromItem(item)),
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
    salesOrderId.value = '';
    deliveryChallanId.value = '';
    createModalOpened.value = false;
};

function resetForm() {
    Object.assign(form, getInitialState());
    errors.value = {};
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
    min-width: 12rem;
    max-width: 18rem;
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
.invoice-lines-table .inv-col-total {
    min-width: 5.5rem;
}
.invoice-lines-table .inv-discount-cell {
    min-width: 9rem;
    position: relative;
    z-index: 2;
    overflow: visible;
}
.inv-line-product {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}
.inv-line-product__name {
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.inv-line-product__sku,
.inv-line-product__wh {
    font-size: 0.75rem;
    color: var(--bs-secondary-color);
}
.inv-line-product__stock {
    font-size: 0.7rem;
    color: var(--bs-success);
}
.inv-line-product__stock.is-over {
    color: var(--bs-danger);
    font-weight: 600;
}
.inv-line-tax-select {
    min-width: 6.5rem;
}
.inv-line-tax-amt {
    display: block;
    font-size: 0.7rem;
    color: var(--bs-secondary-color);
    margin-top: 0.15rem;
}
.inv-line-total {
    font-weight: 600;
    font-size: 0.875rem;
}
</style>
