<template>
    <VModal
        :show-modal="!!createModalOpened"
        @close-click="closeCreateModal"
        size="xl"
        modal-class="large-modal add-centered"
        title="Add Debit Note">
        <template #modal-body>
            <div class="card border-0 shadow-none mb-0">
                <div class="card-body p-0 border-0">
                    <form @submit.prevent="storeDebitNoteWithStatus('draft')" class="row g-3">
                        <div class="col-12">
                            <p class="text-muted small mb-0">
                                Select supplier first, link an approved bill if the return relates to it, then add lines from that bill
                                or search products manually. Warehouse applies to all lines.
                            </p>
                        </div>

                        <div class="col-12 col-lg-4">
                            <VDatepicker
                                id="debit_note_date"
                                input-type="date"
                                v-model="form.debit_note_date"
                                label="Debit Note Date"
                                @validate="validateField('debit_note_date')"
                                :error="errors.debit_note_date"
                            />
                        </div>
                        <div class="col-12 col-lg-8">
                            <div class="d-flex gap-2 align-items-end">
                                <div class="flex-grow-1 min-w-0">
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
                                <div class="ps-0 flex-shrink-0">
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
                            <PartyMetaPanel
                                v-if="resolvedParty"
                                :party="resolvedParty"
                                pan-heading="Seller PAN"
                            />
                        </div>

                        <div class="col-12 col-lg-6">
                            <VMultiselect
                                id="bill_id"
                                v-model="form.bill_id"
                                :options="billPickOptions"
                                name-prop="label"
                                value-prop="id"
                                label="Bill (optional)"
                                :loading="billPickLoading"
                                :disabled="!form.party_id"
                                :filter-results="false"
                                @search-change="debouncedBillSearch"
                                @validate="validateField('bill_id')"
                                :error="errors.bill_id"
                            />
                            <span v-if="!form.party_id" class="form-text text-muted small">Select supplier first.</span>
                            <span v-else class="form-text text-muted small">
                                Search approved bills for this supplier, or leave empty for a standalone return.
                            </span>
                        </div>
                        <div class="col-12 col-lg-6">
                            <VMultiselect
                                id="warehouse_id"
                                v-model="form.warehouse_id"
                                :options="warehouses.data"
                                label="Warehouse"
                                :filter-results="false"
                                @validate="validateField('warehouse_id')"
                                :error="errors.warehouse_id"
                            />
                        </div>

                        <div v-if="form.bill_id && billLinePickOptions.length" class="col-12">
                            <VMultiselect
                                id="bill_line_pick"
                                v-model="billLinePickSelection"
                                :options="billLinePickOptions"
                                name-prop="label"
                                value-prop="id"
                                label="Add line from bill"
                                :filter-results="false"
                                placeholder="Select a bill line to add"
                            />
                        </div>

                        <div class="col-12">
                            <ProductVariantSearchInput
                                label="Product (manual)"
                                required
                                @select="onVariantSelected"
                            />
                            <span class="form-text text-muted small">
                                Adds a line without linking to a bill row (use when the return does not trace to a billed line above).
                            </span>
                        </div>

                        <div class="col-12">
                            <div class="table-responsive no-pagination">
                                <table class="table datanew table-bordered mb-0 debit-note-lines-table">
                                    <thead>
                                    <tr>
                                        <th class="dn-col-sn">SN</th>
                                        <th class="dn-col-product">Product</th>
                                        <th class="dn-col-qty">Qty</th>
                                        <th class="dn-col-rate">Rate</th>
                                        <th class="dn-col-disc">Discount</th>
                                        <th class="dn-col-tax">Tax</th>
                                        <th class="text-center dn-col-action">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-if="!form.items.length">
                                        <td colspan="7" class="text-center text-muted py-4">
                                            Add lines from the bill or search for a product.
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="(item, index) in form.items"
                                        :key="`n-${index}-${item.product_variant_id}-${item.bill_item_id || 'm'}`"
                                        v-memo="[
                                            item.quantity,
                                            item.rate,
                                            item.line_discount_type,
                                            item.line_discount_value,
                                            item.tax_id,
                                        ]">
                                        <td>{{ index + 1 }}</td>
                                        <td
                                            class="text-start text-truncate dn-col-product"
                                            :title="item.product_label">
                                            {{ item.product_label }}
                                        </td>
                                        <td>
                                            <VInput
                                                input-type="number"
                                                input-class="form-control form-control-sm"
                                                v-model="form.items[index].quantity"
                                                :max="maxQtyForLine(item) ?? undefined"
                                                min="1"
                                                step="1"
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
                                        <td class="dn-discount-cell">
                                            <VDiscountAmountTypeGroup
                                                :input-id="`dn_create_line_disc_${index}`"
                                                :input-aria-label="`Line ${index + 1} discount`"
                                                v-model="form.items[index].line_discount_value"
                                                v-model:discount-type="form.items[index].line_discount_type"
                                                :error="errors[`items[${index}].line_discount_value`]"
                                                :disabled="isSubmitting"
                                                extra-group-class="dn-line-disc-input-group"
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
                                                select-class="form-select form-select-sm line-item-tax-select"
                                                :options="lineTaxOptions"
                                                @validate="validateField(`items[${index}].tax_id`)"
                                                :error="errors[`items[${index}].tax_id`]"
                                            />
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
                            <div class="card bg-light border mb-0">
                                <div class="card-body py-2">
                                    <div class="d-flex justify-content-between">
                                        <span>Sub total</span>
                                        <strong>{{ formatMoney(summary.subtotal) }}</strong>
                                    </div>
                                    <div
                                        class="d-flex flex-wrap align-items-center justify-content-between gap-2 border-top pt-2 mt-2">
                                        <span>Discount</span>
                                        <div class="flex-grow-1" style="max-width: 14rem; min-width: 0">
                                            <VDiscountAmountTypeGroup
                                                v-model="form.order_discount_value"
                                                v-model:discount-type="form.order_discount_type"
                                                :error="errors.order_discount_value"
                                                input-id="dn_create_order_discount_value"
                                                input-aria-label="Document-level discount"
                                                :disabled="isSubmitting"
                                                extra-group-class="dn-order-disc-input-group w-100"
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
                            <VTextarea
                                id="remarks"
                                v-model="form.remarks"
                                label="Remarks"
                                @validate="validateField('remarks')"
                                :error="errors.remarks"
                            />
                        </div>

                        <div class="col-12 text-end border-top pt-3 mt-1">
                            <button @click="closeCreateModal" class="btn btn-cancel me-2" type="button">
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="btn btn-outline-primary me-2"
                                :disabled="isSubmitting"
                                @click="storeDebitNoteWithStatus('draft')">
                                Create
                            </button>
                            <button
                                type="button"
                                class="btn btn-primary"
                                :disabled="isSubmitting"
                                @click="storeDebitNoteWithStatus('approved')">
                                Create &amp; Approve
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
import {computed, nextTick, reactive, ref, toRef, watch} from 'vue';
import debounce from 'lodash/debounce';
import {useToast} from 'vue-toastification';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {array, object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import {usePartyStore} from '@/stores/admin/party.js';
import {useTaxStore} from '@/stores/admin/setting/tax.js';
import {useWarehouseStore} from '@/stores/admin/inventory/warehouse.js';
import {useDebitNoteStore} from '@/stores/admin/purchase/debit-note.js';
import {useDateHelper} from '@/composables/dateHelper.js';
import {useResolvedParty} from '@/composables/useResolvedParty.js';
import {useLineItemTaxOptions} from '@/composables/useLineItemTaxOptions.js';
import {useLineOrderDiscountTotals} from '@/composables/useLineOrderDiscountTotals.js';
import {lineDiscountMoneyFromItem} from '@/composables/purchaseOrderTotals.js';
import PartyMetaPanel from '@/components/party/PartyMetaPanel.vue';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import VDiscountAmountTypeGroup from '@/components/base/VDiscountAmountTypeGroup.vue';
import CreateSupplier from '@/views/admin/party/Create.vue';
import {formatMoney} from '@/helpers/formatMoney';

function lineQtyInt(q) {
    const n = parseInt(String(q ?? '0'), 10);
    return Number.isFinite(n) && n > 0 ? n : 1;
}

const debitNoteStore = useDebitNoteStore();
const partyStore = usePartyStore();
const taxStore = useTaxStore();
const warehouseStore = useWarehouseStore();

const notifier = useToast();

const createModalOpened = defineModel('createModalOpened');

const createSupplierOpened = ref(false);

const {currentAdDate} = useDateHelper();

const {parties} = storeToRefs(partyStore);
const {taxes} = storeToRefs(taxStore);
const {warehouses} = storeToRefs(warehouseStore);

const lineTaxOptions = useLineItemTaxOptions(taxes);

const billPickOptions = ref([]);
const billPickLoading = ref(false);
const loadedBill = ref(null);
const billLinePickSelection = ref('');

function getInitialState() {
    return {
        debit_note_date: currentAdDate,
        party_id: '',
        bill_id: '',
        warehouse_id: '',
        remarks: '',
        status: 'draft',
        order_discount_type: 'fixed',
        order_discount_value: '0',
        items: [],
    };
}

const form = reactive(getInitialState());
const isSubmitting = ref(false);

function mergePartyFromPayload(party) {
    if (party?.id && !partyStore.parties.data.some((p) => String(p.id) === String(party.id))) {
        partyStore.parties.data = [party, ...partyStore.parties.data];
    }
}

async function fetchBillPickOptions(search) {
    if (!form.party_id) {
        billPickOptions.value = [];
        return;
    }
    billPickLoading.value = true;
    try {
        const params = new URLSearchParams({
            party_id: String(form.party_id),
            status: 'approved',
            limit: '50',
            page: '1',
            search: search || '',
        });
        const res = await apiAdmin(`bill?${params}`);
        const rows = res.data.data || [];
        billPickOptions.value = rows.map((b) => ({
            id: b.id,
            label: [b.bill_no, b.bill_date].filter(Boolean).join(' · '),
        }));
    } catch (e) {
        showErrors(e);
        billPickOptions.value = [];
    } finally {
        billPickLoading.value = false;
    }
}

const debouncedSupplierSearch = debounce((query) => {
    partyStore.getParties({
        filter: {
            type: 'supplier',
            limit: 50,
            search: query || '',
        },
    });
}, 300);

const debouncedBillSearch = debounce((query) => {
    fetchBillPickOptions(query || '');
}, 300);

const resolvedParty = useResolvedParty(toRef(form, 'party_id'), parties);

watch(
    createModalOpened,
    (opened) => {
        if (opened) {
            taxStore.getTaxes();
            warehouseStore.getWarehouses();
            partyStore.getParties({
                filter: {
                    type: 'supplier',
                    limit: 50,
                    search: '',
                },
            });
        }
    },
    {flush: 'post'},
);

watch(
    () => form.party_id,
    () => {
        form.bill_id = '';
        loadedBill.value = null;
        billPickOptions.value = [];
        billLinePickSelection.value = '';
        if (form.party_id) {
            fetchBillPickOptions('');
        }
    },
);

watch(
    () => form.bill_id,
    async (id) => {
        loadedBill.value = null;
        billLinePickSelection.value = '';
        if (!id) {
            return;
        }
        try {
            const res = await apiAdmin(`bill/${id}`);
            const data = res.data.data || {};
            loadedBill.value = data;
            if (data.party_id && !form.party_id) {
                form.party_id = String(data.party_id);
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
    },
);

watch(billLinePickSelection, async (id) => {
    if (!id) {
        return;
    }
    addOrMergeLineFromBillItem(id);
    await nextTick();
    billLinePickSelection.value = '';
});

const billLinePickOptions = computed(() => {
    const items = loadedBill.value?.items || [];
    return items.map((line) => ({
        id: line.id,
        label: `${productLabelFromBillLine(line)} · billed qty ${Number(line.quantity ?? 0)}`,
    }));
});

function productLabelFromBillLine(line) {
    const v = line.product_variant;
    if (!v) {
        return 'Unknown product';
    }
    return variantLabel(v);
}

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

function maxQtyForLine(item) {
    if (!item.bill_item_id || !loadedBill.value?.items) {
        return undefined;
    }
    const row = loadedBill.value.items.find(
        (l) => String(l.id) === String(item.bill_item_id),
    );
    if (!row) {
        return undefined;
    }
    return Math.max(1, Number(row.quantity ?? 0));
}

function addOrMergeLineFromBillItem(billItemId) {
    const row = loadedBill.value?.items?.find((l) => String(l.id) === String(billItemId));
    if (!row) {
        return;
    }
    const maxBill = Math.max(1, Number(row.quantity ?? 0));
    const existingIdx = form.items.findIndex(
        (it) => it.bill_item_id && String(it.bill_item_id) === String(billItemId),
    );
    if (existingIdx !== -1) {
        const cur = Number(form.items[existingIdx].quantity || 0);
        if (cur < maxBill) {
            form.items[existingIdx].quantity = String(Math.min(cur + 1, maxBill));
        } else {
            notifier.info('Quantity is already at the billed amount for this line.');
        }
        return;
    }
    form.items.push(lineFromBillItem(row, maxBill));
}

function lineFromBillItem(billItem, billedQty) {
    const ldv =
        billItem.line_discount_value !== null &&
        billItem.line_discount_value !== undefined &&
        billItem.line_discount_value !== ''
            ? String(billItem.line_discount_value)
            : '0';
    const qtyDefault = Math.min(1, Math.max(1, billedQty));
    return {
        bill_item_id: billItem.id,
        product_variant_id: billItem.product_variant_id,
        product_label: productLabelFromBillLine(billItem),
        unit_id: billItem.unit_id ?? '',
        quantity: String(qtyDefault),
        rate: String(Number(billItem.rate ?? 0)),
        tax_id: billItem.tax_id || '',
        line_discount_type: billItem.line_discount_type || 'fixed',
        line_discount_value: ldv,
    };
}

const onVariantSelected = (variant) => {
    const vid = variant.id;
    const existing = form.items.findIndex(
        (i) => String(i.product_variant_id) === String(vid) && !i.bill_item_id,
    );
    if (existing !== -1) {
        const nextQty = Number(form.items[existing].quantity || 0) + 1;
        form.items[existing].quantity = String(nextQty);
        return;
    }
    form.items.push({
        bill_item_id: '',
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

const validations = object({
    debit_note_date: string().required('Debit note date is required.'),
    party_id: string().nullable(),
    bill_id: string().nullable(),
    warehouse_id: string().required('Warehouse is required.'),
    remarks: string().nullable(),
    order_discount_type: string().nullable(),
    order_discount_value: string().nullable(),
    items: array().of(
        object({
            product_variant_id: string().required('Product is required.'),
            quantity: string().required('Quantity is required.'),
            rate: string().required('Rate is required.'),
            unit_id: string().nullable(),
            tax_id: string().nullable(),
            line_discount_type: string().nullable(),
            line_discount_value: string().nullable(),
        }),
    ).min(1, 'At least one item is required.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const {calcLineTax, summary, syncTaxAmounts} = useLineOrderDiscountTotals({
    form,
    taxes,
});

function buildDebitNotePayload() {
    syncTaxAmounts();
    const wid = form.warehouse_id;
    return {
        debit_note_date: form.debit_note_date,
        party_id: form.party_id,
        bill_id: form.bill_id,
        remarks: form.remarks,
        status: form.status,
        order_discount_type: form.order_discount_type || 'fixed',
        order_discount_value: form.order_discount_value ?? '0',
        items: form.items.map((item, index) => ({
            product_variant_id: item.product_variant_id,
            warehouse_id: wid,
            unit_id: item.unit_id || '',
            quantity: lineQtyInt(item.quantity),
            rate: Number(item.rate || 0),
            tax_id: item.tax_id || '',
            tax_amount: calcLineTax(item, index),
            line_discount_type: item.line_discount_type || 'fixed',
            line_discount_value: item.line_discount_value ?? '0',
            discount_amount: String(lineDiscountMoneyFromItem(item)),
        })),
    };
}

const storeDebitNoteWithStatus = async (status) => {
    form.status = status;
    const validated = await validateForm(validations, form);
    if (!validated) {
        return;
    }
    isSubmitting.value = true;
    try {
        const res = await debitNoteStore.storeDebitNote(buildDebitNotePayload());
        toast(res.status, res.data.message);
        closeCreateModal();
    } catch (e) {
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
};

function closeCreateModal() {
    resetForm();
    createModalOpened.value = false;
}

function resetForm() {
    Object.assign(form, getInitialState());
    billPickOptions.value = [];
    loadedBill.value = null;
    billLinePickSelection.value = '';
    createSupplierOpened.value = false;
    errors.value = {};
}
</script>

<style scoped>
.debit-note-lines-table .dn-col-sn {
    width: 2.5rem;
}
.debit-note-lines-table .dn-col-product {
    min-width: 10rem;
    max-width: 18rem;
}
.debit-note-lines-table .dn-col-qty,
.debit-note-lines-table .dn-col-rate {
    min-width: 5.5rem;
    max-width: 7rem;
}
.debit-note-lines-table .dn-col-tax {
    min-width: 9rem;
    max-width: 12rem;
}
.debit-note-lines-table .dn-col-disc {
    min-width: 9rem;
    max-width: 14rem;
}
.debit-note-lines-table .dn-discount-cell {
    min-width: 9rem;
    position: relative;
    z-index: 2;
    overflow: visible;
}
.debit-note-lines-table .dn-col-action {
    width: 3.25rem;
}
</style>
