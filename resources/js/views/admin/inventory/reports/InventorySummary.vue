<template>
    <PageHeader hide-action-buttons title="Inventory Summary Report" subtitle="Opening stock, purchases, sales and closing stock with values for the selected period" />

    <section class="section">
        <div class="row g-3 mb-3">
            <div class="col-xl col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-info flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-info-subtle text-info">
                            <i class="ti ti-package fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Opening Value</p>
                            <h4 class="mb-0">{{ formatMoney(kpi.total_opening_value) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-success flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-success-subtle text-success">
                            <i class="ti ti-truck-delivery fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Purchase Value</p>
                            <h4 class="mb-0">{{ formatMoney(kpi.total_purchase_value) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-danger flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-danger-subtle text-danger">
                            <i class="ti ti-shopping-cart fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Sale Qty</p>
                            <h4 class="mb-0">{{ formatMoneyPlain(kpi.total_sale_qty) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-warning flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-warning-subtle text-warning">
                            <i class="ti ti-currency-rupee fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Closing Value</p>
                            <h4 class="mb-0">{{ formatMoney(kpi.total_closing_value) }}</h4>
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
                        <VMultiselect
                            id="category_id"
                            v-model="filters.category_id"
                            :options="categoryOptions"
                            label="Category"
                            placeholder="All Categories"
                        />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Warehouse</label>
                        <VMultiselect
                            id="warehouse_id"
                            v-model="filters.warehouse_id"
                            :options="warehouseStore.optionsTree"
                            :loading="warehouseStore.warehouses.loading"
                            placeholder="All Warehouses"
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
                                <th rowspan="2" class="align-middle">Product</th>
                                <th rowspan="2" class="align-middle">SKU</th>
                                <th rowspan="2" class="align-middle">Category</th>
                                <th colspan="2" class="text-center border-start">Opening Stock</th>
                                <th colspan="2" class="text-center border-start">Purchase</th>
                                <th class="text-center border-start">Purchase Return</th>
                                <th class="text-center border-start">Sale</th>
                                <th class="text-center border-start">Sale Return</th>
                                <th colspan="2" class="text-center border-start">Closing Stock</th>
                            </tr>
                            <tr>
                                <th class="text-end border-start">Qty</th>
                                <th class="text-end">Value</th>
                                <th class="text-end border-start">Qty</th>
                                <th class="text-end">Value</th>
                                <th class="text-end border-start">Qty</th>
                                <th class="text-end border-start">Qty</th>
                                <th class="text-end border-start">Qty</th>
                                <th class="text-end border-start">Qty</th>
                                <th class="text-end">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!rows.length && !loading">
                                <td colspan="11" class="text-center text-muted py-4">Set filters and click Generate to load data.</td>
                            </tr>
                            <tr v-for="(row, idx) in rows" :key="idx">
                                <td>
                                    {{ row.product_name }}
                                    <br><small class="text-muted">{{ row.product_code }}</small>
                                </td>
                                <td class="text-nowrap">{{ row.sku }}</td>
                                <td>{{ row.category }}</td>
                                <td class="text-end border-start">{{ formatMoneyPlain(row.opening_qty) }}</td>
                                <td class="text-end">{{ formatMoney(row.opening_value) }}</td>
                                <td class="text-end border-start">{{ formatMoneyPlain(row.purchase_qty) }}</td>
                                <td class="text-end">{{ formatMoney(row.purchase_value) }}</td>
                                <td class="text-end border-start text-warning fw-medium">{{ formatMoneyPlain(row.purchase_return_qty) }}</td>
                                <td class="text-end border-start text-danger fw-medium">{{ formatMoneyPlain(row.sale_qty) }}</td>
                                <td class="text-end border-start text-success fw-medium">{{ formatMoneyPlain(row.sale_return_qty) }}</td>
                                <td class="text-end border-start fw-semibold">{{ formatMoneyPlain(row.closing_qty) }}</td>
                                <td class="text-end fw-semibold">{{ formatMoney(row.closing_value) }}</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="rows.length" class="table-light fw-semibold">
                            <tr>
                                <td colspan="3">Total</td>
                                <td class="text-end border-start">{{ formatMoneyPlain(totals.opening_qty) }}</td>
                                <td class="text-end">{{ formatMoney(totals.opening_value) }}</td>
                                <td class="text-end border-start">{{ formatMoneyPlain(totals.purchase_qty) }}</td>
                                <td class="text-end">{{ formatMoney(totals.purchase_value) }}</td>
                                <td class="text-end border-start">{{ formatMoneyPlain(totals.purchase_return_qty) }}</td>
                                <td class="text-end border-start">{{ formatMoneyPlain(totals.sale_qty) }}</td>
                                <td class="text-end border-start">{{ formatMoneyPlain(totals.sale_return_qty) }}</td>
                                <td class="text-end border-start">{{ formatMoneyPlain(totals.closing_qty) }}</td>
                                <td class="text-end">{{ formatMoney(totals.closing_value) }}</td>
                            </tr>
                        </tfoot>
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
const categoryOptions = ref([]);

const adminSettingStore = useAdminSettingStore();
const {currentFiscalYear} = storeToRefs(adminSettingStore);

const filters = ref({
    from_date: '',
    to_date: '',
    category_id: '',
    warehouse_id: '',
});

const kpi = computed(() => summary.value ?? {
    total_opening_value: 0,
    total_purchase_value: 0,
    total_sale_qty: 0,
    total_closing_value: 0,
});

const totals = computed(() => ({
    opening_qty: rows.value.reduce((s, r) => s + r.opening_qty, 0),
    opening_value: rows.value.reduce((s, r) => s + r.opening_value, 0),
    purchase_qty: rows.value.reduce((s, r) => s + r.purchase_qty, 0),
    purchase_value: rows.value.reduce((s, r) => s + r.purchase_value, 0),
    purchase_return_qty: rows.value.reduce((s, r) => s + r.purchase_return_qty, 0),
    sale_qty: rows.value.reduce((s, r) => s + r.sale_qty, 0),
    sale_return_qty: rows.value.reduce((s, r) => s + r.sale_return_qty, 0),
    closing_qty: rows.value.reduce((s, r) => s + r.closing_qty, 0),
    closing_value: rows.value.reduce((s, r) => s + r.closing_value, 0),
}));

const loadReport = async (page = 1) => {
    loading.value = true;
    try {
        const params = {...filters.value, page};
        if (page === 1) { params.with_options = 1; }
        Object.keys(params).forEach((k) => { if (params[k] === '' || params[k] === null) { delete params[k]; } });
        const res = await apiAdmin('inventory-report/inventory-summary', 'get', params);
        const data = res.data.data;
        rows.value = data.rows || [];
        summary.value = data.summary;
        pagination.value = data.pagination;
        if (data.filter_options) {
            categoryOptions.value = data.filter_options.category_options || [];
        }
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const exportCsv = () => {
    if (!rows.value.length) { return; }
    const headers = [
        'Product', 'Code', 'SKU', 'Category',
        'Opening Qty', 'Opening Value',
        'Purchase Qty', 'Purchase Value',
        'Purchase Return Qty',
        'Sale Qty',
        'Sale Return Qty',
        'Closing Qty', 'Closing Value',
    ];
    const csvRows = rows.value.map(r => [
        r.product_name, r.product_code, r.sku, r.category,
        r.opening_qty, r.opening_value,
        r.purchase_qty, r.purchase_value,
        r.purchase_return_qty,
        r.sale_qty,
        r.sale_return_qty,
        r.closing_qty, r.closing_value,
    ].map(v => `"${v ?? ''}"`).join(','));
    const csv = [headers.join(','), ...csvRows].join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], {type: 'text/csv'}));
    a.download = 'inventory-summary.csv';
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
