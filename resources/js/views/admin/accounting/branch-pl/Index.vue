<template>
    <PageHeader title="Branch P&L / Consolidated Report" subtitle="Branch-wise profitability and consolidated view">
    </PageHeader>

    <section class="section">
        <!-- Filters -->
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small">From Date</label>
                        <input type="date" class="form-control form-control-sm" v-model="filter.from_date" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">To Date</label>
                        <input type="date" class="form-control form-control-sm" v-model="filter.to_date" />
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-sm btn-primary w-100" :disabled="loading" @click="loadReports">
                            <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                            Generate
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Consolidated Report -->
        <div v-if="consolidatedData.length" class="card mb-4">
            <div class="card-header bg-primary text-white">
                <i class="ti ti-building-bank me-2"></i>Consolidated P&L — All Branches
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Branch</th>
                                <th class="text-end">Total Revenue</th>
                                <th class="text-end">Total Expenses</th>
                                <th class="text-end">Net Profit / (Loss)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in consolidatedData" :key="row.branch.id">
                                <td><strong>{{ row.branch.name }}</strong> <small class="text-muted">({{ row.branch.code }})</small></td>
                                <td class="text-end text-success">{{ fmtNum(row.total_revenue) }}</td>
                                <td class="text-end text-danger">{{ fmtNum(row.total_expenses) }}</td>
                                <td class="text-end fw-bold" :class="row.net_profit >= 0 ? 'text-primary' : 'text-danger'">
                                    {{ fmtNum(row.net_profit) }}
                                </td>
                            </tr>
                            <tr class="table-dark fw-bold">
                                <td>TOTAL</td>
                                <td class="text-end">{{ fmtNum(consolidatedData.reduce((s,r) => s+r.total_revenue, 0)) }}</td>
                                <td class="text-end">{{ fmtNum(consolidatedData.reduce((s,r) => s+r.total_expenses, 0)) }}</td>
                                <td class="text-end">{{ fmtNum(consolidatedData.reduce((s,r) => s+r.net_profit, 0)) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Individual Branch P&L cards -->
        <div class="row g-3" v-if="branchReports.length">
            <div class="col-md-6" v-for="report in branchReports" :key="report.branch.id">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="ti ti-building me-2"></i>{{ report.branch.name }} <small class="text-muted">({{ report.branch.code }})</small></span>
                        <span :class="report.net_profit >= 0 ? 'badge bg-success' : 'badge bg-danger'">
                            {{ report.net_profit >= 0 ? 'Profitable' : 'Loss' }}
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row text-center g-2">
                            <div class="col-4">
                                <div class="text-muted small">Revenue</div>
                                <div class="text-success fw-bold">{{ fmtNum(report.total_revenue) }}</div>
                            </div>
                            <div class="col-4">
                                <div class="text-muted small">Expenses</div>
                                <div class="text-danger fw-bold">{{ fmtNum(report.total_expenses) }}</div>
                            </div>
                            <div class="col-4">
                                <div class="text-muted small">Net Profit</div>
                                <div :class="['fw-bold', report.net_profit >= 0 ? 'text-primary' : 'text-danger']">
                                    {{ fmtNum(report.net_profit) }}
                                </div>
                            </div>
                        </div>
                        <!-- Profit margin bar -->
                        <div class="mt-3">
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span>Profit Margin</span>
                                <span>{{ report.total_revenue > 0 ? Math.round(report.net_profit/report.total_revenue*100) : 0 }}%</span>
                            </div>
                            <div class="progress" style="height:8px">
                                <div class="progress-bar"
                                    :class="report.net_profit >= 0 ? 'bg-success' : 'bg-danger'"
                                    :style="`width:${Math.min(100, Math.abs(report.total_revenue > 0 ? report.net_profit/report.total_revenue*100 : 0))}%`">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer text-muted small">
                        {{ filter.from_date }} to {{ filter.to_date }}
                    </div>
                </div>
            </div>
        </div>

        <div v-else-if="!loading" class="text-center py-5 text-muted">
            <i class="ti ti-building-bank fs-1 d-block mb-2"></i>
            Select date range and click <strong>Generate</strong> to view branch P&L reports.
        </div>
    </section>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { apiAdmin } from '@/helpers/api';
import showErrors from '@/helpers/showErrors';

const loading = ref(false);
const branches = ref([]);
const branchReports = ref([]);
const consolidatedData = ref([]);

const today = new Date().toISOString().slice(0,10);
const firstOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0,10);
const filter = ref({ from_date: firstOfMonth, to_date: today });

onMounted(async () => {
    const { data } = await apiAdmin('branch');
    branches.value = data.data;
});

async function loadReports() {
    loading.value = true;
    branchReports.value = [];
    try {
        const results = await Promise.all(
            branches.value.map(b => apiAdmin(`branch/${b.id}/pl-report?from_date=${filter.value.from_date}&to_date=${filter.value.to_date}`)
                .then(r => ({ branch: b, ...r.data.data }))
                .catch(() => ({ branch: b, total_revenue: 0, total_expenses: 0, net_profit: 0 }))
            )
        );
        branchReports.value = results;
        await loadConsolidated();
    } finally { loading.value = false; }
}

async function loadConsolidated() {
    try {
        const { data } = await apiAdmin(`branch/consolidated-report?from_date=${filter.value.from_date}&to_date=${filter.value.to_date}`);
        consolidatedData.value = data.data;
    } catch (e) { showErrors(e); }
}

function fmtNum(n) {
    return (n ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
