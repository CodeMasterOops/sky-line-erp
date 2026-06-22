<template>
    <VModal
        :show-modal="!!edit_invoice_id"
        @close-click="closeEditModal"
        modal-class="large-modal"
        title="Update Invoice">
        <template #modal-body>
            <VLoader v-if="invoice.loading" loader-type="progress"/>
            <form @submit.prevent="updateInvoice(invoice.data.id)" class="row g-3">
                <div class="col-md-6">
                    <VInput
                        id="invoice_date"
                        input-type="date"
                        v-model="form.invoice_date"
                        label="Invoice Date"
                        @validate="validateField('invoice_date')"
                        :error="errors.invoice_date"
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
                        label="Customer"
                        @validate="validateField('party_id')"
                        required
                        :error="errors.party_id"
                    />
                    <PartyMetaPanel
                        v-if="resolvedParty"
                        :party="resolvedParty"
                        pan-heading="Buyer PAN"
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        id="bijak_no"
                        v-model="form.bijak_no"
                        label="Bijak No (Invoice No)"
                        placeholder="Sequential bill number"
                    />
                </div>

                <div class="col-12">
                    <div class="table-responsive inv-edit-lines">
                        <table class="table table-bordered invoice-lines-table">
                            <thead>
                            <tr>
                                <th class="inv-col-sn">SN</th>
                                <th class="inv-col-product">Product</th>
                                <th class="inv-col-qty">Qty</th>
                                <th class="inv-col-rate">Rate</th>
                                <th class="inv-col-disc">Discount</th>
                                <th class="inv-col-tax">Tax</th>
                                <th class="inv-col-batch">Batch</th>
                                <th class="inv-col-total text-end">Total</th>
                                <th class="text-center inv-col-action">Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr
                                v-for="(item, index) in form.items"
                                :key="item.id != null && item.id !== '' ? `line-${item.id}` : `new-${index}-${item.product_variant_id}-${item.warehouse_id}`">
                                <td>{{ index + 1 }}</td>
                                <td class="inv-col-product">
                                    <template v-if="isDraft">
                                        <VSelect
                                            v-model="form.items[index].product_variant_id"
                                            :options="productVariants.data"
                                            @onInput="onVariantChange(index, $event)"
                                            @validate="validateField(`items[${index}].product_variant_id`)"
                                            :error="errors[`items[${index}].product_variant_id`]"
                                        />
                                    </template>
                                    <div v-else class="inv-line-product">
                                        <span class="inv-line-product__name">
                                            {{ lineProductLabel(item, index) }}
                                        </span>
                                        <span v-if="item.sku" class="inv-line-product__sku">{{ item.sku }}</span>
                                        <span v-if="item.warehouse_name" class="inv-line-product__wh">
                                            <i class="ti ti-building-warehouse"></i>
                                            {{ item.warehouse_name }}
                                        </span>
                                    </div>
                                    <div v-if="isDraft && item.warehouse_name" class="inv-line-product mt-1">
                                        <span class="inv-line-product__wh">
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
                                        :disabled="!isDraft"
                                        @validate="validateField(`items[${index}].quantity`)"
                                        :error="errors[`items[${index}].quantity`]"
                                    />
                                </td>
                                <td>
                                    <VInput
                                        input-type="number"
                                        input-class="form-control form-control-sm text-end"
                                        v-model="form.items[index].rate"
                                        :disabled="!isDraft"
                                        @validate="validateField(`items[${index}].rate`)"
                                        :error="errors[`items[${index}].rate`]"
                                    />
                                </td>
                                <td class="inv-edit-discount-cell">
                                    <VDiscountAmountTypeGroup selector-mode="toggle"
                                        :input-id="`inv_edit_line_disc_${item.id ?? index}`"
                                        :input-aria-label="`Line ${index + 1} discount`"
                                        v-model="form.items[index].line_discount_value"
                                        v-model:discount-type="form.items[index].line_discount_type"
                                        :error="errors[`items[${index}].line_discount_value`]"
                                        :disabled="isSubmitting || !isDraft"
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
                                        :value="item.tax_group_id ? `group:${item.tax_group_id}` : (item.tax_id ?? '')"
                                        :disabled="!isDraft"
                                        @change="onLineTaxChange(index, $event.target.value)">
                                        <option value="">None</option>
                                        <optgroup v-if="taxGroups.data.length" label="Tax Groups">
                                            <option v-for="g in taxGroups.data" :key="`group:${g.id}`" :value="`group:${g.id}`">
                                                {{ g.name }}
                                            </option>
                                        </optgroup>
                                        <optgroup label="Individual Taxes">
                                            <option v-for="t in taxes.data" :key="t.id" :value="t.id">
                                                {{ t.name }} ({{ t.rate }}%)
                                            </option>
                                        </optgroup>
                                    </select>
                                    <span v-if="calcLineTax(item, index) > 0" class="inv-line-tax-amt">
                                        {{ formatMoney(calcLineTax(item, index)) }}
                                    </span>
                                </td>
                                <td class="inv-col-batch">
                                    <BatchPickerInput
                                        v-if="item.is_batch_tracked && !item.is_service"
                                        v-model="form.items[index].batch_id"
                                        :product-variant-id="item.product_variant_id"
                                        :warehouse-id="item.warehouse_id"
                                        :disabled="!isDraft"
                                    />
                                    <span v-else-if="!item.is_batch_tracked && item.batch_id" class="small text-muted">
                                        {{ item.batch_id }}
                                    </span>
                                </td>
                                <td class="inv-col-total text-end">
                                    <span class="inv-line-total">{{ formatMoney(calcLineTotal(item, index)) }}</span>
                                </td>
                                <td class="text-center">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        @click="removeItem(index)"
                                        :disabled="!isDraft || form.items.length === 1"
                                        :title="form.items.length === 1 ? 'At least one line is required' : 'Remove line'">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <button
                        v-if="isDraft"
                        type="button"
                        class="btn btn-sm btn-outline-secondary"
                        @click="addItem">
                        Add Item
                    </button>
                </div>

                <div class="col-12">
                    <div class="card bg-light inv-edit-totals">
                        <div class="card-body py-2">
                            <div class="d-flex justify-content-between">
                                <span>Sub total</span>
                                <strong>{{ formatMoney(summary.subtotal) }}</strong>
                            </div>
                            <template v-if="isDraft">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border-top pt-2 mt-2">
                                    <span class="mb-0">Order discount</span>
                                    <div class="inv-edit-order-disc">
                                        <VDiscountAmountTypeGroup selector-mode="toggle"
                                            v-model="form.order_discount_value"
                                            v-model:discount-type="form.order_discount_type"
                                            :error="errors.order_discount_value"
                                            input-id="inv_edit_order_discount_value"
                                            input-aria-label="Order-level discount"
                                            :disabled="isSubmitting"
                                            extra-group-class="inv-order-disc-input-group"
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
                            </template>
                            <div v-else class="d-flex justify-content-between border-top pt-2 mt-2">
                                <span>Discount (lines + order)</span>
                                <strong>{{ formatMoney(summary.totalDiscount) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Tax</span>
                                <strong>{{ formatMoney(summary.tax) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between border-top pt-2 mt-2">
                                <span>Grand Total</span>
                                <strong>{{ formatMoney(summary.grandTotal) }}</strong>
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
                    <button @click="closeEditModal" class="btn btn-cancel" type="button">
                        Cancel
                    </button>
                    <VButton v-if="isDraft" :loading="isSubmitting" :disabled="isSubmitting"/>
                    <button v-else type="button" class="btn btn-secondary" disabled>
                        Approved
                    </button>
                </div>
            </form>
        </template>
    </VModal>

    <WarehousePickerModal
        ref="warehousePickerRef"
        modal-id="invoice-edit-warehouse-picker"
        confirm-label="Add to Invoice"
        @confirm="onWarehousePicked"
        @cancel="cancelWarehousePick"
    />
</template>

<script setup>
import {formatMoney, formatMoneyPlain} from '@/helpers/formatMoney.js';
import {computed, nextTick, onMounted, reactive, ref, toRef, watch} from 'vue';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {array, object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {useProductStore} from '@/stores/admin/inventory/product.js';
import {usePartyStore} from '@/stores/admin/party.js';
import {useTaxStore} from '@/stores/admin/settings/tax.js';
import {useInvoiceStore} from '@/stores/admin/sales/invoice.js';
import {
    buildOrderAllocations,
    lineDiscountMoneyFromItem,
    lineNetFromItem,
    orderDiscountMoney,
} from '@/composables/purchaseOrderTotals.js';
import {useLineOrderDiscountTotals} from '@/composables/useLineOrderDiscountTotals.js';
import {useProductLineWarehouse} from '@/composables/useProductLineWarehouse.js';
import VDiscountAmountTypeGroup from '@/components/base/VDiscountAmountTypeGroup.vue';
import PartyMetaPanel from '@/components/party/PartyMetaPanel.vue';
import WarehousePickerModal from '@/components/modal/WarehousePickerModal.vue';
import BatchPickerInput from '@/components/inventory/BatchPickerInput.vue';
import {useResolvedParty} from '@/composables/useResolvedParty.js';
import {usePartyDefaultOrderDiscount} from '@/composables/usePartyDefaultOrderDiscount.js';
import {warehouseIdForLineItem} from '@/helpers/productLineValidation.js';

const invoiceStore = useInvoiceStore();
const productStore = useProductStore();
const partyStore = usePartyStore();
const taxStore = useTaxStore();

const {
    warehousePickerRef,
    resolveWarehouse,
    confirmWarehousePick,
    cancelWarehousePick,
    showWarehouseToast,
    buildLineWarehouseFields,
} = useProductLineWarehouse();

const edit_invoice_id = defineModel('invoice_id');

const {invoice} = storeToRefs(invoiceStore);
const {productVariants} = storeToRefs(productStore);
const {parties} = storeToRefs(partyStore);
const {taxes, taxGroups} = storeToRefs(taxStore);

onMounted(() => {
    productStore.getProductVariants();
    partyStore.getParties({filter: {type: 'customer'}});
    taxStore.getTaxes({ filter: { for: 'line_item' } });
    taxStore.getTaxGroups();
});

const emptyLine = () => ({
    id: '',
    product_variant_id: '',
    product_label: '',
    sku: '',
    unit_id: '',
    quantity: '',
    rate: '',
    tax_id: '',
    tax_group_id: null,
    tax_amount: 0,
    line_discount_type: 'fixed',
    line_discount_value: '0',
    warehouse_id: '',
    warehouse_name: '',
    stock_qty: null,
    is_batch_tracked: false,
    batch_id: null,
});

const initialState = {
    invoice_date: '',
    due_date: '',
    party_id: '',
    bijak_no: '',
    remarks: '',
    status: 'draft',
    order_discount_type: 'fixed',
    order_discount_value: '0',
    items: [emptyLine()],
};

const form = reactive({...initialState});
const isSubmitting = ref(false);
const isHydratingInvoice = ref(false);

const documentParty = computed(() => invoice.value.data?.party ?? null);

const resolvedParty = useResolvedParty(toRef(form, 'party_id'), parties, documentParty);
usePartyDefaultOrderDiscount(toRef(form, 'party_id'), resolvedParty, form, {skipInitial: true});

const {calcLineTax, summary, syncTaxAmounts} = useLineOrderDiscountTotals({
    form,
    taxes,
});

const isDraft = computed(() => invoice.value.data?.status === 'draft');


function variantLabel(variant) {
    let label = variant.name || '';
    if (variant.sku) {
        label += ` (${variant.sku})`;
    }

    return label;
}

function lineProductLabel(item, index) {
    if (item.product_label) {
        return item.product_label;
    }

    const variant = getVariantById(item.product_variant_id);
    if (variant) {
        return variantLabel(variant);
    }

    return form.items[index]?.product_label || '—';
}

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

const addItem = () => {
    form.items.push(emptyLine());
};

const removeItem = (index) => {
    if (form.items.length === 1) {
        return;
    }
    form.items.splice(index, 1);
};

watch(() => edit_invoice_id.value, async (id) => {
    if (id) {
        isHydratingInvoice.value = true;
        await invoiceStore.getInvoice(id);
        const data = invoice.value.data;

        form.invoice_date = data.invoice_date || '';
        form.due_date = data.due_date || '';
        form.party_id = data.party_id || '';
        form.bijak_no = data.bijak_no || '';
        form.remarks = data.remarks || '';
        form.status = data.status || 'draft';
        form.order_discount_type = data.order_discount_type || 'fixed';
        const odv = data.order_discount_value;
        form.order_discount_value = odv != null && odv !== '' ? String(odv) : '0';

        const rows = (data.items || []).length ? data.items : [{}];
        form.items = rows.map((item) => ({
            id: item.id ?? '',
            product_variant_id: item.product_variant_id || '',
            product_label: item.product_variant
                ? variantLabel(item.product_variant)
                : '',
            sku: item.product_variant?.sku ?? '',
            unit_id: item.unit_id || '',
            quantity: item.quantity != null && item.quantity !== '' ? String(item.quantity) : '',
            rate: item.rate != null && item.rate !== '' ? String(item.rate) : '',
            tax_id: item.tax_group_id ? '' : (item.tax_id || ''),
            tax_group_id: item.tax_group_id || null,
            tax_amount: Number(item.tax_amount ?? 0),
            line_discount_type: item.line_discount_type || 'fixed',
            line_discount_value:
                item.line_discount_value != null && item.line_discount_value !== ''
                    ? String(item.line_discount_value)
                    : '0',
            is_service: !!item.product_variant?.is_service,
            warehouse_id: item.warehouse_id || '',
            warehouse_name: item.warehouse?.name || '',
            stock_qty: null,
            is_batch_tracked: !!item.product_variant?.is_batch_tracked,
            batch_id: item.batch_id ?? null,
        }));
        await nextTick();
        isHydratingInvoice.value = false;
    }
});

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

const getVariantById = (id) => {
    const numericId = parseInt(id, 10);

    return productVariants.value.data.find((v) => v.id === numericId);
};

const onVariantChange = async (index, variantId) => {
    if (isHydratingInvoice.value) {
        return;
    }

    const variant = getVariantById(variantId);
    if (!variant) {
        return;
    }

    form.items[index].rate = variant.sales_price != null ? String(variant.sales_price) : '';
    form.items[index].unit_id = variant.unit_id ?? '';
    form.items[index].product_label = variantLabel(variant);
    form.items[index].sku = variant.sku ?? '';

    const result = await resolveWarehouse(variant.id, variantLabel(variant), {
        isService: !!variant.is_service,
    });

    if (!result.success) {
        form.items[index].warehouse_id = '';
        form.items[index].warehouse_name = '';
        form.items[index].stock_qty = null;

        if (result.error !== 'cancelled') {
            showWarehouseToast(result.error);
        }

        return;
    }

    form.items[index].warehouse_id = result.warehouse.warehouse_id;
    Object.assign(form.items[index], buildLineWarehouseFields(result.warehouse));
};

const onWarehousePicked = (warehouseOption) => {
    confirmWarehousePick(warehouseOption);
};

const onLineTaxChange = async (index, value) => {
    if (value && String(value).startsWith('group:')) {
        const groupId = parseInt(value.replace('group:', ''), 10);
        form.items[index].tax_group_id = groupId;
        form.items[index].tax_id = '';
        form.items[index].tax_amount = 0;
        try {
            const item = form.items[index];
            const baseAmount = Math.max(
                0,
                (Number(item.quantity) || 0) * (Number(item.rate) || 0)
                    - Number(item.discount_amount ?? 0),
            );
            const result = await taxStore.calculateTaxGroup(
                baseAmount,
                groupId,
                form.party_id || null,
                form.invoice_date || null,
            );
            form.items[index].tax_amount = result.tax_amount ?? 0;
        } catch {
            // Non-fatal: backend will recompute on submit.
        }
    } else {
        form.items[index].tax_id = value || '';
        form.items[index].tax_group_id = null;
        form.items[index].tax_amount = 0;
        validateField(`items[${index}].tax_id`);
    }
};

const buildUpdatePayload = () => {
    syncTaxAmounts();

    return {
        invoice_date: form.invoice_date,
        due_date: form.due_date || null,
        party_id: form.party_id || null,
        remarks: form.remarks,
        order_discount_type: form.order_discount_type || 'fixed',
        order_discount_value: form.order_discount_value ?? '0',
        items: form.items.map((item, index) => ({
            product_variant_id: item.product_variant_id,
            warehouse_id: item.is_service ? null : (item.warehouse_id || null),
            unit_id: item.unit_id || null,
            quantity: item.quantity,
            rate: item.rate,
            tax_id: item.tax_group_id ? null : (item.tax_id || null),
            tax_group_id: item.tax_group_id || null,
            tax_line_type: 'taxable',
            line_discount_type: item.line_discount_type || 'fixed',
            line_discount_value: item.line_discount_value ?? '0',
            tax_amount: calcLineTax(item, index),
            discount_amount: String(lineDiscountMoneyFromItem(item)),
            batch_id: item.batch_id || null,
        })),
    };
};

const updateInvoice = async (id) => {
    if (!isDraft.value) {
        return;
    }
    const validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            const res = await invoiceStore.updateInvoice(id, buildUpdatePayload());
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
    edit_invoice_id.value = '';
};

function resetForm() {
    isHydratingInvoice.value = false;
    Object.assign(form, {...initialState, items: [emptyLine()]});
    errors.value = {};
}
</script>

<style scoped>
.inv-edit-lines .inv-edit-discount-cell {
    min-width: 9rem;
    position: relative;
    z-index: 2;
    overflow: visible;
    vertical-align: middle;
}
.inv-edit-order-disc {
    min-width: 0;
    max-width: 12rem;
    flex: 1 1 auto;
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
.inv-line-product {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
    margin-top: 0.25rem;
}
.inv-line-product__name {
    font-weight: 500;
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
