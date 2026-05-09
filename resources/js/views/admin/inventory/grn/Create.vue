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
                <div class="card-body p-0">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Supplier *</label>
                            <vue-select v-model="form.party_id" :options="supplierOptions" :reduce="o => o.value" placeholder="Select Supplier" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Warehouse *</label>
                            <vue-select v-model="form.warehouse_id" :options="warehouseOptions" :reduce="o => o.value" placeholder="Select Warehouse" />
                        </div>
                        <div class="col-md-4">
                            <VDatepicker id="received_date" v-model="form.received_date" label="Received Date" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Supplier Invoice No</label>
                            <input type="text" class="form-control" v-model="form.supplier_invoice_no" placeholder="Supplier's invoice reference" />
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Remarks</label>
                            <input type="text" class="form-control" v-model="form.remarks" />
                        </div>
                    </div>

                    <h6 class="mb-3">Items</h6>
                    <div class="mb-3">
                        <ProductVariantSearchInput label="Product" required @select="onVariantSelected" />
                    </div>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 30%">Product</th>
                                    <th class="text-end">Ordered Qty</th>
                                    <th class="text-end">Received Qty *</th>
                                    <th class="text-end">Unit Cost *</th>
                                    <th>Batch Info</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!form.items.length">
                                    <td colspan="6" class="text-center text-muted py-4">
                                        Search and select a product to add lines.
                                    </td>
                                </tr>
                                <tr v-for="(item, idx) in form.items" :key="idx">
                                    <td><span class="fw-medium">{{ item.product_label || '-' }}</span></td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm text-end" v-model="item.ordered_qty" min="0" />
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm text-end" v-model="item.received_qty" min="0.001" step="0.001" />
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm text-end" v-model="item.unit_cost" min="0" step="0.01" />
                                    </td>
                                    <td style="min-width:16rem">
                                        <div class="d-flex align-items-center gap-1 mb-1">
                                            <input type="checkbox" class="form-check-input mt-0" v-model="item.create_batch" :id="`cb-batch-${idx}`" />
                                            <label :for="`cb-batch-${idx}`" class="form-label mb-0 small text-nowrap">
                                                {{ item.batch_id ? 'Batch linked ✓' : 'Create & Link Batch' }}
                                            </label>
                                        </div>
                                        <template v-if="item.create_batch && !item.batch_id">
                                            <input type="text" class="form-control form-control-sm mb-1" v-model="item.batch_no" placeholder="Batch No *" />
                                            <input type="date" class="form-control form-control-sm mb-1" v-model="item.mfg_date" title="Mfg Date" />
                                            <input type="date" class="form-control form-control-sm" v-model="item.expiry_date" title="Expiry Date" />
                                        </template>
                                        <span v-else-if="item.batch_id" class="badge bg-success-subtle text-success small">
                                            Batch #{{ item.batch_id }}
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-danger" type="button" @click="removeItem(idx)">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="text-end">
                        <button class="btn btn-secondary me-2" type="button" @click="closeCreateModal">Cancel</button>
                        <button class="btn btn-primary" type="button" @click="save" :disabled="saving">
                            <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                            Save GRN
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </VModal>
</template>

<script setup>
import {computed, onMounted, ref, watch} from 'vue';
import {useRoute, useRouter} from 'vue-router';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import {toast} from '@/helpers/toast.js';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import {useDateHelper} from '@/composables/dateHelper.js';

const emit = defineEmits(['saved']);
const route = useRoute();
const router = useRouter();
const createModalOpened = defineModel('createModalOpened');

const saving = ref(false);
const supplierOptions = ref([]);
const warehouseOptions = ref([]);
const {currentAdDate} = useDateHelper();

const openedFromRoute = computed(() => route.name === 'admin.grn-create');
const showModal = computed(() => openedFromRoute.value || !!createModalOpened.value);

const newItemTemplate = () => ({
    product_variant_id: null,
    product_label: '',
    ordered_qty: 0,
    received_qty: 1,
    unit_cost: 0,
    create_batch: false,
    batch_id: null,
    batch_no: '',
    mfg_date: null,
    expiry_date: null,
});

const initialState = () => ({
    party_id: null,
    warehouse_id: null,
    received_date: currentAdDate,
    supplier_invoice_no: '',
    remarks: '',
    items: [],
});

const form = ref(initialState());

const resetForm = () => {
    form.value = initialState();
};

const closeCreateModal = async () => {
    if (openedFromRoute.value) {
        await router.push({ name: 'admin.grn-list' });
        return;
    }

    createModalOpened.value = false;
};

const removeItem = (idx) => {
    form.value.items.splice(idx, 1);
};

const onVariantSelected = (variant) => {
    const existing = form.value.items.findIndex((item) => String(item.product_variant_id) === String(variant.id));
    if (existing !== -1) {
        form.value.items[existing].received_qty = Number(form.value.items[existing].received_qty || 0) + 1;
        return;
    }

    form.value.items.push({
        ...newItemTemplate(),
        product_variant_id: variant.id,
        product_label: variantLabel(variant),
        unit_cost: Number(variant.purchase_price ?? variant.sales_price ?? 0),
    });
};

const variantLabel = (variant) => {
    let label = variant.name || variant.product?.name || '';
    if (variant.sku) {
        label += ` (${variant.sku})`;
    }
    return label;
};

const createBatchForItem = async (item) => {
    if (!item.create_batch || item.batch_id || !item.batch_no) return;
    const payload = {
        product_variant_id: item.product_variant_id,
        warehouse_id: form.value.warehouse_id,
        batch_no: item.batch_no,
        mfg_date: item.mfg_date || null,
        expiry_date: item.expiry_date || null,
        initial_qty: Number(item.received_qty) || 0,
        unit_cost: Number(item.unit_cost) || 0,
    };
    const res = await apiAdmin('batch', 'post', payload);
    item.batch_id = res.data.data?.id ?? null;
};

const save = async () => {
    saving.value = true;
    try {
        await Promise.all(
            form.value.items
                .filter((i) => i.create_batch && !i.batch_id && i.batch_no)
                .map((i) => createBatchForItem(i))
        );

        const payload = {
            ...form.value,
            items: form.value.items.map((item) => ({
                product_variant_id: item.product_variant_id,
                ordered_qty: item.ordered_qty,
                received_qty: item.received_qty,
                unit_cost: item.unit_cost,
                batch_id: item.batch_id || null,
                batch_no: item.batch_no || null,
                expiry_date: item.expiry_date || null,
            })),
        };

        const res = await apiAdmin('grn', 'post', payload);
        toast(201, res.data.message);
        emit('saved');
        resetForm();

        if (openedFromRoute.value) {
            await router.push({ name: 'admin.grn-list' });
        } else {
            createModalOpened.value = false;
        }
    } catch (e) {
        showErrors(e);
    } finally {
        saving.value = false;
    }
};

const loadOptions = async () => {
    const [partiesRes, warehousesRes] = await Promise.all([
        apiAdmin('party', 'get', { per_page: 500, type: 'supplier' }),
        apiAdmin('warehouse', 'get', { per_page: 100 }),
    ]);
    supplierOptions.value = (partiesRes.data.data || []).map((p) => ({ label: p.name, value: p.id }));
    warehouseOptions.value = (warehousesRes.data.data || []).map((w) => ({ label: w.name, value: w.id }));
};

watch(
    () => showModal.value,
    (opened) => {
        if (opened) {
            resetForm();
        }
    }
);

onMounted(() => {
    loadOptions();
});
</script>
