<template>
    <PageHeader hide-action-buttons title="Stock Ledger Report" subtitle="Per-product running balance of all IN/OUT movements" />

    <div class="card border-0 mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Product <span class="text-danger">*</span></label>
                    <select class="form-select" v-model="filters.product_variant_id">
                        <option value="">— Select Product —</option>
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
                <div class="col-md-2">
                    <VDatepicker id="from_date" label="From Date" v-model="filters.from_date" />
                </div>
                <div class="col-md-2">
                    <VDatepicker id="to_date" label="To Date" v-model="filters.to_date" />
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1" @click="loadReport" :disabled="loading || !filters.product_variant_id">
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

    <div v-if="reportData" class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card border-0 bg-secondary-subtle text-center p-3">
                <div class="text-muted small">Opening Balance</div>
                <div class="fw-bold">{{ formatMoneyPlain(reportData.opening_balance) }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-success-subtle text-center p-3">
                <div class="text-muted small">Total IN</div>
                <div class="fw-bold text-success">{{ formatMoneyPlain(reportData.summary.total_in) }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-danger-subtle text-center p-3">
                <div class="text-muted small">Total OUT</div>
                <div class="fw-bold text-danger">{{ formatMoneyPlain(reportData.summary.total_out) }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-primary-subtle text-center p-3">
                <div class="text-muted small">Closing Balance</div>
                <div class="fw-bold text-primary">{{ formatMoneyPlain(reportData.closing_balance) }}</div>
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
                            <th>Warehouse</th>
                            <th>Transaction Type</th>
                            <th class="text-end text-success">IN Qty</th>
                            <th class="text-end text-danger">OUT Qty</th>
                            <th class="text-end">Balance</th>
                            <th class="text-end">Unit Cost</th>
                            <th class="text-end">Total Cost</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!rows.length && !loading">
                            <td colspan="9" class="text-center text-muted py-4">
                                {{ filters.product_variant_id ? 'Click Generate Report to load ledger.' : 'Select a product to get started.' }}
                            </td>
                        </tr>
                        <tr v-for="(row, idx) in rows" :key="idx">
                            <td class="text-nowrap">{{ row.date }}</td>
                            <td>{{ row.warehouse }}</td>
                            <td><span class="badge bg-secondary-subtle text-secondary">{{ row.type }}</span></td>
                            <td class="text-end text-success fw-semibold">{{ row.in_qty > 0 ? formatMoneyPlain(row.in_qty) : '-' }}</td>
                            <td class="text-end text-danger fw-semibold">{{ row.out_qty > 0 ? formatMoneyPlain(row.out_qty) : '-' }}</td>
                            <td class="text-end fw-bold">{{ formatMoneyPlain(row.balance) }}</td>
                            <td class="text-end">{{ formatMoney(row.unit_cost) }}</td>
                            <td class="text-end">{{ formatMoney(row.total_cost) }}</td>
                            <td class="text-muted small">{{ row.remarks }}</td>
                        </tr>
                    </tbody>
                </table>
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
const reportData = ref(null);
const loading = ref(false);
const productOptions = ref([]);
const warehouseOptions = ref([]);

const adminSettingStore = useAdminSettingStore();
const { currentFiscalYear } = storeToRefs(adminSettingStore);

const filters = ref({
    product_variant_id: '',
    warehouse_id: '',
    from_date: '',
    to_date: '',
});

const exportCsv = () => {
    if (!rows.value.length) { return; }
    const headers = ['Date', 'Warehouse', 'Type', 'IN Qty', 'OUT Qty', 'Balance', 'Unit Cost', 'Total Cost', 'Remarks'];
    const csvRows = rows.value.map(r => [
        r.date, r.warehouse, r.type, r.in_qty, r.out_qty, r.balance, r.unit_cost, r.total_cost, r.remarks,
    ].map(v => `"${v ?? ''}"`).join(','));
    const csv = [headers.join(','), ...csvRows].join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], {type: 'text/csv'}));
    a.download = 'stock-ledger.csv';
    a.click();
};

const loadReport = async () => {
    if (!filters.value.product_variant_id) { return; }
    loading.value = true;
    try {
        const params = { ...filters.value };
        Object.keys(params).forEach((k) => { if (params[k] === '' || params[k] === null) { delete params[k]; } });
        const res = await apiAdmin('inventory-report/stock-ledger', 'get', params);
        const data = res.data.data;
        rows.value = data.rows || [];
        reportData.value = data;
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
    // Load filter options without triggering a full data query
    try {
        const res = await apiAdmin('inventory-report/stock-ledger', 'get', { with_options: 1 });
        const opts = res.data.data.filter_options || {};
        productOptions.value = opts.product_variant_options || [];
        warehouseOptions.value = opts.warehouse_options || [];
    } catch { /* ignore */ }
});
</script>
