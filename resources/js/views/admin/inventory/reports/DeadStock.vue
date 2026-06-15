<template>
    <PageHeader hide-action-buttons title="Dead Stock Report" subtitle="Products with no stock movement in a selected number of days" />

    <div class="card border-0 mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">No movement in last</label>
                    <div class="input-group">
                        <input type="number" class="form-control" v-model="filters.days" min="1" max="999" />
                        <span class="input-group-text">days</span>
                    </div>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-outline-secondary" @click="filters.days = 30" :class="filters.days == 30 ? 'active' : ''">30d</button>
                    <button class="btn btn-outline-secondary" @click="filters.days = 60" :class="filters.days == 60 ? 'active' : ''">60d</button>
                    <button class="btn btn-outline-secondary" @click="filters.days = 90" :class="filters.days == 90 ? 'active' : ''">90d</button>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary" @click="loadReport" :disabled="loading">
                        <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                        Generate Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div v-if="summary && hasLoaded" class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card border-0 bg-warning-subtle text-center p-3">
                <div class="text-muted small">Dead Stock Items</div>
                <div class="fw-bold text-warning">{{ summary.total_items }}</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 bg-secondary-subtle text-center p-3">
                <div class="text-muted small">Total Idle Quantity</div>
                <div class="fw-bold">{{ formatMoneyPlain(summary.total_quantity) }}</div>
            </div>
        </div>
    </div>

    <div v-if="hasLoaded && !rows.length && !loading" class="alert alert-success d-flex align-items-center gap-2">
        <i class="ti ti-circle-check fs-4"></i>
        No dead stock found — all products have had movement in the last {{ filters.days }} days.
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
                            <th>Category</th>
                            <th>Warehouse</th>
                            <th class="text-end">Current Qty</th>
                            <th>Last Movement</th>
                            <th class="text-end">Days Idle</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!rows.length && !loading">
                            <td colspan="8" class="text-center text-muted py-4">Click Generate Report to load data.</td>
                        </tr>
                        <tr v-for="(row, idx) in rows" :key="idx">
                            <td>{{ row.product_name }}</td>
                            <td>{{ row.product_code }}</td>
                            <td>{{ row.sku }}</td>
                            <td>{{ row.category }}</td>
                            <td>{{ row.warehouse }}</td>
                            <td class="text-end fw-semibold">{{ formatMoneyPlain(row.quantity) }}</td>
                            <td class="text-muted">{{ row.last_movement_date ?? 'Never' }}</td>
                            <td class="text-end">
                                <span class="badge" :class="idleBadge(row.days_since_movement)">
                                    {{ row.days_since_movement != null ? row.days_since_movement + 'd' : 'Never moved' }}
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
import {formatMoneyPlain} from '@/helpers/formatMoney.js';
import {ref} from 'vue';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const rows = ref([]);
const summary = ref(null);
const loading = ref(false);
const hasLoaded = ref(false);

const filters = ref({ days: 90 });

const idleBadge = (days) => {
    if (days == null) { return 'bg-danger'; }
    if (days > 180) { return 'bg-danger'; }
    if (days > 90) { return 'bg-warning text-dark'; }
    return 'bg-secondary';
};

const loadReport = async () => {
    loading.value = true;
    try {
        const res = await apiAdmin('inventory-report/dead-stock', 'get', { days: filters.value.days });
        const data = res.data.data;
        rows.value = data.rows || [];
        summary.value = data.summary;
        hasLoaded.value = true;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};
</script>
