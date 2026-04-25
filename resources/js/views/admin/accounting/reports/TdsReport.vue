<template>
    <PageHeader title="TDS Report" subtitle="Tax Deducted at Source – Nepal IRD" />

    <div class="card border-0 mb-3">
        <div class="card-body pb-1">
            <div class="row align-items-end">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" v-model="filters.start_date" />
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" v-model="filters.end_date" />
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <button class="btn btn-primary w-100" @click="loadReport" :disabled="loading">
                            <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                            Generate
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <button class="btn btn-outline-secondary w-100" @click="exportCsv" :disabled="!rows.length">
                            <i class="ti ti-file-export me-1"></i> Export CSV
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div v-if="summary" class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card border-0 bg-primary-subtle">
                <div class="card-body text-center">
                    <h6 class="text-muted">Total Base Amount</h6>
                    <h4 class="fw-bold">NPR {{ fmt(summary.total_base_amount) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 bg-danger-subtle">
                <div class="card-body text-center">
                    <h6 class="text-muted">Total TDS Deducted</h6>
                    <h4 class="fw-bold text-danger">NPR {{ fmt(summary.total_tds_amount) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 bg-success-subtle">
                <div class="card-body text-center">
                    <h6 class="text-muted">Net Amount Paid</h6>
                    <h4 class="fw-bold text-success">NPR {{ fmt((summary.total_base_amount || 0) - (summary.total_tds_amount || 0)) }}</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0">
        <div class="card-header">
            <h6 class="mb-0">TDS Deduction Details</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>SN</th>
                            <th>Party Name</th>
                            <th>PAN</th>
                            <th>TDS Category</th>
                            <th class="text-end">Base Amount</th>
                            <th class="text-end">Rate %</th>
                            <th class="text-end">TDS Amount</th>
                            <th>Period</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!rows.length && !loading">
                            <td colspan="8" class="text-center text-muted py-4">No TDS deductions found for this period.</td>
                        </tr>
                        <tr v-for="(row, idx) in rows" :key="idx">
                            <td>{{ idx + 1 }}</td>
                            <td>{{ row.party_name || '-' }}</td>
                            <td>{{ row.party_pan || '-' }}</td>
                            <td>{{ row.tds_category }}</td>
                            <td class="text-end">{{ fmt(row.base_amount) }}</td>
                            <td class="text-end">{{ row.tds_rate }}%</td>
                            <td class="text-end text-danger fw-semibold">{{ fmt(row.tds_amount) }}</td>
                            <td>{{ row.period_month || '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import {ref} from 'vue';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const filters = ref({ start_date: null, end_date: null });
const loading = ref(false);
const rows = ref([]);
const summary = ref(null);

const loadReport = async () => {
    loading.value = true;
    try {
        const res = await apiAdmin('account-report/tds-report', 'get', filters.value);
        const d = res.data.data;
        rows.value = d.rows || [];
        summary.value = d.summary;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const fmt = (val) => Number(val || 0).toLocaleString('en-NP', { minimumFractionDigits: 2 });

const exportCsv = () => {
    if (!rows.value.length) return;
    const headers = ['Party Name', 'PAN', 'TDS Category', 'Base Amount', 'Rate %', 'TDS Amount', 'Period'];
    const csvRows = rows.value.map(r => [
        r.party_name, r.party_pan, r.tds_category,
        r.base_amount, r.tds_rate, r.tds_amount, r.period_month
    ].map(v => `"${v ?? ''}"`).join(','));
    const csv = [headers.join(','), ...csvRows].join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'tds-report.csv';
    a.click();
};
</script>
