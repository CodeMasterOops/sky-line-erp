<template>
    <PageHeader title="Create Delivery Challan" subtitle="Record goods delivery to customer">
        <template #actions>
            <router-link :to="{ name: 'admin.delivery-challan-list' }" class="btn btn-outline-secondary">
                <i class="ti ti-arrow-left me-1"></i> Back
            </router-link>
        </template>
    </PageHeader>

    <div class="card border-0">
        <div class="card-body">
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label">Customer</label>
                    <vue-select v-model="form.party_id" :options="customerOptions" :reduce="o => o.value" placeholder="Select Customer" />
                </div>
                <div class="col-md-4">
                    <label class="form-label">Warehouse *</label>
                    <vue-select v-model="form.warehouse_id" :options="warehouseOptions" :reduce="o => o.value" placeholder="Select Warehouse" />
                </div>
                <div class="col-md-4">
                    <label class="form-label">Challan Date *</label>
                    <input type="date" class="form-control" v-model="form.challan_date" />
                </div>
                <div class="col-md-4">
                    <label class="form-label">Receiver Name</label>
                    <input type="text" class="form-control" v-model="form.receiver_name" placeholder="Person receiving goods" />
                </div>
                <div class="col-md-4">
                    <label class="form-label">Delivery Address</label>
                    <input type="text" class="form-control" v-model="form.delivery_address" />
                </div>
                <div class="col-md-4">
                    <label class="form-label">Remarks</label>
                    <input type="text" class="form-control" v-model="form.remarks" />
                </div>
            </div>

            <h6 class="mb-3">Items</h6>
            <div class="table-responsive mb-3">
                <table class="table table-sm">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40%">Product</th>
                            <th class="text-end">Quantity *</th>
                            <th class="text-end">Rate</th>
                            <th>Remarks</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, idx) in form.items" :key="idx">
                            <td>
                                <vue-select v-model="item.product_variant_id" :options="productOptions" :reduce="o => o.value" placeholder="Select product" />
                            </td>
                            <td>
                                <input type="number" class="form-control form-control-sm text-end" v-model="item.quantity" min="0.001" step="0.001" />
                            </td>
                            <td>
                                <input type="number" class="form-control form-control-sm text-end" v-model="item.rate" min="0" step="0.01" />
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm" v-model="item.remarks" />
                            </td>
                            <td>
                                <button class="btn btn-sm btn-danger" @click="removeItem(idx)"><i class="ti ti-trash"></i></button>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="5">
                                <button class="btn btn-sm btn-outline-primary" @click="addItem">
                                    <i class="ti ti-plus me-1"></i> Add Item
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <router-link :to="{ name: 'admin.delivery-challan-list' }" class="btn btn-secondary">Cancel</router-link>
                <button class="btn btn-primary" @click="save" :disabled="saving">
                    <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                    Save Challan
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
const customerOptions = ref([]);
const warehouseOptions = ref([]);
const productOptions = ref([]);

const form = ref({
    party_id: null,
    warehouse_id: null,
    challan_date: new Date().toISOString().split('T')[0],
    receiver_name: '',
    delivery_address: '',
    remarks: '',
    items: [{ product_variant_id: null, quantity: 1, rate: 0, remarks: '' }],
});

const addItem = () => form.value.items.push({ product_variant_id: null, quantity: 1, rate: 0, remarks: '' });
const removeItem = (idx) => form.value.items.splice(idx, 1);

const save = async () => {
    saving.value = true;
    try {
        const res = await apiAdmin('delivery-challan', 'post', form.value);
        toast('success', res.data.message);
        router.push({ name: 'admin.delivery-challan-list' });
    } catch (e) {
        showErrors(e);
    } finally {
        saving.value = false;
    }
};

const loadOptions = async () => {
    const [partiesRes, warehousesRes, productsRes] = await Promise.all([
        apiAdmin('party', 'get', { per_page: 500, type: 'customer' }),
        apiAdmin('warehouse', 'get', { per_page: 100 }),
        apiAdmin('product/variant/all', 'get'),
    ]);
    customerOptions.value = (partiesRes.data.data || []).map(p => ({ label: p.name, value: p.id }));
    warehouseOptions.value = (warehousesRes.data.data || []).map(w => ({ label: w.name, value: w.id }));
    productOptions.value = (productsRes.data.data || []).map(v => ({ label: `${v.product?.name} – ${v.sku}`, value: v.id }));
};

onMounted(() => { loadOptions(); });
</script>
