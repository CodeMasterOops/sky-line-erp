<template>
    <PageHeader hide-action-buttons title="Warehouse Wise Stock Report" subtitle="Current stock quantities grouped by warehouse" />

    <div class="card border-0 mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Warehouse</label>
                    <select class="form-select" v-model="filters.warehouse_id">
                        <option value="">All Warehouses</option>
                        <option v-for="w in warehouseOptions" :key="w.id" :value="w.id">{{ w.name }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary" @click="loadReport" :disabled="loading">
                        <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                        Generate Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div v-if="summary" class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card border-0 bg-primary-subtle text-center p-3">
                <div class="text-muted small">Total Warehouses</div>
                <div class="fw-bold">{{ summary.total_warehouses }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 bg-info-subtle text-center p-3">
                <div class="text-muted small">Total SKUs (with stock)</div>
                <div class="fw-bold">{{ summary.total_items }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 bg-success-subtle text-center p-3">
                <div class="text-muted small">Total Quantity</div>
                <div class="fw-bold text-success">{{ formatMoneyPlain(summary.total_quantity) }}</div>
            </div>
        </div>
    </div>

    <div v-for="group in rows" :key="group.warehouse" class="card border-0 mb-3">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="ti ti-building-warehouse me-2"></i>{{ group.warehouse }}</span>
            <span class="text-muted small">{{ group.item_count }} items &bull; {{ formatMoneyPlain(group.total_quantity) }} total qty</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th>Code</th>
                            <th>SKU</th>
                            <th>Category</th>
                            <th class="text-end">Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, idx) in group.items" :key="idx">
                            <td>{{ item.product_name }}</td>
                            <td>{{ item.product_code }}</td>
                            <td>{{ item.sku }}</td>
                            <td>{{ item.category }}</td>
                            <td class="text-end fw-semibold">{{ formatMoneyPlain(item.quantity) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div v-if="!rows.length && !loading" class="card border-0">
        <div class="card-body text-center text-muted py-5">Click Generate Report to load data.</div>
    </div>
</template>

<script setup>
import {formatMoneyPlain} from '@/helpers/formatMoney.js';
import {ref, onMounted} from 'vue';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const rows = ref([]);
const summary = ref(null);
const loading = ref(false);
const warehouseOptions = ref([]);

const filters = ref({ warehouse_id: '' });

const loadReport = async () => {
    loading.value = true;
    try {
        const params = { ...filters.value };
        Object.keys(params).forEach((k) => { if (params[k] === '' || params[k] === null) { delete params[k]; } });
        const res = await apiAdmin('inventory-report/warehouse-stock', 'get', params);
        const data = res.data.data;
        rows.value = data.rows || [];
        summary.value = data.summary;
        warehouseOptions.value = data.warehouse_options || [];
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

// Only load warehouse options on mount, not full data
onMounted(async () => {
    try {
        const res = await apiAdmin('inventory-report/warehouse-stock', 'get', { warehouse_id: '__none__' });
        warehouseOptions.value = res.data.data.warehouse_options || [];
    } catch { /* ignore */ }
});
</script>
