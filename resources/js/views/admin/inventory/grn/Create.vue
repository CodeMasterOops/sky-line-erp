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
                            <th style="width: 35%">Product</th>
                            <th class="text-end">Ordered Qty</th>
                            <th class="text-end">Received Qty *</th>
                            <th class="text-end">Unit Cost *</th>
                            <th>Batch No</th>
                            <th>Expiry Date</th>
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
                            <td>
                                <input type="text" class="form-control form-control-sm" v-model="item.batch_no" placeholder="Batch" />
                            </td>
                            <td>
                                <input type="date" class="form-control form-control-sm" v-model="item.expiry_date" />
                            </td>
                            <td>
                                <button class="btn btn-sm btn-danger" @click="removeItem(idx)">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="7">
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

const form = ref({
    party_id: null,
    warehouse_id: null,
    received_date: new Date().toISOString().split('T')[0],
    supplier_invoice_no: '',
    remarks: '',
    items: [{ product_variant_id: null, ordered_qty: 0, received_qty: 1, unit_cost: 0, batch_no: '', expiry_date: null }],
});

const addItem = () => {
    form.value.items.push({ product_variant_id: null, ordered_qty: 0, received_qty: 1, unit_cost: 0, batch_no: '', expiry_date: null });
};

const removeItem = (idx) => {
    form.value.items.splice(idx, 1);
};

const save = async () => {
    saving.value = true;
    try {
        const res = await apiAdmin('grn', 'post', form.value);
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
