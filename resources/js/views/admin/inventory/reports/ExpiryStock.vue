<template>
    <PageHeader hide-action-buttons title="Expiry Stock Report" subtitle="Batch-level expiry tracking — near expiry and expired items" />

    <section class="section">
        <div class="row g-3 mb-3">
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-warning flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-warning-subtle text-warning">
                            <i class="ti ti-clock fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total Batches</p>
                            <h4 class="mb-0">{{ kpi.total_batches }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-info flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-info-subtle text-info">
                            <i class="ti ti-stack fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total Quantity</p>
                            <h4 class="mb-0">{{ formatMoneyPlain(kpi.total_quantity) }}</h4>
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
                        <VMultiselect
                            id="type"
                            v-model="filters.type"
                            :options="reportTypeOptions"
                            label="Report Type"
                        />
                    </div>
                    <div v-if="filters.type === 'near_expiry'" class="col-md-2">
                        <label class="form-label">Days Ahead</label>
                        <input type="number" class="form-control" v-model="filters.days" min="1" max="365" />
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

        <div v-if="filters.type === 'near_expiry' && !rows.length && !loading && hasLoaded" class="alert alert-success d-flex align-items-center gap-2">
            <i class="ti ti-circle-check fs-4"></i>
            No batches expiring in the next {{ filters.days }} days.
        </div>

        <div v-if="filters.type === 'expired' && !rows.length && !loading && hasLoaded" class="alert alert-success d-flex align-items-center gap-2">
            <i class="ti ti-circle-check fs-4"></i>
            No expired batches found.
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
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
                                <td colspan="11" class="text-center text-muted py-4">Click Generate to load data.</td>
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
    </section>
</template>

<script setup>
import {formatMoney, formatMoneyPlain} from '@/helpers/formatMoney.js';
import {ref, computed, onMounted} from 'vue';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const rows = ref([]);
const summary = ref(null);
const loading = ref(false);
const hasLoaded = ref(false);

const reportTypeOptions = [
    { id: 'near_expiry', name: 'Near Expiry' },
    { id: 'expired', name: 'Expired' },
];

const filters = ref({type: 'near_expiry', days: 30});

const kpi = computed(() => summary.value ?? {total_batches: 0, total_quantity: 0, total_value: 0});

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
        const params = {...filters.value};
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

onMounted(async () => {
    await loadReport();
});
</script>
