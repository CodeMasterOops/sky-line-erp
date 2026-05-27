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
                                :options="warehouses.data"
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
                                        <td class="text-end fw-semibold">{{ fmt(lineTotal(item)) }}</td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1 mb-1">
                                                <input
                                                    type="checkbox"
                                                    class="form-check-input mt-0"
                                                    v-model="form.items[index].create_batch"
                                                    :id="`cb-batch-${index}`"
                                                />
                                                <label :for="`cb-batch-${index}`" class="form-label mb-0 small text-nowrap">
                                                    {{ item.batch_id ? 'Linked' : 'Create batch' }}
                                                </label>
                                            </div>
                                            <template v-if="item.create_batch && !item.batch_id">
                                                <VInput
                                                    input-class="form-control form-control-sm mb-1"
                                                    v-model="form.items[index].batch_no"
                                                    placeholder="Batch No"
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
                                            </template>
                                            <span v-else-if="item.batch_id" class="badge bg-success-subtle text-success small">
                                                Batch #{{ item.batch_id }}
                                            </span>
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
                                        <td class="text-end">{{ fmt(grandTotal) }}</td>
                                        <td colspan="2"></td>
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
import {computed, onMounted, reactive, ref, watch} from 'vue';
import {useRoute, useRouter} from 'vue-router';
import {storeToRefs} from 'pinia';
import debounce from 'lodash/debounce';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import {toast} from '@/helpers/toast.js';
import {useDateHelper} from '@/composables/dateHelper.js';
import {usePartyStore} from '@/stores/admin/party.js';
import {useWarehouseStore} from '@/stores/admin/inventory/warehouse.js';
import {usePurchaseOrderStore} from '@/stores/admin/purchase/purchase-order.js';
import {useResolvedParty} from '@/composables/useResolvedParty.js';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import PartyMetaPanel from '@/components/party/PartyMetaPanel.vue';
import VRequiredMark from '@/components/base/VRequiredMark.vue';

const emit = defineEmits(['saved']);
const route = useRoute();
const router = useRouter();
const createModalOpened = defineModel('createModalOpened');
const purchaseOrderId = defineModel('purchaseOrderId');

const partyStore = usePartyStore();
const warehouseStore = useWarehouseStore();
const purchaseOrderStore = usePurchaseOrderStore();
const {parties} = storeToRefs(partyStore);
const {warehouses} = storeToRefs(warehouseStore);
const {order} = storeToRefs(purchaseOrderStore);
const {currentAdDate} = useDateHelper();

const saving = ref(false);
const productSearchRef = ref(null);
const purchaseOrderOptions = ref([]);

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
    create_batch: false,
    batch_id: null,
    batch_no: '',
    mfg_date: null,
    expiry_date: null,
});

const getInitialState = () => ({
    party_id: '',
    warehouse_id: '',
    purchase_order_id: '',
    received_date: currentAdDate,
    supplier_invoice_no: '',
    remarks: '',
    items: [],
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

const lineTotal = (item) =>
    Number(item.received_qty || 0) * Number(item.unit_cost || 0);

const fmt = (val) =>
    Number(val ?? 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});

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
        });
    });
};

const createBatchForItem = async (item) => {
    if (!item.create_batch || item.batch_id || !item.batch_no) {
        return;
    }
    const payload = {
        product_variant_id: item.product_variant_id,
        warehouse_id: form.warehouse_id,
        batch_no: item.batch_no,
        mfg_date: item.mfg_date || null,
        expiry_date: item.expiry_date || null,
        initial_qty: Number(item.received_qty) || 0,
        unit_cost: Number(item.unit_cost) || 0,
    };
    const res = await apiAdmin('batch', 'post', payload);
    item.batch_id = res.data.data?.id ?? null;
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
        expiry_date: item.expiry_date || null,
    })),
});

const save = async () => {
    if (!form.party_id || !form.warehouse_id || !form.items.length) {
        toast('warning', 'Supplier, warehouse, and at least one item are required.');
        return;
    }

    saving.value = true;
    try {
        await Promise.all(
            form.items
                .filter((i) => i.create_batch && !i.batch_id && i.batch_no)
                .map((i) => createBatchForItem(i))
        );

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
</style>
