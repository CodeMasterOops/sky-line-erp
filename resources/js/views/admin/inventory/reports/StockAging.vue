<template>
    <PageHeader hide-action-buttons title="Stock Aging Report" subtitle="Identify slow-moving and dead stock by FIFO layer age" />

    <section class="section">
        <div class="row g-3 mb-3">
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-success flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-success-subtle text-success">
                            <i class="ti ti-circle-check fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">&lt; 30 Days</p>
                            <h4 class="mb-0">{{ formatMoney(kpi.under_30) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-warning flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-warning-subtle text-warning">
                            <i class="ti ti-clock fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">31–90 Days</p>
                            <h4 class="mb-0">{{ formatMoney(kpi['31_90']) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-danger flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-danger-subtle text-danger">
                            <i class="ti ti-alert-circle fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">91–180 Days</p>
                            <h4 class="mb-0">{{ formatMoney(kpi['91_180']) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-info flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-info-subtle text-info">
                            <i class="ti ti-trending-down fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">&gt; 180 Days</p>
                            <h4 class="mb-0">{{ formatMoney(kpi.over_180) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body d-flex align-items-center gap-2">
                <button class="btn btn-success" @click="loadReport" :disabled="loading">
                    <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                    Generate
                </button>
                <button class="btn btn-outline-secondary" @click="exportCsv" :disabled="!rows.length" title="Export CSV">
                    <i class="ti ti-file-export"></i>
                </button>
            </div>
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
                                <th class="text-end">Qty</th>
                                <th class="text-end">Unit Cost</th>
                                <th class="text-end">Value</th>
                                <th class="text-end">Age (days)</th>
                                <th>Bucket</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!rows.length && !loading">
                                <td colspan="8" class="text-center text-muted py-4">Click Generate to load data.</td>
                            </tr>
                            <tr v-for="(row, idx) in rows" :key="idx">
                                <td>{{ row.product_name }}</td>
                                <td>{{ row.product_code }}<br><small class="text-muted">{{ row.sku }}</small></td>
                                <td>{{ row.warehouse }}</td>
                                <td class="text-end">{{ formatMoneyPlain(row.quantity) }}</td>
                                <td class="text-end">{{ formatMoney(row.unit_cost) }}</td>
                                <td class="text-end fw-semibold">{{ formatMoney(row.total_value) }}</td>
                                <td class="text-end" :class="row.age_days > 90 ? 'text-danger fw-semibold' : ''">{{ row.age_days }}</td>
                                <td>
                                    <span class="badge" :class="bucketBadge[row.age_bucket] || 'bg-secondary'">
                                        {{ bucketLabels[row.age_bucket] || row.age_bucket }}
                                    </span>
                                </td>
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
const buckets = ref(null);
const loading = ref(false);

const bucketLabels = {under_30: '< 30 days', '31_90': '31-90 days', '91_180': '91-180 days', over_180: '> 180 days', total: 'Total'};
const bucketBadge = {under_30: 'bg-success', '31_90': 'bg-warning text-dark', '91_180': 'bg-danger', over_180: 'bg-danger'};

const kpi = computed(() => buckets.value ?? {under_30: 0, '31_90': 0, '91_180': 0, over_180: 0});

const exportCsv = () => {
    if (!rows.value.length) { return; }
    const headers = ['Product', 'Code', 'SKU', 'Warehouse', 'Qty', 'Unit Cost', 'Value', 'Age (days)', 'Bucket'];
    const csvRows = rows.value.map(r => [
        r.product_name, r.product_code, r.sku, r.warehouse,
        r.quantity, r.unit_cost, r.total_value, r.age_days, r.age_bucket,
    ].map(v => `"${v ?? ''}"`).join(','));
    const csv = [headers.join(','), ...csvRows].join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], {type: 'text/csv'}));
    a.download = 'stock-aging.csv';
    a.click();
};

const loadReport = async () => {
    loading.value = true;
    try {
        const res = await apiAdmin('account-report/stock-aging', 'get');
        rows.value = res.data.data.rows || [];
        buckets.value = res.data.data.buckets;
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
