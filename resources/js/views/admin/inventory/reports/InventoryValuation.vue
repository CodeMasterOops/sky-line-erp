<template>
    <PageHeader title="Inventory Valuation Report" subtitle="Current stock value by product (FIFO costing)" />

    <div class="card border-0 mb-3">
        <div class="card-body pb-1">
            <div class="row align-items-end">
                <div class="col-md-3">
                    <button class="btn btn-primary" @click="loadReport" :disabled="loading">
                        <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                        Generate Report
                    </button>
                </div>
                <div class="col-md-3 ms-auto">
                    <button class="btn btn-outline-secondary" @click="exportCsv" :disabled="!rows.length">
                        <i class="ti ti-file-export me-1"></i> Export CSV
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div v-if="summary" class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card border-0 bg-primary-subtle text-center p-3">
                <div class="text-muted small">Total Items</div>
                <div class="fw-bold fs-5">{{ summary.total_items }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 bg-info-subtle text-center p-3">
                <div class="text-muted small">Total Quantity</div>
                <div class="fw-bold fs-5">{{ fmt(summary.total_quantity) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 bg-success-subtle text-center p-3">
                <div class="text-muted small">Total Inventory Value</div>
                <div class="fw-bold fs-5 text-success">NPR {{ fmt(summary.total_value) }}</div>
            </div>
        </div>
    </div>

    <div class="card border-0">
        <div class="card-body">
            <div class="mb-3">
                <input type="text" class="form-control w-auto" v-model="search" placeholder="Search product..." />
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
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
                            <td colspan="8" class="text-center text-muted py-4">Click "Generate Report" to load inventory valuation.</td>
                        </tr>
                        <tr v-for="row in filteredRows" :key="`${row.sku}-${row.warehouse}`">
                            <td>{{ row.product_name }}</td>
                            <td>{{ row.product_code }}</td>
                            <td>{{ row.sku }}</td>
                            <td>{{ row.category }}</td>
                            <td>{{ row.warehouse }}</td>
                            <td class="text-end">{{ fmt(row.quantity) }}</td>
                            <td class="text-end">{{ fmt(row.unit_cost) }}</td>
                            <td class="text-end fw-semibold">NPR {{ fmt(row.total_value) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import {ref, computed} from 'vue';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const rows = ref([]);
const summary = ref(null);
const loading = ref(false);
const search = ref('');

const filteredRows = computed(() => {
    if (!search.value) return rows.value;
    const s = search.value.toLowerCase();
    return rows.value.filter(r =>
        r.product_name?.toLowerCase().includes(s) ||
        r.product_code?.toLowerCase().includes(s) ||
        r.sku?.toLowerCase().includes(s)
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

const fmt = (val) => Number(val || 0).toLocaleString('en-NP', { minimumFractionDigits: 2 });

const exportCsv = () => {
    if (!rows.value.length) return;
    const headers = ['Product', 'Code', 'SKU', 'Category', 'Warehouse', 'Qty', 'Unit Cost', 'Total Value'];
    const csvRows = rows.value.map(r => [
        r.product_name, r.product_code, r.sku, r.category, r.warehouse,
        r.quantity, r.unit_cost, r.total_value
    ].map(v => `"${v ?? ''}"`).join(','));
    const csv = [headers.join(','), ...csvRows].join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'inventory-valuation.csv';
    a.click();
};
</script>
