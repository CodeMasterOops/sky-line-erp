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
                                <th class="inv-col-total text-end">Total</th>
                                <th class="text-center inv-col-action">Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <template
                                v-for="(item, index) in form.items"
                                :key="item.id != null && item.id !== '' ? `line-${item.id}` : `new-${index}-${item.product_variant_id}-${item.warehouse_id}`">
                            <tr>
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
                                        :value="item.tax_id ?? ''"
                                        :disabled="!isDraft"
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
                                        @click="removeItem(index)"
                                        :disabled="!isDraft || form.items.length === 1"
                                        :title="form.items.length === 1 ? 'At least one line is required' : 'Remove line'">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="item.is_service" class="inv-tds-row">
                                <td></td>
                                <td colspan="7" class="py-1">
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <div class="form-check mb-0">
                                            <input
                                                type="checkbox"
                                                class="form-check-input"
                                                :id="`tds_applicable_${index}`"
                                                v-model="form.items[index].is_tds_applicable"
                                                :disabled="!isDraft"
                                                @change="onTdsToggle(index)"
                                            />
                                            <label :for="`tds_applicable_${index}`" class="form-check-label small text-secondary">
                                                TDS Applicable
                                            </label>
                                        </div>
                                        <template v-if="item.is_tds_applicable">
                                            <select
                                                class="form-select form-select-sm inv-tds-select"
                                                :value="item.tds_id ?? ''"
                                                :disabled="!isDraft"
                                                @change="onTdsTaxChange(index, $event.target.value)">
                                                <option value="">Select TDS category</option>
                                                <option v-for="t in tdsTaxes" :key="t.id" :value="t.id">
                                                    {{ t.name }} ({{ t.rate }}%)
                                                </option>
                                            </select>
                                            <span class="small text-secondary">
                                                Base: <strong>{{ formatMoney(Number(item.tds_base_amount) || 0) }}</strong>
                                                &nbsp;TDS: <strong class="text-warning">{{ formatMoney(Number(item.tds_amount) || 0) }}</strong>
                                            </span>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                            </template>
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

                <ChargesSection v-model:charges="form.charges" :disabled="!isDraft" />

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
                            <div v-if="chargesTotal > 0" class="d-flex justify-content-between">
                                <span>Charges</span>
                                <strong>{{ formatMoney(chargesTotal) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between border-top pt-2 mt-2">
                                <span>Grand Total</span>
                                <strong>{{ formatMoney(summary.grandTotal + chargesTotal) }}</strong>
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

                <div class="col-md-12">
                    <div class="form-check form-switch mb-3">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="inv_edit_tax_inclusive"
                            v-model="form.tax_inclusive"
                            @change="syncTaxAmounts"
                        />
                        <label class="form-check-label" for="inv_edit_tax_inclusive">
                            Tax-inclusive pricing (rates include VAT)
                        </label>
                    </div>
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
import {apiAdmin} from '@/helpers/api.js';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {array, object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {useProductStore} from '@/stores/admin/inventory/product.js';
import {usePartyStore} from '@/stores/admin/party.js';
import {useTaxStore} from '@/stores/admin/settings/tax.js';
import {useInvoiceStore} from '@/stores/admin/sales/invoice.js';
import ChargesSection from '@/components/sales/ChargesSection.vue';
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
import {useResolvedParty} from '@/composables/useResolvedParty.js';
import {usePartyDefaultOrderDiscount} from '@/composables/usePartyDefaultOrderDiscount.js';
import {warehouseIdForLineItem} from '@/helpers/productLineValidation.js';

const invoiceStore = useInvoiceStore();
const productStore = useProductStore();
const partyStore = usePartyStore();
const taxStore = useTaxStore();

const tdsTaxes = ref([]);

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
const {taxes} = storeToRefs(taxStore);

onMounted(() => {
    productStore.getProductVariants();
    partyStore.getParties({filter: {type: 'customer'}});
    taxStore.getTaxes({ filter: { for: 'line_item' } });
    apiAdmin('tax?for=tds&limit=100').then((r) => { tdsTaxes.value = r.data.data ?? []; });
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
    line_discount_type: 'fixed',
    line_discount_value: '0',
    warehouse_id: '',
    warehouse_name: '',
    stock_qty: null,
    is_service: false,
    is_tds_applicable: false,
    tds_id: '',
    tds_base_amount: '0',
    tds_amount: '0',
});

const initialState = {
    invoice_date: '',
    due_date: '',
    party_id: '',
    bijak_no: '',
    remarks: '',
    tax_inclusive: false,
    status: 'draft',
    order_discount_type: 'fixed',
    order_discount_value: '0',
    items: [emptyLine()],
    charges: [],
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

const chargesTotal = computed(() =>
    form.charges.reduce((sum, c) => sum + (Number(c.amount) || 0) + (Number(c.tax_amount) || 0), 0),
);

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
        form.tax_inclusive = !!data.tax_inclusive;
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
            tax_id: item.tax_id || '',
            line_discount_type: item.line_discount_type || 'fixed',
            line_discount_value:
                item.line_discount_value != null && item.line_discount_value !== ''
                    ? String(item.line_discount_value)
                    : '0',
            is_service: !!item.product_variant?.is_service,
            warehouse_id: item.warehouse_id || '',
            warehouse_name: item.warehouse?.name || '',
            stock_qty: null,
            is_tds_applicable: !!item.is_tds_applicable,
            tds_id: item.tds_id ? String(item.tds_id) : '',
            tds_base_amount: item.tds_base_amount != null ? String(item.tds_base_amount) : '0',
            tds_amount: item.tds_amount != null ? String(item.tds_amount) : '0',
        }));

        form.charges = (data.charges || []).map((c) => ({
            name: c.name || '',
            charge_type: c.charge_type || 'freight',
            account_id: c.account_id ? String(c.account_id) : '',
            amount: c.amount != null ? String(c.amount) : '0',
            tax_id: c.tax_id ? String(c.tax_id) : '',
            tax_amount: c.tax_amount != null ? String(c.tax_amount) : '0',
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

const onLineTaxChange = (index, taxId) => {
    form.items[index].tax_id = taxId || '';
    validateField(`items[${index}].tax_id`);
};

function onTdsToggle(index) {
    const item = form.items[index];
    if (!item.is_tds_applicable) {
        item.tds_id = '';
        item.tds_base_amount = '0';
        item.tds_amount = '0';
    }
}

function onTdsTaxChange(index, tdsId) {
    form.items[index].tds_id = tdsId || '';
    recalcTds(index);
}

function recalcTds(index) {
    const item = form.items[index];
    if (!item.is_tds_applicable || !item.tds_id) {
        item.tds_base_amount = '0';
        item.tds_amount = '0';
        return;
    }
    const tax = tdsTaxes.value.find((t) => String(t.id) === String(item.tds_id));
    const base = Math.max(0, Number(item.rate) * Number(item.quantity));
    item.tds_base_amount = String(base);
    item.tds_amount = tax ? String(Math.round(base * (tax.rate / 100) * 100) / 100) : '0';
}

const buildUpdatePayload = () => {
    syncTaxAmounts();

    return {
        invoice_date: form.invoice_date,
        due_date: form.due_date || null,
        party_id: form.party_id || null,
        remarks: form.remarks,
        tax_inclusive: form.tax_inclusive,
        order_discount_type: form.order_discount_type || 'fixed',
        order_discount_value: form.order_discount_value ?? '0',
        items: form.items.map((item, index) => ({
            product_variant_id: item.product_variant_id,
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
            is_tds_applicable: item.is_service ? (item.is_tds_applicable ?? false) : false,
            tds_id: (item.is_service && item.is_tds_applicable && item.tds_id) ? Number(item.tds_id) : null,
            tds_base_amount: (item.is_service && item.is_tds_applicable) ? (Number(item.tds_base_amount) || 0) : 0,
            tds_amount: (item.is_service && item.is_tds_applicable) ? (Number(item.tds_amount) || 0) : 0,
        })),
        charges: form.charges.map((c) => ({
            name: c.name,
            charge_type: c.charge_type,
            account_id: c.account_id ? Number(c.account_id) : null,
            amount: Number(c.amount) || 0,
            tax_id: c.tax_id ? Number(c.tax_id) : null,
            tax_amount: Number(c.tax_amount) || 0,
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
    Object.assign(form, {...initialState, items: [emptyLine()], charges: []});
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
.inv-tds-row td {
    background: #fdfaf3;
    border-top: none;
    padding-top: 0.25rem;
    padding-bottom: 0.25rem;
}
.inv-tds-select {
    min-width: 11rem;
    max-width: 16rem;
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
