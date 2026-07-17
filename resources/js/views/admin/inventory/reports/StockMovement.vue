<template>
    <PageHeader hide-action-buttons title="Stock Movement Report" subtitle="All inventory IN/OUT movements with date range and filters" />

    <section class="section">
        <div class="row g-3 mb-3">
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-success flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-success-subtle text-success">
                            <i class="ti ti-arrow-down fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total IN Qty</p>
                            <h4 class="mb-0">{{ formatMoneyPlain(kpi.total_in) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-danger flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-danger-subtle text-danger">
                            <i class="ti ti-arrow-up fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total OUT Qty</p>
                            <h4 class="mb-0">{{ formatMoneyPlain(kpi.total_out) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-info flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-info-subtle text-info">
                            <i class="ti ti-currency-rupee fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total Cost Value</p>
                            <h4 class="mb-0">{{ formatMoney(kpi.total_value) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <VDatepicker id="from_date" label="From Date" v-model="filters.from_date" />
                    </div>
                    <div class="col-md-2">
                        <VDatepicker id="to_date" label="To Date" v-model="filters.to_date" />
                    </div>
                    <div class="col-md-3">
                        <VMultiselect
                            id="product_variant_id"
                            v-model="filters.product_variant_id"
                            :options="productOptions"
                            label="Product"
                            placeholder="All Products"
                        />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Warehouse</label>
                        <VMultiselect
                            id="warehouse_id"
                            v-model="filters.warehouse_id"
                            :options="warehouseStore.stockLocationOptionsTree"
                            :loading="warehouseStore.warehouses.loading"
                            placeholder="All Warehouses"
                        />
                    </div>
                    <div class="col-md-1">
                        <VMultiselect
                            id="direction"
                            v-model="filters.direction"
                            :options="directionOptions"
                            label="Direction"
                            placeholder="All"
                        />
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-success flex-grow-1" @click="loadReport(1)" :disabled="loading">
                            <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                            Generate
                        </button>
                        <button class="btn btn-outline-secondary" @click="exportCsv" :disabled="!rows.length" title="Export CSV">
                            <i class="ti ti-file-export"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
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
                                <td colspan="10" class="text-center text-muted py-4">Set filters and click Generate to load data.</td>
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
                <div v-if="pagination && pagination.last_page > 1" class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
                    <small class="text-muted">Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} records)</small>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary" :disabled="pagination.current_page <= 1" @click="loadReport(pagination.current_page - 1)">‹ Prev</button>
                        <button class="btn btn-outline-secondary" :disabled="pagination.current_page >= pagination.last_page" @click="loadReport(pagination.current_page + 1)">Next ›</button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import {formatMoney, formatMoneyPlain} from '@/helpers/formatMoney.js';
import {ref, computed, onMounted} from 'vue';
import {storeToRefs} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import {useAdminSettingStore} from '@/stores/admin/settings/admin-setting.js';
import {useWarehouseStore} from '@/stores/admin/inventory/warehouse.js';
import VMultiselect from '@/components/base/VMultiselect.vue';

const warehouseStore = useWarehouseStore();

const rows = ref([]);
const summary = ref(null);
const pagination = ref(null);
const loading = ref(false);
const productOptions = ref([]);

const directionOptions = [
    { id: 'in', name: 'In' },
    { id: 'out', name: 'Out' },
];

const adminSettingStore = useAdminSettingStore();
const {currentFiscalYear} = storeToRefs(adminSettingStore);

const filters = ref({
    from_date: '',
    to_date: '',
    product_variant_id: '',
    warehouse_id: '',
    direction: '',
});

const kpi = computed(() => summary.value ?? {total_in: 0, total_out: 0, total_value: 0});

const loadReport = async (page = 1) => {
    loading.value = true;
    try {
        const params = {...filters.value, page};
        if (page === 1) { params.with_options = 1; }
        Object.keys(params).forEach((k) => { if (params[k] === '' || params[k] === null) { delete params[k]; } });
        const res = await apiAdmin('inventory-report/stock-movement', 'get', params);
        const data = res.data.data;
        rows.value = data.rows || [];
        summary.value = data.summary;
        pagination.value = data.pagination;
        if (data.filter_options) {
            productOptions.value = data.filter_options.product_variant_options || [];
        }
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const exportCsv = () => {
    if (!rows.value.length) { return; }
    const headers = ['Date', 'Product', 'Code', 'SKU', 'Warehouse', 'Type', 'Direction', 'Qty', 'Unit Cost', 'Total Cost', 'Remarks'];
    const csvRows = rows.value.map(r => [
        r.date, r.product_name, r.product_code, r.sku, r.warehouse, r.type, r.direction,
        r.quantity, r.unit_cost, r.total_cost, r.remarks,
    ].map(v => `"${v ?? ''}"`).join(','));
    const csv = [headers.join(','), ...csvRows].join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], {type: 'text/csv'}));
    a.download = 'stock-movement.csv';
    a.click();
};

onMounted(async () => {
    warehouseStore.getWarehouses();
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
    await loadReport(1);
});
</script>
