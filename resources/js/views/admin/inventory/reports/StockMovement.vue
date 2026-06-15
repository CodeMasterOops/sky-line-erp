<template>
    <PageHeader hide-action-buttons title="Stock Movement Report" subtitle="All inventory IN/OUT movements with date range and filters" />

    <div class="card border-0 mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <VDatepicker id="from_date" label="From Date" v-model="filters.from_date" />
                </div>
                <div class="col-md-2">
                    <VDatepicker id="to_date" label="To Date" v-model="filters.to_date" />
                </div>
                <div class="col-md-3">
                    <label class="form-label">Product</label>
                    <select class="form-select" v-model="filters.product_variant_id">
                        <option value="">All Products</option>
                        <option v-for="p in productOptions" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Warehouse</label>
                    <select class="form-select" v-model="filters.warehouse_id">
                        <option value="">All Warehouses</option>
                        <option v-for="w in warehouseOptions" :key="w.id" :value="w.id">{{ w.name }}</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Direction</label>
                    <select class="form-select" v-model="filters.direction">
                        <option value="">All</option>
                        <option value="in">In</option>
                        <option value="out">Out</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" @click="loadReport(1)" :disabled="loading">
                        <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                        Generate Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div v-if="summary" class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card border-0 bg-success-subtle text-center p-3">
                <div class="text-muted small">Total IN Qty</div>
                <div class="fw-bold text-success">{{ formatMoneyPlain(summary.total_in) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 bg-danger-subtle text-center p-3">
                <div class="text-muted small">Total OUT Qty</div>
                <div class="fw-bold text-danger">{{ formatMoneyPlain(summary.total_out) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 bg-info-subtle text-center p-3">
                <div class="text-muted small">Total Cost Value</div>
                <div class="fw-bold">{{ formatMoney(summary.total_value) }}</div>
            </div>
        </div>
    </div>

    <div class="card border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Warehouse</th>
                            <th>Type</th>
                            <th>Direction</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Unit Cost</th>
                            <th class="text-end">Total Cost</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!rows.length && !loading">
                            <td colspan="10" class="text-center text-muted py-4">Set filters and click Generate Report to load data.</td>
                        </tr>
                        <tr v-for="(row, idx) in rows" :key="idx">
                            <td class="text-nowrap">{{ row.date }}</td>
                            <td>{{ row.product_name }}<br><small class="text-muted">{{ row.product_code }}</small></td>
                            <td>{{ row.sku }}</td>
                            <td>{{ row.warehouse }}</td>
                            <td><span class="badge bg-secondary-subtle text-secondary">{{ row.type }}</span></td>
                            <td>
                                <span class="badge" :class="row.direction === 'in' ? 'bg-success' : 'bg-danger'">
                                    {{ row.direction === 'in' ? 'IN' : 'OUT' }}
                                </span>
                            </td>
                            <td class="text-end">{{ formatMoneyPlain(row.quantity) }}</td>
                            <td class="text-end">{{ formatMoney(row.unit_cost) }}</td>
                            <td class="text-end fw-semibold">{{ formatMoney(row.total_cost) }}</td>
                            <td class="text-muted small">{{ row.remarks }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="pagination && pagination.last_page > 1" class="d-flex justify-content-between align-items-center mt-3">
                <small class="text-muted">Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} records)</small>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" :disabled="pagination.current_page <= 1" @click="loadReport(pagination.current_page - 1)">‹ Prev</button>
                    <button class="btn btn-outline-secondary" :disabled="pagination.current_page >= pagination.last_page" @click="loadReport(pagination.current_page + 1)">Next ›</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import {formatMoney, formatMoneyPlain} from '@/helpers/formatMoney.js';
import {ref, onMounted} from 'vue';
import {storeToRefs} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import {useAdminSettingStore} from '@/stores/admin/settings/admin-setting.js';

const rows = ref([]);
const summary = ref(null);
const pagination = ref(null);
const loading = ref(false);
const productOptions = ref([]);
const warehouseOptions = ref([]);

const adminSettingStore = useAdminSettingStore();
const { currentFiscalYear } = storeToRefs(adminSettingStore);

const filters = ref({
    from_date: '',
    to_date: '',
    product_variant_id: '',
    warehouse_id: '',
    direction: '',
});

const loadReport = async (page = 1) => {
    loading.value = true;
    try {
        const params = { ...filters.value, page };
        // Only fetch filter options on first load/filter change, not on pagination
        if (page === 1) { params.with_options = 1; }
        Object.keys(params).forEach((k) => { if (params[k] === '' || params[k] === null) { delete params[k]; } });
        const res = await apiAdmin('inventory-report/stock-movement', 'get', params);
        const data = res.data.data;
        rows.value = data.rows || [];
        summary.value = data.summary;
        pagination.value = data.pagination;
        if (data.filter_options) {
            productOptions.value = data.filter_options.product_variant_options || [];
            warehouseOptions.value = data.filter_options.warehouse_options || [];
        }
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    await adminSettingStore.getCurrentFiscalYear();
    const fy = currentFiscalYear.value?.data;
    if (fy?.start_date && fy?.end_date) {
        filters.value.from_date = fy.start_date;
        filters.value.to_date = fy.end_date;
    } else {
        const now = new Date();
        filters.value.from_date = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10);
        filters.value.to_date = now.toISOString().slice(0, 10);
    }
    // Load filter options on mount (empty result, just options)
    try {
        const res = await apiAdmin('inventory-report/stock-movement', 'get', { with_options: 1, limit: 1 });
        const opts = res.data.data.filter_options || {};
        productOptions.value = opts.product_variant_options || [];
        warehouseOptions.value = opts.warehouse_options || [];
    } catch { /* ignore */ }
});
</script>
