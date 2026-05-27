<template>
    <VModal
        :show-modal="!!grnId"
        @close-click="closeEditModal"
        size="xl"
        modal-class="edit-sales-modal"
        title="Update Goods Received Note"
    >
        <template #modal-body>
            <VLoader v-if="loading" loader-type="progress" />
            <div v-else class="card border-0 shadow-none mb-0">
                <div class="card-body p-0 border-0">
                    <div v-if="!isDraft" class="alert alert-warning py-2 mb-3">
                        Approved GRNs cannot be edited.
                    </div>

                    <form @submit.prevent="updateGrn" class="row g-3">
                        <div class="col-lg-4 col-md-6">
                            <VMultiselect
                                id="edit_party_id"
                                v-model="form.party_id"
                                :options="parties.data"
                                label="Supplier"
                                required
                                :disabled="!isDraft"
                                :loading="parties.loading"
                                :filter-results="false"
                                @search-change="debouncedSupplierSearch"
                            />
                            <PartyMetaPanel
                                v-if="resolvedParty"
                                :party="resolvedParty"
                                pan-heading="Supplier PAN"
                            />
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <VMultiselect
                                id="edit_warehouse_id"
                                v-model="form.warehouse_id"
                                :options="warehouseOptionsTree"
                                label="Warehouse"
                                required
                                :disabled="!isDraft"
                                :loading="warehouses.loading"
                            />
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <VDatepicker
                                id="edit_received_date"
                                v-model="form.received_date"
                                label="Received Date"
                                required
                                :disabled="!isDraft"
                            />
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <VInput
                                id="edit_supplier_invoice_no"
                                v-model="form.supplier_invoice_no"
                                label="Supplier Invoice No"
                                :disabled="!isDraft"
                            />
                        </div>
                        <div class="col-12">
                            <VTextarea
                                id="edit_remarks"
                                v-model="form.remarks"
                                label="Remarks"
                                :rows="2"
                                :disabled="!isDraft"
                            />
                        </div>

                        <div v-if="isDraft" class="col-12">
                            <ProductVariantSearchInput
                                ref="productSearchRef"
                                label="Product"
                                required
                                physical-only
                                @select="onVariantSelected"
                            />
                        </div>

                        <div class="col-12">
                            <div class="table-responsive no-pagination">
                                <table class="table datanew table-bordered mb-0 grn-lines-table">
                                    <thead>
                                    <tr>
                                        <th class="grn-col-sn">SN</th>
                                        <th class="grn-col-product">Product</th>
                                        <th class="text-end grn-col-qty">Ordered</th>
                                        <th class="text-end grn-col-qty">Received</th>
                                        <th class="text-end grn-col-cost">Unit Cost</th>
                                        <th class="text-end grn-col-total">Total</th>
                                        <th class="grn-col-batch">Batch</th>
                                        <th v-if="isDraft" class="text-center grn-col-action">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-if="!form.items.length">
                                        <td :colspan="isDraft ? 8 : 7" class="text-center text-muted py-4">
                                            {{ isDraft ? 'Search and select a product to add lines.' : 'No line items.' }}
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="(item, index) in form.items"
                                        :key="`${index}-${item.product_variant_id}`">
                                        <td>{{ index + 1 }}</td>
                                        <td class="text-start">
                                            <div class="grn-line-product">
                                                <span class="grn-line-product__name">{{ item.product_label }}</span>
                                                <span v-if="item.sku" class="grn-line-product__meta">{{ item.sku }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <VInput
                                                input-type="number"
                                                input-class="form-control form-control-sm text-end"
                                                v-model="form.items[index].ordered_qty"
                                                :disabled="!isDraft"
                                            />
                                        </td>
                                        <td>
                                            <VInput
                                                input-type="number"
                                                input-class="form-control form-control-sm text-end"
                                                v-model="form.items[index].received_qty"
                                                :disabled="!isDraft"
                                            />
                                        </td>
                                        <td>
                                            <VInput
                                                input-type="number"
                                                input-class="form-control form-control-sm text-end"
                                                v-model="form.items[index].unit_cost"
                                                :disabled="!isDraft"
                                            />
                                        </td>
                                        <td class="text-end fw-semibold">{{ fmt(lineTotal(item)) }}</td>
                                        <td>{{ item.batch_no || '-' }}</td>
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
                                    <tfoot v-if="form.items.length" class="table-secondary fw-bold">
                                    <tr>
                                        <td :colspan="isDraft ? 5 : 5" class="text-end">Grand Total</td>
                                        <td class="text-end">{{ fmt(grandTotal) }}</td>
                                        <td :colspan="isDraft ? 2 : 1"></td>
                                    </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <h6 class="mb-0">Additional Charges / Landed Costs</h6>
                                    <small class="text-muted">
                                        Capitalized charges increase inventory cost; expense charges post separately.
                                    </small>
                                </div>
                                <button
                                    v-if="isDraft"
                                    type="button"
                                    class="btn btn-sm btn-outline-primary"
                                    @click="addLandedCost">
                                    <i class="ti ti-plus me-1"></i> Add Charge
                                </button>
                            </div>
                            <div class="table-responsive no-pagination">
                                <table class="table datanew table-bordered mb-0 landed-costs-table">
                                    <thead>
                                    <tr>
                                        <th class="landed-col-type">Type</th>
                                        <th class="landed-col-treatment">Treatment</th>
                                        <th class="landed-col-allocation">Allocation</th>
                                        <th class="text-end landed-col-amount">Amount</th>
                                        <th class="text-end landed-col-amount">VAT</th>
                                        <th class="text-end landed-col-amount">Claimable VAT</th>
                                        <th class="landed-col-account">Account</th>
                                        <th class="landed-col-description">Description</th>
                                        <th v-if="isDraft" class="text-center landed-col-action">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-if="!form.landed_costs.length">
                                        <td :colspan="isDraft ? 9 : 8" class="text-center text-muted py-3">
                                            No additional charges added.
                                        </td>
                                    </tr>
                                    <tr v-for="(cost, index) in form.landed_costs" :key="cost.id || index">
                                        <td>
                                            <VInput
                                                input-class="form-control form-control-sm"
                                                v-model="form.landed_costs[index].cost_type"
                                                :disabled="!isDraft"
                                            />
                                        </td>
                                        <td>
                                            <VSelect
                                                select-class="form-select form-select-sm"
                                                v-model="form.landed_costs[index].treatment"
                                                :options="treatmentOptions"
                                                :disabled="!isDraft"
                                            />
                                        </td>
                                        <td>
                                            <VSelect
                                                select-class="form-select form-select-sm"
                                                v-model="form.landed_costs[index].allocation_method"
                                                :options="allocationOptions"
                                                :disabled="!isDraft || cost.treatment === 'expense'"
                                            />
                                        </td>
                                        <td>
                                            <VInput
                                                input-type="number"
                                                input-class="form-control form-control-sm text-end"
                                                v-model="form.landed_costs[index].amount"
                                                :disabled="!isDraft"
                                            />
                                        </td>
                                        <td>
                                            <VInput
                                                input-type="number"
                                                input-class="form-control form-control-sm text-end"
                                                v-model="form.landed_costs[index].vat_amount"
                                                :disabled="!isDraft"
                                            />
                                        </td>
                                        <td>
                                            <VInput
                                                input-type="number"
                                                input-class="form-control form-control-sm text-end"
                                                v-model="form.landed_costs[index].vat_claimable_amount"
                                                :disabled="!isDraft"
                                            />
                                        </td>
                                        <td>
                                            <VSelect
                                                select-class="form-select form-select-sm"
                                                v-model="form.landed_costs[index].account_id"
                                                :options="accounts.data"
                                                placeholder="Account"
                                                :disabled="!isDraft"
                                            />
                                        </td>
                                        <td>
                                            <VInput
                                                input-class="form-control form-control-sm"
                                                v-model="form.landed_costs[index].description"
                                                :disabled="!isDraft"
                                            />
                                        </td>
                                        <td v-if="isDraft" class="text-center">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-danger"
                                                @click="removeLandedCost(index)">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    </tbody>
                                    <tfoot v-if="form.landed_costs.length" class="table-secondary fw-bold">
                                    <tr>
                                        <td colspan="3" class="text-end">Charge Total</td>
                                        <td class="text-end">{{ fmt(landedCostSummary.amount) }}</td>
                                        <td class="text-end">{{ fmt(landedCostSummary.vat) }}</td>
                                        <td class="text-end">{{ fmt(landedCostSummary.claimableVat) }}</td>
                                        <td :colspan="isDraft ? 3 : 2"></td>
                                    </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div v-if="isDraft" class="col-12 text-end">
                            <button class="btn btn-cancel add-cancel me-2" type="button" @click="closeEditModal">
                                Cancel
                            </button>
                            <button class="btn btn-primary" type="submit" :disabled="saving">
                                <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                                Update GRN
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
import {storeToRefs} from 'pinia';
import debounce from 'lodash/debounce';
import moment from 'moment';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import {toast} from '@/helpers/toast.js';
import {usePartyStore} from '@/stores/admin/party.js';
import {useAccountStore} from '@/stores/admin/accounting/account.js';
import {useWarehouseStore} from '@/stores/admin/inventory/warehouse.js';
import {useResolvedParty} from '@/composables/useResolvedParty.js';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import PartyMetaPanel from '@/components/party/PartyMetaPanel.vue';

const emit = defineEmits(['saved']);
const grnId = defineModel('grnId');

const partyStore = usePartyStore();
const accountStore = useAccountStore();
const warehouseStore = useWarehouseStore();
const {parties} = storeToRefs(partyStore);
const {accounts} = storeToRefs(accountStore);
const {warehouses, optionsTree: warehouseOptionsTree} = storeToRefs(warehouseStore);

const loading = ref(false);
const saving = ref(false);
const productSearchRef = ref(null);
const grnStatus = ref('draft');

const isDraft = computed(() => grnStatus.value === 'draft');

const treatmentOptions = [
    {id: 'capitalized', name: 'Capitalize'},
    {id: 'expense', name: 'Expense'},
];

const allocationOptions = [
    {id: 'value', name: 'By Value'},
    {id: 'quantity', name: 'By Quantity'},
    {id: 'equal', name: 'Equal'},
];

const form = reactive({
    party_id: '',
    warehouse_id: '',
    purchase_order_id: '',
    received_date: '',
    supplier_invoice_no: '',
    remarks: '',
    items: [],
    landed_costs: [],
});

const resolvedParty = useResolvedParty(() => form.party_id, parties);

const debouncedSupplierSearch = debounce((query) => {
    partyStore.getParties({
        filter: {type: 'supplier', limit: 50, search: query || ''},
    });
}, 300);

const grandTotal = computed(() =>
    form.items.reduce((sum, item) => sum + lineTotal(item), 0)
);

const landedCostSummary = computed(() =>
    form.landed_costs.reduce((summary, cost) => {
        summary.amount += Number(cost.amount || 0);
        summary.vat += Number(cost.vat_amount || 0);
        summary.claimableVat += Number(cost.vat_claimable_amount || 0);

        return summary;
    }, {amount: 0, vat: 0, claimableVat: 0})
);

const lineTotal = (item) =>
    Number(item.received_qty || 0) * Number(item.unit_cost || 0);

const fmt = (val) =>
    Number(val ?? 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});

const variantLabel = (variant) => {
    let label = variant?.product?.name || variant?.name || '';
    if (variant?.sku) {
        label += ` (${variant.sku})`;
    }
    return label;
};

const mergePartyFromPayload = (party) => {
    if (party?.id && !partyStore.parties.data.some((p) => String(p.id) === String(party.id))) {
        partyStore.parties.data = [party, ...partyStore.parties.data];
    }
};

const hydrateForm = (data) => {
    mergePartyFromPayload(data.party);
    grnStatus.value = data.status || 'draft';
    form.party_id = data.party_id ? String(data.party_id) : '';
    form.warehouse_id = data.warehouse_id ? String(data.warehouse_id) : '';
    form.purchase_order_id = data.purchase_order_id ? String(data.purchase_order_id) : '';
    form.received_date = data.received_date ? moment(data.received_date).format('YYYY-MM-DD') : '';
    form.supplier_invoice_no = data.supplier_invoice_no || '';
    form.remarks = data.remarks || '';
    form.items = (data.grn_items || []).map((item) => ({
        product_variant_id: item.product_variant_id,
        product_label: variantLabel(item.product_variant ?? {}),
        sku: item.product_variant?.sku || '',
        purchase_order_item_id: item.purchase_order_item_id || null,
        ordered_qty: Number(item.ordered_qty || 0),
        received_qty: Number(item.received_qty || 0),
        unit_cost: Number(item.unit_cost || 0),
        batch_no: item.batch_no || '',
    }));
    form.landed_costs = (data.landed_costs || []).map((cost) => ({
        id: cost.id,
        cost_type: cost.cost_type || '',
        description: cost.description || '',
        treatment: cost.treatment || 'capitalized',
        allocation_method: cost.allocation_method || 'value',
        amount: Number(cost.amount || 0),
        vat_amount: Number(cost.vat_amount || 0),
        vat_claimable_amount: Number(cost.vat_claimable_amount || 0),
        account_id: cost.account_id ? String(cost.account_id) : '',
    }));
};

const loadGrn = async (id) => {
    loading.value = true;
    try {
        const res = await apiAdmin(`grn/${id}`, 'get');
        hydrateForm(res.data.data);
    } catch (e) {
        showErrors(e);
        closeEditModal();
    } finally {
        loading.value = false;
    }
};

const onVariantSelected = (variant) => {
    const existing = form.items.findIndex((item) => String(item.product_variant_id) === String(variant.id));
    if (existing !== -1) {
        form.items[existing].received_qty = Number(form.items[existing].received_qty || 0) + 1;
        return;
    }

    form.items.push({
        product_variant_id: variant.id,
        product_label: variantLabel(variant),
        sku: variant.sku || '',
        purchase_order_item_id: null,
        ordered_qty: 0,
        received_qty: 1,
        unit_cost: Number(variant.purchase_price ?? variant.sales_price ?? 0),
        batch_no: '',
    });
    productSearchRef.value?.focus?.();
};

const removeItem = (index) => {
    form.items.splice(index, 1);
};

const addLandedCost = () => {
    form.landed_costs.push({
        cost_type: '',
        description: '',
        treatment: 'capitalized',
        allocation_method: 'value',
        amount: 0,
        vat_amount: 0,
        vat_claimable_amount: 0,
        account_id: '',
    });
};

const removeLandedCost = (index) => {
    form.landed_costs.splice(index, 1);
};

const buildPayload = () => ({
    party_id: form.party_id,
    warehouse_id: form.warehouse_id,
    purchase_order_id: form.purchase_order_id || null,
    received_date: form.received_date,
    supplier_invoice_no: form.supplier_invoice_no || null,
    remarks: form.remarks || null,
    items: form.items.map((item) => ({
        product_variant_id: item.product_variant_id,
        purchase_order_item_id: item.purchase_order_item_id || null,
        ordered_qty: item.ordered_qty,
        received_qty: item.received_qty,
        unit_cost: item.unit_cost,
        batch_no: item.batch_no || null,
    })),
    landed_costs: form.landed_costs
        .filter((cost) => cost.cost_type || Number(cost.amount || 0) > 0 || Number(cost.vat_amount || 0) > 0)
        .map((cost) => ({
            cost_type: cost.cost_type,
            description: cost.description || null,
            treatment: cost.treatment || 'capitalized',
            allocation_method: cost.treatment === 'expense' ? 'value' : (cost.allocation_method || 'value'),
            amount: Number(cost.amount || 0),
            vat_amount: Number(cost.vat_amount || 0),
            vat_claimable_amount: Number(cost.vat_claimable_amount || 0),
            account_id: cost.account_id || null,
        })),
});

const updateGrn = async () => {
    if (!isDraft.value) {
        return;
    }

    saving.value = true;
    try {
        const res = await apiAdmin(`grn/${grnId.value}`, 'put', buildPayload());
        toast(res.status, res.data.message);
        emit('saved');
        closeEditModal();
    } catch (e) {
        showErrors(e);
    } finally {
        saving.value = false;
    }
};

const closeEditModal = () => {
    grnId.value = '';
};

watch(
    grnId,
    async (id) => {
        if (id) {
            await Promise.all([
                partyStore.getParties({filter: {type: 'supplier', limit: 50, search: ''}}),
                accountStore.getAccounts(),
                warehouseStore.getWarehouses(),
                loadGrn(id),
            ]);
        }
    },
    {flush: 'post'},
);
</script>

<style scoped>
.grn-lines-table :deep(.form-control) {
    min-width: 4.25rem;
}

.grn-lines-table th,
.grn-lines-table td {
    vertical-align: middle;
}

.grn-col-sn {
    width: 2.5rem;
}

.grn-col-product {
    min-width: 11rem;
}

.grn-col-qty,
.grn-col-cost {
    min-width: 5.5rem;
}

.grn-col-total {
    min-width: 6rem;
}

.grn-col-batch {
    min-width: 7rem;
}

.grn-col-action {
    width: 3rem;
}

.grn-line-product__name {
    display: block;
    font-weight: 500;
}

.grn-line-product__meta {
    display: block;
    font-size: 0.75rem;
    color: var(--bs-secondary-color);
}

.landed-costs-table :deep(.form-control),
.landed-costs-table :deep(.form-select) {
    min-width: 6rem;
}

.landed-col-type,
.landed-col-treatment,
.landed-col-allocation {
    min-width: 8rem;
}

.landed-col-amount {
    min-width: 7rem;
}

.landed-col-account {
    min-width: 10rem;
}

.landed-col-description {
    min-width: 12rem;
}

.landed-col-action {
    width: 3rem;
}
</style>
