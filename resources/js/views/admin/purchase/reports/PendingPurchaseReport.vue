<template>
    <PageHeader hide-action-buttons title="Pending Purchase Report" subtitle="Approved purchase orders not yet fully received" />

    <section class="section">
        <div class="row g-3 mb-3">
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-warning flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-warning-subtle text-warning">
                            <i class="ti ti-clock fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Pending Orders</p>
                            <h4 class="mb-0">{{ kpi.total_orders }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-info flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-info-subtle text-info">
                            <i class="ti ti-package fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total Ordered Qty</p>
                            <h4 class="mb-0">{{ kpi.total_ordered_qty }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-danger flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-danger-subtle text-danger">
                            <i class="ti ti-package-off fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total Pending Qty</p>
                            <h4 class="mb-0">{{ kpi.total_pending_qty }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Supplier</label>
                        <select class="form-select" v-model="filters.party_id">
                            <option value="">All Suppliers</option>
                            <option v-for="p in partyOptions" :key="p.id" :value="p.id">{{ p.name }}</option>
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
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Order #</th>
                                <th>Order Date</th>
                                <th>Supplier</th>
                                <th class="text-end">Ordered Qty</th>
                                <th class="text-end">Received Qty</th>
                                <th class="text-end">Pending Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading">
                                <td colspan="7" class="text-center py-4">
                                    <div class="spinner-border spinner-border-sm me-2"></div>Loading...
                                </td>
                            </tr>
                            <tr v-else-if="!rows.length">
                                <td colspan="7" class="text-center text-muted py-4">No pending orders found.</td>
                            </tr>
                            <tr v-for="(row, i) in rows" :key="i">
                                <td>{{ i + 1 }}</td>
                                <td>{{ row.order_no }}</td>
                                <td>{{ row.order_date }}</td>
                                <td>{{ row.party_name }}</td>
                                <td class="text-end">{{ row.ordered_qty }}</td>
                                <td class="text-end text-success">{{ row.received_qty }}</td>
                                <td class="text-end fw-semibold text-danger">{{ row.pending_qty }}</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="rows.length" class="table-light fw-semibold">
                            <tr>
                                <td colspan="4">Total</td>
                                <td class="text-end">{{ kpi.total_ordered_qty }}</td>
                                <td class="text-end">—</td>
                                <td class="text-end">{{ kpi.total_pending_qty }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import {ref, computed, onMounted} from 'vue';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const reportData = ref(null);
const loading = ref(false);
const partyOptions = ref([]);

const filters = ref({party_id: ''});

const rows = computed(() => reportData.value?.rows ?? []);
const kpi = computed(() => reportData.value?.summary ?? {total_orders: 0, total_ordered_qty: 0, total_pending_qty: 0});

const loadReport = async () => {
    loading.value = true;
    try {
        const params = {...filters.value};
        Object.keys(params).forEach((k) => { if (!params[k]) { delete params[k]; } });
        const res = await apiAdmin('purchase-report/pending-purchase', 'get', params);
        reportData.value = res.data.data;
        partyOptions.value = reportData.value?.party_options ?? [];
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const exportCsv = () => {
    if (!rows.value.length) { return; }
    const headers = ['Order #', 'Order Date', 'Supplier', 'Ordered Qty', 'Received Qty', 'Pending Qty'];
    const data = rows.value.map(r => [r.order_no, r.order_date, r.party_name, r.ordered_qty, r.received_qty, r.pending_qty]);
    const csv = [headers, ...data].map(r => r.map(v => `"${v ?? ''}"`).join(',')).join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], {type: 'text/csv'}));
    a.download = 'pending-purchase.csv';
    a.click();
};

onMounted(async () => { await loadReport(); });
</script>
