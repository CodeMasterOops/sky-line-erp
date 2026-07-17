<template>
    <PageHeader hide-action-buttons title="Stock Ledger Report" subtitle="Per-product running balance of all IN/OUT movements" />

    <section class="section">
        <div class="row g-3 mb-3">
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-info flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-info-subtle text-info">
                            <i class="ti ti-wallet fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Opening Balance</p>
                            <h4 class="mb-0">{{ reportData ? formatMoneyPlain(reportData.opening_balance) : '—' }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-success flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-success-subtle text-success">
                            <i class="ti ti-arrow-down fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total IN</p>
                            <h4 class="mb-0">{{ reportData ? formatMoneyPlain(reportData.summary.total_in) : '—' }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-danger flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-danger-subtle text-danger">
                            <i class="ti ti-arrow-up fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total OUT</p>
                            <h4 class="mb-0">{{ reportData ? formatMoneyPlain(reportData.summary.total_out) : '—' }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-warning flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-warning-subtle text-warning">
                            <i class="ti ti-scale fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Closing Balance</p>
                            <h4 class="mb-0">{{ reportData ? formatMoneyPlain(reportData.closing_balance) : '—' }}</h4>
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
                            id="product_variant_id"
                            v-model="filters.product_variant_id"
                            :options="productOptions"
                            label="Product"
                            placeholder="— Select Product —"
                            required
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
                    <div class="col-md-2">
                        <VDatepicker id="from_date" label="From Date" v-model="filters.from_date" />
                    </div>
                    <div class="col-md-2">
                        <VDatepicker id="to_date" label="To Date" v-model="filters.to_date" />
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button class="btn btn-success flex-grow-1" @click="loadReport" :disabled="loading || !filters.product_variant_id">
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
                                    {{ filters.product_variant_id ? 'Click Generate to load ledger.' : 'Select a product to get started.' }}
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
    </section>
</template>

<script setup>
import {formatMoney, formatMoneyPlain} from '@/helpers/formatMoney.js';
import {ref, onMounted} from 'vue';
import {storeToRefs} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import {useAdminSettingStore} from '@/stores/admin/settings/admin-setting.js';
import {useWarehouseStore} from '@/stores/admin/inventory/warehouse.js';
import VMultiselect from '@/components/base/VMultiselect.vue';

const warehouseStore = useWarehouseStore();

const rows = ref([]);
const reportData = ref(null);
const loading = ref(false);
const productOptions = ref([]);

const adminSettingStore = useAdminSettingStore();
const {currentFiscalYear} = storeToRefs(adminSettingStore);

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
        const params = {...filters.value};
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
    try {
        warehouseStore.getWarehouses();
        const res = await apiAdmin('inventory-report/stock-ledger', 'get', {with_options: 1});
        const opts = res.data.data.filter_options || {};
        productOptions.value = opts.product_variant_options || [];
    } catch { /* ignore */ }
});
</script>
