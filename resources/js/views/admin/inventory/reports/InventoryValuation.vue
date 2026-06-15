<template>
    <PageHeader title="Inventory Valuation Report" subtitle="Current stock value by product (FIFO costing)" hide-action-buttons />

    <section class="section">
        <div class="row g-3 mb-3">
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-info flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-info-subtle text-info">
                            <i class="ti ti-package fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total Items</p>
                            <h4 class="mb-0">{{ kpi.total_items }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-success flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-success-subtle text-success">
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
                <div class="card border-0 shadow-sm sale-widget widget-warning flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-warning-subtle text-warning">
                            <i class="ti ti-currency-rupee fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total Inventory Value</p>
                            <h4 class="mb-0">{{ formatMoney(kpi.total_value) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <input type="text" class="form-control" v-model="search" placeholder="Search product..." />
                    </div>
                    <div class="col-md-3 d-flex gap-2">
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
                                <th>Product</th>
                                <th>Code</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th>Warehouse</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Unit Cost</th>
                                <th class="text-end">Total Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!filteredRows.length && !loading">
                                <td colspan="8" class="text-center text-muted py-4">Loading inventory valuation...</td>
                            </tr>
                            <tr v-for="row in filteredRows" :key="`${row.sku}-${row.warehouse}`">
                                <td>{{ row.product_name }}</td>
                                <td>{{ row.product_code }}</td>
                                <td>{{ row.sku }}</td>
                                <td>{{ row.category }}</td>
                                <td>{{ row.warehouse }}</td>
                                <td class="text-end">{{ formatMoneyPlain(row.quantity) }}</td>
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
const search = ref('');

const kpi = computed(() => summary.value ?? {total_items: 0, total_quantity: 0, total_value: 0});

const filteredRows = computed(() => {
    if (!search.value) { return rows.value; }
    const s = search.value.toLowerCase();
    return rows.value.filter(r =>
        r.product_name?.toLowerCase().includes(s) ||
        r.product_code?.toLowerCase().includes(s) ||
        r.sku?.toLowerCase().includes(s),
    );
});

const loadReport = async () => {
    loading.value = true;
    try {
        const res = await apiAdmin('account-report/inventory-valuation', 'get');
        rows.value = res.data.data.rows || [];
        summary.value = res.data.data.summary;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const exportCsv = () => {
    if (!rows.value.length) { return; }
    const headers = ['Product', 'Code', 'SKU', 'Category', 'Warehouse', 'Qty', 'Unit Cost', 'Total Value'];
    const csvRows = rows.value.map(r => [
        r.product_name, r.product_code, r.sku, r.category, r.warehouse,
        r.quantity, r.unit_cost, r.total_value,
    ].map(v => `"${v ?? ''}"`).join(','));
    const csv = [headers.join(','), ...csvRows].join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], {type: 'text/csv'}));
    a.download = 'inventory-valuation.csv';
    a.click();
};

onMounted(async () => {
    await loadReport();
});
</script>
