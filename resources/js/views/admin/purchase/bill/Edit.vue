<template>
    <VModal
        :show-modal="!!edit_bill_id"
        @close-click="closeEditModal"
        modal-class="edit-sales-modal"
        size="xl"
        title="Update Bill">
        <template #modal-body>
            <VLoader v-if="bill.loading" loader-type="progress"/>
            <div v-else class="card border-0 shadow-none mb-0">
                <div class="card-body p-0">
                    <form @submit.prevent="updateBill(bill.data.id)" class="row g-1">
                        <div class="col-lg-4 col-sm-6 col-12">
                            <div class="input-blocks">
                                <VDatepicker
                                    id="bill_date"
                                    input-type="date"
                                    v-model="form.bill_date"
                                    label="Bill Date"
                                    :disabled="!isDraft"
                                    @validate="validateField('bill_date')"
                                    :error="errors.bill_date"
                                />
                            </div>
                        </div>
                        <div class="col-lg-4 col-sm-6 col-12">
                            <div class="input-blocks">
                                <VDatepicker
                                    id="due_date"
                                    input-type="date"
                                    v-model="form.due_date"
                                    label="Due Date"
                                    :disabled="!isDraft"
                                    @validate="validateField('due_date')"
                                    :error="errors.due_date"
                                />
                            </div>
                        </div>
                        <div class="col-lg-4 col-sm-6 col-12">
                            <div class="d-flex gap-2 align-items-end">
                                <div class="flex-grow-1">
                                    <VMultiselect
                                        id="party_id"
                                        v-model="form.party_id"
                                        :options="parties.data"
                                        label="Supplier Name"
                                        :filter-results="false"
                                        :disabled="!isDraft"
                                        @validate="validateField('party_id')"
                                        @search-change="debouncedSupplierSearch"
                                        :error="errors.party_id"
                                    />
                                </div>
                                <div v-if="isDraft" class="ps-0">
                                    <div class="add-icon">
                                        <a
                                            href="#"
                                            class="bg-dark text-white p-2 rounded d-inline-flex align-items-center justify-content-center"
                                            title="Add supplier"
                                            @click.prevent="createSupplierOpened = true">
                                            <vue-feather type="plus-circle" class="plus"></vue-feather>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 col-sm-6 col-12">
                            <div class="input-blocks">
                                <VMultiselect
                                    id="warehouse_id"
                                    v-model="form.warehouse_id"
                                    :options="warehouses.data"
                                    label="Warehouse"
                                    :disabled="!isDraft"
                                    @validate="validateField('warehouse_id')"
                                    :error="errors.warehouse_id"
                                />
                            </div>
                        </div>

                        <div class="col-lg-4 col-sm-6 col-12">
                            <div class="input-blocks">
                                <VInput
                                    id="seller_pan"
                                    v-model="form.seller_pan"
                                    label="Seller PAN"
                                    placeholder="Supplier PAN (required for VAT bills)"
                                    :disabled="!isDraft"
                                />
                            </div>
                        </div>

                        <div v-if="isDraft" class="col-12">
                            <ProductVariantSearchInput
                                label="Product name / code / SKU"
                                required
                                @select="onVariantSelected"
                            />
                        </div>

                        <div class="col-12">
                            <div
                                class="table-responsive no-pagination"
                                :class="{'po-purchase-order-lines-wrap': isDraft}">
                                <table class="table datanew table-bordered mb-0 order-lines-table">
                                    <thead>
                                    <tr>
                                        <th class="po-col-sn">SN</th>
                                        <th class="po-col-product">Product</th>
                                        <th class="po-col-qty">Qty</th>
                                        <th
                                            class="po-col-rate"
                                            title="Purchase rate; inventory cost is net of line discount and excludes tax.">
                                            Rate (purchase)</th>
                                        <th class="po-col-disc">Discount</th>
                                        <th class="po-col-tax">Tax</th>
                                        <th v-if="isDraft" class="text-center po-col-action">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-if="!form.items.length">
                                        <td :colspan="isDraft ? 7 : 6" class="text-center text-muted py-4">
                                            No line items.
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="(item, index) in form.items"
                                        :key="item.id ?? `n-${index}-${item.product_variant_id}`"
                                        v-memo="[
                                            item.id,
                                            item.quantity,
                                            item.rate,
                                            item.line_discount_type,
                                            item.line_discount_value,
                                            item.tax_id,
                                            isDraft,
                                        ]">
                                        <td>{{ index + 1 }}</td>
                                        <td
                                            class="text-start text-truncate po-col-product"
                                            :title="item.product_label">
                                            {{ item.product_label }}
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
                                        <td class="text-end" :class="{'po-discount-cell': isDraft}">
                                            <template v-if="isDraft">
                                                <VDiscountAmountTypeGroup
                                                    :input-id="`bill_edit_line_disc_${index}`"
                                                    :input-aria-label="`Line ${index + 1} discount`"
                                                    v-model="form.items[index].line_discount_value"
                                                    v-model:discount-type="form.items[index].line_discount_type"
                                                    :error="errors[`items[${index}].line_discount_value`]"
                                                    :disabled="isSubmitting"
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
                                            </template>
                                            <span v-else>{{ formatMoney(lineDiscountMoneyFromItem(item)) }}</span>
                                        </td>
                                        <td>
                                            <VSelect
                                                v-model="form.items[index].tax_id"
                                                select-class="form-select form-select-sm po-line-tax-select"
                                                :options="lineTaxOptions"
                                                :disabled="!isDraft"
                                                @validate="validateField(`items[${index}].tax_id`)"
                                                :error="errors[`items[${index}].tax_id`]"
                                            />
                                        </td>
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
                                    <li v-if="!isDraft">
                                        <h4>Discount</h4>
                                        <h5>{{ summary.totalDiscount }}</h5>
                                    </li>
                                    <li v-else class="po-total-order-discount">
                                        <h4>Discount</h4>
                                        <div class="po-total-order-discount__controls">
                                            <VDiscountAmountTypeGroup
                                                v-model="form.order_discount_value"
                                                v-model:discount-type="form.order_discount_type"
                                                :error="errors.order_discount_value"
                                                input-id="bill_edit_order_discount_value"
                                                input-aria-label="Order-level discount"
                                                :disabled="isSubmitting"
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
                                <VTextarea
                                    id="remarks"
                                    v-model="form.remarks"
                                    label="Remarks"
                                    :disabled="!isDraft"
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
    <CreateSupplier
        v-if="createSupplierOpened"
        v-model:createModalOpened="createSupplierOpened"
        type="supplier"
    />
</template>

<script setup>
import {computed, nextTick, reactive, ref, watch} from 'vue';
import debounce from 'lodash/debounce';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {array, object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {usePartyStore} from '@/stores/admin/party.js';
import {useTaxStore} from '@/stores/admin/setting/tax.js';
import {useWarehouseStore} from '@/stores/admin/inventory/warehouse.js';
import {useBillStore} from '@/stores/admin/purchase/bill.js';
import {lineDiscountMoneyFromItem} from '@/composables/purchaseOrderTotals.js';
import {useLineOrderDiscountTotals} from '@/composables/useLineOrderDiscountTotals.js';
import VDiscountAmountTypeGroup from '@/components/base/VDiscountAmountTypeGroup.vue';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import CreateSupplier from '@/views/admin/party/Create.vue';

const billStore = useBillStore();
const partyStore = usePartyStore();
const taxStore = useTaxStore();
const warehouseStore = useWarehouseStore();

const edit_bill_id = defineModel('bill_id');
const createSupplierOpened = ref(false);

const {bill} = storeToRefs(billStore);
const {parties} = storeToRefs(partyStore);
const {taxes} = storeToRefs(taxStore);
const {warehouses} = storeToRefs(warehouseStore);

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

const initialState = {
    bill_date: '',
    due_date: '',
    party_id: '',
    warehouse_id: '',
    seller_pan: '',
    remarks: '',
    status: 'draft',
    order_discount_type: 'fixed',
    order_discount_value: '0',
    items: [],
};

const form = reactive({...initialState});
const isSubmitting = ref(false);
const isHydratingBill = ref(false);

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
        tax_line_type: 'taxable',
        line_discount_type: 'fixed',
        line_discount_value: '0',
    });
};

const removeItem = (index) => {
    form.items.splice(index, 1);
};

watch(
    () => edit_bill_id.value,
    async (id) => {
        if (!id) {
            return;
        }
        taxStore.getTaxes();
        warehouseStore.getWarehouses();
        await billStore.getBill(id);
        const data = bill.value.data;
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

        const whId = data.items?.[0]?.warehouse_id;
        const whName = data.items?.[0]?.warehouse?.name;
        if (whId && whName && !warehouseStore.warehouses.data.some((w) => String(w.id) === String(whId))) {
            warehouseStore.warehouses.data = [{id: whId, name: whName}, ...warehouseStore.warehouses.data];
        }

        isHydratingBill.value = true;
        Object.keys(form).forEach((key) => {
            if (key === 'items') {
                form.items = (data.items || []).map((item) => ({
                    id: item.id,
                    product_variant_id: item.product_variant_id || '',
                    product_label: item.product_variant ? variantLabel(item.product_variant) : '',
                    list_sale_snapshot: item.product_variant?.sales_price ?? 0,
                    unit_id: item.unit_id || '',
                    quantity: String(item.quantity ?? '1'),
                    rate: rateStringFromApiLine(item),
                    tax_id: item.tax_id || '',
                    tax_line_type: item.tax_line_type || 'taxable',
                    line_discount_type: item.line_discount_type || 'fixed',
                    line_discount_value: String(
                        item.line_discount_value != null
                            ? item.line_discount_value
                            : (item.discount_amount ?? 0)
                    ),
                }));
            } else if (key === 'warehouse_id') {
                form.warehouse_id = whId || '';
            } else {
                form[key] = data[key] ?? (key === 'items' ? [] : '');
            }
        });
        form.order_discount_type = data.order_discount_type || 'fixed';
        form.order_discount_value =
            data.order_discount_value != null && data.order_discount_value !== ''
                ? String(data.order_discount_value)
                : '0';
        await nextTick();
        isHydratingBill.value = false;
    }
);

const isDraft = computed(() => bill.value.data.status === 'draft');

const validations = object({
    bill_date: string().required('Bill date is required.'),
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

const {summary, syncTaxAmounts} = useLineOrderDiscountTotals({form, taxes});

const formatMoney = (value) => {
    if (value === '' || value === null || value === undefined) {
        return '—';
    }
    return Number(value).toFixed(2);
};

const lineQtyInt = (q) => {
    const n = parseInt(String(q ?? '0'), 10);
    return Number.isFinite(n) && n > 0 ? n : 1;
};

const buildBillPayload = () => {
    syncTaxAmounts();
    const wid = form.warehouse_id;
    return {
        bill_date: form.bill_date,
        due_date: form.due_date || null,
        party_id: form.party_id || null,
        remarks: form.remarks,
        order_discount_type: form.order_discount_type || 'fixed',
        order_discount_value: form.order_discount_value ?? '0',
        items: form.items.map((item) => ({
            product_variant_id: item.product_variant_id,
            warehouse_id: wid,
            unit_id: item.unit_id || null,
            quantity: lineQtyInt(item.quantity),
            rate: Number(item.rate || 0),
            line_discount_type: item.line_discount_type || 'fixed',
            line_discount_value: item.line_discount_value ?? '0',
            tax_id: item.tax_id || null,
            tax_amount: item.tax_amount ?? 0,
            discount_amount: String(lineDiscountMoneyFromItem(item)),
            tax_line_type: item.tax_line_type || 'taxable',
        })),
    };
};

const updateBill = async (id) => {
    if (!isDraft.value) {
        return;
    }
    const validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            const res = await billStore.updateBill(id, buildBillPayload());
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
    edit_bill_id.value = '';
};

function resetForm() {
    isHydratingBill.value = false;
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
.order-lines-table .po-col-tax {
    min-width: 7.5rem;
}
.order-lines-table .po-col-sn {
    width: 2.5rem;
}
.order-lines-table .po-col-action {
    width: 3rem;
}

.order-lines-table :deep(select.form-select-sm.po-line-tax-select) {
    min-height: calc(2px + 0.5rem + 0.8rem * 1.5);
    padding: 0.25rem 1.75rem 0.25rem 0.8rem;
    font-size: 0.8rem;
    line-height: 1.5;
    background-size: 0.9rem;
    background-position: right 0.45rem center;
}

.order-lines-table .po-discount-cell {
    min-width: 9rem;
    position: relative;
    z-index: 2;
    overflow: visible;
}

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
