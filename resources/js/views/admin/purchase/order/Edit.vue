<template>
    <VModal
        :show-modal="!!edit_order_id"
        @close-click="closeEditModal"
        modal-class="edit-sales-modal"
        size="xl"
        title="Edit Purchase Order">
        <template #modal-body>
            <VLoader v-if="order.loading" loader-type="progress"/>
            <div v-else class="card border-0 shadow-none mb-0">
                <div class="card-body p-0">
                    <form @submit.prevent="updateOrder(order.data.id)" class="row g-3">
                        <div class="col-lg-6 col-sm-6 col-12">
                            <div class="input-blocks">
                                <VDatepicker
                                    id="order_date"
                                    input-type="date"
                                    v-model="form.order_date"
                                    label="Order Date"
                                    @validate="validateField('order_date')"
                                    :error="errors.order_date"
                                />
                            </div>
                        </div>
                        <div class="col-lg-6 col-sm-6 col-12">
                            <div class="input-blocks">
                                <VMultiselect
                                    id="party_id"
                                    v-model="form.party_id"
                                    :options="parties.data"
                                    label="Supplier"
                                    :filter-results="false"
                                    @validate="validateField('party_id')"
                                    @search-change="debouncedSupplierSearch"
                                    :error="errors.party_id"
                                />
                            </div>
                        </div>

                        <div v-if="isDraft" class="col-12">
                            <ProductVariantSearchInput
                                label="Product name / code / SKU"
                                @select="onVariantSelected"
                            />
                        </div>

                        <div class="col-12">
                            <div class="table-responsive no-pagination">
                                <table class="table datanew table-bordered mb-0 order-lines-table">
                                    <thead>
                                    <tr>
                                        <th class="po-col-sn">SN</th>
                                        <th class="po-col-product">Product</th>
                                        <th class="po-col-unit">Unit</th>
                                        <th class="po-col-qty">Qty</th>
                                        <th class="po-col-rate">Rate (purchase)</th>
                                        <th class="po-col-ref">Sale (ref)</th>
                                        <th class="po-col-disc">Discount</th>
                                        <th class="po-col-tax">Tax</th>
                                        <th class="po-col-amt">Tax amt</th>
                                        <th class="po-col-line">Line total</th>
                                        <th v-if="isDraft" class="text-center po-col-action">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-if="!form.items.length">
                                        <td :colspan="isDraft ? 11 : 10" class="text-center text-muted py-4">
                                            No line items.
                                        </td>
                                    </tr>
                                    <tr v-for="(item, index) in form.items" :key="`${index}-${item.product_variant_id}`">
                                        <td>{{ index + 1 }}</td>
                                        <td
                                            class="text-start text-truncate po-col-product"
                                            :title="item.product_label">
                                            {{ item.product_label }}
                                        </td>
                                        <td>
                                            <VSelect
                                                v-model="form.items[index].unit_id"
                                                select-class="form-select form-select-sm"
                                                :options="units.data"
                                                :disabled="!isDraft"
                                                @validate="validateField(`items[${index}].unit_id`)"
                                                :error="errors[`items[${index}].unit_id`]"
                                            />
                                        </td>
                                        <td>
                                            <VInput
                                                input-type="number"
                                                input-class="form-control form-control-sm"
                                                v-model="form.items[index].quantity"
                                                :disabled="!isDraft"
                                                @validate="validateField(`items[${index}].quantity`)"
                                                :error="errors[`items[${index}].quantity`]"
                                            />
                                        </td>
                                        <td>
                                            <VInput
                                                input-type="number"
                                                input-class="form-control form-control-sm"
                                                v-model="form.items[index].rate"
                                                :disabled="!isDraft"
                                                @validate="validateField(`items[${index}].rate`)"
                                                :error="errors[`items[${index}].rate`]"
                                            />
                                        </td>
                                        <td class="text-end">{{ formatMoney(item.list_sale_snapshot) }}</td>
                                        <td>
                                            <VInput
                                                input-type="number"
                                                input-class="form-control form-control-sm"
                                                v-model="form.items[index].discount_amount"
                                                :disabled="!isDraft"
                                                @validate="validateField(`items[${index}].discount_amount`)"
                                                :error="errors[`items[${index}].discount_amount`]"
                                            />
                                        </td>
                                        <td>
                                            <VSelect
                                                v-model="form.items[index].tax_id"
                                                select-class="form-select form-select-sm"
                                                :options="taxes.data"
                                                :disabled="!isDraft"
                                                @validate="validateField(`items[${index}].tax_id`)"
                                                :error="errors[`items[${index}].tax_id`]"
                                            />
                                        </td>
                                        <td class="text-end">{{ calcLineTax(item).toFixed(2) }}</td>
                                        <td class="text-end">{{ calcLineTotal(item).toFixed(2) }}</td>
                                        <td v-if="isDraft" class="text-center">
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
                            <button @click="closeEditModal" class="btn btn-cancel add-cancel me-2" type="button">
                                Cancel
                            </button>
                            <VButton v-if="isDraft" :loading="isSubmitting" :disabled="isSubmitting"/>
                            <button v-else type="button" class="btn btn-secondary" disabled>
                                Approved
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
import {usePurchaseOrderStore} from '@/stores/admin/purchase/purchase-order.js';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';

const purchaseOrderStore = usePurchaseOrderStore();
const unitStore = useUnitStore();
const partyStore = usePartyStore();
const taxStore = useTaxStore();

const edit_order_id = defineModel('order_id');

const {order} = storeToRefs(purchaseOrderStore);
const {units} = storeToRefs(unitStore);
const {parties} = storeToRefs(partyStore);
const {taxes} = storeToRefs(taxStore);

const debouncedSupplierSearch = debounce((query) => {
    partyStore.getParties({
        filter: {
            type: 'supplier',
            limit: 50,
            search: query || '',
        },
    });
}, 300);

const initialState = {
    order_date: '',
    party_id: '',
    remarks: '',
    status: 'draft',
    items: [],
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

function variantLabel(variant) {
    let label = variant.name || '';
    if (variant.sku) {
        label += ` (${variant.sku})`;
    }
    return label;
}

function defaultLineRateString(variant) {
    const n = Number(variant.purchase_price ?? variant.sales_price ?? 0);
    return String(Number.isFinite(n) ? n : 0);
}

function rateStringFromApiLine(item) {
    if (item.rate !== null && item.rate !== undefined && item.rate !== '') {
        return String(Number(item.rate));
    }
    if (item.product_variant) {
        return defaultLineRateString(item.product_variant);
    }
    return '0';
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
        discount_amount: '0',
    });
};

const removeItem = (index) => {
    form.items.splice(index, 1);
};

watch(
    () => edit_order_id.value,
    async (id) => {
        if (!id) {
            return;
        }
        unitStore.getUnits();
        taxStore.getTaxes();
        await purchaseOrderStore.getOrder(id);
        const data = order.value.data;
        await partyStore.getParties({
            filter: {
                type: 'supplier',
                limit: 50,
                search: data.party_name || '',
            },
        });
        const pid = data.party_id;
        const pname = data.party_name;
        if (pid && pname && !partyStore.parties.data.some((p) => String(p.id) === String(pid))) {
            partyStore.parties.data = [{id: pid, name: pname}, ...partyStore.parties.data];
        }
        Object.keys(form).forEach((key) => {
            if (key === 'items') {
                form.items = (data.items || []).map((item) => ({
                    product_variant_id: item.product_variant_id || '',
                    product_label: item.product_variant ? variantLabel(item.product_variant) : '',
                    list_sale_snapshot: item.product_variant?.sales_price ?? 0,
                    unit_id: item.unit_id || '',
                    quantity: String(item.quantity ?? '1'),
                    rate: rateStringFromApiLine(item),
                    tax_id: item.tax_id || '',
                    discount_amount:
                        item.discount_amount !== null && item.discount_amount !== undefined
                            ? String(item.discount_amount)
                            : '0',
                }));
            } else {
                form[key] = data[key] ?? (key === 'items' ? [] : '');
            }
        });
    }
);

const isDraft = computed(() => order.value.data.status === 'draft');

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

const updateOrder = async (id) => {
    if (!isDraft.value) {
        return;
    }
    const validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            const res = await purchaseOrderStore.updateOrder(id, buildOrderPayload());
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
    edit_order_id.value = '';
};

function resetForm() {
    Object.assign(form, {...initialState});
    errors.value = {};
}
</script>

<style scoped>
.order-lines-table :deep(.form-control),
.order-lines-table :deep(.form-select) {
    min-width: 4.25rem;
}
.order-lines-table th,
.order-lines-table td {
    vertical-align: middle;
}
.order-lines-table .po-col-product {
    min-width: 11rem;
    max-width: 16rem;
}
.order-lines-table .po-col-unit {
    min-width: 7rem;
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
</style>
