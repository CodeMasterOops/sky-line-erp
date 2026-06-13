<template>
    <PageHeader hide-action-buttons title="Customer Outstanding" subtitle="Net outstanding balances per customer" @refresh="generateReport" />

    <section class="section">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-xl-3 col-md-5">
                        <label class="form-label">As Of Date</label>
                        <input
                            v-model="filter.as_of_date"
                            type="date"
                            class="form-control"
                        >
                    </div>
                    <div class="col-xl-2 col-md-3">
                        <button
                            type="button"
                            class="btn btn-success w-100"
                            :disabled="outstanding.loading"
                            @click="generateReport"
                        >
                            {{ outstanding.loading ? 'Loading...' : 'Generate' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body">
                        <p class="text-muted mb-1 small">Total Invoiced</p>
                        <h5 class="mb-0 text-primary">{{ formatAmount(outstanding.data.summary.total_invoiced) }}</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body">
                        <p class="text-muted mb-1 small">Total Received</p>
                        <h5 class="mb-0 text-success">{{ formatAmount(outstanding.data.summary.total_received) }}</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body">
                        <p class="text-muted mb-1 small">TDS Deducted</p>
                        <h5 class="mb-0 text-warning">{{ formatAmount(outstanding.data.summary.tds_deducted) }}</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body">
                        <p class="text-muted mb-1 small">Net Outstanding</p>
                        <h5 class="mb-0 text-danger fw-bold">{{ formatAmount(outstanding.data.summary.net_outstanding) }}</h5>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 outstanding-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Customer</th>
                                <th>PAN</th>
                                <th class="text-end">Total Invoiced</th>
                                <th class="text-end">Total Received</th>
                                <th class="text-end">TDS Deducted</th>
                                <th class="text-end">Net Outstanding</th>
                                <th>Oldest Unpaid</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="outstanding.loading">
                                <td colspan="8" class="text-center py-4 text-muted">Loading...</td>
                            </tr>
                            <tr v-else-if="!outstanding.data.rows.length">
                                <td colspan="8" class="text-center py-4 text-muted">No outstanding balances.</td>
                            </tr>
                            <tr v-for="(row, index) in outstanding.data.rows" :key="row.party_id">
                                <td>{{ index + 1 }}</td>
                                <td>{{ row.party_name }}</td>
                                <td>{{ row.party_pan || '—' }}</td>
                                <td class="text-end">{{ formatAmount(row.total_invoiced) }}</td>
                                <td class="text-end text-success">{{ formatAmount(row.total_received) }}</td>
                                <td class="text-end text-warning">{{ formatAmount(row.tds_deducted) }}</td>
                                <td class="text-end fw-bold text-danger">{{ formatAmount(row.net_outstanding) }}</td>
                                <td>{{ row.oldest_unpaid_date || '—' }}</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="outstanding.data.rows.length">
                            <tr class="fw-bold table-light">
                                <td colspan="3">Total</td>
                                <td class="text-end">{{ formatAmount(outstanding.data.summary.total_invoiced) }}</td>
                                <td class="text-end text-success">{{ formatAmount(outstanding.data.summary.total_received) }}</td>
                                <td class="text-end text-warning">{{ formatAmount(outstanding.data.summary.tds_deducted) }}</td>
                                <td class="text-end text-danger">{{ formatAmount(outstanding.data.summary.net_outstanding) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { reactive, computed, onMounted } from 'vue';
import { useSalesReportStore } from '@/stores/admin/sales/report.js';
import { formatMoney } from '@/helpers/formatMoney.js';

const store = useSalesReportStore();
const outstanding = computed(() => store.outstanding);

const today = new Date().toISOString().slice(0, 10);
const filter = reactive({ as_of_date: today });

const formatAmount = (value) => formatMoney(Number(value || 0));

const generateReport = () => store.getOutstanding({ as_of_date: filter.as_of_date });

onMounted(generateReport);
</script>

<style scoped>
.outstanding-table thead th {
    background: #eef3f9;
    border-color: #dbe5f0;
    color: #384860;
    font-size: 13px;
    font-weight: 700;
}
</style>
