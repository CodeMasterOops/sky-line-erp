<template>
    <PageHeader hide-action-buttons title="Batch Stock Report" subtitle="Current stock position by batch with quantity and value" />

    <section class="section">
        <div class="row g-3 mb-3">
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-info flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-info-subtle text-info">
                            <i class="ti ti-stack fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total Batches</p>
                            <h4 class="mb-0">{{ kpi.total_batches }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-warning flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-warning-subtle text-warning">
                            <i class="ti ti-archive fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total Remaining Qty</p>
                            <h4 class="mb-0">{{ formatMoneyPlain(kpi.total_remaining_qty) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-success flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-success-subtle text-success">
                            <i class="ti ti-currency-rupee fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total Value</p>
                            <h4 class="mb-0">{{ formatMoney(kpi.total_value) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Search (Batch No / SKU / Product)</label>
                        <input type="text" class="form-control" v-model="filters.search" placeholder="Search..." />
                    </div>
                    <div class="col-md-2">
                        <VMultiselect
                            id="status"
                            v-model="filters.status"
                            :options="statusOptions"
                            label="Status"
                            placeholder="All"
                        />
                    </div>
                    <div class="col-md-3">
                        <VMultiselect
                            id="warehouse_id"
                            v-model="filters.warehouse_id"
                            :options="warehouseOptions"
                            label="Warehouse"
                            placeholder="All Warehouses"
                        />
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-success flex-grow-1" @click="loadReport" :disabled="loading">
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
                                <th>Batch No</th>
                                <th>Lot No</th>
                                <th>Product</th>
                                <th>Code / SKU</th>
                                <th>Warehouse</th>
                                <th>Mfg. Date</th>
                                <th>Expiry Date</th>
                                <th class="text-end">Initial Qty</th>
                                <th class="text-end">Consumed</th>
                                <th class="text-end">Remaining</th>
                                <th class="text-end">Unit Cost</th>
                                <th class="text-end">Total Value</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!rows.length && !loading">
                                <td colspan="13" class="text-center text-muted py-4">Click Generate to load data.</td>
                            </tr>
                            <tr v-if="loading">
                                <td colspan="13" class="text-center py-4">
                                    <span class="spinner-border spinner-border-sm me-1"></span> Loading...
                                </td>
                            </tr>
                            <tr v-for="(row, idx) in rows" :key="idx">
                                <td class="fw-semibold">{{ row.batch_no }}</td>
                                <td>{{ row.lot_no }}</td>
                                <td>{{ row.product_name }}</td>
                                <td>{{ row.product_code }}<br><small class="text-muted">{{ row.sku }}</small></td>
                                <td>{{ row.warehouse }}</td>
                                <td>{{ row.mfg_date ?? '-' }}</td>
                                <td>{{ row.expiry_date ?? '-' }}</td>
                                <td class="text-end">{{ formatMoneyPlain(row.initial_qty) }}</td>
                                <td class="text-end">{{ formatMoneyPlain(row.consumed_qty) }}</td>
                                <td class="text-end fw-semibold" :class="row.remaining_qty <= 0 ? 'text-muted' : 'text-success'">{{ formatMoneyPlain(row.remaining_qty) }}</td>
                                <td class="text-end">{{ formatMoney(row.unit_cost) }}</td>
                                <td class="text-end fw-semibold">{{ formatMoney(row.total_value) }}</td>
                                <td>
                                    <span class="badge" :class="statusBadge(row.status)">{{ row.status }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div v-if="pagination && pagination.last_page > 1" class="card-footer d-flex justify-content-between align-items-center">
                <small class="text-muted">Showing {{ rows.length }} of {{ pagination.total }} batches</small>
                <VPagination :pagination="pagination" @page-changed="onPageChanged" />
            </div>
        </div>
    </section>
</template>

<script setup>
import {formatMoney, formatMoneyPlain} from '@/helpers/formatMoney.js';
import {ref, computed, onMounted} from 'vue';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const rows = ref([]);
const summary = ref(null);
const pagination = ref(null);
const warehouseOptions = ref([]);
const loading = ref(false);

const statusOptions = [
    { id: 'active', name: 'Active' },
    { id: 'expired', name: 'Expired' },
    { id: 'depleted', name: 'Depleted' },
];

const filters = ref({search: '', status: '', warehouse_id: '', page: 1, limit: 50});

const kpi = computed(() => summary.value ?? {total_batches: 0, total_remaining_qty: 0, total_value: 0});

const statusBadge = (status) => {
    if (status === 'active') return 'bg-success';
    if (status === 'expired') return 'bg-danger';
    if (status === 'depleted') return 'bg-secondary';
    return 'bg-light text-dark';
};

const exportCsv = () => {
    if (!rows.value.length) { return; }
    const headers = ['Batch No', 'Lot No', 'Product', 'Code', 'SKU', 'Warehouse', 'Mfg Date', 'Expiry Date', 'Initial Qty', 'Consumed', 'Remaining', 'Unit Cost', 'Total Value', 'Status'];
    const csvRows = rows.value.map(r => [
        r.batch_no, r.lot_no, r.product_name, r.product_code, r.sku, r.warehouse,
        r.mfg_date, r.expiry_date, r.initial_qty, r.consumed_qty, r.remaining_qty,
        r.unit_cost, r.total_value, r.status,
    ].map(v => `"${v ?? ''}"`).join(','));
    const csv = [headers.join(','), ...csvRows].join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], {type: 'text/csv'}));
    a.download = 'batch-stock.csv';
    a.click();
};

const onPageChanged = (page) => {
    filters.value.page = page;
    loadReport();
};

const loadReport = async () => {
    loading.value = true;
    try {
        const res = await apiAdmin('inventory-report/batch-stock', 'get', {...filters.value});
        const data = res.data.data;
        rows.value = data.rows || [];
        summary.value = data.summary;
        pagination.value = data.pagination;
        warehouseOptions.value = data.warehouse_options || warehouseOptions.value;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    await loadReport();
});
</script>
