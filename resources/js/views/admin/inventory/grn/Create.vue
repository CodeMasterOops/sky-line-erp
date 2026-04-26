<template>
    <PageHeader title="Create Goods Received Note" subtitle="Record stock receipt from supplier">
        <template #actions>
            <router-link :to="{ name: 'admin.grn-list' }" class="btn btn-outline-secondary">
                <i class="ti ti-arrow-left me-1"></i> Back
            </router-link>
        </template>
    </PageHeader>

    <div class="card border-0">
        <div class="card-body">
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
                    <label class="form-label">Received Date *</label>
                    <input type="date" class="form-control" v-model="form.received_date" />
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
                        <tr v-for="(item, idx) in form.items" :key="idx">
                            <td>
                                <vue-select
                                    v-model="item.product_variant_id"
                                    :options="productOptions"
                                    :reduce="o => o.value"
                                    placeholder="Select product"
                                />
                            </td>
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
                                <button class="btn btn-sm btn-danger" @click="removeItem(idx)">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="6">
                                <button class="btn btn-sm btn-outline-primary" @click="addItem">
                                    <i class="ti ti-plus me-1"></i> Add Item
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <router-link :to="{ name: 'admin.grn-list' }" class="btn btn-secondary">Cancel</router-link>
                <button class="btn btn-primary" @click="save" :disabled="saving">
                    <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                    Save GRN
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import {ref, onMounted} from 'vue';
import {useRouter} from 'vue-router';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import {toast} from '@/helpers/toast.js';

const router = useRouter();
const saving = ref(false);
const supplierOptions = ref([]);
const warehouseOptions = ref([]);
const productOptions = ref([]);

const newItemTemplate = () => ({
    product_variant_id: null,
    ordered_qty: 0,
    received_qty: 1,
    unit_cost: 0,
    // batch fields
    create_batch: false,
    batch_id: null,
    batch_no: '',
    mfg_date: null,
    expiry_date: null,
});

const form = ref({
    party_id: null,
    warehouse_id: null,
    received_date: new Date().toISOString().split('T')[0],
    supplier_invoice_no: '',
    remarks: '',
    items: [newItemTemplate()],
});

const addItem = () => {
    form.value.items.push(newItemTemplate());
};

const removeItem = (idx) => {
    form.value.items.splice(idx, 1);
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
        // Create batches first for lines that need it
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
        toast('success', res.data.message);
        router.push({ name: 'admin.grn-list' });
    } catch (e) {
        showErrors(e);
    } finally {
        saving.value = false;
    }
};

const loadOptions = async () => {
    const [partiesRes, warehousesRes, productsRes] = await Promise.all([
        apiAdmin('party', 'get', { per_page: 500, type: 'supplier' }),
        apiAdmin('warehouse', 'get', { per_page: 100 }),
        apiAdmin('product/variant/all', 'get'),
    ]);
    supplierOptions.value = (partiesRes.data.data || []).map(p => ({ label: p.name, value: p.id }));
    warehouseOptions.value = (warehousesRes.data.data || []).map(w => ({ label: w.name, value: w.id }));
    productOptions.value = (productsRes.data.data || []).map(v => ({
        label: `${v.product?.name} – ${v.sku}`,
        value: v.id,
    }));
};

onMounted(() => { loadOptions(); });
</script>
