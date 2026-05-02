<template>
    <VModal
        :show-modal="!!edit_quotation_id"
        @close-click="closeEditModal"
        modal-class="edit-sales-modal large-modal"
        size="xl"
        title="Update Quotation">
        <template #modal-body>
            <VLoader v-if="quotation.loading" loader-type="progress"/>
            <div v-else class="card border-0 shadow-none mb-0">
                <div class="card-body p-0 border-0">
                    <form @submit.prevent="updateQuotation(quotation.data.id)" class="row g-2">
                        <div class="col-12">
                            <p class="text-muted small mb-0">
                                Update dates, customer, and lines while the quotation is in draft. Approved quotations are read-only.
                            </p>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="input-blocks">
                                <VDatepicker
                                    id="quotation_date"
                                    input-type="date"
                                    v-model="form.quotation_date"
                                    label="Quotation Date"
                                    :disabled="!isDraft"
                                    @validate="validateField('quotation_date')"
                                    :error="errors.quotation_date"
                                />
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="input-blocks">
                                <VDatepicker
                                    id="expiry_date"
                                    input-type="date"
                                    v-model="form.expiry_date"
                                    label="Expiry Date"
                                    :disabled="!isDraft"
                                    @validate="validateField('expiry_date')"
                                    :error="errors.expiry_date"
                                />
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="input-blocks">
                                <div class="d-flex gap-2 align-items-end">
                                    <div class="flex-grow-1 min-w-0">
                                        <VMultiselect
                                            id="party_id"
                                            v-model="form.party_id"
                                            :options="parties.data"
                                            label="Customer"
                                            :filter-results="false"
                                            :disabled="!isDraft"
                                            @validate="validateField('party_id')"
                                            @search-change="debouncedPartySearch"
                                            :error="errors.party_id"
                                        />
                                    </div>
                                    <div v-if="isDraft" class="ps-0 flex-shrink-0">
                                        <div class="add-icon">
                                            <a
                                                href="#"
                                                class="bg-dark text-white p-2 rounded d-inline-flex align-items-center justify-content-center"
                                                title="Add customer"
                                                @click.prevent="createCustomerOpened = true">
                                                <vue-feather type="plus-circle" class="plus"></vue-feather>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-if="resolvedParty" class="col-12">
                            <PartyMetaPanel
                                :party="resolvedParty"
                                pan-heading="Customer PAN"
                            />
                        </div>

                        <div v-if="isDraft" class="col-12">
                            <ProductVariantSearchInput
                                label="Product"
                                required
                                @select="onVariantSelected"
                            />
                            <span class="form-text text-muted small">Search by name, code, or SKU to add a line.</span>
                        </div>

                        <div class="col-12">
                            <div class="table-responsive no-pagination">
                                <table class="table datanew table-bordered mb-0 quotation-lines-table">
                                    <thead>
                                    <tr>
                                        <th class="qt-col-sn">SN</th>
                                        <th class="qt-col-product">Product</th>
                                        <th class="qt-col-qty">Qty</th>
                                        <th class="qt-col-rate">Rate</th>
                                        <th class="qt-col-disc">Discount</th>
                                        <th class="qt-col-tax">Tax</th>
                                        <th v-if="isDraft" class="text-center qt-col-action">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-if="!form.items.length">
                                        <td :colspan="isDraft ? 7 : 6" class="text-center text-muted py-4">
                                            <template v-if="isDraft">Search and select a product to add lines.</template>
                                            <template v-else>No line items.</template>
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="(item, index) in form.items"
                                        :key="item.id != null && item.id !== '' ? `line-${item.id}` : `n-${index}-${item.product_variant_id}`"
                                        v-memo="[
                                            item.quantity,
                                            item.rate,
                                            item.line_discount_type,
                                            item.line_discount_value,
                                            item.tax_id,
                                            isDraft,
                                        ]">
                                        <td>{{ index + 1 }}</td>
                                        <td class="text-start text-truncate qt-col-product" :title="item.product_label">
                                            {{ item.product_label }}
                                        </td>
                                        <td>
                                            <VInput
                                                v-if="isDraft"
                                                input-type="number"
                                                input-class="form-control form-control-sm"
                                                v-model="form.items[index].quantity"
                                                min="1"
                                                step="1"
                                                @validate="validateField(`items[${index}].quantity`)"
                                                :error="errors[`items[${index}].quantity`]"
                                            />
                                            <span v-else>{{ item.quantity }}</span>
                                        </td>
                                        <td>
                                            <VInput
                                                v-if="isDraft"
                                                input-type="number"
                                                input-class="form-control form-control-sm"
                                                v-model="form.items[index].rate"
                                                @validate="validateField(`items[${index}].rate`)"
                                                :error="errors[`items[${index}].rate`]"
                                            />
                                            <span v-else>{{ formatMoney(item.rate) }}</span>
                                        </td>
                                        <td :class="{'qt-discount-cell': isDraft}">
                                            <template v-if="isDraft">
                                                <VDiscountAmountTypeGroup
                                                    :input-id="`qt_edit_line_disc_${item.id ?? index}`"
                                                    :input-aria-label="`Line ${index + 1} discount`"
                                                    v-model="form.items[index].line_discount_value"
                                                    v-model:discount-type="form.items[index].line_discount_type"
                                                    :error="errors[`items[${index}].line_discount_value`]"
                                                    :disabled="isSubmitting"
                                                    extra-group-class="qt-discount-input-group"
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
                                                v-if="isDraft"
                                                v-model="form.items[index].tax_id"
                                                select-class="form-select form-select-sm line-item-tax-select"
                                                :options="lineTaxOptions"
                                                @validate="validateField(`items[${index}].tax_id`)"
                                                :error="errors[`items[${index}].tax_id`]"
                                            />
                                            <span v-else>{{ taxNameFor(item.tax_id) }}</span>
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
                            <div class="card bg-light mb-0">
                                <div class="card-body py-2">
                                    <div class="d-flex justify-content-between">
                                        <span>Sub total</span>
                                        <strong>{{ summary.subtotal }}</strong>
                                    </div>
                                    <template v-if="isDraft">
                                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border-top pt-2 mt-2">
                                            <span>Order discount</span>
                                            <div class="qt-order-disc flex-grow-1">
                                                <VDiscountAmountTypeGroup
                                                    v-model="form.order_discount_value"
                                                    v-model:discount-type="form.order_discount_type"
                                                    :error="errors.order_discount_value"
                                                    input-id="qt_edit_order_discount_value"
                                                    input-aria-label="Order-level discount"
                                                    :disabled="isSubmitting"
                                                    extra-group-class="qt-order-disc-input-group"
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
                                    </template>
                                    <div v-else class="d-flex justify-content-between border-top pt-2 mt-2">
                                        <span>Discount (lines + order)</span>
                                        <strong>{{ summary.totalDiscount }}</strong>
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
                                    :disabled="!isDraft"
                                    @validate="validateField('remarks')"
                                    :error="errors.remarks"
                                />
                            </div>
                        </div>

                        <div class="col-12 text-end border-top pt-3 mt-1">
                            <button @click="closeEditModal" class="btn btn-cancel me-2" type="button">
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
    <CreateCustomer
        v-if="createCustomerOpened"
        v-model:create-modal-opened="createCustomerOpened"
        type="customer"
    />
</template>

<script setup>
import {computed, reactive, ref, toRef, watch} from 'vue';
import debounce from 'lodash/debounce';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {array, object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {usePartyStore} from '@/stores/admin/party.js';
import {useTaxStore} from '@/stores/admin/settings/tax.js';
import {useQuotationStore} from '@/stores/admin/sales/quotation.js';
import {lineDiscountMoneyFromItem} from '@/composables/purchaseOrderTotals.js';
import {useLineOrderDiscountTotals} from '@/composables/useLineOrderDiscountTotals.js';
import {useLineItemTaxOptions} from '@/composables/useLineItemTaxOptions.js';
import {useResolvedParty} from '@/composables/useResolvedParty.js';
import PartyMetaPanel from '@/components/party/PartyMetaPanel.vue';
import VDiscountAmountTypeGroup from '@/components/base/VDiscountAmountTypeGroup.vue';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import CreateCustomer from '@/views/admin/party/Create.vue';

const quotationStore = useQuotationStore();
const partyStore = usePartyStore();
const taxStore = useTaxStore();

const edit_quotation_id = defineModel('quotation_id');
const createCustomerOpened = ref(false);

const {quotation} = storeToRefs(quotationStore);
const {parties} = storeToRefs(partyStore);
const {taxes} = storeToRefs(taxStore);

const lineTaxOptions = useLineItemTaxOptions(taxes);

const debouncedPartySearch = debounce((query) => {
    partyStore.getParties({
        filter: {
            type: 'customer',
            limit: 50,
            search: query || '',
        },
    });
}, 300);

const initialState = {
    quotation_date: '',
    expiry_date: '',
    party_id: '',
    remarks: '',
    status: 'draft',
    order_discount_type: 'fixed',
    order_discount_value: '0',
    items: [],
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

const resolvedParty = useResolvedParty(toRef(form, 'party_id'), parties);

const isDraft = computed(() => quotation.value.data?.status === 'draft');

function variantLabel(variant) {
    if (!variant) {
        return '';
    }
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

const taxNameFor = (taxId) => {
    const list = taxes.value?.data || [];
    const row = list.find((t) => String(t.id) === String(taxId));
    return row?.name ?? '—';
};

const onVariantSelected = (variant) => {
    if (!isDraft.value) {
        return;
    }
    const vid = variant.id;
    const existing = form.items.findIndex((i) => String(i.product_variant_id) === String(vid));
    if (existing !== -1) {
        const nextQty = Number(form.items[existing].quantity || 0) + 1;
        form.items[existing].quantity = String(nextQty);
        return;
    }
    form.items.push({
        id: '',
        product_variant_id: vid,
        product_label: variantLabel(variant),
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

watch(
    () => edit_quotation_id.value,
    async (id) => {
        if (!id) {
            return;
        }
        taxStore.getTaxes();
        await quotationStore.getQuotation(id);
        const data = quotation.value.data;

        await partyStore.getParties({
            filter: {
                type: 'customer',
                limit: 50,
                search: data.party_name || '',
            },
        });
        const pid = data.party_id;
        const pname = data.party_name;
        if (pid && pname && !partyStore.parties.data.some((p) => String(p.id) === String(pid))) {
            partyStore.parties.data = [{id: pid, name: pname}, ...partyStore.parties.data];
        }

        const odv = data.order_discount_value;
        form.quotation_date = data.quotation_date || '';
        form.expiry_date = data.expiry_date || '';
        form.party_id = data.party_id || '';
        form.remarks = data.remarks || '';
        form.status = data.status || 'draft';
        form.order_discount_type = data.order_discount_type || 'fixed';
        form.order_discount_value = odv != null && odv !== '' ? String(odv) : '0';

        const rows = (data.items || []).length ? data.items : [];
        form.items = rows.map((item) => ({
            id: item.id ?? '',
            product_variant_id: item.product_variant_id || '',
            product_label: item.product_variant ? variantLabel(item.product_variant) : '',
            unit_id: item.unit_id || '',
            quantity: item.quantity != null && item.quantity !== '' ? String(item.quantity) : '1',
            rate: item.rate != null && item.rate !== '' ? String(item.rate) : '',
            tax_id: item.tax_id || '',
            line_discount_type: item.line_discount_type || 'fixed',
            line_discount_value:
                item.line_discount_value != null && item.line_discount_value !== ''
                    ? String(item.line_discount_value)
                    : '0',
        }));
    }
);

const validations = object({
    quotation_date: string().required('Quotation date is required.'),
    expiry_date: string().nullable(),
    party_id: string().nullable(),
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

const buildQuotationPayload = () => {
    syncTaxAmounts();
    return {
        quotation_date: form.quotation_date,
        expiry_date: form.expiry_date || null,
        party_id: form.party_id || null,
        remarks: form.remarks,
        status: form.status,
        order_discount_type: form.order_discount_type || 'fixed',
        order_discount_value: form.order_discount_value ?? '0',
        items: form.items.map((item, index) => ({
            product_variant_id: item.product_variant_id,
            unit_id: item.unit_id || null,
            quantity: lineQtyInt(item.quantity),
            rate: Number(item.rate || 0),
            tax_id: item.tax_id || null,
            tax_amount: calcLineTax(item, index),
            line_discount_type: item.line_discount_type || 'fixed',
            line_discount_value: item.line_discount_value ?? '0',
            discount_amount: String(lineDiscountMoneyFromItem(item)),
        })),
    };
};

const updateQuotation = async (id) => {
    if (!isDraft.value) {
        return;
    }
    const validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            const res = await quotationStore.updateQuotation(id, buildQuotationPayload());
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
    edit_quotation_id.value = '';
};

function resetForm() {
    createCustomerOpened.value = false;
    Object.assign(form, {...initialState, items: []});
    errors.value = {};
}
</script>

<style scoped>
.quotation-lines-table :deep(.form-control),
.quotation-lines-table :deep(.form-select) {
    min-width: 4.25rem;
}
.quotation-lines-table th,
.quotation-lines-table td {
    vertical-align: middle;
}
.quotation-lines-table .qt-col-product {
    min-width: 11rem;
    max-width: 18rem;
}
.quotation-lines-table .qt-col-sn {
    width: 2.5rem;
}
.quotation-lines-table .qt-col-action {
    width: 3rem;
}
.quotation-lines-table .qt-discount-cell {
    min-width: 9rem;
    position: relative;
    z-index: 2;
    overflow: visible;
}
.qt-order-disc {
    min-width: 0;
    max-width: 14rem;
}
</style>
