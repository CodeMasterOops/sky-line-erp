<template>
    <PageHeader hide-action-buttons title="Dead Stock Report" subtitle="Products with no stock movement in a selected number of days" />

    <section class="section">
        <div class="row g-3 mb-3">
            <div class="col-xl-6 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-warning flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-warning-subtle text-warning">
                            <i class="ti ti-box-off fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Dead Stock Items</p>
                            <h4 class="mb-0">{{ kpi.total_items }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-info flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-info-subtle text-info">
                            <i class="ti ti-stack fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total Idle Quantity</p>
                            <h4 class="mb-0">{{ formatMoneyPlain(kpi.total_quantity) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
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
                    <div class="col-md-2 d-flex gap-2">
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

        <div v-if="hasLoaded && !rows.length && !loading" class="alert alert-success d-flex align-items-center gap-2">
            <i class="ti ti-circle-check fs-4"></i>
            No dead stock found — all products have had movement in the last {{ filters.days }} days.
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
                                <th class="text-end">Current Qty</th>
                                <th class="text-end">Held</th>
                                <th>Last Movement</th>
                                <th class="text-end">Days Idle</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!rows.length && !loading">
                                <td colspan="9" class="text-center text-muted py-4">Click Generate to load data.</td>
                            </tr>
                            <tr v-for="(row, idx) in rows" :key="idx">
                                <td>{{ row.product_name }}</td>
                                <td>{{ row.product_code }}</td>
                                <td>{{ row.sku }}</td>
                                <td>{{ row.category }}</td>
                                <td>{{ row.warehouse }}</td>
                                <td class="text-end fw-semibold">{{ formatMoneyPlain(row.quantity) }}</td>
                                <td class="text-end" :class="row.held > 0 ? 'text-warning fw-semibold' : 'text-muted'">{{ formatMoneyPlain(row.held) }}</td>
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
    </section>
</template>

<script setup>
import {formatMoneyPlain} from '@/helpers/formatMoney.js';
import {ref, computed, onMounted} from 'vue';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const rows = ref([]);
const summary = ref(null);
const loading = ref(false);
const hasLoaded = ref(false);

const filters = ref({days: 90});

const kpi = computed(() => summary.value ?? {total_items: 0, total_quantity: 0});

const idleBadge = (days) => {
    if (days == null) { return 'bg-danger'; }
    if (days > 180) { return 'bg-danger'; }
    if (days > 90) { return 'bg-warning text-dark'; }
    return 'bg-secondary';
};

const exportCsv = () => {
    if (!rows.value.length) { return; }
    const headers = ['Product', 'Code', 'SKU', 'Category', 'Warehouse', 'Current Qty', 'Held', 'Last Movement', 'Days Idle'];
    const csvRows = rows.value.map(r => [
        r.product_name, r.product_code, r.sku, r.category, r.warehouse,
        r.quantity, r.held, r.last_movement_date ?? 'Never', r.days_since_movement ?? 'Never moved',
    ].map(v => `"${v ?? ''}"`).join(','));
    const csv = [headers.join(','), ...csvRows].join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], {type: 'text/csv'}));
    a.download = 'dead-stock.csv';
    a.click();
};

const loadReport = async () => {
    loading.value = true;
    try {
        const res = await apiAdmin('inventory-report/dead-stock', 'get', {days: filters.value.days});
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

onMounted(async () => {
    await loadReport();
});
</script>
