<template>
    <PageHeader title="Reorder Alerts" subtitle="Products at or below minimum stock level" />

    <div class="card border-0 mb-3">
        <div class="card-body">
            <button class="btn btn-primary" @click="loadReport" :disabled="loading">
                <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                Refresh
            </button>
        </div>
    </div>

    <div v-if="rows.length" class="alert alert-warning d-flex align-items-center gap-2">
        <i class="ti ti-alert-triangle fs-4"></i>
        <strong>{{ rows.length }} product(s)</strong> are at or below their minimum stock level and require reordering.
    </div>
    <div v-else-if="!loading" class="alert alert-success d-flex align-items-center gap-2">
        <i class="ti ti-circle-check fs-4"></i>
        All products are above their minimum stock levels.
    </div>

    <div class="card border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th>Code</th>
                            <th>SKU</th>
                            <th>Warehouse</th>
                            <th class="text-end">Current Stock</th>
                            <th class="text-end">Min Stock Level</th>
                            <th class="text-end">Reorder Qty</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!rows.length && !loading">
                            <td colspan="8" class="text-center text-muted py-4">Click Refresh to check reorder alerts.</td>
                        </tr>
                        <tr v-for="row in rows" :key="`${row.variant_id}-${row.warehouse_id}`">
                            <td>{{ row.product_name }}</td>
                            <td>{{ row.product_code }}</td>
                            <td>{{ row.sku }}</td>
                            <td>{{ row.warehouse_name }}</td>
                            <td class="text-end fw-semibold" :class="row.current_stock <= 0 ? 'text-danger' : 'text-warning'">
                                {{ fmt(row.current_stock) }}
                            </td>
                            <td class="text-end">{{ fmt(row.min_stock_level) }}</td>
                            <td class="text-end">{{ fmt(row.reorder_quantity) }}</td>
                            <td>
                                <span class="badge" :class="row.current_stock <= 0 ? 'bg-danger' : 'bg-warning text-dark'">
                                    {{ row.current_stock <= 0 ? 'Out of Stock' : 'Low Stock' }}
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
import {ref, onMounted} from 'vue';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const rows = ref([]);
const loading = ref(false);

const loadReport = async () => {
    loading.value = true;
    try {
        const res = await apiAdmin('account-report/reorder-alerts', 'get');
        rows.value = res.data.data.rows || [];
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const fmt = (val) => Number(val || 0).toLocaleString('en-NP', { minimumFractionDigits: 2 });

onMounted(() => { loadReport(); });
</script>
