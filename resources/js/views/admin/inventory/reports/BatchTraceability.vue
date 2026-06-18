<template>
    <PageHeader hide-action-buttons title="Batch Traceability Report" subtitle="Full lifecycle of a batch from receipt through consumption" />

    <section class="section">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Batch Number <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            class="form-control"
                            v-model="batchNo"
                            placeholder="Enter batch number..."
                            @keyup.enter="loadReport"
                        />
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-success flex-grow-1" @click="loadReport" :disabled="loading || !batchNo">
                            <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                            Trace
                        </button>
                        <button class="btn btn-outline-secondary" @click="exportCsv" :disabled="!rows.length" title="Export CSV">
                            <i class="ti ti-file-export"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="hasLoaded && !batchInfo" class="alert alert-warning d-flex align-items-center gap-2">
            <i class="ti ti-alert-triangle fs-4"></i>
            No batch found with number <strong class="ms-1">{{ lastSearched }}</strong>.
        </div>

        <template v-if="batchInfo">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="text-muted mb-3">Batch Details</h6>
                            <dl class="row mb-0 small">
                                <dt class="col-5">Batch No</dt>
                                <dd class="col-7 fw-semibold">{{ batchInfo.batch_no }}</dd>
                                <dt class="col-5">Lot No</dt>
                                <dd class="col-7">{{ batchInfo.lot_no ?? '-' }}</dd>
                                <dt class="col-5">Product</dt>
                                <dd class="col-7">{{ batchInfo.product_name }}</dd>
                                <dt class="col-5">SKU</dt>
                                <dd class="col-7">{{ batchInfo.sku }}</dd>
                                <dt class="col-5">Warehouse</dt>
                                <dd class="col-7">{{ batchInfo.warehouse }}</dd>
                                <dt class="col-5">Mfg. Date</dt>
                                <dd class="col-7">{{ batchInfo.mfg_date ?? '-' }}</dd>
                                <dt class="col-5">Expiry Date</dt>
                                <dd class="col-7">{{ batchInfo.expiry_date ?? '-' }}</dd>
                                <dt class="col-5">Status</dt>
                                <dd class="col-7">
                                    <span class="badge" :class="statusBadge(batchInfo.status)">{{ batchInfo.status }}</span>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="row g-3">
                        <div class="col-md-4 d-flex">
                            <div class="card border-0 shadow-sm sale-widget widget-info flex-fill">
                                <div class="card-body d-flex align-items-center">
                                    <span class="sale-icon bg-info-subtle text-info">
                                        <i class="ti ti-arrow-down fs-24"></i>
                                    </span>
                                    <div class="ms-3">
                                        <p class="fw-medium mb-1">Total In</p>
                                        <h4 class="mb-0">{{ formatMoneyPlain(summary.total_in) }}</h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 d-flex">
                            <div class="card border-0 shadow-sm sale-widget widget-danger flex-fill">
                                <div class="card-body d-flex align-items-center">
                                    <span class="sale-icon bg-danger-subtle text-danger">
                                        <i class="ti ti-arrow-up fs-24"></i>
                                    </span>
                                    <div class="ms-3">
                                        <p class="fw-medium mb-1">Total Out</p>
                                        <h4 class="mb-0">{{ formatMoneyPlain(summary.total_out) }}</h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 d-flex">
                            <div class="card border-0 shadow-sm sale-widget widget-success flex-fill">
                                <div class="card-body d-flex align-items-center">
                                    <span class="sale-icon bg-success-subtle text-success">
                                        <i class="ti ti-archive fs-24"></i>
                                    </span>
                                    <div class="ms-3">
                                        <p class="fw-medium mb-1">Remaining</p>
                                        <h4 class="mb-0">{{ formatMoneyPlain(summary.remaining_qty) }}</h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Type</th>
                                                    <th>Direction</th>
                                                    <th>Warehouse</th>
                                                    <th class="text-end">Qty</th>
                                                    <th class="text-end">Unit Cost</th>
                                                    <th class="text-end">Total Cost</th>
                                                    <th>Remarks</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-if="!rows.length">
                                                    <td colspan="8" class="text-center text-muted py-3">No stock movements found for this batch.</td>
                                                </tr>
                                                <tr v-for="(row, idx) in rows" :key="idx">
                                                    <td>{{ row.date }}</td>
                                                    <td><span class="badge bg-light text-dark border">{{ row.type }}</span></td>
                                                    <td>
                                                        <span class="badge" :class="row.direction === 'in' ? 'bg-success' : 'bg-danger'">
                                                            {{ row.direction }}
                                                        </span>
                                                    </td>
                                                    <td>{{ row.warehouse }}</td>
                                                    <td class="text-end">{{ formatMoneyPlain(row.quantity) }}</td>
                                                    <td class="text-end">{{ formatMoney(row.unit_cost) }}</td>
                                                    <td class="text-end fw-semibold">{{ formatMoney(row.total_cost) }}</td>
                                                    <td class="text-muted small">{{ row.remarks || '-' }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div v-if="!hasLoaded" class="text-center text-muted py-5">
            <i class="ti ti-search fs-1 d-block mb-2"></i>
            Enter a batch number above and click Trace to see its full movement history.
        </div>
    </section>
</template>

<script setup>
import {formatMoney, formatMoneyPlain} from '@/helpers/formatMoney.js';
import {ref} from 'vue';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const batchNo = ref('');
const lastSearched = ref('');
const batchInfo = ref(null);
const rows = ref([]);
const summary = ref({total_in: 0, total_out: 0, remaining_qty: 0});
const loading = ref(false);
const hasLoaded = ref(false);

const statusBadge = (status) => {
    if (status === 'active') return 'bg-success';
    if (status === 'expired') return 'bg-danger';
    if (status === 'depleted') return 'bg-secondary';
    return 'bg-light text-dark';
};

const exportCsv = () => {
    if (!rows.value.length || !batchInfo.value) { return; }
    const headers = ['Date', 'Type', 'Direction', 'Warehouse', 'Qty', 'Unit Cost', 'Total Cost', 'Remarks'];
    const csvRows = rows.value.map(r => [
        r.date, r.type, r.direction, r.warehouse, r.quantity, r.unit_cost, r.total_cost, r.remarks,
    ].map(v => `"${v ?? ''}"`).join(','));
    const csv = [headers.join(','), ...csvRows].join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], {type: 'text/csv'}));
    a.download = `batch-traceability-${batchInfo.value.batch_no}.csv`;
    a.click();
};

const loadReport = async () => {
    if (!batchNo.value.trim()) { return; }
    loading.value = true;
    lastSearched.value = batchNo.value.trim();
    try {
        const res = await apiAdmin('inventory-report/batch-traceability', 'get', {batch_no: batchNo.value.trim()});
        const data = res.data.data;
        batchInfo.value = data.batch;
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
