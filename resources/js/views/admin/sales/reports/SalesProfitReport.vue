<template>
    <PageHeader hide-action-buttons title="Sales Profit Report" subtitle="Product-level gross profit and margin based on sales price vs purchase cost" />

    <section class="section">
        <div class="row g-3 mb-3">
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-info flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-info-subtle text-info">
                            <i class="ti ti-currency-rupee fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Net Revenue</p>
                            <h4 class="mb-0">{{ formatMoney(kpi.net_revenue) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-danger flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-danger-subtle text-danger">
                            <i class="ti ti-package fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total Cost</p>
                            <h4 class="mb-0">{{ formatMoney(kpi.total_cost) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-success flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-success-subtle text-success">
                            <i class="ti ti-trending-up fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Gross Profit</p>
                            <h4 class="mb-0">{{ formatMoney(kpi.gross_profit) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-warning flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-warning-subtle text-warning">
                            <i class="ti ti-percentage fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Margin %</p>
                            <h4 class="mb-0">{{ kpi.margin_pct }}%</h4>
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
                    <div class="col-md-2">
                        <label class="form-label">Customer</label>
                        <select class="form-select" v-model="filters.party_id">
                            <option value="">All</option>
                            <option v-for="p in partyOptions" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Category</label>
                        <select class="form-select" v-model="filters.category_id">
                            <option value="">All</option>
                            <option v-for="c in categoryOptions" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-success w-100" @click="loadReport" :disabled="loading">
                            {{ loading ? 'Generating...' : 'Generate' }}
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
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Net Revenue</th>
                                <th class="text-end">Cost</th>
                                <th class="text-end">Profit</th>
                                <th class="text-end">Margin %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!rows.length && !loading">
                                <td colspan="8" class="text-center text-muted py-5">Set filters and click Generate to load data.</td>
                            </tr>
                            <tr v-for="(row, idx) in rows" :key="idx">
                                <td>
                                    {{ row.product_name }}
                                    <br><small class="text-muted">{{ row.product_code }}</small>
                                </td>
                                <td class="text-muted small">{{ row.sku }}</td>
                                <td class="text-muted small">{{ row.category_name }}</td>
                                <td class="text-end">{{ formatMoneyPlain(row.total_qty) }}</td>
                                <td class="text-end">{{ formatMoney(row.net_revenue) }}</td>
                                <td class="text-end text-danger">{{ formatMoney(row.total_cost) }}</td>
                                <td class="text-end fw-semibold" :class="row.gross_profit >= 0 ? 'text-success' : 'text-danger'">{{ formatMoney(row.gross_profit) }}</td>
                                <td class="text-end fw-semibold" :class="row.margin_pct >= 0 ? 'text-primary' : 'text-danger'">{{ row.margin_pct }}%</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="summary && rows.length" class="table-light">
                            <tr class="fw-bold">
                                <td colspan="3">Total</td>
                                <td class="text-end">{{ formatMoneyPlain(summary.total_qty) }}</td>
                                <td class="text-end">{{ formatMoney(summary.net_revenue) }}</td>
                                <td class="text-end text-danger">{{ formatMoney(summary.total_cost) }}</td>
                                <td class="text-end text-success">{{ formatMoney(summary.gross_profit) }}</td>
                                <td class="text-end text-primary">{{ summary.margin_pct }}%</td>
                            </tr>
                        </tfoot>
                    </table>
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

const rows = ref([]);
const summary = ref(null);
const loading = ref(false);
const partyOptions = ref([]);
const categoryOptions = ref([]);

const adminSettingStore = useAdminSettingStore();
const {currentFiscalYear} = storeToRefs(adminSettingStore);

const filters = ref({ from_date: '', to_date: '', party_id: '', category_id: '' });

const kpi = computed(() => summary.value ?? { net_revenue: 0, total_cost: 0, gross_profit: 0, margin_pct: 0 });

const loadReport = async () => {
    loading.value = true;
    try {
        const params = {...filters.value};
        Object.keys(params).forEach((k) => { if (!params[k]) { delete params[k]; } });
        const res = await apiAdmin('sales-report/sales-profit', 'get', params);
        const data = res.data.data;
        rows.value = data.rows || [];
        summary.value = data.summary;
        partyOptions.value = data.party_options || partyOptions.value;
        categoryOptions.value = data.category_options || categoryOptions.value;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const exportCsv = () => {
    if (!rows.value.length) { return; }
    const headers = ['Product', 'Code', 'SKU', 'Category', 'Qty', 'Net Revenue', 'Cost', 'Gross Profit', 'Margin %'];
    const csvRows = rows.value.map(r => [r.product_name, r.product_code, r.sku, r.category_name, r.total_qty, r.net_revenue, r.total_cost, r.gross_profit, r.margin_pct].map(v => `"${v ?? ''}"`).join(','));
    const csv = [headers.join(','), ...csvRows].join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], {type: 'text/csv'}));
    a.download = 'sales-profit.csv';
    a.click();
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
    await loadReport();
});
</script>
