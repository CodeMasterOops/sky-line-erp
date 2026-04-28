<template>
    <PageHeader title="Reorder Alerts" subtitle="Products at or below minimum stock level" />

    <div class="card border-0 mb-3">
        <div class="card-body d-flex align-items-center gap-3 flex-wrap">
            <button class="btn btn-primary" @click="loadReport" :disabled="loading">
                <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                Refresh
            </button>
            <button
                v-if="rows.length"
                class="btn btn-success"
                @click="generateAllPos"
                :disabled="generatingAll">
                <span v-if="generatingAll" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="ti ti-file-plus me-1"></i>
                Generate All POs ({{ rows.length }})
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
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!rows.length && !loading">
                            <td colspan="9" class="text-center text-muted py-4">Click Refresh to check reorder alerts.</td>
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
                            <td>
                                <button
                                    class="btn btn-xs btn-outline-primary text-nowrap"
                                    @click="generatePo(row)"
                                    :disabled="generatingRow[`${row.variant_id}-${row.warehouse_id}`]">
                                    <span
                                        v-if="generatingRow[`${row.variant_id}-${row.warehouse_id}`]"
                                        class="spinner-border spinner-border-sm"></span>
                                    <i v-else class="ti ti-file-plus"></i>
                                    Generate PO
                                </button>
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
import {toast} from '@/helpers/toast.js';
const rows = ref([]);
const loading = ref(false);
const generatingAll = ref(false);
const generatingRow = ref({});

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

const buildPoPayload = (row) => ({
    order_date: new Date().toISOString().split('T')[0],
    party_id: null,
    status: 'draft',
    remarks: `Auto-generated from reorder alert for ${row.product_name}`,
    items: [
        {
            product_variant_id: row.variant_id,
            warehouse_id: row.warehouse_id,
            quantity: Math.ceil(row.reorder_quantity) || 1,
            rate: 0,
        },
    ],
});

const generatePo = async (row) => {
    const key = `${row.variant_id}-${row.warehouse_id}`;
    generatingRow.value = { ...generatingRow.value, [key]: true };
    try {
        await apiAdmin('purchase-order', 'post', buildPoPayload(row));
        toast('success', `Draft PO created for ${row.product_name}. Open Purchase Orders to review.`);
    } catch (e) {
        showErrors(e);
    } finally {
        generatingRow.value = { ...generatingRow.value, [key]: false };
    }
};

const generateAllPos = async () => {
    generatingAll.value = true;
    try {
        let success = 0;
        for (const row of rows.value) {
            try {
                await apiAdmin('purchase-order', 'post', buildPoPayload(row));
                success++;
            } catch { /* continue for remaining rows */ }
        }
        toast('success', `Created ${success} draft PO(s). Open Purchase Orders to review.`);
    } finally {
        generatingAll.value = false;
    }
};

const fmt = (val) => Number(val || 0).toLocaleString('en-NP', { minimumFractionDigits: 2 });

onMounted(() => { loadReport(); });
</script>
