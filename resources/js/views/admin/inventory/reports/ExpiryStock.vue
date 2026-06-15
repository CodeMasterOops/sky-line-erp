<template>
    <PageHeader hide-action-buttons title="Expiry Stock Report" subtitle="Batch-level expiry tracking — near expiry and expired items" />

    <div class="card border-0 mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Report Type</label>
                    <select class="form-select" v-model="filters.type">
                        <option value="near_expiry">Near Expiry</option>
                        <option value="expired">Expired</option>
                    </select>
                </div>
                <div v-if="filters.type === 'near_expiry'" class="col-md-2">
                    <label class="form-label">Days Ahead</label>
                    <input type="number" class="form-control" v-model="filters.days" min="1" max="365" />
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1" @click="loadReport" :disabled="loading">
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

    <div v-if="summary" class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card border-0 text-center p-3" :class="filters.type === 'expired' ? 'bg-danger-subtle' : 'bg-warning-subtle'">
                <div class="text-muted small">Total Batches</div>
                <div class="fw-bold">{{ summary.total_batches }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 bg-info-subtle text-center p-3">
                <div class="text-muted small">Total Quantity</div>
                <div class="fw-bold">{{ formatMoneyPlain(summary.total_quantity) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 bg-secondary-subtle text-center p-3">
                <div class="text-muted small">Total Value</div>
                <div class="fw-bold">{{ formatMoney(summary.total_value) }}</div>
            </div>
        </div>
    </div>

    <div v-if="filters.type === 'near_expiry' && !rows.length && !loading && hasLoaded" class="alert alert-success d-flex align-items-center gap-2">
        <i class="ti ti-circle-check fs-4"></i>
        No batches expiring in the next {{ filters.days }} days.
    </div>

    <div v-if="filters.type === 'expired' && !rows.length && !loading && hasLoaded" class="alert alert-success d-flex align-items-center gap-2">
        <i class="ti ti-circle-check fs-4"></i>
        No expired batches found.
    </div>

    <div class="card border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th>Code / SKU</th>
                            <th>Warehouse</th>
                            <th>Batch No</th>
                            <th>Lot No</th>
                            <th>Mfg. Date</th>
                            <th>Expiry Date</th>
                            <th class="text-end">Days</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Unit Cost</th>
                            <th class="text-end">Total Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!rows.length && !loading">
                            <td colspan="11" class="text-center text-muted py-4">Click Generate Report to load data.</td>
                        </tr>
                        <tr v-for="(row, idx) in rows" :key="idx" :class="row.days_to_expiry < 0 ? 'table-danger' : row.days_to_expiry <= 7 ? 'table-warning' : ''">
                            <td>{{ row.product_name }}</td>
                            <td>{{ row.product_code }}<br><small class="text-muted">{{ row.sku }}</small></td>
                            <td>{{ row.warehouse }}</td>
                            <td>{{ row.batch_no }}</td>
                            <td>{{ row.lot_no }}</td>
                            <td>{{ row.mfg_date }}</td>
                            <td class="fw-semibold" :class="row.days_to_expiry < 0 ? 'text-danger' : row.days_to_expiry <= 7 ? 'text-warning' : ''">{{ row.expiry_date }}</td>
                            <td class="text-end">
                                <span v-if="row.days_to_expiry < 0" class="badge bg-danger">Expired</span>
                                <span v-else class="fw-semibold" :class="row.days_to_expiry <= 7 ? 'text-warning' : ''">{{ row.days_to_expiry }}d</span>
                            </td>
                            <td class="text-end">{{ formatMoneyPlain(row.remaining_qty) }}</td>
                            <td class="text-end">{{ formatMoney(row.unit_cost) }}</td>
                            <td class="text-end fw-semibold">{{ formatMoney(row.total_value) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import {formatMoney, formatMoneyPlain} from '@/helpers/formatMoney.js';
import {ref} from 'vue';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const rows = ref([]);
const summary = ref(null);
const loading = ref(false);
const hasLoaded = ref(false);

const filters = ref({ type: 'near_expiry', days: 30 });

const exportCsv = () => {
    if (!rows.value.length) { return; }
    const headers = ['Product', 'Code', 'SKU', 'Warehouse', 'Batch No', 'Lot No', 'Mfg Date', 'Expiry Date', 'Days', 'Qty', 'Unit Cost', 'Total Value'];
    const csvRows = rows.value.map(r => [
        r.product_name, r.product_code, r.sku, r.warehouse, r.batch_no, r.lot_no,
        r.mfg_date, r.expiry_date, r.days_to_expiry, r.remaining_qty, r.unit_cost, r.total_value,
    ].map(v => `"${v ?? ''}"`).join(','));
    const csv = [headers.join(','), ...csvRows].join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], {type: 'text/csv'}));
    a.download = 'expiry-stock.csv';
    a.click();
};

const loadReport = async () => {
    loading.value = true;
    try {
        const params = { ...filters.value };
        const res = await apiAdmin('inventory-report/expiry-stock', 'get', params);
        const data = res.data.data;
        rows.value = data.rows || [];
        summary.value = data.summary;
        hasLoaded.value = true;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};
</script>
