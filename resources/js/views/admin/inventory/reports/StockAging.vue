<template>
    <PageHeader hide-action-buttons title="Stock Aging Report" subtitle="Identify slow-moving and dead stock by FIFO layer age" />

    <div class="card border-0 mb-3">
        <div class="card-body d-flex align-items-center gap-2">
            <button class="btn btn-primary" @click="loadReport" :disabled="loading">
                <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                Generate Report
            </button>
            <button class="btn btn-outline-secondary" @click="exportCsv" :disabled="!rows.length">
                <i class="ti ti-file-export me-1"></i> Export CSV
            </button>
        </div>
    </div>

    <div v-if="buckets" class="row g-3 mb-3">
        <div class="col" v-for="(label, key) in bucketLabels" :key="key">
            <div class="card border-0 p-3 text-center" :class="bucketBg[key]">
                <div class="text-muted small">{{ label }}</div>
                <div class="fw-bold">{{ formatMoney(buckets[key]) }}</div>
            </div>
        </div>
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
                            <th class="text-end">Qty</th>
                            <th class="text-end">Unit Cost</th>
                            <th class="text-end">Value</th>
                            <th class="text-end">Age (days)</th>
                            <th>Bucket</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!rows.length && !loading">
                            <td colspan="8" class="text-center text-muted py-4">Click Generate Report to load data.</td>
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
</template>

<script setup>
import {formatMoney, formatMoneyPlain} from '@/helpers/formatMoney.js';
import {ref} from 'vue';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const rows = ref([]);
const buckets = ref(null);
const loading = ref(false);

const bucketLabels = { under_30: '< 30 days', '31_90': '31-90 days', '91_180': '91-180 days', over_180: '> 180 days', total: 'Total' };
const bucketBg = { under_30: 'bg-success-subtle', '31_90': 'bg-warning-subtle', '91_180': 'bg-danger-subtle', over_180: 'bg-danger', total: 'bg-secondary-subtle' };
const bucketBadge = { under_30: 'bg-success', '31_90': 'bg-warning text-dark', '91_180': 'bg-danger', over_180: 'bg-danger' };

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

</script>
