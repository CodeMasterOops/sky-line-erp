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
                                label="Product *"
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
                                        <th class="inv-ref-col-unit">Unit</th>
                                        <th class="inv-ref-col-qty">Qty</th>
                                        <th class="inv-ref-col-rate">Rate (sale)</th>
                                        <th class="inv-ref-col-purchase">Purchase</th>
                                        <th class="inv-ref-col-disc">Discount</th>
                                        <th class="inv-ref-col-tax">Tax</th>
                                        <th class="inv-ref-col-amt">Tax amt</th>
                                        <th class="inv-ref-col-line">Line total</th>
                                        <th class="text-center inv-ref-col-action">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-if="!form.items.length">
                                        <td colspan="11" class="text-center text-muted py-4">
                                            Search and select a product to add lines.
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="(item, index) in form.items"
                                        :key="`${index}-${item.product_variant_id}`">
                                        <td>{{ index + 1 }}</td>
                                        <td
                                            class="text-start text-truncate inv-ref-col-product"
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
import {computed, reactive, ref, watch} from 'vue';
import debounce from 'lodash/debounce';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {array, object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import {useUnitStore} from '@/stores/admin/inventory/unit.js';
import {usePartyStore} from '@/stores/admin/party.js';
import {useTaxStore} from '@/stores/admin/setting/tax.js';
import {useWarehouseStore} from '@/stores/admin/inventory/warehouse.js';
import {useInvoiceStore} from '@/stores/admin/sales/invoice.js';
import {useDateHelper} from '@/composables/dateHelper.js';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';

const invoiceStore = useInvoiceStore();
const unitStore = useUnitStore();
const partyStore = usePartyStore();
const taxStore = useTaxStore();
const warehouseStore = useWarehouseStore();

const open = defineModel('open');
const referenceId = defineModel('referenceId');
const referenceType = defineModel('referenceType');

const {currentAdDate} = useDateHelper();

const {units} = storeToRefs(unitStore);
const {parties} = storeToRefs(partyStore);
const {taxes} = storeToRefs(taxStore);
const {warehouses} = storeToRefs(warehouseStore);

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
            unitStore.getUnits();
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
        discount_amount: '0',
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

const buildInvoicePayload = () => {
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
        items: form.items.map((item) => ({
            product_variant_id: item.product_variant_id,
            warehouse_id: wid,
            unit_id: item.unit_id || null,
            quantity: item.quantity,
            rate: item.rate,
            tax_id: item.tax_id || null,
            tax_amount: calcLineTax(item),
            discount_amount: item.discount_amount || null,
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
        form.items = (data.items || []).map((item) => ({
            product_variant_id: item.product_variant_id || '',
            product_label: lineLabelFromRefItem(item),
            purchase_snapshot: purchaseSnapshotFromRefItem(item),
            unit_id: item.unit_id ?? '',
            quantity: item.quantity != null ? String(item.quantity) : '',
            rate: item.rate != null ? String(item.rate) : '',
            tax_id: item.tax_id || '',
            discount_amount: item.discount_amount != null && item.discount_amount !== ''
                ? String(item.discount_amount)
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
.invoice-ref-lines-table .inv-ref-col-unit {
    min-width: 7rem;
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
</style>
