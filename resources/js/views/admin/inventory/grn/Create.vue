<template>
    <VModal
        :show-modal="showModal"
        @close-click="closeCreateModal"
        size="xl"
        modal-class="add-centered"
        title="Create Goods Received Note"
    >
        <template #modal-body>
            <div class="card border-0 shadow-none mb-0">
                <div class="card-body p-0 border-0">
                    <div v-if="purchaseOrderId" class="alert alert-info py-2 mb-3">
                        Creating GRN from Purchase Order #{{ purchaseOrderId }}
                    </div>

                    <form @submit.prevent="save" class="row g-3">
                        <div class="col-lg-4 col-md-6">
                            <VMultiselect
                                id="party_id"
                                v-model="form.party_id"
                                :options="parties.data"
                                label="Supplier"
                                required
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
                                id="warehouse_id"
                                v-model="form.warehouse_id"
                                :options="warehouseOptionsTree"
                                label="Warehouse"
                                required
                                :loading="warehouses.loading"
                            />
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <VDatepicker
                                id="received_date"
                                v-model="form.received_date"
                                label="Received Date"
                                required
                            />
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <VInput
                                id="supplier_invoice_no"
                                v-model="form.supplier_invoice_no"
                                label="Supplier Invoice No"
                                placeholder="Supplier invoice reference"
                            />
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <VMultiselect
                                id="purchase_order_id"
                                v-model="form.purchase_order_id"
                                :options="purchaseOrderOptions"
                                name-prop="label"
                                value-prop="value"
                                label="Purchase Order"
                                placeholder="Optional — load lines from PO"
                                @update:model-value="onPurchaseOrderChange"
                            />
                        </div>
                        <div class="col-12">
                            <VTextarea
                                id="remarks"
                                v-model="form.remarks"
                                label="Remarks"
                                :rows="2"
                            />
                        </div>

                        <div class="col-12">
                            <ProductVariantSearchInput
                                ref="productSearchRef"
                                label="Product"
                                required
                                physical-only
                                @select="onVariantSelected"
                            />
                            <small class="text-muted">
                                Search products to add receipt lines. Ordered qty is filled automatically when loading from a PO.
                            </small>
                        </div>

                        <div class="col-12">
                            <div class="table-responsive no-pagination">
                                <table class="table datanew table-bordered mb-0 grn-lines-table">
                                    <thead>
                                    <tr>
                                        <th class="grn-col-sn">SN</th>
                                        <th class="grn-col-product">Product</th>
                                        <th class="text-end grn-col-qty">Ordered</th>
                                        <th class="text-end grn-col-qty">Received <VRequiredMark /></th>
                                        <th class="text-end grn-col-cost">Unit Cost <VRequiredMark /></th>
                                        <th class="text-end grn-col-total">Total</th>
                                        <th class="grn-col-batch">Batch</th>
                                        <th class="text-center grn-col-action">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-if="!form.items.length">
                                        <td colspan="8" class="text-center text-muted py-4">
                                            Search and select a product to add lines.
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
                                                :min-value="0"
                                            />
                                        </td>
                                        <td>
                                            <VInput
                                                input-type="number"
                                                input-class="form-control form-control-sm text-end"
                                                v-model="form.items[index].received_qty"
                                                :min-value="0.001"
                                            />
                                        </td>
                                        <td>
                                            <VInput
                                                input-type="number"
                                                input-class="form-control form-control-sm text-end"
                                                v-model="form.items[index].unit_cost"
                                                :min-value="0"
                                            />
                                        </td>
                                        <td class="text-end fw-semibold">{{ formatMoney(lineTotal(item)) }}</td>
                                        <td>
                                            <template v-if="item.is_batch_tracked">
                                                <div class="d-flex align-items-center gap-1 mb-1">
                                                    <input
                                                        type="checkbox"
                                                        class="form-check-input mt-0"
                                                        v-model="form.items[index].create_batch"
                                                        :id="`cb-batch-${index}`"
                                                        @change="form.items[index].batch_id = null"
                                                    />
                                                    <label :for="`cb-batch-${index}`" class="form-label mb-0 small text-nowrap">
                                                        New batch
                                                    </label>
                                                </div>
                                                <template v-if="item.create_batch">
                                                    <VInput
                                                        input-class="form-control form-control-sm mb-1"
                                                        :class="{ 'is-invalid': !item.batch_no }"
                                                        v-model="form.items[index].batch_no"
                                                        placeholder="Batch No *"
                                                    />
                                                    <input
                                                        type="date"
                                                        class="form-control form-control-sm mb-1"
                                                        v-model="form.items[index].mfg_date"
                                                        title="Mfg Date"
                                                    />
                                                    <input
                                                        type="date"
                                                        class="form-control form-control-sm"
                                                        v-model="form.items[index].expiry_date"
                                                        title="Expiry Date"
                                                    />
                                                    <small v-if="batchDateWarning(item)" class="text-warning d-block mt-1">
                                                        <i class="ti ti-alert-triangle me-1"></i>{{ batchDateWarning(item) }}
                                                    </small>
                                                    <small class="text-muted d-block mt-1">Qty &amp; cost taken from this line.</small>
                                                </template>
                                                <BatchPickerInput
                                                    v-else
                                                    v-model="form.items[index].batch_id"
                                                    :product-variant-id="item.product_variant_id"
                                                    :warehouse-id="form.warehouse_id"
                                                />
                                            </template>
                                            <span v-else class="text-muted small">—</span>
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
                                    <tfoot v-if="form.items.length" class="table-secondary fw-bold">
                                    <tr>
                                        <td colspan="5" class="text-end">Grand Total</td>
                                        <td class="text-end">{{ formatMoney(grandTotal) }}</td>
                                        <td colspan="2"></td>
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
                                <button type="button" class="btn btn-sm btn-outline-primary" @click="addLandedCost">
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
                                        <th class="text-center landed-col-action">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-if="!form.landed_costs.length">
                                        <td colspan="9" class="text-center text-muted py-3">
                                            No additional charges added.
                                        </td>
                                    </tr>
                                    <tr v-for="(cost, index) in form.landed_costs" :key="index">
                                        <td>
                                            <VInput
                                                input-class="form-control form-control-sm"
                                                v-model="form.landed_costs[index].cost_type"
                                                placeholder="Transport"
                                            />
                                        </td>
                                        <td>
                                            <VSelect
                                                select-class="form-select form-select-sm"
                                                v-model="form.landed_costs[index].treatment"
                                                :options="treatmentOptions"
                                            />
                                        </td>
                                        <td>
                                            <VSelect
                                                select-class="form-select form-select-sm"
                                                v-model="form.landed_costs[index].allocation_method"
                                                :options="allocationOptions"
                                                :disabled="cost.treatment === 'expense'"
                                            />
                                        </td>
                                        <td>
                                            <VInput
                                                input-type="number"
                                                input-class="form-control form-control-sm text-end"
                                                v-model="form.landed_costs[index].amount"
                                                :min-value="0"
                                            />
                                        </td>
                                        <td>
                                            <VInput
                                                input-type="number"
                                                input-class="form-control form-control-sm text-end"
                                                v-model="form.landed_costs[index].vat_amount"
                                                :min-value="0"
                                            />
                                        </td>
                                        <td>
                                            <VInput
                                                input-type="number"
                                                input-class="form-control form-control-sm text-end"
                                                v-model="form.landed_costs[index].vat_claimable_amount"
                                                :min-value="0"
                                            />
                                        </td>
                                        <td>
                                            <VSelect
                                                select-class="form-select form-select-sm"
                                                v-model="form.landed_costs[index].account_id"
                                                :options="accounts.data"
                                                placeholder="Account"
                                            />
                                        </td>
                                        <td>
                                            <VInput
                                                input-class="form-control form-control-sm"
                                                v-model="form.landed_costs[index].description"
                                                placeholder="Optional note"
                                            />
                                        </td>
                                        <td class="text-center">
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
                                        <td class="text-end">{{ formatMoney(landedCostSummary.amount) }}</td>
                                        <td class="text-end">{{ formatMoney(landedCostSummary.vat) }}</td>
                                        <td class="text-end">{{ formatMoney(landedCostSummary.claimableVat) }}</td>
                                        <td colspan="3"></td>
                                    </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div class="col-12 text-end">
                            <button class="btn btn-cancel add-cancel me-2" type="button" @click="closeCreateModal">
                                Cancel
                            </button>
                            <button class="btn btn-primary" type="submit" :disabled="saving">
                                <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                                Save GRN
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </VModal>
</template>

<script setup>
import {formatMoney} from '@/helpers/formatMoney.js';
import {computed, onMounted, reactive, ref, watch} from 'vue';
import {useRoute, useRouter} from 'vue-router';
import {storeToRefs} from 'pinia';
import debounce from 'lodash/debounce';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import {toast} from '@/helpers/toast.js';
import {useDateHelper} from '@/composables/dateHelper.js';
import {usePartyStore} from '@/stores/admin/party.js';
import {useAccountStore} from '@/stores/admin/accounting/account.js';
import {useWarehouseStore} from '@/stores/admin/inventory/warehouse.js';
import {usePurchaseOrderStore} from '@/stores/admin/purchase/purchase-order.js';
import {useResolvedParty} from '@/composables/useResolvedParty.js';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import BatchPickerInput from '@/components/inventory/BatchPickerInput.vue';
import PartyMetaPanel from '@/components/party/PartyMetaPanel.vue';
import VRequiredMark from '@/components/base/VRequiredMark.vue';

const emit = defineEmits(['saved']);
const route = useRoute();
const router = useRouter();
const createModalOpened = defineModel('createModalOpened');
const purchaseOrderId = defineModel('purchaseOrderId');

const partyStore = usePartyStore();
const accountStore = useAccountStore();
const warehouseStore = useWarehouseStore();
const purchaseOrderStore = usePurchaseOrderStore();
const {parties} = storeToRefs(partyStore);
const {accounts} = storeToRefs(accountStore);
const {warehouses, optionsTree: warehouseOptionsTree} = storeToRefs(warehouseStore);
const {order} = storeToRefs(purchaseOrderStore);
const {currentAdDate} = useDateHelper();

const saving = ref(false);
const productSearchRef = ref(null);
const purchaseOrderOptions = ref([]);

const treatmentOptions = [
    {id: 'capitalized', name: 'Capitalize'},
    {id: 'expense', name: 'Expense'},
];

const allocationOptions = [
    {id: 'value', name: 'By Value'},
    {id: 'quantity', name: 'By Quantity'},
    {id: 'equal', name: 'Equal'},
];

const openedFromRoute = computed(() => route.name === 'admin.grn-create');
const showModal = computed(() => openedFromRoute.value || !!createModalOpened.value || !!purchaseOrderId.value);

const newItemTemplate = () => ({
    product_variant_id: null,
    product_label: '',
    sku: '',
    purchase_order_item_id: null,
    ordered_qty: 0,
    received_qty: 1,
    unit_cost: 0,
    is_batch_tracked: false,
    create_batch: false,
    batch_id: null,
    batch_no: '',
    mfg_date: null,
    expiry_date: null,
});

const newLandedCostTemplate = () => ({
    cost_type: '',
    description: '',
    treatment: 'capitalized',
    allocation_method: 'value',
    amount: 0,
    vat_amount: 0,
    vat_claimable_amount: 0,
    account_id: '',
});

const getInitialState = () => ({
    party_id: '',
    warehouse_id: '',
    purchase_order_id: '',
    received_date: currentAdDate,
    supplier_invoice_no: '',
    remarks: '',
    items: [],
    landed_costs: [],
});

const form = reactive({...getInitialState()});

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

const batchDateWarning = (item) => {
    if (!item.create_batch) {
        return null;
    }
    if (item.mfg_date && item.expiry_date && item.expiry_date <= item.mfg_date) {
        return 'Expiry is on or before the manufacture date.';
    }
    return null;
};


const variantLabel = (variant) => {
    let label = variant.name || variant.product?.name || '';
    if (variant.sku) {
        label += ` (${variant.sku})`;
    }
    return label;
};

const resetForm = () => {
    Object.assign(form, getInitialState());
};

const closeCreateModal = async () => {
    if (openedFromRoute.value) {
        await router.push({name: 'admin.grn-list'});
        return;
    }
    createModalOpened.value = false;
    purchaseOrderId.value = '';
};

const removeItem = (index) => {
    form.items.splice(index, 1);
};

const addLandedCost = () => {
    form.landed_costs.push(newLandedCostTemplate());
};

const removeLandedCost = (index) => {
    form.landed_costs.splice(index, 1);
};

const onVariantSelected = (variant) => {
    const existing = form.items.findIndex((item) => String(item.product_variant_id) === String(variant.id));
    if (existing !== -1) {
        form.items[existing].received_qty = Number(form.items[existing].received_qty || 0) + 1;
        return;
    }

    form.items.push({
        ...newItemTemplate(),
        product_variant_id: variant.id,
        product_label: variantLabel(variant),
        sku: variant.sku || '',
        unit_cost: Number(variant.purchase_price ?? variant.sales_price ?? 0),
        is_batch_tracked: !!variant.is_batch_tracked,
    });
    productSearchRef.value?.focus?.();
};

const loadPurchaseOrders = async () => {
    try {
        const res = await apiAdmin('purchase-order', 'get', {
            per_page: 100,
            status: 'approved',
            party_id: form.party_id || undefined,
        });
        purchaseOrderOptions.value = (res.data.data || []).map((po) => ({
            label: po.order_no,
            value: String(po.id),
        }));
    } catch (e) {
        showErrors(e);
    }
};

const onPurchaseOrderChange = async (poId) => {
    if (!poId) {
        return;
    }
    await loadFromPurchaseOrder(poId);
};

const mergePartyFromPayload = (party) => {
    if (party?.id && !partyStore.parties.data.some((p) => String(p.id) === String(party.id))) {
        partyStore.parties.data = [party, ...partyStore.parties.data];
    }
};

const loadFromPurchaseOrder = async (poId) => {
    await purchaseOrderStore.getOrder(poId);
    const data = order.value.data;

    mergePartyFromPayload(data.party);
    form.party_id = data.party_id ? String(data.party_id) : form.party_id;
    form.purchase_order_id = String(poId);
    form.remarks = data.remarks || form.remarks;
    form.items = [];

    (data.items || []).forEach((item) => {
        form.items.push({
            ...newItemTemplate(),
            product_variant_id: item.product_variant_id,
            product_label: variantLabel(item.product_variant || {}),
            sku: item.product_variant?.sku || '',
            purchase_order_item_id: item.id,
            ordered_qty: Number(item.quantity || 0),
            received_qty: Number(item.quantity || 1),
            unit_cost: Number(item.rate || 0),
            is_batch_tracked: !!item.product_variant?.is_batch_tracked,
        });
    });
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
        batch_id: item.create_batch ? null : (item.batch_id || null),
        batch_no: item.create_batch ? (item.batch_no || null) : null,
        expiry_date: item.create_batch ? (item.expiry_date || null) : null,
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

const save = async () => {
    if (!form.party_id || !form.warehouse_id || !form.items.length) {
        toast('warning', 'Supplier, warehouse, and at least one item are required.');
        return;
    }

    saving.value = true;
    try {
        const res = await apiAdmin('grn', 'post', buildPayload());
        toast(201, res.data.message);
        emit('saved');
        resetForm();

        if (openedFromRoute.value) {
            await router.push({name: 'admin.grn-list'});
        } else {
            createModalOpened.value = false;
            purchaseOrderId.value = '';
        }
    } catch (e) {
        showErrors(e);
    } finally {
        saving.value = false;
    }
};

watch(
    () => showModal.value,
    async (opened) => {
        if (opened) {
            resetForm();
            await Promise.all([
                partyStore.getParties({filter: {type: 'supplier', limit: 50, search: ''}}),
                accountStore.getAccounts(),
                warehouseStore.getWarehouses(),
                loadPurchaseOrders(),
            ]);
            if (purchaseOrderId.value) {
                await loadFromPurchaseOrder(purchaseOrderId.value);
            }
        }
    },
    {flush: 'post'},
);

watch(
    () => form.party_id,
    () => loadPurchaseOrders()
);

onMounted(() => {
    partyStore.getParties({filter: {type: 'supplier', limit: 50, search: ''}});
    accountStore.getAccounts();
    warehouseStore.getWarehouses();
});
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
    min-width: 10rem;
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
