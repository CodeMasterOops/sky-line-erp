<template>
    <VModal
        :show-modal="!!createModalOpened"
        @close-click="createModalOpened = false"
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
                                        <th class="so-col-unit">Unit</th>
                                        <th class="so-col-qty">Qty</th>
                                        <th class="so-col-rate">Rate (sale)</th>
                                        <th class="so-col-purchase">Purchase</th>
                                        <th class="so-col-disc">Discount</th>
                                        <th class="so-col-tax">Tax</th>
                                        <th class="so-col-amt">Tax amt</th>
                                        <th class="so-col-line">Line total</th>
                                        <th class="text-center so-col-action">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-if="!form.items.length">
                                        <td colspan="11" class="text-center text-muted py-4">
                                            Search and select a product to add lines.
                                        </td>
                                    </tr>
                                    <tr v-for="(item, index) in form.items" :key="`${index}-${item.product_variant_id}`">
                                        <td>{{ index + 1 }}</td>
                                        <td
                                            class="text-start text-truncate so-col-product"
                                            :title="item.product_label">
                                            {{ item.product_label }}
                                        </td>
                                        <td>
                                            <VSelect
                                                v-model="form.items[index].unit_id"
                                                select-class="form-select form-select-sm"
                                                :options="units.data"
                                                @validate="validateField(`items[${index}].unit_id`)"
                                                :error="errors[`items[${index}].unit_id`]"
                                            />
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
                                        <td class="text-end">{{ formatMoney(item.purchase_snapshot) }}</td>
                                        <td>
                                            <VInput
                                                input-type="number"
                                                input-class="form-control form-control-sm"
                                                v-model="form.items[index].discount_amount"
                                                @validate="validateField(`items[${index}].discount_amount`)"
                                                :error="errors[`items[${index}].discount_amount`]"
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
                                        <td class="text-end">{{ calcLineTax(item).toFixed(2) }}</td>
                                        <td class="text-end">{{ calcLineTotal(item).toFixed(2) }}</td>
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
                            <div class="total-order w-100 max-widthauto m-auto mb-4">
                                <ul>
                                    <li>
                                        <h4>Sub total</h4>
                                        <h5>{{ summary.subtotal }}</h5>
                                    </li>
                                    <li>
                                        <h4>Discount</h4>
                                        <h5>{{ summary.discount }}</h5>
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
import {computed, reactive, ref, watch} from 'vue';
import debounce from 'lodash/debounce';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {array, object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {useUnitStore} from '@/stores/admin/inventory/unit.js';
import {usePartyStore} from '@/stores/admin/party.js';
import {useTaxStore} from '@/stores/admin/setting/tax.js';
import {useSalesOrderStore} from '@/stores/admin/sales/sales-order.js';
import {useDateHelper} from '@/composables/dateHelper.js';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';

const salesOrderStore = useSalesOrderStore();
const unitStore = useUnitStore();
const partyStore = usePartyStore();
const taxStore = useTaxStore();

const {currentAdDate} = useDateHelper();

const createModalOpened = defineModel('createModalOpened');

const {units} = storeToRefs(unitStore);
const {parties} = storeToRefs(partyStore);
const {taxes} = storeToRefs(taxStore);

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
            unitStore.getUnits();
            taxStore.getTaxes();
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
    order_date: currentAdDate,
    party_id: '',
    remarks: '',
    status: 'draft',
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
        discount_amount: '0',
    });
};

const removeItem = (index) => {
    form.items.splice(index, 1);
};

const validations = object({
    order_date: string().required('Order date is required.'),
    party_id: string().nullable(),
    items: array()
        .of(
            object({
                product_variant_id: string().required('Product is required.'),
                quantity: string().required('Quantity is required.'),
                rate: string().required('Rate is required.'),
                unit_id: string().nullable(),
                tax_id: string().nullable(),
                discount_amount: string().nullable(),
            })
        )
        .min(1, 'At least one item is required.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const getTaxRate = (taxId) => {
    if (!taxId) {
        return 0;
    }
    const numericId = parseInt(taxId, 10);
    const tax = taxes.value.data.find((t) => t.id === numericId);
    return tax ? Number(tax.rate || 0) : 0;
};

const calcLineTax = (item) => {
    const qty = Number(item.quantity || 0);
    const rate = Number(item.rate || 0);
    const lineSubtotal = qty * rate;
    const lineDiscount = Number(item.discount_amount || 0);
    const taxRate = getTaxRate(item.tax_id);
    const taxable = Math.max(lineSubtotal - lineDiscount, 0);
    return taxable * (taxRate / 100);
};

const calcLineTotal = (item) => {
    const qty = Number(item.quantity || 0);
    const rate = Number(item.rate || 0);
    const lineSubtotal = qty * rate;
    const lineDiscount = Number(item.discount_amount || 0);
    return lineSubtotal - lineDiscount + calcLineTax(item);
};

const formatMoney = (value) => {
    if (value === '' || value === null || value === undefined) {
        return '—';
    }
    return Number(value).toFixed(2);
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
        subtotal += lineSubtotal;
        discount += lineDiscount;
        tax += calcLineTax(item);
    });

    const grandTotal = subtotal - discount + tax;

    return {
        subtotal: subtotal.toFixed(2),
        discount: discount.toFixed(2),
        tax: tax.toFixed(2),
        grandTotal: grandTotal.toFixed(2),
    };
});

const syncTaxAmounts = () => {
    form.items = form.items.map((item) => ({
        ...item,
        tax_amount: calcLineTax(item),
    }));
};

const buildOrderPayload = () => {
    syncTaxAmounts();
    return {
        order_date: form.order_date,
        party_id: form.party_id || null,
        remarks: form.remarks,
        status: form.status,
        items: form.items.map((item) => ({
            product_variant_id: item.product_variant_id,
            unit_id: item.unit_id || null,
            quantity: item.quantity,
            rate: item.rate,
            tax_id: item.tax_id || null,
            tax_amount: item.tax_amount ?? 0,
            discount_amount: item.discount_amount || null,
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
.sales-order-lines-table .so-col-unit {
    min-width: 7rem;
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
</style>
