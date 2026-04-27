<template>
    <VModal :show-modal="!!createModalOpened" @close-click="createModalOpened = false" size="xl"
        modal-class="add-centered" title="Add Purchase Order">
        <template #modal-body>
            <div class="card border-0 shadow-none mb-0">
                <div class="card-body p-0 border-0">
                    <form @submit.prevent="storeOrderWithStatus('draft')" class="row g-3">
                        <div class="col-lg-6 col-sm-6 col-12">
                            <div class="d-flex gap-2 align-items-end">
                                <div class="flex-grow-1">
                                    <VMultiselect id="party_id" v-model="form.party_id" :options="parties.data"
                                        label="Supplier Name" :filter-results="false"
                                        @validate="validateField('party_id')" required
                                        @search-change="debouncedSupplierSearch" :error="errors.party_id" />
                                </div>

                                <div class="ps-0">
                                    <div class="add-icon">
                                        <a href="#"
                                            class="bg-dark text-white p-2 rounded d-inline-flex align-items-center justify-content-center"
                                            title="Add supplier" @click.prevent="createSupplierOpened = true">
                                            <vue-feather type="plus-circle" class="plus"></vue-feather>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 col-sm-6 col-12">
                            <div class="input-blocks">
                                <VDatepicker id="order_date" v-model="form.order_date" label="Order Date"
                                    @validate="validateField('order_date')" :error="errors.order_date" />
                            </div>
                        </div>

                        <div class="col-12">
                            <ProductVariantSearchInput label="Product" required @select="onVariantSelected" />
                        </div>

                        <div class="col-12">
                            <div class="table-responsive no-pagination po-purchase-order-lines-wrap">
                                <table class="table datanew table-bordered mb-0 order-lines-table">
                                    <thead>
                                        <tr>
                                            <th class="po-col-sn">SN</th>
                                            <th class="po-col-product">Product</th>
                                            <th class="po-col-qty">Qty</th>
                                            <th class="po-col-rate"
                                                title="Purchase rate; line valuation is net of line discount and excludes tax.">
                                                Rate (purchase)
                                            </th>
                                            <th class="po-col-disc">Discount</th>
                                            <th class="po-col-tax">Tax</th>
                                            <th class="text-center po-col-action">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!form.items.length">
                                            <td colspan="7" class="text-center text-muted py-4">
                                                Search and select a product to add lines.
                                            </td>
                                        </tr>
                                        <tr v-for="(item, index) in form.items"
                                            :key="`${index}-${item.product_variant_id}`">
                                            <td>{{ index + 1 }}</td>
                                            <td class="text-start text-truncate po-col-product"
                                                :title="item.product_label">
                                                {{ item.product_label }}
                                            </td>
                                            <td>
                                                <VInput input-type="number" input-class="form-control form-control-sm"
                                                    v-model="form.items[index].quantity"
                                                    @validate="validateField(`items[${index}].quantity`)"
                                                    :error="errors[`items[${index}].quantity`]" />
                                            </td>
                                            <td>
                                                <VInput input-type="number" input-class="form-control form-control-sm"
                                                    v-model="form.items[index].rate"
                                                    @validate="validateField(`items[${index}].rate`)"
                                                    :error="errors[`items[${index}].rate`]" />
                                            </td>
                                            <td class="po-discount-cell">
                                                <VDiscountAmountTypeGroup
                                                    :input-id="`po_line_disc_${index}`"
                                                    v-model="form.items[index].line_discount_value"
                                                    v-model:discount-type="form.items[index].line_discount_type"
                                                    :error="errors[`items[${index}].line_discount_value`]"
                                                    extra-group-class="po-discount-input-group"
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
                                                    select-class="form-select form-select-sm po-line-tax-select"
                                                    :options="lineTaxOptions"
                                                    @validate="validateField(`items[${index}].tax_id`)"
                                                    :error="errors[`items[${index}].tax_id`]" />
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-outline-danger"
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
                            <div class="total-order w-100 max-widthauto m-auto mb-4">
                                <ul>
                                    <li>
                                        <h4>Sub total</h4>
                                        <h5>{{ summary.subtotal }}</h5>
                                    </li>
                                    <li class="po-total-order-discount">
                                        <h4>Discount</h4>
                                        <div class="po-total-order-discount__controls">
                                            <VDiscountAmountTypeGroup
                                                v-model="form.order_discount_value"
                                                v-model:discount-type="form.order_discount_type"
                                                :error="errors.order_discount_value"
                                                input-id="order_discount_value"
                                                extra-group-class="po-order-disc-input-group w-100"
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
                                        <h5>{{ summary.totalDiscount }}</h5>
                                    </li>
                                    <li>
                                        <h4>Non-taxable (net)</h4>
                                        <h5>{{ summary.nonTaxableBase }}</h5>
                                    </li>
                                    <li>
                                        <h4>Taxable (net)</h4>
                                        <h5>{{ summary.taxableBase }}</h5>
                                    </li>
                                    <li>
                                        <h4>Tax</h4>
                                        <h5>{{ summary.tax }}</h5>
                                    </li>
                                    <li>
                                        <h4>Grand total</h4>
                                        <h5>{{ summary.grandTotal }}</h5>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="input-blocks">
                                <VTextarea id="remarks" v-model="form.remarks" label="Remarks"
                                    @validate="validateField('remarks')" :error="errors.remarks" />
                            </div>
                        </div>

                        <div class="col-12 text-end">
                            <button @click="closeCreateModal" class="btn btn-cancel add-cancel me-2" type="button">
                                Cancel
                            </button>
                            <button type="button" class="btn btn-outline-primary me-2" :disabled="isSubmitting"
                                @click="storeOrderWithStatus('draft')">
                                Create
                            </button>
                            <button type="button" class="btn btn-submit add-sale btn-primary" :disabled="isSubmitting"
                                @click="storeOrderWithStatus('approved')">
                                Create &amp; Approve
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </VModal>
    <CreateSupplier v-if="createSupplierOpened" v-model:createModalOpened="createSupplierOpened" type="supplier" />
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import debounce from 'lodash/debounce';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { array, object, string } from 'yup';
import { useYup } from '@/helpers/yup';
import { storeToRefs } from 'pinia';
import { usePartyStore } from '@/stores/admin/party.js';
import { useTaxStore } from '@/stores/admin/setting/tax.js';
import { usePurchaseOrderStore } from '@/stores/admin/purchase/purchase-order.js';
import { useDateHelper } from '@/composables/dateHelper.js';
import {
    buildOrderAllocations,
    lineDiscountMoneyFromItem,
    lineNetFromItem,
    orderDiscountMoney,
} from '@/composables/purchaseOrderTotals.js';
import VDiscountAmountTypeGroup from '@/components/base/VDiscountAmountTypeGroup.vue';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import CreateSupplier from '@/views/admin/party/Create.vue';

const purchaseOrderStore = usePurchaseOrderStore();
const partyStore = usePartyStore();
const taxStore = useTaxStore();

const { currentAdDate } = useDateHelper();

const createModalOpened = defineModel('createModalOpened');
const createSupplierOpened = ref(false);

const { parties } = storeToRefs(partyStore);
const { taxes } = storeToRefs(taxStore);

/** No selection → API null; first option label matches theme `form-control-sm` row inputs. */
const lineTaxOptions = computed(() => {
    const list = Array.isArray(taxes.value.data) ? taxes.value.data : [];
    return [{ id: '', name: 'No tax' }, ...list];
});

const debouncedSupplierSearch = debounce((query) => {
    partyStore.getParties({
        filter: {
            type: 'supplier',
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
            partyStore.getParties({
                filter: {
                    type: 'supplier',
                    limit: 50,
                    search: '',
                },
            });
        }
    },
    { flush: 'post' }
);

const getInitialState = () => ({
    order_date: currentAdDate,
    party_id: '',
    remarks: '',
    status: 'draft',
    order_discount_type: 'fixed',
    order_discount_value: '0',
    items: [],
});

const form = reactive({ ...getInitialState() });
const isSubmitting = ref(false);

function variantLabel(variant) {
    let label = variant.name || '';
    if (variant.sku) {
        label += ` (${variant.sku})`;
    }
    return label;
}

/** PO line rate: purchase price, else list/sales price. */
function defaultLineRateString(variant) {
    const n = Number(variant.purchase_price ?? variant.sales_price ?? 0);
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
        list_sale_snapshot: variant.sales_price ?? 0,
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
    party_id: string().required('Supplier is required'),
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

const { errors, validateField, validateForm } = useYup(form, validations);

const getTaxRate = (taxId) => {
    if (!taxId) {
        return 0;
    }
    const numericId = parseInt(taxId, 10);
    const tax = taxes.value.data.find((t) => t.id === numericId);
    return tax ? Number(tax.rate || 0) : 0;
};

const orderLevelComputed = computed(() => {
    const nets = form.items.map((it) => lineNetFromItem(it));
    const sumLineNet = nets.reduce((a, b) => a + b, 0);
    const orderDisc = orderDiscountMoney(
        sumLineNet,
        form.order_discount_type || 'fixed',
        form.order_discount_value
    );
    const allocs = buildOrderAllocations(nets, orderDisc);
    return { nets, sumLineNet, orderDisc, allocs };
});

const calcLineTax = (item, index) => {
    const { nets, allocs } = orderLevelComputed.value;
    const lineNet = nets[index] ?? 0;
    const alloc = allocs[index] || 0;
    const taxable = Math.max(0, lineNet - alloc);
    const taxRate = getTaxRate(item.tax_id);
    return taxable * (taxRate / 100);
};

const summary = computed(() => {
    let subtotalGross = 0;
    let lineDiscount = 0;
    let tax = 0;
    let nonTaxableBase = 0;
    let taxableBase = 0;

    const { nets, allocs, orderDisc } = orderLevelComputed.value;
    const sumLineNet = nets.reduce((a, b) => a + b, 0);

    form.items.forEach((item, index) => {
        const g =
            (Number(item.quantity) || 0) * (Number(item.rate) || 0);
        const ld = lineDiscountMoneyFromItem(item);
        subtotalGross += g;
        lineDiscount += ld;
        tax += calcLineTax(item, index);

        const lineNet = nets[index] ?? 0;
        const alloc = allocs[index] || 0;
        const afterOrder = Math.max(0, lineNet - alloc);
        const r = getTaxRate(item.tax_id);
        if (r > 0) {
            taxableBase += afterOrder;
        } else {
            nonTaxableBase += afterOrder;
        }
    });

    const grandTotal = sumLineNet - orderDisc + tax;
    const totalDiscountAmount = lineDiscount + orderDisc;

    return {
        subtotal: subtotalGross.toFixed(2),
        lineDiscount: lineDiscount.toFixed(2),
        orderDiscount: orderDisc.toFixed(2),
        totalDiscount: totalDiscountAmount.toFixed(2),
        nonTaxableBase: nonTaxableBase.toFixed(2),
        taxableBase: taxableBase.toFixed(2),
        tax: tax.toFixed(2),
        grandTotal: grandTotal.toFixed(2),
    };
});

const syncTaxAmounts = () => {
    form.items = form.items.map((item, index) => ({
        ...item,
        tax_amount: calcLineTax(item, index),
    }));
};

const buildOrderPayload = () => {
    syncTaxAmounts();
    return {
        order_date: form.order_date,
        party_id: form.party_id || null,
        remarks: form.remarks,
        status: form.status,
        order_discount_type: form.order_discount_type || 'fixed',
        order_discount_value: form.order_discount_value ?? '0',
        items: form.items.map((item) => ({
            product_variant_id: item.product_variant_id,
            unit_id: item.unit_id || null,
            quantity: item.quantity,
            rate: item.rate,
            line_discount_type: item.line_discount_type || 'fixed',
            line_discount_value: item.line_discount_value ?? '0',
            tax_id: item.tax_id || null,
            tax_amount: item.tax_amount ?? 0,
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
            const res = await purchaseOrderStore.storeOrder(buildOrderPayload());
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
}
</script>

<style scoped>
.order-lines-table :deep(.form-control),
.order-lines-table :deep(.form-select) {
    min-width: 4.25rem;
}

/* Match `form-control-sm` in this theme: `.form-select` defaults to full-size min-height. */
.order-lines-table :deep(select.form-select-sm.po-line-tax-select) {
    min-height: calc(2px + 0.5rem + 0.8rem * 1.5);
    padding: 0.25rem 1.75rem 0.25rem 0.8rem;
    font-size: 0.8rem;
    line-height: 1.5;
    background-size: 0.9rem;
    background-position: right 0.45rem center;
}

.order-lines-table th,
.order-lines-table td {
    vertical-align: middle;
}

.order-lines-table .po-col-product {
    min-width: 11rem;
    max-width: 16rem;
}

.order-lines-table .po-col-tax {
    min-width: 7.5rem;
}

.order-lines-table .po-col-sn {
    width: 2.5rem;
}

.order-lines-table .po-col-action {
    width: 3rem;
}

.order-lines-table .po-discount-cell {
    min-width: 9rem;
    position: relative;
    z-index: 2;
    overflow: visible;
}

/* Let line discount menus escape table clipping (complements Popper fixed in VDiscountAmountTypeGroup). */
.po-purchase-order-lines-wrap {
    overflow: visible;
}

.total-order :deep(ul li.po-total-order-discount) {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.25rem;
}

.total-order :deep(ul li.po-total-order-discount h4) {
    width: 28%;
    min-width: 4.5rem;
    flex: 0 0 auto;
    border-right: 1px solid var(--bs-border-color, #dee2e6);
    margin: 0;
    padding: 0.5rem 0.5rem 0.5rem 0.625rem;
    align-self: stretch;
    display: flex;
    align-items: center;
}

.total-order :deep(ul li.po-total-order-discount .po-total-order-discount__controls) {
    flex: 1 1 40%;
    min-width: 0;
    max-width: 12rem;
    padding: 0.2rem 0.35rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.total-order :deep(ul li.po-total-order-discount h5) {
    width: auto;
    flex: 1 0 22%;
    min-width: 3.5rem;
    margin: 0;
    text-align: right;
    border-left: 0;
    padding: 0.5rem 0.625rem 0.5rem 0.5rem;
}

.po-order-disc-input-group {
    max-width: 15rem;
}
</style>
