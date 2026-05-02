<template>
    <VModal
        :show-modal="!!edit_credit_note_id"
        @close-click="closeEditModal"
        modal-class="edit-sales-modal"
        size="xl"
        title="Update Credit Note">
        <template #modal-body>
            <VLoader v-if="creditNote.loading" loader-type="progress"/>
            <div v-else class="card border-0 shadow-none mb-0">
                <div class="card-body p-0">
                    <form @submit.prevent="updateCreditNote(creditNote.data.id)" class="row g-2">
                        <div class="col-12">
                            <p class="text-muted small mb-0">
                                Select customer, search an approved invoice, and add return lines from that invoice where possible,
                                or add products manually. Warehouse applies to all lines.
                            </p>
                        </div>
                        <div class="col-lg-4 col-sm-6 col-12">
                            <div class="input-blocks">
                                <VDatepicker
                                    id="credit_note_date"
                                    input-type="date"
                                    v-model="form.credit_note_date"
                                    label="Credit Note Date"
                                    :disabled="!isDraft"
                                    @validate="validateField('credit_note_date')"
                                    :error="errors.credit_note_date"
                                />
                            </div>
                        </div>
                        <div class="col-lg-4 col-sm-6 col-12">
                            <div class="input-blocks">
                                <div class="d-flex gap-2 align-items-end">
                                    <div class="flex-grow-1">
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
                                    <div v-if="isDraft" class="ps-0">
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
                                <PartyMetaPanel
                                    v-if="resolvedParty"
                                    :party="resolvedParty"
                                    pan-heading="Customer PAN"
                                />
                            </div>
                        </div>
                        <div class="col-lg-4 col-sm-6 col-12">
                            <div class="input-blocks">
                                <VMultiselect
                                    id="invoice_id"
                                    v-model="form.invoice_id"
                                    :options="invoicePickOptions"
                                    name-prop="label"
                                    value-prop="id"
                                    label="Invoice"
                                    :loading="invoicePickLoading"
                                    :disabled="!isDraft || !form.party_id"
                                    :filter-results="false"
                                    @search-change="debouncedInvoiceSearch"
                                    @validate="validateField('invoice_id')"
                                    :error="errors.invoice_id"
                                />
                                <span v-if="isDraft && !form.party_id" class="form-text text-muted small">Select customer first.</span>
                                <span v-else-if="isDraft" class="form-text text-muted small">Search approved invoices for this customer.</span>
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

                        <div v-if="isDraft && form.invoice_id && invoiceLinePickOptions.length" class="col-12">
                            <VMultiselect
                                id="invoice_line_pick_edit"
                                v-model="invoiceLinePickSelection"
                                :options="invoiceLinePickOptions"
                                name-prop="label"
                                value-prop="id"
                                label="Add line from invoice"
                                :filter-results="false"
                                placeholder="Select an invoice line to add"
                            />
                        </div>
                        <div v-if="isDraft" class="col-12">
                            <ProductVariantSearchInput
                                label="Product (manual)"
                                required
                                @select="onVariantSelected"
                            />
                            <span class="form-text text-muted small">
                                Adds a line without linking to an invoice row.
                            </span>
                        </div>

                        <div class="col-12">
                            <div class="table-responsive no-pagination">
                                <table class="table datanew table-bordered mb-0 sales-order-lines-table">
                                    <thead>
                                    <tr>
                                        <th class="so-col-sn">SN</th>
                                        <th class="so-col-product">Product</th>
                                        <th class="so-col-qty">Qty</th>
                                        <th class="so-col-rate">Rate (sale)</th>
                                        <th class="so-col-disc">Discount</th>
                                        <th class="so-col-tax">Tax</th>
                                        <th v-if="isDraft" class="text-center so-col-action">Action</th>
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
                                        :key="item.id != null && item.id !== '' ? `line-${item.id}` : `n-${index}-${item.product_variant_id}`"
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
                                            class="text-start text-truncate so-col-product"
                                            :title="item.product_label">
                                            {{ item.product_label }}
                                        </td>
                                        <td>
                                            <VInput
                                                input-type="number"
                                                input-class="form-control form-control-sm"
                                                v-model="form.items[index].quantity"
                                                min="1"
                                                step="1"
                                                :max="isDraft ? (maxQtyForLine(item) ?? undefined) : undefined"
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
                                        <td :class="{'so-discount-cell': isDraft}">
                                            <template v-if="isDraft">
                                                <VDiscountAmountTypeGroup
                                                    :input-id="`cn_edit_line_disc_${item.id ?? index}`"
                                                    :input-aria-label="`Line ${index + 1} discount`"
                                                    v-model="form.items[index].line_discount_value"
                                                    v-model:discount-type="form.items[index].line_discount_type"
                                                    :error="errors[`items[${index}].line_discount_value`]"
                                                    :disabled="isSubmitting"
                                                    extra-group-class="so-discount-input-group"
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
                                                select-class="form-select form-select-sm line-item-tax-select"
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
                            <div class="card bg-light mb-4">
                                <div class="card-body py-2">
                                    <div class="d-flex justify-content-between">
                                        <span>Sub total</span>
                                        <strong>{{ summary.subtotal }}</strong>
                                    </div>
                                    <template v-if="isDraft">
                                        <div
                                            class="d-flex flex-wrap align-items-center justify-content-between gap-2 border-top pt-2 mt-2">
                                            <span>Discount</span>
                                            <div class="flex-grow-1" style="max-width: 14rem; min-width: 0">
                                                <VDiscountAmountTypeGroup
                                                    v-model="form.order_discount_value"
                                                    v-model:discount-type="form.order_discount_type"
                                                    :error="errors.order_discount_value"
                                                    input-id="cn_edit_order_discount_value"
                                                    input-aria-label="Order-level discount"
                                                    :disabled="isSubmitting"
                                                    extra-group-class="cn-order-disc-input-group w-100"
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
                                        <span>Discount</span>
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
    <CreateCustomer
        v-if="createCustomerOpened"
        v-model:createModalOpened="createCustomerOpened"
        type="customer"
    />
</template>

<script setup>
import {computed, nextTick, reactive, ref, toRef, watch} from 'vue';
import debounce from 'lodash/debounce';
import {toast} from '@/helpers/toast';
import {useToast} from 'vue-toastification';
import showErrors from '@/helpers/showErrors';
import {array, object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import {usePartyStore} from '@/stores/admin/party.js';
import {useTaxStore} from '@/stores/admin/settings/tax.js';
import {useWarehouseStore} from '@/stores/admin/inventory/warehouse.js';
import {useCreditNoteStore} from '@/stores/admin/sales/credit-note.js';
import {lineDiscountMoneyFromItem} from '@/composables/purchaseOrderTotals.js';
import {useLineOrderDiscountTotals} from '@/composables/useLineOrderDiscountTotals.js';
import {useLineItemTaxOptions} from '@/composables/useLineItemTaxOptions.js';
import {useResolvedParty} from '@/composables/useResolvedParty.js';
import PartyMetaPanel from '@/components/party/PartyMetaPanel.vue';
import VDiscountAmountTypeGroup from '@/components/base/VDiscountAmountTypeGroup.vue';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import CreateCustomer from '@/views/admin/party/Create.vue';

const creditNoteStore = useCreditNoteStore();
const partyStore = usePartyStore();
const taxStore = useTaxStore();
const warehouseStore = useWarehouseStore();

const edit_credit_note_id = defineModel('credit_note_id');

const createCustomerOpened = ref(false);

const {creditNote} = storeToRefs(creditNoteStore);
const {parties} = storeToRefs(partyStore);
const {taxes} = storeToRefs(taxStore);
const {warehouses} = storeToRefs(warehouseStore);

const lineTaxOptions = useLineItemTaxOptions(taxes);

const notifier = useToast();

const invoicePickOptions = ref([]);
const invoicePickLoading = ref(false);
const loadedInvoice = ref(null);
const invoiceLinePickSelection = ref('');

const debouncedPartySearch = debounce((query) => {
    partyStore.getParties({
        filter: {
            type: 'customer',
            limit: 50,
            search: query || '',
        },
    });
}, 300);

function mergePartyFromPayload(party) {
    if (party?.id && !partyStore.parties.data.some((p) => String(p.id) === String(party.id))) {
        partyStore.parties.data = [party, ...partyStore.parties.data];
    }
}

const initialState = {
    credit_note_date: '',
    party_id: '',
    invoice_id: '',
    warehouse_id: '',
    remarks: '',
    status: 'draft',
    order_discount_type: 'fixed',
    order_discount_value: '0',
    items: [],
};

const form = reactive({...initialState});
const isSubmitting = ref(false);
const isHydratingCredit = ref(false);

async function fetchInvoicePickOptions(search) {
    if (!form.party_id) {
        invoicePickOptions.value = [];
        return;
    }
    invoicePickLoading.value = true;
    try {
        const params = new URLSearchParams({
            party_id: String(form.party_id),
            status: 'approved',
            limit: '50',
            page: '1',
            search: search || '',
        });
        const res = await apiAdmin(`invoice?${params.toString()}`);
        const rows = res.data.data || [];
        invoicePickOptions.value = rows.map((inv) => ({
            id: inv.id,
            label: [inv.invoice_no, inv.invoice_date].filter(Boolean).join(' · '),
        }));
    } catch (e) {
        showErrors(e);
        invoicePickOptions.value = [];
    } finally {
        invoicePickLoading.value = false;
    }
}

const debouncedInvoiceSearch = debounce((query) => {
    fetchInvoicePickOptions(query);
}, 300);

const resolvedParty = useResolvedParty(toRef(form, 'party_id'), parties);

watch(
    () => form.party_id,
    () => {
        if (isHydratingCredit.value) {
            return;
        }
        form.invoice_id = '';
        loadedInvoice.value = null;
        invoicePickOptions.value = [];
        invoiceLinePickSelection.value = '';
        if (form.party_id) {
            fetchInvoicePickOptions('');
        }
    }
);

watch(
    () => form.invoice_id,
    async (id) => {
        if (isHydratingCredit.value) {
            return;
        }
        loadedInvoice.value = null;
        invoiceLinePickSelection.value = '';
        if (!id) {
            return;
        }
        try {
            const res = await apiAdmin(`invoice/${id}`);
            const data = res.data.data || {};
            loadedInvoice.value = data;
            if (data.party_id && !form.party_id) {
                form.party_id = data.party_id;
            }
            if (data.party) {
                mergePartyFromPayload(data.party);
            }
            if (!form.warehouse_id && data.items?.length) {
                const wh = data.items.find((it) => it.warehouse_id);
                if (wh?.warehouse_id) {
                    form.warehouse_id = String(wh.warehouse_id);
                }
            }
        } catch (e) {
            showErrors(e);
        }
    }
);

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

function rateStringFromApiLine(item) {
    if (item.rate !== null && item.rate !== undefined && item.rate !== '') {
        return String(Number(item.rate));
    }
    if (item.product_variant) {
        return defaultLineRateString(item.product_variant);
    }
    return '0';
}

function productLabelFromInvoiceLine(line) {
    const v = line.product_variant;
    if (!v) {
        return 'Unknown product';
    }
    return variantLabel(v);
}

function maxQtyForLine(item) {
    if (!item.invoice_item_id || !loadedInvoice.value?.items) {
        return undefined;
    }
    const invLine = loadedInvoice.value.items.find(
        (l) => String(l.id) === String(item.invoice_item_id)
    );
    if (!invLine) {
        return undefined;
    }
    return Math.max(1, Number(invLine.quantity ?? 0));
}

const invoiceLinePickOptions = computed(() => {
    const items = loadedInvoice.value?.items || [];
    return items.map((line) => ({
        id: line.id,
        label: `${productLabelFromInvoiceLine(line)} · invoiced qty ${Number(line.quantity ?? 0)}`,
    }));
});

function lineFromInvoiceItem(item, invoicedQty) {
    const ldv =
        item.line_discount_value !== null && item.line_discount_value !== undefined && item.line_discount_value !== ''
            ? String(item.line_discount_value)
            : '0';
    const qtyDefault = Math.min(1, Math.max(1, invoicedQty));
    return {
        id: '',
        invoice_item_id: item.id,
        product_variant_id: item.product_variant_id,
        product_label: productLabelFromInvoiceLine(item),
        purchase_snapshot: item.product_variant?.purchase_price ?? 0,
        unit_id: item.unit_id ?? '',
        quantity: String(qtyDefault),
        rate: String(Number(item.rate ?? 0)),
        tax_id: item.tax_id || '',
        line_discount_type: item.line_discount_type || 'fixed',
        line_discount_value: ldv,
    };
}

function addOrMergeLineFromInvoiceItem(invoiceItemId) {
    const item = loadedInvoice.value?.items?.find((l) => String(l.id) === String(invoiceItemId));
    if (!item) {
        return;
    }
    const maxInv = Math.max(1, Number(item.quantity ?? 0));
    const existingIdx = form.items.findIndex(
        (row) => row.invoice_item_id && String(row.invoice_item_id) === String(invoiceItemId)
    );
    if (existingIdx !== -1) {
        const cur = Number(form.items[existingIdx].quantity || 0);
        if (cur < maxInv) {
            form.items[existingIdx].quantity = String(Math.min(cur + 1, maxInv));
        } else {
            notifier.info('Return quantity is already at the invoiced amount for this line.');
        }
        return;
    }
    form.items.push(lineFromInvoiceItem(item, maxInv));
}

watch(invoiceLinePickSelection, async (pickId) => {
    if (!pickId) {
        return;
    }
    addOrMergeLineFromInvoiceItem(pickId);
    await nextTick();
    invoiceLinePickSelection.value = '';
});

const onVariantSelected = (variant) => {
    const vid = variant.id;
    const existing = form.items.findIndex(
        (i) => String(i.product_variant_id) === String(vid) && !i.invoice_item_id
    );
    if (existing !== -1) {
        const nextQty = Number(form.items[existing].quantity || 0) + 1;
        form.items[existing].quantity = String(nextQty);
        return;
    }
    form.items.push({
        invoice_item_id: '',
        id: '',
        product_variant_id: vid,
        product_label: variantLabel(variant),
        purchase_snapshot: variant.purchase_price ?? 0,
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
    () => edit_credit_note_id.value,
    async (id) => {
        if (!id) {
            return;
        }
        invoicePickOptions.value = [];
        loadedInvoice.value = null;
        invoiceLinePickSelection.value = '';
        taxStore.getTaxes();
        warehouseStore.getWarehouses();
        await creditNoteStore.getCreditNote(id);
        const data = creditNote.value.data;

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

        const whId = data.items?.[0]?.warehouse_id;
        const whName = data.items?.[0]?.warehouse?.name;
        if (whId && whName && !warehouseStore.warehouses.data.some((w) => String(w.id) === String(whId))) {
            warehouseStore.warehouses.data = [{id: whId, name: whName}, ...warehouseStore.warehouses.data];
        }

        const invId = data.invoice_id;
        const invNo = data.invoice_no || '';

        isHydratingCredit.value = true;
        const odv = data.order_discount_value;
        form.credit_note_date = data.credit_note_date || '';
        form.party_id = pid || '';
        await fetchInvoicePickOptions('');
        if (invId) {
            const invLabel = [invNo].filter(Boolean).join(' · ') || `Invoice #${invId}`;
            if (!invoicePickOptions.value.some((row) => String(row.id) === String(invId))) {
                invoicePickOptions.value = [
                    { id: invId, label: invLabel || `Invoice #${invId}` },
                    ...invoicePickOptions.value,
                ];
            }
            try {
                const invRes = await apiAdmin(`invoice/${invId}`);
                const invPayload = invRes.data.data || {};
                loadedInvoice.value = invPayload;
                if (invPayload.party) {
                    mergePartyFromPayload(invPayload.party);
                }
            } catch (e) {
                showErrors(e);
            }
        }

        form.invoice_id = invId || '';
        form.remarks = data.remarks || '';
        form.status = data.status || 'draft';
        form.order_discount_type = data.order_discount_type || 'fixed';
        form.order_discount_value = odv != null && odv !== '' ? String(odv) : '0';
        form.warehouse_id = whId || '';
        form.items = (data.items || []).map((item) => ({
            id: item.id ?? '',
            invoice_item_id: item.invoice_item_id ?? '',
            product_variant_id: item.product_variant_id || '',
            product_label: item.product_variant ? variantLabel(item.product_variant) : '',
            purchase_snapshot: item.product_variant?.purchase_price ?? 0,
            unit_id: item.unit_id || '',
            quantity: String(item.quantity ?? '1'),
            rate: rateStringFromApiLine(item),
            tax_id: item.tax_id || '',
            line_discount_type: item.line_discount_type || 'fixed',
            line_discount_value:
                item.line_discount_value != null && item.line_discount_value !== ''
                    ? String(item.line_discount_value)
                    : '0',
        }));
        await nextTick();
        isHydratingCredit.value = false;
    }
);

const isDraft = computed(() => creditNote.value.data.status === 'draft');

const validations = object({
    credit_note_date: string().required('Credit note date is required.'),
    party_id: string().nullable(),
    invoice_id: string().nullable(),
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

const buildCreditNotePayload = () => {
    syncTaxAmounts();
    const wid = form.warehouse_id;
    return {
        credit_note_date: form.credit_note_date,
        party_id: form.party_id || null,
        invoice_id: form.invoice_id || null,
        remarks: form.remarks,
        status: form.status,
        order_discount_type: form.order_discount_type || 'fixed',
        order_discount_value: form.order_discount_value ?? '0',
        items: form.items.map((item, index) => ({
            invoice_item_id: item.invoice_item_id || null,
            product_variant_id: item.product_variant_id,
            warehouse_id: wid,
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

const updateCreditNote = async (id) => {
    if (!isDraft.value) {
        return;
    }
    const validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            const res = await creditNoteStore.updateCreditNote(id, buildCreditNotePayload());
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
    edit_credit_note_id.value = '';
};

function resetForm() {
    isHydratingCredit.value = false;
    createCustomerOpened.value = false;
    invoicePickOptions.value = [];
    loadedInvoice.value = null;
    invoiceLinePickSelection.value = '';
    Object.assign(form, {...initialState});
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
.sales-order-lines-table .so-col-tax {
    min-width: 7.5rem;
}
.sales-order-lines-table .so-col-sn {
    width: 2.5rem;
}
.sales-order-lines-table .so-col-action {
    width: 3rem;
}
.sales-order-lines-table .so-discount-cell {
    min-width: 9rem;
    position: relative;
    z-index: 2;
    overflow: visible;
}
</style>
