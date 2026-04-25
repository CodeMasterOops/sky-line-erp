<template>
    <PageHeader title="Serial / Lot Numbers" subtitle="Track individual product units and batches" />

    <div class="card border-0 mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Product</label>
                    <vue-select v-model="filters.product_id" :options="productOptions" :reduce="o => o.value" placeholder="All Products" clearable @update:modelValue="load" />
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" v-model="filters.status" @change="load">
                        <option value="">All</option>
                        <option value="in_stock">In Stock</option>
                        <option value="sold">Sold</option>
                        <option value="returned">Returned</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Serial / Lot No</label>
                    <input type="text" class="form-control" v-model="filters.search" @input="load" placeholder="Search..." />
                </div>
                <div class="col-md-2">
                    <label class="form-label">Batch No</label>
                    <input type="text" class="form-control" v-model="filters.batch_no" @input="load" />
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <label class="form-label me-2">Expiring within (days)</label>
                    <input type="number" class="form-control" v-model="filters.expiry_within_days" @input="load" placeholder="e.g. 30" />
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Serial / Lot No</th>
                            <th>Product</th>
                            <th>Batch No</th>
                            <th>Expiry Date</th>
                            <th>Status</th>
                            <th>Received Date</th>
                            <th>Source</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading"><td colspan="7" class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></td></tr>
                        <tr v-else-if="!rows.length"><td colspan="7" class="text-center text-muted py-4">No records found.</td></tr>
                        <tr v-for="sn in rows" :key="sn.id" :class="{'table-warning': isExpiringSoon(sn.expiry_date), 'table-danger': isExpired(sn.expiry_date)}">
                            <td class="fw-semibold">{{ sn.serial_no }}</td>
                            <td>{{ sn.product_name }}</td>
                            <td>{{ sn.batch_no || '–' }}</td>
                            <td>
                                <span v-if="sn.expiry_date">{{ sn.expiry_date }}
                                    <span v-if="isExpired(sn.expiry_date)" class="badge bg-danger ms-1">Expired</span>
                                    <span v-else-if="isExpiringSoon(sn.expiry_date)" class="badge bg-warning text-dark ms-1">Expiring Soon</span>
                                </span>
                                <span v-else>–</span>
                            </td>
                            <td>
                                <span class="badge" :class="statusClass(sn.status)">{{ statusLabel(sn.status) }}</span>
                            </td>
                            <td>{{ formatDate(sn.created_at) }}</td>
                            <td>{{ sn.source || '–' }}</td>
                        </tr>
                    </tbody>
                </table>

                <Pagination v-if="meta" :meta="meta" @change="page => { filters.page = page; load(); }" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import { formatDate } from '@/helpers/helper.js';

const rows = ref([]);
const loading = ref(false);
const meta = ref(null);
const productOptions = ref([]);

const filters = ref({ product_id: null, status: '', search: '', batch_no: '', expiry_within_days: null, page: 1 });

const load = async () => {
    loading.value = true;
    try {
        const params = Object.fromEntries(Object.entries(filters.value).filter(([, v]) => v !== null && v !== ''));
        const res = await apiAdmin('serial-number', 'get', params);
        rows.value = res.data.data || [];
        meta.value = res.data.meta || null;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const loadProducts = async () => {
    try {
        const res = await apiAdmin('product', 'get', { per_page: 500 });
        productOptions.value = (res.data.data || []).map(p => ({ label: p.name, value: p.id }));
    } catch (e) { /* ignore */ }
};

const isExpired = (d) => d && new Date(d) < new Date();
const isExpiringSoon = (d) => {
    if (!d) return false;
    const diff = (new Date(d) - new Date()) / (1000 * 60 * 60 * 24);
    return diff > 0 && diff <= 30;
};

const statusClass = (s) => ({ in_stock: 'bg-success', sold: 'bg-secondary', returned: 'bg-warning text-dark' }[s] || 'bg-light');
const statusLabel = (s) => ({ in_stock: 'In Stock', sold: 'Sold', returned: 'Returned' }[s] || s);

onMounted(() => { load(); loadProducts(); });
</script>
